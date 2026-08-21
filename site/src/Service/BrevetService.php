<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier pour les brevets FFESSM d'un adhérent (#__gda_brevets).
 *
 * Point d'extension unique pour la lecture et l'écriture des brevets : le parcours d'adhésion
 * (AdhesionModel::saveInBrevets) et l'édition depuis la fiche Profil (ProfilController::saveBrevets)
 * passent tous deux par ce service, afin de ne pas dupliquer la règle "annule et remplace".
 *
 * Correspondance avec le référentiel FFESSM (#__gda_mapping_brevets) : résolue une fois pour
 * toutes à l'écriture (replaceBrevets), pas à chaque lecture. #__gda_brevets.id_mapping porte le
 * résultat (NULL si le libellé brut n'est reconnu par aucune ligne du référentiel). Les méthodes
 * getBrevetsImportants*() n'ont donc qu'à faire un JOIN, sans jamais comparer de texte.
 *
 * Le service détient les DEUX tables du domaine : #__gda_brevets et le référentiel
 * #__gda_mapping_brevets, ce dernier n'étant plus en lecture seule depuis la vue « Brevets »
 * (0.9.8), qui permet au Bureau de l'administrer et de rattacher à la main les libellés non
 * reconnus. Les méthodes correspondantes sont regroupées en fin de classe.
 */
final class BrevetService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Brevets d'un profil, du plus récent au plus ancien.
     *
     * `id_mapping` est exposé pour que la fiche Profil sache quelles lignes sont déjà rattachées
     * au référentiel officiel : leur libellé n'y est plus modifiable à la main (voir
     * layouts/profil/card_brevet.php et profil_brevets.js).
     *
     * @return object[] objets {id, nom, obtention, lieu, id_mapping}
     */
    public function getBrevets(int $idProfil): array
    {
        if ($idProfil <= 0) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['b.id', 'b.nom', 'b.obtention', 'b.lieu', 'b.id_mapping']))
            ->from($this->db->quoteName('#__gda_brevets', 'b'))
            ->where($this->db->quoteName('b.id_profil') . ' = :id_profil')
            ->order($this->db->quoteName('b.obtention') . ' DESC')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Annule et remplace l'intégralité des brevets d'un profil, en transaction : sans elle, un
     * échec d'insertion après le DELETE laisserait l'adhérent sans aucun brevet.
     * Les lignes sans nom (ligne de saisie laissée vide) sont ignorées.
     *
     * @param array<int, array{nom?: mixed, obtention?: mixed, lieu?: mixed}> $brevets
     *
     * @return int nombre de brevets réellement enregistrés
     */
    public function replaceBrevets(int $idProfil, array $brevets): int
    {
        if ($idProfil <= 0) {
            throw new \InvalidArgumentException('id_profil invalide');
        }

        $this->db->transactionStart();

        try {
            $this->deleteForProfil($idProfil);

            // Chargé une fois pour tout l'appel (pas une requête par brevet) : le référentiel ne
            // fait que quelques dizaines de lignes, autant le garder en mémoire pour la boucle.
            $mappingParLabelNorm = $this->loadMappingByNormLabel();

            $enregistres = 0;

            foreach ($brevets as $brevet) {
                $nom = trim((string) ($brevet['nom'] ?? ''));

                if ($nom === '') {
                    continue;
                }

                // strtoupper (et non removeAccentsAndUppercase) pour rester homogène avec les
                // lignes déjà enregistrées par le parcours d'adhésion historique.
                $lieu = strtoupper(trim((string) ($brevet['lieu'] ?? '')));
                $obtention = !empty($brevet['obtention']) ? ToolsHelper::to_sqldate((string) $brevet['obtention']) : null;

                // Résolution du code métier : NULL si le libellé n'est reconnu par aucune ligne du
                // référentiel (nouveau brevet FFESSM, variante d'orthographe...). Ce n'est jamais
                // bloquant, on trace juste un WARNING pour repérer les libellés à ajouter au
                // référentiel (#__gda_mapping_brevets) — le brevet est enregistré tel quel.
                $mapping = $mappingParLabelNorm[self::normalizeLabel($nom)] ?? null;
                $idMapping = $mapping->id ?? null;

                if ($idMapping === null) {
                    GdaLogger::warning(sprintf(
                        'BrevetService::replaceBrevets - libellé non reconnu par #__gda_mapping_brevets : "%s" (id_profil=%d)',
                        $nom,
                        $idProfil
                    ));
                }

                $query = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__gda_brevets'))
                    ->columns($this->db->quoteName(['nom', 'lieu', 'obtention', 'id_mapping', 'id_profil']))
                    ->values(':nom, :lieu, :obtention, :id_mapping, :id_profil')
                    ->bind(':nom', $nom)
                    ->bind(':lieu', $lieu)
                    ->bind(':obtention', $obtention, $obtention === null ? ParameterType::NULL : ParameterType::STRING)
                    ->bind(':id_mapping', $idMapping, $idMapping === null ? ParameterType::NULL : ParameterType::INTEGER)
                    ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $this->db->execute();

                $enregistres++;
            }

            $this->db->transactionCommit();

            return $enregistres;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException('Enregistrement des brevets impossible : ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Renseigne le token FFESSM du profil (identifiant technique extrait du QR code de la carte
     * licence). N'écrase jamais un token déjà présent : le scan sert à compléter le profil, pas à
     * réécrire une donnée déjà validée.
     *
     * @return bool true si le token a été écrit
     */
    public function updateFfessmToken(int $idProfil, string $token): bool
    {
        $token = trim($token);

        if ($idProfil <= 0 || $token === '') {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_profils'))
            ->set($this->db->quoteName('ffessm_token') . ' = :token')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->where('(' . $this->db->quoteName('ffessm_token') . ' IS NULL OR ' . $this->db->quoteName('ffessm_token') . " = '')")
            ->bind(':token', $token)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows() > 0;
    }

    private function deleteForProfil(int $idProfil): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__gda_brevets'))
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Brevets "importants" d'un profil : pour chaque couple (activité, rôle) dans lequel
     * l'adhérent a au moins un brevet reconnu, celui de plus fort poids est retourné (ex :
     * en Plongée/pratiquant, si l'adhérent a Niveau 1 et Niveau 3, seul le Niveau 3 remonte).
     *
     * Fine couche au-dessus de getBrevetsImportantsPourProfils() : à ne pas dupliquer si un appel
     * porte sur plusieurs profils à la fois (trombinoscope, accueil, rapports de campagne) - voir
     * cette méthode dans ce cas, pour éviter le N+1 requêtes.
     *
     * @param  string[] $activites Filtre optionnel (ex: ['Plongée', 'Apnée']) ; vide = toutes les
     *                             activités où l'adhérent a un brevet reconnu.
     * @return object[] objets {activite, role, code, label_ffessm, poids}
     */
    public function getBrevetsShortList(int $idProfil, array $activites = []): array
    {
        if ($idProfil <= 0) {
            return [];
        }

        return $this->getBrevetsShortListProfils([$idProfil], $activites)[$idProfil] ?? [];
    }

    /**
     * Variante multi-profils de getBrevetsImportants() : une seule requête pour tous les profils
     * demandés (au lieu d'un appel par adhérent), pour les vues qui affichent plusieurs adhérents
     * à la fois (trombinoscope par activité, accueil, rapports de campagne).
     *
     * @param  int[]    $idProfils
     * @param  string[] $activites Filtre optionnel, voir getBrevetsImportants().
     * @return array<int, object[]> brevets importants indexés par id_profil (objets {activite,
     *                               role, code, label_ffessm, poids}) ; un id_profil sans aucun
     *                               brevet reconnu n'apparaît pas dans le tableau retourné.
     */
    public function getBrevetsShortListProfils(array $idProfils, array $activites = []): array
    {
        $idProfils = array_values(array_unique(array_filter(array_map('intval', $idProfils), static fn($id) => $id > 0)));

        if ($idProfils === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName([
                'b.id_profil', 'm.code', 'm.activite', 'm.role', 'm.label_ffessm', 'm.poids',
            ]))
            ->from($this->db->quoteName('#__gda_brevets', 'b'))
            ->innerJoin(
                $this->db->quoteName('#__gda_mapping_brevets', 'm')
                . ' ON ' . $this->db->quoteName('m.id') . ' = ' . $this->db->quoteName('b.id_mapping')
            )
            ->whereIn($this->db->quoteName('b.id_profil'), $idProfils, ParameterType::INTEGER);

        if ($activites !== []) {
            $query->whereIn($this->db->quoteName('m.activite'), $activites, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $lignes = $this->db->loadObjectList() ?: [];

        // Réduction "plus fort poids par (id_profil, activite, role)" en PHP plutôt qu'en SQL
        // (greatest-n-per-group) : plus simple à lire, et sans coût réel vu le volume (quelques
        // centaines de brevets au total sur tout le club).
        $meilleurs = [];

        foreach ($lignes as $ligne) {
            $idProfilLigne = (int) $ligne->id_profil;
            $cle = $ligne->activite . '|' . $ligne->role;

            if (
                !isset($meilleurs[$idProfilLigne][$cle])
                || (int) $ligne->poids > (int) $meilleurs[$idProfilLigne][$cle]->poids
            ) {
                $meilleurs[$idProfilLigne][$cle] = $ligne;
            } elseif ((int) $ligne->poids === (int) $meilleurs[$idProfilLigne][$cle]->poids) {
                // Égalité (ex: RIFA Plongée / RIFA Apnée, même poids) : les deux comptent comme
                // "importants", on les garde toutes les deux plutôt que d'en écraser une.
                $meilleurs[$idProfilLigne][$cle . '#' . $ligne->code] = $ligne;
            }
        }

        return array_map('array_values', $meilleurs);
    }

    /**
     * Activités distinctes du référentiel FFESSM (#__gda_mapping_brevets) : « Technique »,
     * « Apnée », « Handisub », « Nitrox »... Sert à proposer une liste fermée partout où une
     * activité doit être saisie (rattachement d'un groupe du club, filtres de trombinoscope),
     * plutôt que de dupliquer une liste en dur.
     *
     * @return string[] activités triées par ordre alphabétique
     */
    public function getActivitesReferentiel(): array
    {
        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('activite'))
            ->from($this->db->quoteName('#__gda_mapping_brevets'))
            ->order($this->db->quoteName('activite') . ' ASC');

        $this->db->setQuery($query);

        return $this->db->loadColumn() ?: [];
    }

    // ---------------------------------------------------------------------------------------
    // Vue « Brevets » (Bureau) : administration du référentiel FFESSM et rattachement manuel
    // des brevets saisis par les adhérents. Regroupé ici et non dans un service dédié pour que
    // #__gda_brevets et #__gda_mapping_brevets gardent un propriétaire unique.
    // ---------------------------------------------------------------------------------------

    /**
     * Référentiel complet, pour l'onglet « Référentiel FFESSM » de la vue Brevets.
     *
     * @return object[] objets {id, code, activite, role, label_ffessm, poids}
     */
    public function getMappings(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'code', 'activite', 'role', 'label_ffessm', 'poids']))
            ->from($this->db->quoteName('#__gda_mapping_brevets'))
            ->order([
                $this->db->quoteName('activite') . ' ASC',
                $this->db->quoteName('role') . ' ASC',
                $this->db->quoteName('poids') . ' DESC',
            ]);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Ajoute une correspondance au référentiel. `label_ffessm_norm` n'est jamais saisi : il est
     * dérivé du libellé officiel par normalizeLabel(), la même fonction que celle utilisée par
     * replaceBrevets() pour le rapprochement — c'est ce qui garantit qu'un libellé ajouté ici
     * sera effectivement reconnu lors du prochain enregistrement de brevets.
     *
     * @param array{label_ffessm?: mixed, activite?: mixed, role?: mixed, code?: mixed, poids?: mixed} $donnees
     *
     * @return object la ligne créée
     */
    public function createMapping(array $donnees): object
    {
        $label = trim((string) ($donnees['label_ffessm'] ?? ''));
        $activite = trim((string) ($donnees['activite'] ?? ''));
        $role = (string) ($donnees['role'] ?? '');
        $code = trim((string) ($donnees['code'] ?? ''));
        $poids = (int) ($donnees['poids'] ?? 0);

        if ($label === '' || $code === '') {
            throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_MAPPING_ERR_REQUIRED'));
        }

        if (!\in_array($role, ['pratiquant', 'encadrant'], true)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_MAPPING_ERR_ROLE'));
        }

        if (!\in_array($activite, $this->getActivitesReferentiel(), true)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_MAPPING_ERR_ACTIVITE'));
        }

        $labelNorm = self::normalizeLabel($label);

        // La contrainte unique porte sur (code, label_ffessm_norm) : on la teste explicitement
        // pour renvoyer un message métier plutôt que de laisser remonter une erreur SQL brute.
        if ($this->mappingExiste($code, $labelNorm)) {
            throw new \RuntimeException(Text::sprintf('COM_GDA_BREVETS_MAPPING_ERR_DUPLICATE', $label, $code));
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__gda_mapping_brevets'))
            ->columns($this->db->quoteName(['code', 'activite', 'role', 'label_ffessm', 'label_ffessm_norm', 'poids']))
            ->values(':code, :activite, :role, :label, :label_norm, :poids')
            ->bind(':code', $code)
            ->bind(':activite', $activite)
            ->bind(':role', $role)
            ->bind(':label', $label)
            ->bind(':label_norm', $labelNorm)
            ->bind(':poids', $poids, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (object) [
            'id'           => (int) $this->db->insertid(),
            'code'         => $code,
            'activite'     => $activite,
            'role'         => $role,
            'label_ffessm' => $label,
            'poids'        => $poids,
        ];
    }

    /**
     * Édition inline d'une case du référentiel. Seuls `code` et `poids` sont modifiables : le
     * libellé officiel et son couple (activité, rôle) définissent l'identité de la ligne, les
     * changer reviendrait à créer une autre correspondance — et invaliderait `label_ffessm_norm`
     * ainsi que les `#__gda_brevets.id_mapping` déjà résolus.
     */
    public function updateMappingChamp(int $idMapping, string $champ, string $valeur): void
    {
        if ($idMapping <= 0 || !\in_array($champ, ['code', 'poids'], true)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_MAPPING_ERR_FIELD'));
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_mapping_brevets'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $idMapping, ParameterType::INTEGER);

        if ($champ === 'poids') {
            $poids = max(0, (int) $valeur);
            $query->set($this->db->quoteName('poids') . ' = :poids')
                ->bind(':poids', $poids, ParameterType::INTEGER);
        } else {
            $code = trim($valeur);

            if ($code === '') {
                throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_MAPPING_ERR_REQUIRED'));
            }

            $query->set($this->db->quoteName('code') . ' = :code')
                ->bind(':code', $code);
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Supprime une correspondance du référentiel. La contrainte `gda_brevets_id_mapping_FK` est
     * en ON DELETE SET NULL : les brevets adhérents qui la référençaient ne sont pas supprimés,
     * ils redeviennent simplement « non rattachés ». D'où countBrevetsLies() en amont, pour que
     * la confirmation annonce le nombre de brevets concernés.
     *
     * @return string le libellé supprimé, pour le message de confirmation
     */
    public function deleteMapping(int $idMapping): string
    {
        if ($idMapping <= 0) {
            throw new \InvalidArgumentException('id_mapping invalide');
        }

        // Lu avant la suppression : après, le libellé n'est plus consultable pour le message.
        $mapping = $this->getMapping($idMapping);

        if ($mapping === null) {
            throw new \RuntimeException(Text::_('COM_GDA_BREVETS_ERR_MAPPING_INTROUVABLE'));
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__gda_mapping_brevets'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $idMapping, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return $mapping->label_ffessm;
    }

    /**
     * Nombre de brevets adhérents actuellement rattachés à une correspondance donnée.
     */
    public function countBrevetsLies(int $idMapping): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_brevets'))
            ->where($this->db->quoteName('id_mapping') . ' = :id')
            ->bind(':id', $idMapping, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Tous les brevets saisis par les adhérents, avec leur identité et la correspondance résolue
     * (NULL si le libellé n'est reconnu par aucune ligne du référentiel), pour l'onglet
     * « Brevets des adhérents ».
     *
     * Une seule requête pour l'ensemble du club : la vue affiche plusieurs milliers de lignes,
     * un chargement par adhérent y serait rédhibitoire.
     *
     * @return object[] objets {id, nom, obtention, lieu, id_mapping, id_profil, civilite, nom_profil,
     *                          prenom, photo, label_ffessm, activite, role, code}
     */
    public function getBrevetsAvecMapping(): array
    {
        $query = $this->db->getQuery(true)
            ->select(
                $this->db->quoteName(
                    [
                        'b.id', 'b.nom', 'b.obtention', 'b.lieu', 'b.id_mapping', 'b.id_profil',
                        'p.civilite', 'p.nom', 'p.prenom', 'p.photo',
                        'm.label_ffessm', 'm.activite', 'm.role', 'm.code',
                    ],
                    [
                        'id', 'nom', 'obtention', 'lieu', 'id_mapping', 'id_profil',
                        'civilite', 'nom_profil', 'prenom', 'photo',
                        'label_ffessm', 'activite', 'role', 'code',
                    ]
                )
            )
            ->from($this->db->quoteName('#__gda_brevets', 'b'))
            ->innerJoin(
                $this->db->quoteName('#__gda_profils', 'p')
                . ' ON ' . $this->db->quoteName('p.id_profil') . ' = ' . $this->db->quoteName('b.id_profil')
            )
            ->leftJoin(
                $this->db->quoteName('#__gda_mapping_brevets', 'm')
                . ' ON ' . $this->db->quoteName('m.id') . ' = ' . $this->db->quoteName('b.id_mapping')
            )
            ->order([
                $this->db->quoteName('p.nom') . ' ASC',
                $this->db->quoteName('p.prenom') . ' ASC',
                $this->db->quoteName('b.obtention') . ' DESC',
            ]);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Corrige le libellé saisi par un adhérent pour un brevet donné, sans toucher à son
     * rattachement (celui-ci sera de toute façon re-résolu au prochain replaceBrevets()).
     *
     * @return object {adherent, ancien_nom, nom} pour le message de confirmation
     */
    public function updateNomBrevet(int $idBrevet, string $nom): object
    {
        $nom = trim($nom);

        if ($idBrevet <= 0 || $nom === '') {
            throw new \InvalidArgumentException(Text::_('COM_GDA_BREVETS_ERR_NOM_REQUIRED'));
        }

        $brevet = $this->getBrevetAvecAdherent($idBrevet);

        if ($brevet === null) {
            throw new \RuntimeException(Text::_('COM_GDA_BREVETS_ERR_BREVET_INTROUVABLE'));
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_brevets'))
            ->set($this->db->quoteName('nom') . ' = :nom')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':nom', $nom)
            ->bind(':id', $idBrevet, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (object) [
            'adherent'   => $brevet->adherent,
            'ancien_nom' => $brevet->nom,
            'nom'        => $nom,
        ];
    }

    /**
     * Rattache manuellement un brevet à une correspondance du référentiel, et **remplace son
     * libellé par le libellé officiel**.
     *
     * C'est ce qui rend la correction durable : sans réécriture du nom, le prochain
     * replaceBrevets() repartirait du libellé saisi par l'adhérent et retomberait sur le même
     * échec de résolution. En écrivant exactement `label_ffessm`, on garantit au contraire que
     * normalizeLabel() retrouvera la même ligne — la correction se rejoue toute seule.
     *
     * @return object {nom, label_ffessm, activite, role, code, adherent, ancien_nom} — les deux
     *                derniers pour le message de confirmation, qui doit rappeler quel libellé
     *                de quel adhérent vient d'être remplacé.
     */
    public function attacherMapping(int $idBrevet, int $idMapping): object
    {
        if ($idBrevet <= 0 || $idMapping <= 0) {
            throw new \InvalidArgumentException('Identifiant invalide');
        }

        $mapping = $this->getMapping($idMapping);

        if ($mapping === null) {
            throw new \RuntimeException(Text::_('COM_GDA_BREVETS_ERR_MAPPING_INTROUVABLE'));
        }

        // Lu avant la mise à jour : l'ancien libellé est écrasé par le libellé officiel.
        $brevet = $this->getBrevetAvecAdherent($idBrevet);

        if ($brevet === null) {
            throw new \RuntimeException(Text::_('COM_GDA_BREVETS_ERR_BREVET_INTROUVABLE'));
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_brevets'))
            ->set([
                $this->db->quoteName('id_mapping') . ' = :id_mapping',
                $this->db->quoteName('nom') . ' = :nom',
            ])
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id_mapping', $idMapping, ParameterType::INTEGER)
            ->bind(':nom', $mapping->label_ffessm)
            ->bind(':id', $idBrevet, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        $mapping->nom = $mapping->label_ffessm;
        $mapping->adherent = $brevet->adherent;
        $mapping->ancien_nom = $brevet->nom;

        return $mapping;
    }

    /**
     * Un brevet et le nom d'affichage de son porteur, pour composer les messages de confirmation
     * de la vue Brevets (« [Untel] le "N3" a été rattaché à "Niveau 3" »).
     *
     * @return object|null {nom, adherent}
     */
    private function getBrevetAvecAdherent(int $idBrevet): ?object
    {
        $query = $this->db->getQuery(true)
            ->select(
                $this->db->quoteName(
                    ['b.nom', 'p.nom', 'p.prenom'],
                    ['nom', 'nom_profil', 'prenom']
                )
            )
            ->from($this->db->quoteName('#__gda_brevets', 'b'))
            ->innerJoin(
                $this->db->quoteName('#__gda_profils', 'p')
                . ' ON ' . $this->db->quoteName('p.id_profil') . ' = ' . $this->db->quoteName('b.id_profil')
            )
            ->where($this->db->quoteName('b.id') . ' = :id')
            ->bind(':id', $idBrevet, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $ligne = $this->db->loadObject();

        if (!$ligne) {
            return null;
        }

        $ligne->adherent = trim($ligne->nom_profil . ' ' . $ligne->prenom);

        return $ligne;
    }

    /**
     * Une correspondance du référentiel par son identifiant.
     */
    private function getMapping(int $idMapping): ?object
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'code', 'activite', 'role', 'label_ffessm', 'poids']))
            ->from($this->db->quoteName('#__gda_mapping_brevets'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $idMapping, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObject() ?: null;
    }

    /**
     * Teste la contrainte unique (code, label_ffessm_norm) avant insertion.
     */
    private function mappingExiste(string $code, string $labelNorm): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_mapping_brevets'))
            ->where($this->db->quoteName('code') . ' = :code')
            ->where($this->db->quoteName('label_ffessm_norm') . ' = :label_norm')
            ->bind(':code', $code)
            ->bind(':label_norm', $labelNorm);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * Référentiel FFESSM (#__gda_mapping_brevets) chargé une seule fois, indexé par libellé
     * normalisé - pour résoudre id_mapping dans replaceBrevets() sans une requête par brevet.
     *
     * @return array<string, object> objets {id, code, activite, role, label_ffessm, poids},
     *                                indexés par label_ffessm_norm
     */
    private function loadMappingByNormLabel(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName([
                'id', 'code', 'activite', 'role', 'label_ffessm', 'label_ffessm_norm', 'poids',
            ]))
            ->from($this->db->quoteName('#__gda_mapping_brevets'));

        $this->db->setQuery($query);

        $parLabelNorm = [];

        foreach ($this->db->loadObjectList() ?: [] as $ligne) {
            // La table autorise plusieurs libellés pour un même code (équivalences, recyclages),
            // mais chaque label_ffessm_norm y est unique (contrainte idx_code_label_norm) : pas
            // de collision possible ici.
            $parLabelNorm[$ligne->label_ffessm_norm] = $ligne;
        }

        return $parLabelNorm;
    }

    /**
     * Normalise un libellé de brevet pour le rapprochement avec #__gda_mapping_brevets.label_ffessm_norm
     * (MAJUSCULE, sans accents, sans points, tirets/apostrophes uniformisés, espaces multiples
     * réduits). S'appuie sur ToolsHelper::removeAccentsAndUppercase() (accents + majuscule) et n'y
     * ajoute que le nettoyage propre au rapprochement FFESSM, pour ne pas modifier un helper
     * générique utilisé ailleurs dans le composant.
     */
    private static function normalizeLabel(string $text): string
    {
        $text = ToolsHelper::removeAccentsAndUppercase($text);
        $text = str_replace(['’', '‘'], "'", $text);
        $text = str_replace(['–', '—'], '-', $text);
        $text = str_replace('.', '', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
