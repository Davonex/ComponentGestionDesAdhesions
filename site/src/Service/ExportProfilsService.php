<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use Shuchkin\SimpleXLSXGen;

require_once JPATH_SITE . '/components/com_gdadhesions/libraries/simplexlsx/SimpleXLSXGen.php';

/**
 * Service d'export Excel (.xlsx) des profils adhérents (onglet « Profils » de la vue Utilisateurs).
 *
 * Le catalogue CHAMPS est la source unique des colonnes exportables : il alimente la popup de
 * sélection (getChampsDisponibles()) et sert de liste blanche côté serveur (genererClasseur()).
 *
 * Le classeur est construit en mémoire par SimpleXLSXGen (libraries/simplexlsx, fichier amont non
 * modifié) puis envoyé directement au navigateur par l'appelant : aucun fichier n'est écrit sur
 * disque (données personnelles).
 *
 * Toute valeur texte issue de la base est passée par SimpleXLSXGen::raw() : sans cela, la
 * bibliothèque interprète les balises (<f> = formule, <a href>, <style>…) contenues dans la valeur.
 */
final class ExportProfilsService
{
    public const GROUPE_IDENTITE = 'IDENTITE';
    public const GROUPE_ADHESION = 'ADHESION';
    public const GROUPE_SANTE = 'SANTE';

    private const TYPE_TEXTE = 'texte';
    private const TYPE_DATE = 'date';
    private const TYPE_ENTIER = 'entier';
    private const TYPE_MONTANT = 'montant';
    private const TYPE_BOOLEEN = 'booleen';

    /**
     * Catalogue des colonnes exportables, dans l'ordre du classeur : clé => [groupe, type].
     * Le libellé est la clé de langue COM_GDA_UTILISATEURS_EXPORT_COL_<CLÉ EN MAJUSCULES>.
     */
    private const CHAMPS = [
        'civilite'            => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'nom'                 => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'prenom'              => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'licence'             => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'date_de_naissance'   => [self::GROUPE_IDENTITE, self::TYPE_DATE],
        'age'                 => [self::GROUPE_IDENTITE, self::TYPE_ENTIER],
        'email'               => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'telephone'           => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'adresse'             => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'code_postal'         => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'ville'               => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'a_prevenir'          => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'a_prevenir_tel'      => [self::GROUPE_IDENTITE, self::TYPE_TEXTE],
        'statut_adhesion'     => [self::GROUPE_ADHESION, self::TYPE_TEXTE],
        'tarif_libelle'       => [self::GROUPE_ADHESION, self::TYPE_TEXTE],
        'tarif_montant'       => [self::GROUPE_ADHESION, self::TYPE_MONTANT],
        'categorie'           => [self::GROUPE_ADHESION, self::TYPE_TEXTE],
        'caci_valide'         => [self::GROUPE_ADHESION, self::TYPE_BOOLEEN],
        'paiement_valide'     => [self::GROUPE_ADHESION, self::TYPE_BOOLEEN],
        'licence_enregistree' => [self::GROUPE_ADHESION, self::TYPE_BOOLEEN],
        'date_souscription'   => [self::GROUPE_ADHESION, self::TYPE_DATE],
        'date_caci'           => [self::GROUPE_SANTE, self::TYPE_DATE],
        'statut_caci'         => [self::GROUPE_SANTE, self::TYPE_TEXTE],
        'date_licence'        => [self::GROUPE_SANTE, self::TYPE_DATE],
        'nbr_plongee'         => [self::GROUPE_SANTE, self::TYPE_ENTIER],
        'nbr_plongee_35'      => [self::GROUPE_SANTE, self::TYPE_ENTIER],
        'droit_img'           => [self::GROUPE_SANTE, self::TYPE_BOOLEEN],
    ];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Obtenir les colonnes exportables regroupées par domaine, avec leurs libellés traduits,
     * pour la popup de sélection.
     *
     * @return array<string, array{label: string, champs: array<string, string>}> Groupes indexés
     *         par code (GROUPE_*), chacun avec son libellé et ses champs (clé => libellé).
     */
    public function getChampsDisponibles(): array
    {
        $groupes = [];

        foreach (self::CHAMPS as $cle => [$groupe]) {
            if (!isset($groupes[$groupe])) {
                $groupes[$groupe] = [
                    'label'  => Text::_('COM_GDA_UTILISATEURS_EXPORT_GROUPE_' . $groupe),
                    'champs' => [],
                ];
            }

            $groupes[$groupe]['champs'][$cle] = $this->getLibelleChamp($cle);
        }

        return $groupes;
    }

    /**
     * Construire le classeur Excel des profils demandés, limité aux colonnes demandées.
     *
     * Les colonnes inconnues sont ignorées (liste blanche CHAMPS) et l'ordre du catalogue est
     * conservé, quel que soit l'ordre reçu. Les comptes Super Users sont exclus, comme dans la
     * vue (UtilisateursModel::getUtilisateurs()).
     *
     * @param int[]    $idsProfils Identifiants des comptes à exporter (lignes filtrées à l'écran).
     * @param string[] $champs     Clés des colonnes demandées.
     * @return SimpleXLSXGen Classeur prêt à être envoyé (downloadAs()).
     * @throws \InvalidArgumentException Si aucune colonne valide ou aucun profil n'est demandé (400).
     * @throws \RuntimeException Si la requête échoue.
     */
    public function genererClasseur(array $idsProfils, array $champs): SimpleXLSXGen
    {
        $champs = array_values(array_intersect(array_keys(self::CHAMPS), $champs));
        $idsProfils = array_values(array_unique(array_filter(array_map('intval', $idsProfils))));

        if ($champs === []) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_UTILISATEURS_EXPORT_AUCUN_CHAMP'), 400);
        }

        if ($idsProfils === []) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_UTILISATEURS_EXPORT_AUCUNE_LIGNE'), 400);
        }

        $lignes = [array_map(fn (string $cle) => '<b>' . $this->getLibelleChamp($cle) . '</b>', $champs)];

        foreach ($this->getProfils($idsProfils) as $profil) {
            $lignes[] = array_map(fn (string $cle) => $this->formaterCellule($cle, $profil), $champs);
        }

        $classeur = SimpleXLSXGen::fromArray($lignes, Text::_('COM_GDA_UTILISATEURS_EXPORT_FEUILLE'));
        $classeur->freezePanes('A2');
        $classeur->autoFilter('A1:' . SimpleXLSXGen::coord2cell(\count($champs) - 1) . \count($lignes));

        return $classeur;
    }

    /**
     * Charger en une seule requête les comptes, leur profil et leur souscription à la saison
     * courante.
     *
     * @param int[] $idsProfils Identifiants des comptes.
     * @return object[] Lignes triées par nom puis prénom.
     * @throws \RuntimeException Si la requête échoue.
     */
    private function getProfils(array $idsProfils): array
    {
        $db = $this->db;
        $saisonCourante = ConfHelper::getSaisonService()->getSaisonCourante();
        $idCampagne = $saisonCourante !== null ? (int) $saisonCourante->id_campagne : 0;

        $query = $db->createQuery()
            ->select([
                $db->quoteName('u.id', 'id_profil'),
                $db->quoteName('u.username'),
                $db->quoteName('u.name'),
                $db->quoteName('u.email'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.date_de_naissance'),
                $db->quoteName('p.adresse'),
                $db->quoteName('p.code_postal'),
                $db->quoteName('p.ville'),
                $db->quoteName('p.telephone'),
                $db->quoteName('p.a_prevenir'),
                $db->quoteName('p.a_prevenir_tel'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
                $db->quoteName('p.date_licence'),
                $db->quoteName('p.nbr_plongee'),
                $db->quoteName('p.nbr_plongee_35'),
                $db->quoteName('p.droit_img'),
                $db->quoteName('s.id_campagne'),
                $db->quoteName('s.date_souscription'),
                $db->quoteName('s.cotisation_code'),
                $db->quoteName('s.cotisation_montant'),
                $db->quoteName('s.categorie'),
                $db->quoteName('s.caci_check'),
                $db->quoteName('s.cotisation_check'),
                $db->quoteName('s.licence_check'),
                $db->quoteName('s.id_order'),
            ])
            ->from($db->quoteName('#__users', 'u'))
            ->join('LEFT', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('u.id'))
            ->join(
                'LEFT',
                $db->quoteName('#__gda_souscriptions', 's') . ' ON ' . $db->quoteName('s.id_profil') . ' = ' . $db->quoteName('u.id')
                    . ' AND ' . $db->quoteName('s.id_campagne') . ' = :id_campagne'
            )
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->whereIn($db->quoteName('u.id'), $idsProfils)
            ->order($db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC');

        $superUsersGroupId = UsersHelper::getSuperUsersGroupId();

        if ($superUsersGroupId !== null) {
            $query->where(
                $db->quoteName('u.id') . ' NOT IN (' .
                    'SELECT ' . $db->quoteName('user_id') .
                    ' FROM ' . $db->quoteName('#__user_usergroup_map') .
                    ' WHERE ' . $db->quoteName('group_id') . ' = :super_users_group_id' .
                    ')'
            )->bind(':super_users_group_id', $superUsersGroupId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Calculer la valeur d'une cellule, typée pour Excel selon le type déclaré dans CHAMPS.
     *
     * @param string $cle    Clé de la colonne (présente dans CHAMPS).
     * @param object $profil Ligne chargée par getProfils().
     * @return string|int|null Valeur prête pour SimpleXLSXGen (null = cellule vide).
     */
    private function formaterCellule(string $cle, object $profil)
    {
        $valeur = $this->getValeurBrute($cle, $profil);

        if ($valeur === null || $valeur === '') {
            return null;
        }

        switch (self::CHAMPS[$cle][1]) {
            case self::TYPE_DATE:
                // Chaîne AAAA-MM-JJ : reconnue par SimpleXLSXGen comme une vraie date Excel.
                $date = substr((string) $valeur, 0, 10);

                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date !== '0000-00-00' ? $date : null;

            case self::TYPE_ENTIER:
                return (int) $valeur;

            case self::TYPE_MONTANT:
                // « 123.45 € » : reconnu par SimpleXLSXGen comme un nombre au format monétaire euro.
                return sprintf('%.2f €', (float) $valeur);

            case self::TYPE_BOOLEEN:
                return Text::_((int) $valeur === 1 ? 'JYES' : 'JNO');

            default:
                return SimpleXLSXGen::raw((string) $valeur);
        }
    }

    /**
     * Lire ou calculer la valeur métier d'une colonne pour un profil, avant typage Excel.
     *
     * @param string $cle    Clé de la colonne (présente dans CHAMPS).
     * @param object $profil Ligne chargée par getProfils().
     * @return mixed Valeur brute (null si non applicable, par exemple sans souscription).
     */
    private function getValeurBrute(string $cle, object $profil)
    {
        $souscrit = $profil->id_campagne !== null;

        switch ($cle) {
            case 'nom':
                return $profil->nom ?? $profil->name;

            case 'licence':
                return $profil->username;

            case 'age':
                return $this->calculerAge($profil->date_de_naissance);

            case 'statut_adhesion':
                // Même calcul que l'onglet Profils (UtilisateursModel::getUtilisateurs()), cache
                // HelloAsso de 30 minutes accepté.
                return AdhesionStatusHelper::getSimplifiedStatusLabel(AdhesionStatusHelper::getSimplifiedStatus(
                    AdhesionStatusHelper::getStatusEnum($souscrit ? $profil : null, false)
                ));

            case 'tarif_libelle':
                return $souscrit && $profil->cotisation_code ? CotisationService::getLabel((string) $profil->cotisation_code, $this->db) : null;

            case 'tarif_montant':
                return $souscrit && $profil->cotisation_code
                    ? CotisationService::getMontantFige($profil->cotisation_montant, (string) $profil->cotisation_code, $this->db)
                    : null;

            case 'caci_valide':
            case 'paiement_valide':
            case 'licence_enregistree':
                $colonnes = ['caci_valide' => 'caci_check', 'paiement_valide' => 'cotisation_check', 'licence_enregistree' => 'licence_check'];

                return $souscrit ? (int) $profil->{$colonnes[$cle]} : null;

            case 'statut_caci':
                return AdhesionStatusHelper::getStatusLabel(
                    AdhesionStatusHelper::getCaciFileStatus($profil->caci, $profil->date_caci)
                );

            default:
                return $profil->{$cle} ?? null;
        }
    }

    /**
     * Calculer l'âge révolu à partir d'une date de naissance SQL.
     *
     * @param string|null $dateDeNaissance Date au format AAAA-MM-JJ.
     * @return int|null Âge en années, null si la date est absente ou invalide.
     */
    private function calculerAge(?string $dateDeNaissance): ?int
    {
        if (empty($dateDeNaissance) || str_starts_with($dateDeNaissance, '0000')) {
            return null;
        }

        try {
            return (new \DateTime($dateDeNaissance))->diff(new \DateTime('today'))->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtenir le libellé traduit d'une colonne.
     *
     * @param string $cle Clé de la colonne.
     * @return string Libellé.
     */
    private function getLibelleChamp(string $cle): string
    {
        return Text::_('COM_GDA_UTILISATEURS_EXPORT_COL_' . strtoupper($cle));
    }
}
