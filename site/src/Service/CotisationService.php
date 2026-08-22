<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use SpomkyLabs\Pki\ASN1\Type\Primitive\Real;

/**
 * Service métier pour le calcul des cotisations.
 *
 * Reprend la logique de l'ancien helper Joomla 3
 * (GestionDesAdherentsHelperCotisation) en chargeant
 * les tarifs depuis la table #__gda_cotisation.
 */
final class CotisationService
{
    // Types de réduction
    public const PAS_DE_REDUCTION   = 0;
    public const REDUCTION_FAMILLE   = 1;
    public const REDUCTION_ENCADRANT = 2;
    public const REDUCTION_ETUDIANT  = 3;
    public const REDUCTION_HANDI     = 4;

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

    /** @var array<string, object>|null  Cache des lignes cotisation indexées par code */
    private ?array $tableau = null;

    /** @var array<string, int>|null Cache statique des tarifs indexés par code (A1, A2, ...). */
    private static ?array $tableauStatic = null;

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

        $this->loadTableauCotisation();
    }

    // ──────────────────────────────────────────────
    //  API publique
    // ──────────────────────────────────────────────


    /**
     * Calculer le code cotisation (ex : "A1", "E2", …).
     *
     * Le chiffre 1 = Val d'Yerres, 2 = hors Val d'Yerres.
     *
     * @return string Code cotisation
     */
    public function getCode(): string
    {
        $suffix = $this->isValYerres() ? '1' : '2';

        if ($this->isHandi()) {
            return 'G' . $suffix;
        }

        if ($this->isEncadrant()) {
            return 'F' . $suffix;
        }

        if ($this->isEnfant()) {
            return $this->isFamille()
                ? 'E' . $suffix
                : 'D' . $suffix;
        }

        if ($this->isEtudiant()) {
            return 'C' . $suffix;
        }

        if ($this->isFamille()) {
            return 'B' . $suffix;
        }

        return 'A' . $suffix;
    }

    /**
     * Obtenir le tableau complet des cotisations (chargé depuis la DB).
     *
     * @return array<string, object>  Clé = code (A, B, …), valeur = objet avec label, montant_vy, montant_hvy
     */
    public function getTableau(): array
    {
        $this->loadTableauCotisation();

        return $this->tableau;
    }

   
    /**
     * Obtenir le libellé de la cotisation pour un code lettre.
     *
     * @param string $codeCotisation  Code renvoyé par calcul() (ex : "B1")
     * @return string  Clé de langue (ex : "COM_GADHERENTS_COTISATION_ADULTES_FAMILE")
     */
    public function getLabel(string $codeCotisation): string
    {
        $this->loadTableauCotisation();

        $lettre = substr($codeCotisation, 0, 1);

        return $this->tableau[$lettre]->label ?? '';
    }


    /**
     * La réduction "Famille" ne s'applique qu'aux adultes (le tarif enfant/jeune familial est
     * déjà un tarif réduit distinct, cf. getCode() codes D/E) : false si "Famille" est choisie
     * pour un mineur.
     */
    public function isReductionFamilleValide(): bool
    {
        return !($this->isFamille() && $this->isEnfant());
    }

    /**
     * Âge minimum accepté par le club, calculé à la même date de référence que GetCategorie()
     * (1er septembre de la saison) pour rester cohérent : un enfant qui aura l'âge minimum avant
     * la rentrée est accepté. Retourne true si la date de naissance est manquante/invalide (ce
     * n'est pas à ce contrôle de le signaler, un champ requis s'en charge déjà).
     *
     * @param int $ageMinimum Âge minimum en années (8 par défaut).
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
    //  Chargement DB
    // ──────────────────────────────────────────────

    /**
     * Charger les tarifs cotisation depuis #__gda_cotisation (une seule fois).
     */
    private function loadTableauCotisation(): void
    {
        if ($this->tableau !== null) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_cotisation'));


        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList();

        $this->tableau = [];
        foreach ($rows as $row) {
            $this->tableau[$row->code . "1"] = $row->tarif_vy;
            $this->tableau[$row->code . "2"] = $row->tarif_hvy;
        }
    }

   

    /**
     * =========================================================
     * Fonction statiques pour être utilisées en dehors du contexte d'un calcul (ex : dans le secrétariat)
     * =========================================================
     */


     /**
     * Charger les tarifs cotisation en cache statique (une seule fois par requete).
     *
     * @param DatabaseInterface $db
     * @return array<string, int>
     */
    private static function loadTableauCotisationStatic(DatabaseInterface $db): array
    {
        if (self::$tableauStatic !== null) {
            return self::$tableauStatic;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gda_cotisation'));

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        self::$tableauStatic = [];

        foreach ($rows as $row) {
            self::$tableauStatic[$row->code . '1'] = (int) $row->tarif_vy;
            self::$tableauStatic[$row->code . '2'] = (int) $row->tarif_hvy;
        }

        return self::$tableauStatic;
    }

     /**
     * Obtenir le montant de la cotisation pour le code calculé.
     *
     * @param string $codeCotisation  Code renvoyé par calcul() (ex : "B1")
     * @return string  Montant formaté tel que stocké en base (ex : "180")
     */
    public static function getMontant(string $codeCotisation, ?DatabaseInterface $db = null): int
    {
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $tableau = self::loadTableauCotisationStatic($db);

        // $lettre = substr($codeCotisation, 0, 1);
        // $zone   = substr($codeCotisation, 1, 1); // 1 ou 2

        if (!isset($tableau[$codeCotisation])) {
            GdaLogger::warning(sprintf(
                'CotisationService::getMontant() - Code cotisation inconnu ou vide : "%s"',
                $codeCotisation
            ));
            return 0;
        }

        return (int) $tableau[$codeCotisation];
    }


    /**
     * Obenir le cout de la licence en fonction de la categorie donner en arguments
     *  @param string $categorie  Catégorie de l'adhérent (ex : "ADULTE", "JEUNE", "ENFANT", etc.)
     * 
     */
    public static function getMontantLicence(string $categorie): string
    {
        $categorie = strtoupper(trim($categorie));

        $raw = match ($categorie) {
            'ADULTE' => ConfHelper::getValue('LicADULTE'),
            'JEUNE'  => ConfHelper::getValue('LicJEUNE'),
            'ENFANT' => ConfHelper::getValue('LicENFANT'),
            default  => false,
        };

        if ($raw === false) {
            return "";
        }

        return  $raw;
    }

    /** 
     *  Function que renvoie la date du prochain 1 septembre à partir de maintenant
     *  Si on est avant le 1 septembre, renvoie le 1 septembre de l'année en cours, sinon renvoie le 1 septembre de l'année suivante
     */
     static public function getProchaineRentree(): string
    {
        $now = new \DateTime();
        $currentYear = (int) $now->format('Y');
        $rentreeThisYear = new \DateTime("$currentYear-09-01");

        if ($now < $rentreeThisYear) {
            return $rentreeThisYear->format('Y-m-d');
        } else {
            $rentreeNextYear = new \DateTime(($currentYear + 1) . "-09-01");
            return $rentreeNextYear->format('Y-m-d');
        }
    }


    /**
     * **Obtenir la catégorie d'une souscription à partir du code cotisation et de la date de naissance.**
     * Catégories :
     * - ENFANT  : si  moin de 12 ans à la prise de la licence
     * - JEUNE : si moins de 17 ans à la date de rentrée
     * - ADULTE : sinon
     */
    static public function GetCategorie(string $code, string  $date_de_naissance): string
    {

        $prochaineRentree = new \DateTime(self::getProchaineRentree());
        $age = $prochaineRentree->diff(new \DateTime($date_de_naissance))->y;
         if ($age < 12) {
            return "ENFANT";
            // Avec la date de naissance verifier si il a strictement moins de 17 ans à la date de rentrée
        } else if ($age < 17) {
            return "JEUNE";
        } else {
            return "ADULTE";
        }
    }
}
