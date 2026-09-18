<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\GdaLogger;

/**
 * Service métier des tarifs : cotisations club et licences FFESSM.
 *
 * Référentiel unique : la table #__gda_cotisation, administrable par le Bureau depuis l'onglet
 * « Tarification » de la vue Saisons. Chaque ligne y porte son code, son libellé, ses deux tarifs
 * (agglomération / hors agglomération), un commentaire libre et un indicateur d'activité.
 *
 * Deux natures de lignes cohabitent :
 *  - `COTISATION` : cotisation club, codes A..H (H = Licence seule, aucune cotisation due - voir
 *    CODE_LICENCE_SEULE). Le code réellement stocké dans #__gda_souscriptions.cotisation_code est
 *    la lettre suivie du chiffre de zone (1 = Val d'Yerres, 2 = hors agglomération), par exemple
 *    `B1`.
 *  - `LICENCE` : licence FFESSM, codes LIC_ADULTE / LIC_JEUNE / LIC_ENFANT, indexées sur la
 *    catégorie renvoyée par GetCategorie(). Une licence ne dépend pas du lieu de résidence :
 *    ses deux tarifs sont maintenus égaux.
 *
 * Les méthodes de calcul d'un code (getCode() et les règles métier privées) exigent une instance
 * construite avec les données de l'adhérent. L'instance partagée du conteneur de services est
 * construite avec `$data = []` : elle n'est là que pour les méthodes **statiques**.
 */
final class CotisationService
{
    // Types de réduction
    public const PAS_DE_REDUCTION   = 0;
    public const REDUCTION_FAMILLE   = 1;
    public const REDUCTION_ENCADRANT = 2;
    public const REDUCTION_ETUDIANT  = 3;
    public const REDUCTION_HANDI     = 4;
    public const REDUCTION_LICENCE_SEULE = 5;

    /**
     * Code cotisation de l'option « Licence seule » (achat de la seule licence FFESSM, sans
     * adhésion au club). Comparé au premier caractère de #__gda_souscriptions.cotisation_code.
     */
    public const CODE_LICENCE_SEULE = 'H';

    /** Natures de lignes du référentiel. */
    public const NATURE_COTISATION = 'COTISATION';
    public const NATURE_LICENCE    = 'LICENCE';

    /**
     * Cibles d'une ligne de cotisation. Lève l'ambiguïté des couples de codes qui partagent une
     * même option de réduction : A/D sur « Normal », B/E sur « Réduction Famille ».
     */
    public const CIBLE_ADULTE = 'ADULTE';
    public const CIBLE_ENFANT = 'ENFANT';
    public const CIBLE_TOUS   = 'TOUS';

    /**
     * Champs modifiables depuis l'onglet « Tarification ». `code`, `nature`, `cible` et
     * `reduction` définissent l'identité métier de la ligne : ils sont référencés en dur dans
     * getCode() et dans #__gda_souscriptions.cotisation_code, donc jamais éditables.
     *
     * `option_libelle` est un cas particulier : il appartient à l'option du formulaire, pas à la
     * ligne. Plusieurs lignes partagent une même option (A/D sur « Normal », B/E sur « Réduction
     * Famille ») et doivent donc afficher le même libellé — updateLigne() propage.
     */
    private const CHAMPS_EDITABLES = ['libelle', 'option_libelle', 'commentaire', 'tarif_vy', 'tarif_hvy', 'actif'];

    /** Montant maximum acceptable, borné par le decimal(6,2) de la colonne. */
    private const MONTANT_MAX = 9999.99;

    /** Codes postaux Val d'Yerres */
    private const CP_VAL_YERRES = [
        '91800',
        '91560',
        '91860',
        '91480',
        '91330',
        '91230',
        '91210',
        '91270',
    ];

    private DatabaseInterface $db;

    /**
     * Cache du référentiel tarifaire, indexé par code (`A`, `LIC_ADULTE`, …).
     *
     * Statique et non par instance : ces lignes sont lues aussi bien depuis une instance (calcul
     * d'une adhésion) que statiquement (secrétariat, mails, statuts). Une requête par requête
     * HTTP suffit, et PHP ne partage rien entre deux requêtes — ne jamais transformer ce cache en
     * cache Joomla persistant, une modification de tarif doit être visible immédiatement.
     *
     * @var array<string, object>|null
     */
    private static ?array $referentiel = null;

    // --- Données d'un calcul en cours ---
    private string $dateRentree;
    private string $dateDeNaissance;
    private string $codePostal;
    private int    $reduction;

    public function __construct(DatabaseInterface $db, $data = [])
    {
        $this->db = $db;
        $this->dateRentree = $this->getProchaineRentree();
        $this->dateDeNaissance = $data['dateDeNaissance'] ?? '';
        $this->codePostal = (string) ($data['codePostal'] ?? '');
        $this->reduction = (int)($data['reduction'] ?? 0);
    }

    // ──────────────────────────────────────────────
    //  API publique — calcul d'un code (instance)
    // ──────────────────────────────────────────────

    /**
     * Calculer le code cotisation de l'adhérent (ex : "A1", "E2", …).
     *
     * Le chiffre 1 = Val d'Yerres, 2 = hors Val d'Yerres. Si la ligne correspondant à la lettre
     * calculée a été désactivée par le Bureau, un repli est appliqué (voir replierSiInactif()) :
     * une adhésion ne doit jamais échouer parce qu'un tarif a été retiré en cours de saison.
     *
     * @return string Code cotisation sur 2 caractères.
     */
    public function getCode(): string
    {
        $suffix = $this->isValYerres() ? '1' : '2';
        $cible  = $this->isEnfant() ? self::CIBLE_ENFANT : self::CIBLE_ADULTE;

        return $this->replierSiInactif($this->calculerLettre(), $cible) . $suffix;
    }

    /**
     * Âge minimum accepté par le club, calculé à la même date de référence que GetCategorie()
     * (1er septembre de la saison) pour rester cohérent : un enfant qui aura l'âge minimum avant
     * la rentrée est accepté. Retourne true si la date de naissance est manquante/invalide (ce
     * n'est pas à ce contrôle de le signaler, un champ requis s'en charge déjà).
     *
     * @param int $ageMinimum Âge minimum en années (8 par défaut).
     * @return bool True si l'âge minimum est respecté.
     */
    public function isAgeMinimumRespecte(int $ageMinimum = 8): bool
    {
        $naissance = $this->parseDateNaissance();

        if ($naissance === null) {
            return true;
        }

        $limite = (new \DateTime($this->dateRentree))->modify('-' . $ageMinimum . ' years');

        return $naissance <= $limite;
    }

    // ──────────────────────────────────────────────
    //  Règles métier (privées)
    // ──────────────────────────────────────────────

    /**
     * Cascade historique de détermination de la lettre de cotisation, par ordre de priorité
     * décroissant. Volontairement isolée du repli sur ligne inactive, pour rester lisible d'un
     * bloc et directement comparable aux colonnes `reduction`/`cible` du référentiel.
     *
     * @return string Lettre de cotisation (A..H).
     */
    private function calculerLettre(): string
    {
        // Licence seule (achat de la licence FFESSM sans adhésion au club) : aucune cotisation
        // club due, quel que soit l'âge - la licence elle-même reste facturée séparément selon
        // l'âge par le mécanisme existant (secrétariat, GetCategorie()/getMontantLicence()).
        // Priorité la plus haute : les autres réductions n'ont pas de sens pour ce choix.
        if ($this->isLicenceSeule()) {
            return self::CODE_LICENCE_SEULE;
        }

        if ($this->isHandi()) {
            return 'G';
        }

        if ($this->isEncadrant()) {
            return 'F';
        }

        if ($this->isEnfant()) {
            return $this->isFamille() ? 'E' : 'D';
        }

        if ($this->isEtudiant()) {
            return 'C';
        }

        if ($this->isFamille()) {
            return 'B';
        }

        return 'A';
    }

    /**
     * Replier une lettre dont la ligne a été désactivée par le Bureau vers le tarif plein
     * correspondant à l'âge de l'adhérent : ENFANT → D, ADULTE → A.
     *
     * Le repli n'intervient qu'à l'écriture (getCode()). La lecture d'un montant ou d'un libellé
     * ignore délibérément `actif`, sans quoi les souscriptions déjà enregistrées avec un code
     * depuis désactivé deviendraient illisibles.
     *
     * @param string $lettre Lettre issue de calculerLettre().
     * @param string $cible  Cible déduite de l'âge (CIBLE_ENFANT ou CIBLE_ADULTE).
     * @return string Lettre effectivement applicable.
     */
    private function replierSiInactif(string $lettre, string $cible): string
    {
        $referentiel = self::charger($this->db);
        $ligne = $referentiel[$lettre] ?? null;

        if ($ligne !== null && (int) $ligne->actif === 1) {
            return $lettre;
        }

        foreach ([$cible, self::CIBLE_ADULTE] as $cibleRepli) {
            foreach ($referentiel as $candidat) {
                if (
                    $candidat->nature === self::NATURE_COTISATION
                    && (int) $candidat->actif === 1
                    && $candidat->cible === $cibleRepli
                    && (int) $candidat->reduction === self::PAS_DE_REDUCTION
                ) {
                    GdaLogger::warning(sprintf(
                        'CotisationService::getCode() - Tarif "%s" inactif, repli sur "%s".',
                        $lettre,
                        $candidat->code
                    ));

                    return $candidat->code;
                }
            }
        }

        // Plus aucun tarif plein actif : la configuration est cassée, mais on ne bloque pas
        // l'adhésion pour autant. La trace permet au Bureau de corriger.
        GdaLogger::error(sprintf(
            'CotisationService::getCode() - Tarif "%s" inactif et aucun tarif plein actif pour le repli.',
            $lettre
        ));

        return $lettre;
    }

    private function isValYerres(): bool
    {
        return \in_array($this->codePostal, self::CP_VAL_YERRES, true);
    }

    private function isEnfant(): bool
    {
        $limite = (new \DateTime($this->dateRentree))->modify('-18 years');
        $naissance = $this->parseDateNaissance();

        return $naissance && $limite <= $naissance;
    }

    private function isEncadrant(): bool
    {
        return $this->reduction === self::REDUCTION_ENCADRANT;
    }

    private function isFamille(): bool
    {
        return $this->reduction === self::REDUCTION_FAMILLE;
    }

    private function isEtudiant(): bool
    {
        if ($this->reduction !== self::REDUCTION_ETUDIANT) {
            return false;
        }

        $limite = (new \DateTime($this->dateRentree))->modify('-25 years');
        $naissance = $this->parseDateNaissance();

        return $naissance && $limite <= $naissance;
    }

    private function isHandi(): bool
    {
        return $this->reduction === self::REDUCTION_HANDI;
    }

    private function isLicenceSeule(): bool
    {
        return $this->reduction === self::REDUCTION_LICENCE_SEULE;
    }

    /**
     * Parse la date de naissance qui peut arriver en d/m/Y ou Y-m-d.
     */
    private function parseDateNaissance(): ?\DateTime
    {
        if (empty($this->dateDeNaissance)) {
            return null;
        }

        // Format SQL Y-m-d
        $date = \DateTime::createFromFormat('Y-m-d', $this->dateDeNaissance);
        if ($date !== false) {
            return $date;
        }

        // Format français d/m/Y
        $date = \DateTime::createFromFormat('d/m/Y', $this->dateDeNaissance);
        if ($date !== false) {
            return $date;
        }

        return null;
    }

    // ──────────────────────────────────────────────
    //  Référentiel tarifaire (statique)
    // ──────────────────────────────────────────────

    /**
     * Charger le référentiel tarifaire depuis #__gda_cotisation, une seule fois par requête HTTP.
     *
     * @param DatabaseInterface|null $db Connexion à utiliser ; le conteneur Joomla est interrogé
     *                                   si elle est omise.
     * @return array<string, object> Lignes complètes, indexées par `code`, triées par `ordre`.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    private static function charger(?DatabaseInterface $db = null): array
    {
        if (self::$referentiel !== null) {
            return self::$referentiel;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__gda_cotisation'))
            ->order($db->quoteName('ordre') . ' ASC')
            ->order($db->quoteName('code') . ' ASC');

        $db->setQuery($query);

        self::$referentiel = [];

        foreach ($db->loadObjectList() as $row) {
            self::$referentiel[$row->code] = $row;
        }

        return self::$referentiel;
    }

    /**
     * Vider le cache du référentiel.
     *
     * À appeler après toute écriture dans #__gda_cotisation : sans cela, la réponse ajax qui suit
     * une modification de tarif re-rendrait l'ancienne valeur et l'écran semblerait ne rien
     * enregistrer.
     *
     * @return void
     */
    public static function resetCache(): void
    {
        self::$referentiel = null;
    }

    /**
     * Résoudre un code en une ligne du référentiel et la zone tarifaire applicable.
     *
     * Accepte indifféremment un code exact (`A`, `LIC_ADULTE`) ou un code de souscription à deux
     * caractères (`A1`, `B2`), auquel cas le second caractère donne la zone.
     *
     * @param string                 $code Code à résoudre.
     * @param DatabaseInterface|null $db   Connexion optionnelle.
     * @return array{0: object|null, 1: string} La ligne (ou null) et la zone ('1' ou '2').
     * @throws \RuntimeException Si la table est inaccessible.
     */
    private static function resoudre(string $code, ?DatabaseInterface $db = null): array
    {
        $code = trim($code);
        $referentiel = self::charger($db);

        if (isset($referentiel[$code])) {
            return [$referentiel[$code], '1'];
        }

        if (\strlen($code) === 2 && isset($referentiel[$code[0]])) {
            return [$referentiel[$code[0]], $code[1]];
        }

        return [null, '1'];
    }

    /**
     * Obtenir la ligne tarifaire correspondant à un code.
     *
     * @param string                 $code Code cotisation (`A`, `A1`, `A2`) ou code licence (`LIC_ADULTE`).
     * @param DatabaseInterface|null $db   Connexion optionnelle.
     * @return object|null Ligne complète, ou null si le code est inconnu.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getLigne(string $code, ?DatabaseInterface $db = null): ?object
    {
        return self::resoudre($code, $db)[0];
    }

    /**
     * Obtenir le montant applicable à un code cotisation, zone comprise.
     *
     * Ne filtre volontairement pas sur `actif` : une ligne désactivée par le Bureau doit
     * continuer à tarifer les souscriptions déjà enregistrées avec ce code.
     *
     * @param string                 $codeCotisation Code à 2 caractères (ex : `B1`), ou code licence.
     * @param DatabaseInterface|null $db             Connexion optionnelle.
     * @return float Montant en euros ; 0.0 si le code est inconnu (un avertissement est journalisé).
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getMontant(string $codeCotisation, ?DatabaseInterface $db = null): float
    {
        [$ligne, $zone] = self::resoudre($codeCotisation, $db);

        if ($ligne === null) {
            GdaLogger::warning(sprintf(
                'CotisationService::getMontant() - Code cotisation inconnu ou vide : "%s"',
                $codeCotisation
            ));

            return 0.0;
        }

        return (float) ($zone === '2' ? $ligne->tarif_hvy : $ligne->tarif_vy);
    }

    /**
     * Obtenir le montant réellement dû pour un code cotisation, licence FFESSM comprise pour
     * l'option « Licence seule » (code CODE_LICENCE_SEULE) : aucune cotisation club n'est due,
     * mais l'adhérent doit régler la licence FFESSM correspondant à sa catégorie d'âge - montant
     * qui, pour tout autre code, est facturé séparément (secrétariat) et ne rentre pas ici.
     *
     * Point d'entrée à utiliser pour calculer (et figer) un montant de souscription, en amont de
     * getMontantFige() : CheckCotisation() (ajax), AdhesionModel::saveSouscription() et
     * SecretariatModel::updateCotisationCode().
     *
     * @param string                 $codeCotisation Code à 2 caractères (ex : `H1`), tel que renvoyé par getCode().
     * @param string                 $dateDeNaissance Date de naissance de l'adhérent (d/m/Y ou Y-m-d).
     * @param DatabaseInterface|null $db              Connexion optionnelle.
     * @return float Montant en euros.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getMontantSouscription(string $codeCotisation, string $dateDeNaissance, ?DatabaseInterface $db = null): float
    {
        if (self::isCodeLicenceSeule($codeCotisation)) {
            $categorie = self::GetCategorie($codeCotisation, $dateDeNaissance);

            return self::getMontantLicence($categorie, $db);
        }

        return self::getMontant($codeCotisation, $db);
    }

    /**
     * Obtenir le montant réellement facturé pour une souscription.
     *
     * Le montant est figé dans #__gda_souscriptions.cotisation_montant au moment de la
     * souscription, pour qu'une correction de tarif par le Bureau ne réécrive pas l'historique.
     * Les souscriptions antérieures à la 0.9.17 n'en ont pas : elles retombent sur le tarif
     * courant du code.
     *
     * @param float|string|null      $montantFige    Valeur de #__gda_souscriptions.cotisation_montant.
     * @param string                 $codeCotisation Code de la souscription, utilisé en repli.
     * @param DatabaseInterface|null $db             Connexion optionnelle.
     * @return float Montant en euros.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getMontantFige($montantFige, string $codeCotisation, ?DatabaseInterface $db = null): float
    {
        if ($montantFige !== null && $montantFige !== '') {
            return (float) $montantFige;
        }

        return self::getMontant($codeCotisation, $db);
    }

    /**
     * Obtenir le montant de la licence FFESSM pour une catégorie d'adhérent.
     *
     * Lit désormais #__gda_cotisation (lignes `nature = LICENCE`) : les clés #__gda_conf
     * LicADULTE / LicJEUNE / LicENFANT ont été supprimées par la migration 0.9.17.
     *
     * @param string                 $categorie Catégorie de l'adhérent : `ADULTE`, `JEUNE` ou
     *                                          `ENFANT` (cf. GetCategorie()).
     * @param DatabaseInterface|null $db        Connexion optionnelle.
     * @return float Montant en euros ; 0.0 si la catégorie est inconnue.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getMontantLicence(string $categorie, ?DatabaseInterface $db = null): float
    {
        $categorie = strtoupper(trim($categorie));

        foreach (self::charger($db) as $ligne) {
            if ($ligne->nature === self::NATURE_LICENCE && $ligne->cible === $categorie) {
                return (float) $ligne->tarif_vy;
            }
        }

        GdaLogger::warning(sprintf(
            'CotisationService::getMontantLicence() - Aucune licence pour la catégorie "%s".',
            $categorie
        ));

        return 0.0;
    }

    /**
     * Obtenir le libellé d'affichage d'un code cotisation.
     *
     * Le suffixe « [Hors Agglo] » n'est ajouté que lorsqu'il apporte une information : zone 2 ET
     * tarif hors agglomération différent du tarif agglomération. Un code dont les deux tarifs
     * sont identiques s'affiche donc sans suffixe, quelle que soit la zone.
     *
     * Repli, pour qu'une migration à moitié appliquée n'affiche jamais une clé de langue brute :
     * libellé en base, sinon clé COM_GDA_COTISATION_TARIF_* historique, sinon libellé générique.
     *
     * @param string                 $codeCotisation Code à 2 caractères (ex : `A2`), ou code licence.
     * @param DatabaseInterface|null $db             Connexion optionnelle.
     * @return string Libellé brut, à échapper par l'appelant avant insertion dans du HTML.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getLabel(string $codeCotisation, ?DatabaseInterface $db = null): string
    {
        $codeCotisation = trim($codeCotisation);
        [$ligne, $zone] = self::resoudre($codeCotisation, $db);

        if ($ligne === null || $ligne->libelle === '') {
            $cleHistorique = 'COM_GDA_COTISATION_TARIF_' . $codeCotisation;

            if ($codeCotisation !== '' && Text::_($cleHistorique) !== $cleHistorique) {
                return Text::_($cleHistorique);
            }

            GdaLogger::warning(sprintf(
                'CotisationService::getLabel() - Libellé introuvable pour le code "%s".',
                $codeCotisation
            ));

            return Text::_('COM_GDA_COTISATION_LABEL_INCONNU');
        }

        if ($zone === '2' && self::tarifsDifferents($ligne)) {
            return $ligne->libelle . Text::_('COM_GDA_COTISATION_SUFFIXE_HORS_AGGLO');
        }

        return $ligne->libelle;
    }

    /**
     * Déterminer si une ligne porte deux tarifs distincts selon la zone.
     *
     * Comparaison sur chaînes normalisées : les colonnes decimal remontent en PHP sous forme de
     * chaînes via PDO, et une comparaison de flottants serait instable.
     *
     * @param object $ligne Ligne du référentiel.
     * @return bool True si le tarif hors agglomération diffère du tarif agglomération.
     */
    private static function tarifsDifferents(object $ligne): bool
    {
        return number_format((float) $ligne->tarif_vy, 2, '.', '')
            !== number_format((float) $ligne->tarif_hvy, 2, '.', '');
    }

    /**
     * Déterminer si un code cotisation correspond à l'option « Licence seule ».
     *
     * @param string $codeCotisation Code de souscription (ex : `H1`, `H2`), ou vide.
     * @return bool True si le code commence par CODE_LICENCE_SEULE.
     */
    public static function isCodeLicenceSeule(string $codeCotisation): bool
    {
        return strncmp(trim($codeCotisation), self::CODE_LICENCE_SEULE, \strlen(self::CODE_LICENCE_SEULE)) === 0;
    }

    /**
     * Formater un montant pour l'affichage : 2 décimales, virgule décimale, espace comme
     * séparateur de milliers, symbole euro.
     *
     * Point de formatage unique du composant. Le serveur renvoie toujours une chaîne déjà
     * formatée : aucun montant ne doit être mis en forme côté JavaScript.
     *
     * @param float|string|null $montant Montant en euros.
     * @return string Montant formaté (ex : « 205,00 € »).
     */
    public static function formatMontant($montant): string
    {
        return number_format((float) $montant, 2, ',', ' ') . ' €';
    }

    // ──────────────────────────────────────────────
    //  Administration du référentiel (onglet « Tarification »)
    // ──────────────────────────────────────────────

    /**
     * Obtenir toutes les lignes tarifaires dans l'ordre d'affichage, pour l'onglet
     * « Tarification » de la vue Saisons.
     *
     * Inclut les lignes inactives : c'est précisément l'écran qui permet de les réactiver.
     *
     * @param DatabaseInterface|null $db Connexion optionnelle.
     * @return object[] Lignes complètes, triées par `ordre` puis `code`.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getLignesAdmin(?DatabaseInterface $db = null): array
    {
        return array_values(self::charger($db));
    }

    /**
     * Modifier une case du référentiel tarifaire (édition inline de l'onglet « Tarification »).
     *
     * Seuls libelle, commentaire, tarif_vy, tarif_hvy et actif sont modifiables. Pour une ligne
     * `nature = LICENCE`, les deux tarifs sont écrits ensemble : une licence FFESSM ne dépend pas
     * du lieu de résidence.
     *
     * @param int                    $idTarif Identifiant de la ligne.
     * @param string                 $champ   Champ à modifier (liste blanche CHAMPS_EDITABLES).
     * @param string                 $valeur  Nouvelle valeur, telle que saisie par le Bureau.
     * @param DatabaseInterface|null $db      Connexion optionnelle.
     * @return object La ligne relue après écriture, pour re-rendu côté serveur.
     * @throws \InvalidArgumentException Identifiant invalide, champ hors liste blanche, libellé
     *                                   vide ou montant non numérique, négatif ou hors bornes.
     * @throws \RuntimeException         Ligne introuvable ou écriture SQL en échec.
     */
    public static function updateLigne(int $idTarif, string $champ, string $valeur, ?DatabaseInterface $db = null): object
    {
        if ($idTarif <= 0) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_INTROUVABLE'));
        }

        if (!\in_array($champ, self::CHAMPS_EDITABLES, true)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_FIELD'));
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $ligne = self::lireLigneParId($idTarif, $db);

        if ($ligne === null) {
            throw new \RuntimeException(Text::_('COM_GDA_SAISONS_TARIF_ERR_INTROUVABLE'));
        }

        $query = $db->createQuery()->update($db->quoteName('#__gda_cotisation'));

        // Portée de l'écriture : la ligne éditée, sauf pour `option_libelle` qui appartient à
        // l'option du formulaire et doit rester identique sur toutes les lignes qui la partagent.
        $propagerSurReduction = false;

        switch ($champ) {
            case 'libelle':
                $libelle = trim($valeur);

                if ($libelle === '') {
                    throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_LIBELLE'));
                }

                $libelle = mb_substr($libelle, 0, 255);
                $query->set($db->quoteName('libelle') . ' = :libelle')
                    ->bind(':libelle', $libelle);
                break;

            case 'option_libelle':
                // Une ligne LICENCE n'apparaît jamais dans la liste du formulaire : elle n'a pas
                // d'option, donc pas de libellé d'option à modifier.
                if ($ligne->reduction === null) {
                    throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_FIELD'));
                }

                $optionLibelle = trim($valeur);

                if ($optionLibelle === '') {
                    throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_OPTION'));
                }

                $optionLibelle = mb_substr($optionLibelle, 0, 100);
                $query->set($db->quoteName('option_libelle') . ' = :option_libelle')
                    ->bind(':option_libelle', $optionLibelle);
                $propagerSurReduction = true;
                break;

            case 'commentaire':
                $commentaire = trim($valeur);

                if ($commentaire === '') {
                    $query->set($db->quoteName('commentaire') . ' = NULL');
                    break;
                }

                $commentaire = mb_substr($commentaire, 0, 255);
                $query->set($db->quoteName('commentaire') . ' = :commentaire')
                    ->bind(':commentaire', $commentaire);
                break;

            case 'tarif_vy':
            case 'tarif_hvy':
                $montant = self::normaliserMontant($valeur);
                $query->bind(':montant', $montant);

                if ($ligne->nature === self::NATURE_LICENCE) {
                    // Une licence FFESSM ne dépend pas du lieu de résidence : les deux tarifs
                    // restent alignés, quel que soit le champ effectivement édité.
                    $query->set($db->quoteName('tarif_vy') . ' = :montant')
                        ->set($db->quoteName('tarif_hvy') . ' = :montant');
                    break;
                }

                $query->set($db->quoteName($champ) . ' = :montant');
                break;

            case 'actif':
                $actif = (int) ((bool) $valeur);
                $query->set($db->quoteName('actif') . ' = :actif')
                    ->bind(':actif', $actif, ParameterType::INTEGER);
                break;
        }

        if ($propagerSurReduction) {
            $nature = self::NATURE_COTISATION;
            $reduction = (int) $ligne->reduction;
            $query->where($db->quoteName('nature') . ' = :nature')
                ->where($db->quoteName('reduction') . ' = :reduction')
                ->bind(':nature', $nature, ParameterType::STRING)
                ->bind(':reduction', $reduction, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('id_tarif') . ' = :id_tarif')
                ->bind(':id_tarif', $idTarif, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $db->execute();

        // Le cache doit tomber AVANT la relecture, sinon la réponse ajax re-rendrait la ligne
        // telle qu'elle était au début de la requête.
        self::resetCache();

        $ligneRelue = self::lireLigneParId($idTarif, $db);

        if ($ligneRelue === null) {
            throw new \RuntimeException(Text::_('COM_GDA_SAISONS_TARIF_ERR_INTROUVABLE'));
        }

        return $ligneRelue;
    }

    /**
     * Lire une ligne du référentiel par son identifiant technique.
     *
     * Passe par le cache déjà chargé plutôt que par une requête dédiée : le référentiel compte une
     * dizaine de lignes et est de toute façon lu au moins une fois par requête HTTP.
     *
     * @param int               $idTarif Identifiant de la ligne.
     * @param DatabaseInterface $db      Connexion à utiliser.
     * @return object|null La ligne, ou null si l'identifiant est inconnu.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    private static function lireLigneParId(int $idTarif, DatabaseInterface $db): ?object
    {
        foreach (self::charger($db) as $ligne) {
            if ((int) $ligne->id_tarif === $idTarif) {
                return $ligne;
            }
        }

        return null;
    }

    /**
     * Normaliser un montant saisi par le Bureau vers la notation SQL du decimal(6,2).
     *
     * Accepte la virgule comme séparateur décimal et les espaces de saisie.
     *
     * @param string $valeur Montant tel que saisi (ex : « 205,50 »).
     * @return string Montant en notation SQL (ex : « 205.50 »).
     * @throws \InvalidArgumentException Si la valeur n'est pas un montant positif dans les bornes.
     */
    private static function normaliserMontant(string $valeur): string
    {
        $brut = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($valeur));

        if ($brut === '' || !is_numeric($brut)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_MONTANT'));
        }

        $montant = (float) $brut;

        if ($montant < 0 || $montant > self::MONTANT_MAX) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_SAISONS_TARIF_ERR_MONTANT'));
        }

        return number_format($montant, 2, '.', '');
    }

    /**
     * Obtenir les options de la liste « Tarification » du formulaire d'adhésion, construites
     * depuis les lignes actives du référentiel.
     *
     * Une option n'est proposée que si au moins une ligne active porte sa valeur de `reduction` :
     * plusieurs lignes peuvent partager une même option (A/D sur « Normal », B/E sur « Réduction
     * Famille »), la ligne effectivement retenue étant choisie par getCode() en fonction de l'âge.
     * Le libellé vient de `option_libelle`, administrable par le Bureau : il décrit la situation
     * de l'adhérent, pas le tarif, et est identique sur toutes les lignes d'une même option.
     *
     * @param DatabaseInterface|null $db Connexion optionnelle.
     * @return array<int, string> Valeur de réduction => libellé traduit, ordonné par `ordre`.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getOptionsReduction(?DatabaseInterface $db = null): array
    {
        $options = [];

        foreach (self::charger($db) as $ligne) {
            if (
                $ligne->nature !== self::NATURE_COTISATION
                || (int) $ligne->actif !== 1
                || $ligne->reduction === null
            ) {
                continue;
            }

            $reduction = (int) $ligne->reduction;

            if (!isset($options[$reduction])) {
                $options[$reduction] = self::libelleOptionOuDefaut($ligne->option_libelle, $reduction);
            }
        }

        return $options;
    }

    /**
     * Obtenir le libellé d'une option de tarification, y compris pour une valeur de réduction
     * dont plus aucun tarif actif n'est porteur (souscription historique).
     *
     * @param int                    $reduction Valeur de réduction.
     * @param DatabaseInterface|null $db        Connexion optionnelle.
     * @return string Libellé de l'option, prêt à l'affichage.
     * @throws \RuntimeException Si le référentiel est inaccessible.
     */
    public static function getLibelleOption(int $reduction, ?DatabaseInterface $db = null): string
    {
        foreach (self::charger($db) as $ligne) {
            if (
                $ligne->nature === self::NATURE_COTISATION
                && $ligne->reduction !== null
                && (int) $ligne->reduction === $reduction
            ) {
                return self::libelleOptionOuDefaut($ligne->option_libelle, $reduction);
            }
        }

        return self::libelleOptionOuDefaut(null, $reduction);
    }

    /**
     * Repli d'affichage pour un libellé d'option absent en base.
     *
     * Ne peut se produire qu'entre le déploiement du code et celui du schéma, ou sur une valeur de
     * réduction qui n'existe plus dans le référentiel : on affiche alors la valeur brute plutôt
     * qu'une chaîne vide, pour que le Bureau voie qu'il manque quelque chose.
     *
     * @param string|null $optionLibelle Valeur lue en base.
     * @param int         $reduction     Valeur de réduction concernée.
     * @return string Libellé d'affichage.
     */
    private static function libelleOptionOuDefaut(?string $optionLibelle, int $reduction): string
    {
        $optionLibelle = trim((string) $optionLibelle);

        if ($optionLibelle !== '') {
            return $optionLibelle;
        }

        GdaLogger::warning(sprintf(
            'CotisationService - Libellé d\'option manquant pour la réduction %d.',
            $reduction
        ));

        return Text::sprintf('COM_GDA_SAISONS_TARIF_OPTION_INCONNUE', $reduction);
    }

    /**
     * Obtenir les libellés des autres lignes actives qui partagent une valeur de réduction.
     *
     * Sert à avertir le Bureau qu'une désactivation ne retire pas l'option du formulaire tant
     * qu'une autre ligne du même couple reste active (A/D, B/E).
     *
     * @param int                    $reduction Valeur de réduction concernée.
     * @param int                    $exclureId id_tarif à exclure (la ligne qui vient d'être désactivée).
     * @param DatabaseInterface|null $db        Connexion optionnelle.
     * @return string[] Libellés des autres lignes actives portant cette réduction.
     * @throws \RuntimeException Si la table est inaccessible.
     */
    public static function getLignesActivesPourReduction(int $reduction, int $exclureId = 0, ?DatabaseInterface $db = null): array
    {
        $libelles = [];

        foreach (self::charger($db) as $ligne) {
            if (
                $ligne->nature === self::NATURE_COTISATION
                && (int) $ligne->actif === 1
                && $ligne->reduction !== null
                && (int) $ligne->reduction === $reduction
                && (int) $ligne->id_tarif !== $exclureId
            ) {
                $libelles[] = $ligne->libelle;
            }
        }

        return $libelles;
    }

    // ──────────────────────────────────────────────
    //  Dates et catégories
    // ──────────────────────────────────────────────

    /**
     * Renvoie la date du prochain 1er septembre à partir de maintenant.
     *
     * Si on est avant le 1er septembre, renvoie le 1er septembre de l'année en cours, sinon celui
     * de l'année suivante.
     *
     * @return string Date au format Y-m-d.
     */
    public static function getProchaineRentree(): string
    {
        $now = new \DateTime();
        $currentYear = (int) $now->format('Y');
        $rentreeThisYear = new \DateTime("$currentYear-09-01");

        if ($now < $rentreeThisYear) {
            return $rentreeThisYear->format('Y-m-d');
        }

        return (new \DateTime(($currentYear + 1) . '-09-01'))->format('Y-m-d');
    }

    /**
     * **Obtenir la catégorie d'une souscription à partir du code cotisation et de la date de naissance.**
     * Catégories :
     * - ENFANT  : si moins de 12 ans à la prise de la licence
     * - JEUNE : si moins de 17 ans à la date de rentrée
     * - ADULTE : sinon
     *
     * @param string $code              Code cotisation (conservé pour compatibilité d'appel).
     * @param string $date_de_naissance Date de naissance de l'adhérent.
     * @return string ENFANT, JEUNE ou ADULTE.
     */
    public static function GetCategorie(string $code, string $date_de_naissance): string
    {
        $prochaineRentree = new \DateTime(self::getProchaineRentree());
        $age = $prochaineRentree->diff(new \DateTime($date_de_naissance))->y;

        if ($age < 12) {
            return "ENFANT";
        }

        if ($age < 17) {
            return "JEUNE";
        }

        return "ADULTE";
    }
}
