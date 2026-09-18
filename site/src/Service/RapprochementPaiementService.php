<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service de rapprochement entre les paiements HelloAsso d'une campagne et les souscriptions
 * (#__gda_souscriptions) des adhérents. Complète l'auto-résolution existante
 * (SouscriptionService::resolveIdOrder(), qui associe une commande par correspondance exacte de
 * licence) pour les cas où celle-ci échoue (typo du payeur dans le formulaire HelloAsso) : liste les
 * paiements résolus et orphelins d'une campagne, et permet une association manuelle.
 *
 * Sortie volontairement générique (indépendante de #__gda_souscriptions dans sa forme), pour
 * permettre une réutilisation future avec les campagnes Formation/Loisir (paiements rattachés via
 * #__gda_reservation.id_order) : seules getSouscriptionsResolues()/getCandidatsSansPaiement()
 * seraient alors dupliquées/adaptées pour cette autre table.
 */
final class RapprochementPaiementService
{
    private DatabaseInterface $db;

    private ?HelloAssoService $helloAsso = null;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Accesseur lazy vers le service HelloAsso (non partagé par le conteneur DI, même motif que
     * dans les Models du composant : HelloAssoService lit sa propre configuration à l'instanciation).
     *
     * @return HelloAssoService
     */
    private function getHelloAsso(): HelloAssoService
    {
        if ($this->helloAsso === null) {
            $this->helloAsso = new HelloAssoService();
        }

        return $this->helloAsso;
    }

    /**
     * Construit la liste normalisée des paiements HelloAsso d'une campagne — résolus (déjà associés
     * à un adhérent) ou orphelins — ainsi que la liste des adhérents candidats pouvant être associés
     * manuellement.
     *
     * @param object $saison       Campagne décorée (ConfHelper::getSaisonService()->getSaisonCourante()),
     *                              doit exposer ->id_campagne, ->formType, ->formSlug.
     * @param bool   $forceRefresh true = contourne le cache fichier HelloAsso de 30 min et déclenche une
     *                             tentative d'association automatique par correspondance exacte de
     *                             licence sur les lignes encore orphelines (bouton "Rafraîchir" — voir
     *                             autoAssocierParLicenceExacte()).
     * @return array{lignes: array<int, object>, candidats: array<int, object>, nb_non_associes: int, nb_auto_associes: int}
     *   - lignes[]: {id_order, date, payeur_nom, beneficiaire_nom, beneficiaire_licence, resolved(bool), adherent_id_profil(?int), adherent_nom(?string), adherent_licence(?string)}
     *   - candidats[]: {id_profil, label, username} — adhérents de la campagne dont id_order est encore vide.
     *   - nb_non_associes : nombre de lignes encore orphelines (resolved = false), pour le message
     *     récapitulatif en tête de l'onglet (même motif que Brevets::nbNonRattaches).
     *   - nb_auto_associes : nombre d'associations automatiques effectuées lors de cet appel (0 si
     *     $forceRefresh = false ou si aucune correspondance exacte n'a été trouvée).
     * @throws \RuntimeException Si la campagne n'a pas de formulaire HelloAsso configuré, si l'appel
     *                           API échoue, ou si une requête SQL échoue.
     */
    public function getPaiementsOrphelins(object $saison, bool $forceRefresh = false): array
    {
        if (empty($saison->formType) || empty($saison->formSlug)) {
            throw new \RuntimeException(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_NO_HELLOASSO'));
        }

        $idCampagne = (int) $saison->id_campagne;

        $orders = $this->getHelloAsso()->getFormsOrders($saison->formType, $saison->formSlug, 'true', $forceRefresh);
        $items = $this->flattenHelloAssoItems($orders);
        $resolus = $this->getSouscriptionsResolues($idCampagne);
        $lignes = $this->resoudreItemsParAdherent($items, $resolus);
        $candidats = $this->getCandidatsSansPaiement($idCampagne);

        $nbAutoAssocies = 0;

        if ($forceRefresh) {
            $nbAutoAssocies = $this->autoAssocierParLicenceExacte($lignes, $candidats, $idCampagne);

            if ($nbAutoAssocies > 0) {
                // Les associations qui viennent d'être persistées changent l'état de la campagne :
                // on reconstruit lignes/candidats à partir de la base plutôt que de patcher les
                // tableaux en mémoire, pour rester la seule source de vérité.
                $resolus = $this->getSouscriptionsResolues($idCampagne);
                $lignes = $this->resoudreItemsParAdherent($items, $resolus);
                $candidats = $this->getCandidatsSansPaiement($idCampagne);
            }
        }

        return [
            'lignes'           => $lignes,
            'candidats'        => $candidats,
            'nb_non_associes'  => count(array_filter($lignes, static fn (object $ligne): bool => empty($ligne->resolved))),
            'nb_auto_associes' => $nbAutoAssocies,
        ];
    }

    /**
     * Tente une association automatique par correspondance exacte de licence, pour chaque ligne
     * encore orpheline dont la licence déclarée dans HelloAsso correspond exactement au username
     * d'un candidat libre de la campagne. Reproduit à l'échelle de toute la campagne ce que
     * SouscriptionService::resolveIdOrder() fait déjà pour un seul adhérent, mais seulement à la
     * visite de son propre tableau de bord : en production, un paiement dont ni l'adhérent ni le
     * secrétariat n'a revisité cette page reste orphelin indéfiniment malgré une licence correcte —
     * ce mécanisme comble ce trou en le déclenchant explicitement au clic sur "Rafraîchir", pour
     * toute la campagne d'un coup.
     *
     * Ne fait rien de plus qu'une correspondance déjà sans ambiguïté (licence strictement identique,
     * après `trim()`) : ne remplace pas la vérification manuelle nécessaire pour les cas ambigus
     * (typo, homonymie), qui restent affichés comme orphelins pour traitement par la secrétaire.
     *
     * @param array<int, object> $lignes     Lignes déjà résolues/orphelines (resoudreItemsParAdherent()).
     * @param array<int, object> $candidats  Candidats libres de la campagne (getCandidatsSansPaiement()),
     *                                       doit exposer ->id_profil et ->username.
     * @param int                $idCampagne Identifiant de la campagne.
     * @return int Nombre d'associations automatiques effectuées.
     */
    private function autoAssocierParLicenceExacte(array $lignes, array $candidats, int $idCampagne): int
    {
        $candidatParUsername = [];

        foreach ($candidats as $candidat) {
            $candidatParUsername[trim((string) $candidat->username)] = $candidat;
        }

        if ($candidatParUsername === []) {
            return 0;
        }

        $souscriptionService = new SouscriptionService($this->db);
        $nbAssocies = 0;

        foreach ($lignes as $ligne) {
            if (!empty($ligne->resolved)) {
                continue;
            }

            $licence = trim((string) ($ligne->beneficiaire_licence ?? ''));

            if ($licence === '' || !isset($candidatParUsername[$licence])) {
                continue;
            }

            $candidat = $candidatParUsername[$licence];

            try {
                $souscriptionService->updateIdOrder((int) $candidat->id_profil, $idCampagne, (string) $ligne->id_order);
                $nbAssocies++;
                // Un même candidat ne doit pas être réutilisé pour une autre ligne dans cette passe.
                unset($candidatParUsername[$licence]);

                GdaLogger::info(sprintf(
                    'RapprochementPaiementService::autoAssocierParLicenceExacte() - Association automatique : commande %s -> profil %d (%s), campagne %d',
                    $ligne->id_order,
                    $candidat->id_profil,
                    $licence,
                    $idCampagne
                ));
            } catch (\Throwable $e) {
                GdaLogger::warning(sprintf(
                    'RapprochementPaiementService::autoAssocierParLicenceExacte() - Échec association automatique (commande %s, profil %d) : %s',
                    $ligne->id_order,
                    $candidat->id_profil,
                    $e->getMessage()
                ));
            }
        }

        return $nbAssocies;
    }

    /**
     * Aplatit les commandes HelloAsso (getFormsOrders(..., withDetails='true')) en une liste d'items,
     * chaque item conservant l'identifiant de sa commande parente ('_id_order'), sa date ('_date')
     * et le payeur de la commande ('_payer', order.payer — distinct de item.user : sur un paiement
     * groupé, le payeur peut différer du bénéficiaire de chaque item). Une commande peut contenir
     * plusieurs items : contrairement à une simplification sur items[0], tous sont conservés.
     *
     * @param array $orders Tableau brut retourné par HelloAssoService::getFormsOrders().
     * @return array<int, array> Items enrichis des clés internes '_id_order', '_date' et '_payer'.
     */
    private function flattenHelloAssoItems(array $orders): array
    {
        $items = [];

        foreach ($orders as $order) {
            $idOrder = (string) ($order['id'] ?? '');
            $date = (string) ($order['date'] ?? '');
            $payer = $order['payer'] ?? [];

            foreach (($order['items'] ?? []) as $item) {
                $item['_id_order'] = $idOrder;
                $item['_date'] = $date;
                $item['_payer'] = $payer;
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Liste les souscriptions de la campagne dont l'id_order est déjà connu (non '0'/''/NULL),
     * groupées par id_order : une commande peut légitimement être partagée par plusieurs adhérents
     * (paiement groupé familial), chacun l'ayant obtenue via sa propre auto-résolution par licence.
     *
     * @param int $idCampagne Identifiant de la campagne.
     * @return array<string, array<int, object>> id_order => liste de {id_profil, civilite, nom, prenom, username}.
     * @throws \RuntimeException Si la requête échoue.
     */
    private function getSouscriptionsResolues(int $idCampagne): array
    {
        $db = $this->db;

        $query = $db->createQuery()
            ->select([
                $db->quoteName('s.id_order'),
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('u.username'),
            ])
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('INNER', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->join('INNER', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('s.id_order') . ' IS NOT NULL')
            ->where($db->quoteName('s.id_order') . " != ''")
            ->where($db->quoteName('s.id_order') . " != '0'")
            ->bind(':id_campagne', $idCampagne);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur lecture des souscriptions résolues : ' . $e->getMessage(), 500, $e);
        }

        $parOrder = [];

        foreach ($rows as $row) {
            $parOrder[(string) $row->id_order][] = $row;
        }

        return $parOrder;
    }

    /**
     * Attribue chaque item HelloAsso à l'adhérent correspondant, en réutilisant le même mécanisme
     * que SecretariatModel::getPayement() (HelloAssoService::findItemForAdherent()). Les items
     * restants (non réclamés par un adhérent déjà résolu) constituent les lignes orphelines.
     *
     * @param array<int, array>                 $items   Items aplatis (flattenHelloAssoItems()).
     * @param array<string, array<int, object>> $resolus id_order => profils, voir getSouscriptionsResolues().
     * @return array<int, object> Lignes {id_order, date, payeur_nom, beneficiaire_nom, beneficiaire_licence, resolved, adherent_id_profil, adherent_nom, adherent_licence}.
     */
    private function resoudreItemsParAdherent(array $items, array $resolus): array
    {
        $itemsParOrder = [];

        foreach ($items as $item) {
            $itemsParOrder[$item['_id_order']][] = $item;
        }

        $lignes = [];

        foreach ($resolus as $idOrder => $profils) {
            $candidats = $itemsParOrder[$idOrder] ?? [];

            foreach ($profils as $profil) {
                $item = $this->trouverItemPourProfil($candidats, $profil);

                if ($item === null) {
                    continue;
                }

                $lignes[] = $this->construireLigne($idOrder, $item, true, $profil);
                $candidats = $this->retirerItem($candidats, $item);
            }

            $itemsParOrder[$idOrder] = $candidats;
        }

        foreach ($itemsParOrder as $idOrder => $itemsRestants) {
            foreach ($itemsRestants as $item) {
                $lignes[] = $this->construireLigne($idOrder, $item, false, null);
            }
        }

        return $lignes;
    }

    /**
     * Attribue un item HelloAsso à un profil précis, en réutilisant
     * HelloAssoService::findItemForAdherent() (licence puis nom/prénom). Contrairement à cette
     * dernière, ne retombe PAS sur un item par défaut dès qu'il y a plusieurs candidats sans
     * correspondance franche : un repli hasardeux ferait disparaître à tort une ligne orpheline
     * (l'item resterait "réclamé" par le mauvais profil). Le repli n'est accepté que lorsqu'un seul
     * item candidat reste (cas sans ambiguïté, la commande ayant déjà été retrouvée spécifiquement
     * pour ce profil par SouscriptionService::resolveIdOrder()).
     *
     * @param array<int, array> $candidats Items candidats (ceux de la commande de ce profil, non encore réclamés).
     * @param object             $profil   Profil {id_profil, civilite, nom, prenom, username}.
     * @return array|null L'item attribué, ou null si aucune correspondance fiable.
     */
    private function trouverItemPourProfil(array $candidats, object $profil): ?array
    {
        if ($candidats === []) {
            return null;
        }

        if (count($candidats) === 1) {
            return $candidats[0];
        }

        $item = $this->getHelloAsso()->findItemForAdherent($candidats, $profil->username, $profil->nom, $profil->prenom);

        // findItemForAdherent() retombe sur le premier item si aucune correspondance franche n'est
        // trouvée, et ce retour est indiscernable d'une vraie correspondance : on revérifie donc
        // nous-mêmes avant d'accepter l'attribution.
        if ($item !== null && $this->correspondReellement($item, $profil)) {
            return $item;
        }

        return null;
    }

    /**
     * Vérifie qu'un item HelloAsso correspond réellement à un profil (licence exacte, ou nom/prénom
     * normalisés identiques) — utilisé pour ne pas accepter à tort le repli de
     * HelloAssoService::findItemForAdherent() sur un item non concluant.
     *
     * @param array  $item   Item HelloAsso.
     * @param object $profil Profil {nom, prenom, username}.
     * @return bool
     */
    private function correspondReellement(array $item, object $profil): bool
    {
        $licence = $this->getHelloAsso()->extractLicenceAnswer($item);

        if ($licence !== null && $licence === trim($profil->username)) {
            return true;
        }

        $itemNom = ToolsHelper::removeAccentsAndUppercase((string) ($item['user']['lastName'] ?? ''));
        $itemPrenom = ToolsHelper::removeAccentsAndUppercase((string) ($item['user']['firstName'] ?? ''));
        $profilNom = ToolsHelper::removeAccentsAndUppercase($profil->nom);
        $profilPrenom = ToolsHelper::removeAccentsAndUppercase($profil->prenom);

        return $itemNom === $profilNom && $itemPrenom === $profilPrenom;
    }

    /**
     * Retire d'une liste d'items HelloAsso la première occurrence strictement identique à celle
     * donnée (comparaison par valeur "===", suffisante ici car tous les items proviennent du même
     * tableau source, sans copie modifiée entre-temps).
     *
     * @param array<int, array> $items Liste d'items.
     * @param array              $item  Item à retirer (une seule occurrence).
     * @return array<int, array> Liste réindexée sans cet item.
     */
    private function retirerItem(array $items, array $item): array
    {
        foreach ($items as $index => $candidat) {
            if ($candidat === $item) {
                unset($items[$index]);
                break;
            }
        }

        return array_values($items);
    }

    /**
     * Construit une ligne d'affichage normalisée à partir d'un item HelloAsso. Distingue le payeur
     * réel de la commande (order.payer — celui qui a réglé) du bénéficiaire de cet item (item.user —
     * la personne pour laquelle cette adhésion a été prise, avec sa licence telle que saisie dans
     * HelloAsso) : sur un paiement groupé, les deux peuvent différer, et c'est justement le
     * rapprochement entre le bénéficiaire déclaré et l'adhérent réellement associé qui permet à la
     * secrétaire de vérifier qu'une association est correcte.
     *
     * @param string      $idOrder  Identifiant de la commande HelloAsso.
     * @param array       $item     Item HelloAsso (aplati, voir flattenHelloAssoItems()).
     * @param bool        $resolved true si un adhérent est déjà associé à cet item.
     * @param object|null $profil   Profil associé {id_profil, civilite, nom, prenom, username}, si $resolved.
     * @return object Ligne {id_order, date, payeur_nom, beneficiaire_nom, beneficiaire_licence, resolved, adherent_id_profil, adherent_nom, adherent_licence}.
     */
    private function construireLigne(string $idOrder, array $item, bool $resolved, ?object $profil): object
    {
        $payeurNom = trim(($item['_payer']['firstName'] ?? '') . ' ' . ($item['_payer']['lastName'] ?? ''));
        $beneficiaireNom = trim(($item['user']['firstName'] ?? '') . ' ' . ($item['user']['lastName'] ?? ''));

        return (object) [
            'id_order'             => $idOrder,
            'date'                 => $this->formatDate($item['_date'] ?? null),
            // Repli sur le bénéficiaire si le payeur n'est pas renseigné (ne devrait pas arriver,
            // mais un objet 'payer' vide ne doit pas afficher une ligne "Payé par" sans nom).
            'payeur_nom'           => $payeurNom !== '' ? $payeurNom : $beneficiaireNom,
            'beneficiaire_nom'     => $beneficiaireNom,
            'beneficiaire_licence' => $this->getHelloAsso()->extractLicenceAnswer($item),
            'resolved'             => $resolved,
            'adherent_id_profil'   => $profil !== null ? (int) $profil->id_profil : null,
            'adherent_nom'         => $profil !== null ? $this->formaterNomComplet($profil) : null,
            'adherent_licence'     => $profil !== null ? $profil->username : null,
        ];
    }

    /**
     * Formate le nom complet d'un profil au même ordre que l'affichage HelloAsso (payeur_nom :
     * prénom puis nom), pour une présentation homogène entre les colonnes d'origine HelloAsso et
     * les colonnes locales (adhérent associé, candidats) de l'onglet Paiements orphelins.
     *
     * @param object $profil Profil {nom, prenom, ...}.
     * @return string
     */
    private function formaterNomComplet(object $profil): string
    {
        return trim($profil->prenom . ' ' . $profil->nom);
    }

    /**
     * Formate la date d'une commande HelloAsso (clé 'date' de l'objet Order), même motif que
     * SecretariatModel::buildPaymentReport().
     *
     * @param string|null $dateStr Date brute HelloAsso (ISO 8601), ou null/vide si absente.
     * @return string Date formatée 'd/m/Y H:i', ou chaîne vide si absente/invalide.
     */
    private function formatDate(?string $dateStr): string
    {
        $dateStr = (string) $dateStr;

        if ($dateStr === '') {
            return '';
        }

        try {
            return (new \DateTime($dateStr))->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Liste les adhérents de la campagne dont l'id_order est encore vide, candidats à une
     * association manuelle (liste déroulante de l'onglet Paiements orphelins).
     *
     * @param int $idCampagne Identifiant de la campagne.
     * @return array<int, object> Candidats {id_profil, label, username} triés par ordre alphabétique
     *                             du libellé affiché (prénom puis nom, voir formaterNomComplet()).
     * @throws \RuntimeException Si la requête échoue.
     */
    private function getCandidatsSansPaiement(int $idCampagne): array
    {
        $db = $this->db;

        $query = $db->createQuery()
            ->select([
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('u.username'),
            ])
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('INNER', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->join('INNER', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where('(' . $db->quoteName('s.id_order') . ' IS NULL OR ' . $db->quoteName('s.id_order') . " = '' OR " . $db->quoteName('s.id_order') . " = '0')")
            // Trié par prénom puis nom : le libellé affiché (formaterNomComplet()) commence par le
            // prénom, trier par nom en premier donnerait une liste visuellement dans le désordre.
            ->order($db->quoteName('p.prenom') . ' ASC')
            ->order($db->quoteName('p.nom') . ' ASC')
            ->bind(':id_campagne', $idCampagne);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur lecture des candidats sans paiement : ' . $e->getMessage(), 500, $e);
        }

        $candidats = [];

        foreach ($rows as $row) {
            $candidats[] = (object) [
                'id_profil' => (int) $row->id_profil,
                'label'     => $this->formaterNomComplet($row) . ' (' . $row->username . ')',
                // Exposé pour autoAssocierParLicenceExacte() (comparaison exacte avec la licence
                // déclarée dans HelloAsso) — le layout n'utilise que 'label'.
                'username'  => (string) $row->username,
            ];
        }

        return $candidats;
    }

    /**
     * Lit la souscription d'un profil pour une campagne (id_order actuel + identité), utilisé à la
     * fois pour le profil cible et pour l'ancien profil lors d'une correction d'association.
     *
     * @param int $idCampagne Identifiant de la campagne.
     * @param int $idProfil   Identifiant du profil.
     * @return object|null {id_order, civilite, nom, prenom}, ou null si la souscription n'existe pas.
     * @throws \RuntimeException Si la requête échoue.
     */
    private function lireSouscriptionProfil(int $idCampagne, int $idProfil): ?object
    {
        $db = $this->db;

        $query = $db->createQuery()
            ->select([
                $db->quoteName('s.id_order'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
            ])
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('INNER', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('s.id_profil') . ' = :id_profil')
            ->bind(':id_campagne', $idCampagne)
            ->bind(':id_profil', $idProfil);

        $db->setQuery($query);

        try {
            return $db->loadObject() ?: null;
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur lecture de la souscription (profil ' . $idProfil . ') : ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Associe manuellement une commande HelloAsso à un adhérent de la campagne — soit un paiement
     * encore orphelin ($idAncienProfil = null), soit la correction d'une association existante
     * (l'ancien profil libère la commande, le nouveau la reçoit, dans une même transaction).
     * SouscriptionService::updateIdOrder() ne fait lui-même aucune vérification : les garde-fous
     * nécessaires sont donc portés ici — refuser d'écraser un id_order déjà renseigné pour le profil
     * cible (le partage d'un même id_order entre plusieurs adhérents reste légitime par ailleurs,
     * voir resoudreItemsParAdherent() — c'est uniquement une réassignation accidentelle du profil
     * cible que ce garde-fou empêche), et revérifier que l'ancien profil détient toujours cette
     * commande au moment de la correction (garde-fou contre une modification concurrente).
     *
     * @param int      $idProfil      Identifiant du profil candidat (nouvelle association).
     * @param int      $idCampagne    Identifiant de la campagne.
     * @param string   $idOrder       Identifiant de la commande HelloAsso à associer.
     * @param int|null $idAncienProfil Identifiant du profil actuellement associé, si l'appel corrige
     *                                 une association existante ; null pour un simple orphelin.
     * @return string Nom d'affichage de l'adhérent associé (pour le message de confirmation).
     * @throws \InvalidArgumentException Si les identifiants sont invalides.
     * @throws \RuntimeException Si une souscription impliquée est introuvable, si le profil cible a
     *                           déjà un id_order non vide, si l'ancien profil ne détient plus cette
     *                           commande, ou si la mise à jour échoue.
     */
    public function associerPaiementOrphelin(int $idProfil, int $idCampagne, string $idOrder, ?int $idAncienProfil = null): string
    {
        $idOrder = trim($idOrder);

        if ($idProfil <= 0 || $idCampagne <= 0 || $idOrder === '') {
            throw new \InvalidArgumentException('Paramètres invalides pour associer un paiement.');
        }

        if ($idAncienProfil !== null && $idAncienProfil === $idProfil) {
            // Aucun changement réel (l'adhérent sélectionné est déjà celui associé) : no-op.
            $idAncienProfil = null;
        }

        $db = $this->db;

        $cible = $this->lireSouscriptionProfil($idCampagne, $idProfil);

        if ($cible === null) {
            throw new \RuntimeException('Souscription cible introuvable.');
        }

        $idOrderCibleActuel = (string) ($cible->id_order ?? '');
        $cibleDejaAssociee = $idOrderCibleActuel !== '' && $idOrderCibleActuel !== '0';

        if ($idAncienProfil === null) {
            if ($cibleDejaAssociee) {
                throw new \RuntimeException(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_TARGET_ALREADY_ASSOCIATED'));
            }

            (new SouscriptionService($db))->updateIdOrder($idProfil, $idCampagne, $idOrder);

            return $this->formaterNomComplet($cible);
        }

        // Correction d'une association existante.
        if ($cibleDejaAssociee && $idOrderCibleActuel !== $idOrder) {
            throw new \RuntimeException(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_TARGET_ALREADY_ASSOCIATED'));
        }

        $ancien = $this->lireSouscriptionProfil($idCampagne, $idAncienProfil);

        if ($ancien === null) {
            throw new \RuntimeException('Souscription d\'origine introuvable.');
        }

        if ((string) ($ancien->id_order ?? '') !== $idOrder) {
            throw new \RuntimeException('La commande a changé entre-temps, veuillez rafraîchir la page.');
        }

        $souscriptionService = new SouscriptionService($db);

        $db->transactionStart();

        try {
            $souscriptionService->updateIdOrder($idAncienProfil, $idCampagne, '0');
            $souscriptionService->updateIdOrder($idProfil, $idCampagne, $idOrder);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return $this->formaterNomComplet($cible);
    }

    /**
     * Dissocie une commande HelloAsso d'un adhérent (option "Aucun" du sélecteur de correction) :
     * remet son id_order à '0', sans l'attribuer à personne d'autre. Revérifie que le profil détient
     * toujours cette commande au moment de l'appel (garde-fou contre une modification concurrente,
     * même motif que la correction d'association dans associerPaiementOrphelin()).
     *
     * @param int    $idProfil   Identifiant du profil à dissocier.
     * @param int    $idCampagne Identifiant de la campagne.
     * @param string $idOrder    Identifiant de la commande HelloAsso à dissocier.
     * @return string Nom d'affichage de l'adhérent dissocié (pour le message de confirmation).
     * @throws \InvalidArgumentException Si les identifiants sont invalides.
     * @throws \RuntimeException Si la souscription est introuvable, si elle ne détient plus cette
     *                           commande, ou si la mise à jour échoue.
     */
    public function dissocierPaiementOrphelin(int $idProfil, int $idCampagne, string $idOrder): string
    {
        $idOrder = trim($idOrder);

        if ($idProfil <= 0 || $idCampagne <= 0 || $idOrder === '') {
            throw new \InvalidArgumentException('Paramètres invalides pour dissocier un paiement.');
        }

        $souscription = $this->lireSouscriptionProfil($idCampagne, $idProfil);

        if ($souscription === null) {
            throw new \RuntimeException('Souscription introuvable.');
        }

        if ((string) ($souscription->id_order ?? '') !== $idOrder) {
            throw new \RuntimeException('La commande a changé entre-temps, veuillez rafraîchir la page.');
        }

        (new SouscriptionService($this->db))->updateIdOrder($idProfil, $idCampagne, '0');

        return $this->formaterNomComplet($souscription);
    }

    /**
     * Construit un résumé simplifié d'une commande HelloAsso pour l'affichage d'une ligne encore
     * orpheline (aucun adhérent connu, donc pas de comparaison avec une cotisation attendue —
     * contrairement à SecretariatModel::getPayement()/buildPaymentReport()). Liste tous les items de
     * la commande (un paiement groupé peut regrouper plusieurs adhérents), pour aider la secrétaire
     * à identifier à qui ce paiement appartient avant de l'associer manuellement.
     *
     * @param string $idOrder Identifiant de la commande HelloAsso.
     * @return object {id_order, date, payeur_nom, payeur_email, montant_total, items: array<int, object>{nom, licence}}
     * @throws \RuntimeException Si l'appel à l'API HelloAsso échoue.
     */
    public function getDetailCommandeOrpheline(string $idOrder): object
    {
        $orderDetails = $this->getHelloAsso()->getOrderDetails($idOrder);
        $payer = $orderDetails['payer'] ?? [];

        $montantTotal = 0;

        foreach (($orderDetails['items'] ?? []) as $item) {
            foreach (($item['payments'] ?? []) as $itemPayment) {
                $montantTotal += (int) ($itemPayment['shareAmount'] ?? 0);
            }
        }

        $items = [];

        foreach (($orderDetails['items'] ?? []) as $item) {
            $items[] = (object) [
                'nom'     => trim(($item['user']['firstName'] ?? '') . ' ' . ($item['user']['lastName'] ?? '')),
                'licence' => $this->getHelloAsso()->extractLicenceAnswer($item),
            ];
        }

        return (object) [
            'id_order'      => (string) ($orderDetails['id'] ?? $idOrder),
            'date'          => $this->formatDate((string) ($orderDetails['date'] ?? '')),
            'payeur_nom'    => trim(($payer['firstName'] ?? '') . ' ' . ($payer['lastName'] ?? '')),
            'payeur_email'  => (string) ($payer['email'] ?? ''),
            'montant_total' => $montantTotal / 100,
            'items'         => $items,
        ];
    }
}
