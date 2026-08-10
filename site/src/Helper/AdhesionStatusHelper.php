<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\SaisonService;
use NCB\Component\Gda\Site\Service\SouscriptionService;

/**
 * Helper pour la gestion du statut d'adhésion
 *
 * @since  1.0.0
 */
class AdhesionStatusHelper
{
    /**
     * Constantes de statut d'adhésion
     */
    const STATUS_NOT_SUBSCRIBED = 'NOT_SUBSCRIBED';
    // const STATUS_CACI_REQUIRED = 'CACI_REQUIRED';
    const STATUS_CACI_VALIDATING = 'CACI_VALIDATING';
    const STATUS_CACI_EXPIRED = 'CACI_EXPIRED';
    const STATUS_PAYMENT_REQUIRED = 'PAYMENT_REQUIRED';
    const STATUS_PAYMENT_VALIDATING = 'PAYMENT_VALIDATING';
    const STATUS_LICENCE_REQUIRED = 'LICENCE_REQUIRED';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CACI_VALID = 'CACI_VALID';
    // const STATUS_CACI_EXPIRED = 'CACI_EXPIRED'; // Supprimé car déjà défini à la ligne 31
    const STATUS_CACI_EXPIRING_SOON = 'CACI_EXPIRING_SOON';
    const STATUS_CACI_MISSING = 'CACI_MISSING';

    /**
     * Détermine le statut d'adhésion en fonction de la souscription.
     * Logique prioritaire : pas de souscription → CACI → Paiement → Licence → Complété
     *
     * @param object|null $souscription Objet souscription ou null
     * @return string Code du statut
     */
    public static function getStatusEnum(?object $souscription): string
    {
        // Pas de souscription
        if ($souscription === null) {
            return self::STATUS_NOT_SUBSCRIBED;
        }

        // CACI manquant ou non validé
        if (!$souscription->caci_check) {
            return self::getCaciFileStatus($souscription->caci, $souscription->date_caci);
        }



        // Paiement manquant ou non validé
        if (!$souscription->cotisation_check) {
            // id_order pas encore connu localement : on tente de le retrouver sur HelloAsso
            // (ex: paiement effectué mais webhook/retour HelloAsso pas encore traité)
            if ($souscription->id_order === "0" || empty($souscription->id_order)) {
                $souscription->id_order = self::resolveIdOrder($souscription);
            }

            if ($souscription->id_order === "0" || empty($souscription->id_order)) {
                return self::STATUS_PAYMENT_REQUIRED;
            }
            return self::STATUS_PAYMENT_VALIDATING;
        }

        // Licence manquante ou non validée
        if (!$souscription->licence_check) {
            return self::STATUS_LICENCE_REQUIRED;
        }

        // Tous les contrôles passés
        return self::STATUS_COMPLETED;
    }

    /**
     * Tente de retrouver l'id_order manquant d'une souscription auprès de HelloAsso
     * (recherche par username) et le persiste dans #__gda_souscriptions si trouvé.
     *
     * @param object $souscription Objet souscription (id_profil, id_campagne, id_order, username)
     * @return string L'id_order résolu, ou '0'/vide si toujours introuvable
     */
    private static function resolveIdOrder(object $souscription): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (new SouscriptionService($db))->resolveIdOrder(
            (int) ($souscription->id_profil ?? 0),
            (int) ($souscription->id_campagne ?? 0),
            (string) ($souscription->id_order ?? '0'),
            (string) ($souscription->username ?? '')
        );
    }

    /**
     * Calcule le nombre de jours avant expiration d'une date.
     *
     * @param \DateTime|string|null $expireDate Date d'expiration (objet DateTime ou chaîne SQL)
     * @return int Nombre de jours (négatif = expiré, 0 = expire aujourd'hui)
     */
    public static function getDaysBeforeExpiry($expireDate): int
    {
        if ($expireDate === null || $expireDate === '') {
            return -999; // Considérer comme expiré si aucune date
        }

        try {
            if (is_string($expireDate)) {
                $expireDate = new \DateTime($expireDate, new \DateTimeZone('UTC'));
            }

            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $interval = $now->diff($expireDate);

            // Calcul du nombre de jours
            $days = (int) $interval->format('%r%a'); // %r = signe +/-, %a = jours

            return $days;
        } catch (\Exception $e) {
            return -999;
        }
    }

    /**
     * Détermine le statut du CACI d'un profil directement à partir de #__gda_profils
     * (fichier + date de validité), sans dépendre d'une souscription/campagne ni de la
     * validation du secrétariat (contrairement à getStatusEnum()).
     *
     * @param string|null $caciFile Nom du fichier CACI (colonne #__gda_profils.caci)
     * @param string|null $dateCaci Date de fin de validité au format SQL (colonne #__gda_profils.date_caci)
     * @return string Un des STATUS_CACI_*
     */
    public static function getCaciFileStatus(?string $caciFile, ?string $dateCaci): string
    {
        $path = (string) ConfHelper::getValue("CaciPath") . $caciFile;

        if (empty($caciFile) || empty($dateCaci) || \is_file(JPATH_ROOT . $path) === false) {
            return self::STATUS_CACI_MISSING;
        }

        $daysBeforeExpiry = self::getDaysBeforeExpiry($dateCaci);

        if ($daysBeforeExpiry < 0) {
            return self::STATUS_CACI_EXPIRED;
        }

        if ($daysBeforeExpiry < 90) {
            // Expire dans moins de 3 mois
            return self::STATUS_CACI_EXPIRING_SOON;
        }

        return self::STATUS_CACI_VALID;
    }

    /**
     * Construit un lien d'action en fonction du statut d'adhésion.
     *
     * @param string $statusEnum Code du statut
     * @param object|null $souscription Objet souscription
     * @return array|null Tableau avec ['url' => '', 'label' => '', 'type' => 'link|button|modal'] ou null
     */
    public static function buildActionLink(string $statusEnum, ?object $souscription): ?array
    {
        $color = self::getStatusBadgeClass($statusEnum);
        switch ($statusEnum) {
            case self::STATUS_NOT_SUBSCRIBED:
                return [
                    'url'    => 'index.php?option=com_gdadhesions&view=adhesion',
                    'label'  => 'COM_GDA_ACTION_SUBSCRIBE',
                    'type'   => 'button',
                    'color'  => $color, // rouge
                    'icon'   => 'fa-plus-circle'
                ];

            case self::STATUS_CACI_EXPIRED:
            case self::STATUS_CACI_EXPIRING_SOON:
            case self::STATUS_CACI_MISSING:
                return [
                    'url'    => 'index.php?option=com_gdadhesions&view=profil',
                    'label'  => 'COM_GDA_ACTION_UPDATE_CACI',
                    'type'   => 'link',
                    'color'  => $color, // orange
                    'icon'   => 'fa-exclamation-triangle'
                ];

            case self::STATUS_PAYMENT_REQUIRED:
                $saison = SaisonService::getSaison((int) ($souscription->id_campagne ?? 0));
                return [
                    'url'    => $saison->url ?? '#',
                    'label'  => 'COM_GDA_ACTION_PAY',
                    'type'   => 'external_link',
                    'color'  => $color, // orange
                    'icon'   => 'fa-credit-card',
                ];

            case self::STATUS_PAYMENT_VALIDATING:
                return [
                    'url'    => '#payementModal',
                    'label'  => 'COM_GDA_ACTION_PAY_VALIDATING',
                    'type'   => 'ajax_modal',
                    'color'  => $color,
                    'icon'   => 'fa-credit-card',
                    'id_profil'   => (int) ($souscription->id_profil ?? 0),
                    'id_campagne' => (int) ($souscription->id_campagne ?? 0),
                    'id_order'    => (string) ($souscription->id_order ?? '0'),
                    'username'    => (string) ($souscription->username ?? ''),
                    'cotisation'  => CotisationService::getMontant((string) ($souscription->cotisation_code ?? '')),
                ];

            case self::STATUS_LICENCE_REQUIRED:
            case self::STATUS_COMPLETED:
                return null; // Pas d'action requise

            default:
                return null;
        }
    }

    /**
     * Retourne le libellé du statut en fonction du code.
     *
     * @param string $statusEnum Code du statut
     * @return string Libellé traduit
     */
    public static function getStatusLabel(string $statusEnum): string
    {
        $labels = [
            self::STATUS_NOT_SUBSCRIBED   => 'COM_GDA_STATUS_NOT_SUBSCRIBED',
            // self::STATUS_CACI_REQUIRED    => 'COM_GDA_STATUS_CACI_REQUIRED',
            self::STATUS_CACI_EXPIRED     => 'COM_GDA_STATUS_CACI_EXPIRED',
            self::STATUS_CACI_VALIDATING => 'COM_GDA_STATUS_CACI_VALIDATING',
            self::STATUS_PAYMENT_REQUIRED => 'COM_GDA_STATUS_PAYMENT_REQUIRED',
            self::STATUS_PAYMENT_VALIDATING => 'COM_GDA_STATUS_PAYMENT_VALIDATING',
            self::STATUS_LICENCE_REQUIRED => 'COM_GDA_STATUS_LICENCE_REQUIRED',
            self::STATUS_COMPLETED        => 'COM_GDA_STATUS_COMPLETED',
            self::STATUS_CACI_VALID          => 'COM_GDA_PROFIL_CACI_STATUS_VALID',
            self::STATUS_CACI_EXPIRED        => 'COM_GDA_PROFIL_CACI_STATUS_EXPIRED',
            self::STATUS_CACI_EXPIRING_SOON  => 'COM_GDA_PROFIL_CACI_STATUS_EXPIRING_SOON',
            self::STATUS_CACI_MISSING        => 'COM_GDA_PROFIL_CACI_STATUS_MISSING',
        ];

        $key = $labels[$statusEnum] ?? 'COM_GDA_STATUS_UNKNOWN';
        return Text::_($key);
    }

    /**
     * Statuts simplifiés (3 états), pour un affichage synthétique (ex: colonne "Statut" de la
     * vue Utilisateurs) sans exposer le détail des étapes CACI/Paiement/Licence.
     */
    const SIMPLIFIED_STATUS_NOT_SUBSCRIBED = 'NOT_SUBSCRIBED';
    const SIMPLIFIED_STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const SIMPLIFIED_STATUS_COMPLETED = 'COMPLETED';

    /**
     * Regroupe un statut détaillé (STATUS_*) en un statut simplifié à 3 états :
     * pas d'adhésion / adhésion en cours (CACI, paiement ou licence en attente) / adhésion finalisée.
     *
     * @param string $statusEnum Code du statut détaillé (voir getStatusEnum())
     * @return string Un des SIMPLIFIED_STATUS_*
     */
    public static function getSimplifiedStatus(string $statusEnum): string
    {
        if ($statusEnum === self::STATUS_NOT_SUBSCRIBED) {
            return self::SIMPLIFIED_STATUS_NOT_SUBSCRIBED;
        }

        if ($statusEnum === self::STATUS_COMPLETED) {
            return self::SIMPLIFIED_STATUS_COMPLETED;
        }

        return self::SIMPLIFIED_STATUS_IN_PROGRESS;
    }

    /**
     * Libellé traduit du statut simplifié.
     *
     * @param string $simplifiedStatus Un des SIMPLIFIED_STATUS_*
     * @return string Libellé traduit
     */
    public static function getSimplifiedStatusLabel(string $simplifiedStatus): string
    {
        $labels = [
            self::SIMPLIFIED_STATUS_NOT_SUBSCRIBED => 'COM_GDA_UTILISATEURS_ADHESION_STATUS_NOT_SUBSCRIBED',
            self::SIMPLIFIED_STATUS_IN_PROGRESS    => 'COM_GDA_UTILISATEURS_ADHESION_STATUS_IN_PROGRESS',
            self::SIMPLIFIED_STATUS_COMPLETED      => 'COM_GDA_UTILISATEURS_ADHESION_STATUS_COMPLETED',
        ];

        return Text::_($labels[$simplifiedStatus] ?? 'COM_GDA_STATUS_UNKNOWN');
    }

    /**
     * Couleur de badge Bootstrap associée au statut simplifié.
     *
     * @param string $simplifiedStatus Un des SIMPLIFIED_STATUS_*
     * @return string Classe Bootstrap (bg-danger, bg-warning, bg-success, ...)
     */
    public static function getSimplifiedStatusBadgeClass(string $simplifiedStatus): string
    {
        switch ($simplifiedStatus) {
            case self::SIMPLIFIED_STATUS_NOT_SUBSCRIBED:
                return 'danger';
            case self::SIMPLIFIED_STATUS_IN_PROGRESS:
                return 'warning';
            case self::SIMPLIFIED_STATUS_COMPLETED:
                return 'success';
            default:
                return 'secondary';
        }
    }

    /**
     * Icône Font Awesome associée au statut simplifié (cohérente avec getStatusDescription()).
     *
     * @param string $simplifiedStatus Un des SIMPLIFIED_STATUS_*
     * @return string Classe icône (ex: "fa-solid fa-check-circle")
     */
    public static function getSimplifiedStatusIcon(string $simplifiedStatus): string
    {
        switch ($simplifiedStatus) {
            case self::SIMPLIFIED_STATUS_NOT_SUBSCRIBED:
                return 'fa-solid fa-user-plus';
            case self::SIMPLIFIED_STATUS_IN_PROGRESS:
                return 'fa-solid fa-hourglass-half';
            case self::SIMPLIFIED_STATUS_COMPLETED:
                return 'fa-solid fa-check-circle';
            default:
                return 'fa-solid fa-circle-info';
        }
    }

    /**
     * Retourne une description enrichie du statut, sous forme de données structurées
     * (type d'alerte, icône, message traduit) à charge du layout de produire le HTML.
     *
     * @param string $statusEnum Code du statut
     * @param object|null $souscription Objet souscription, utilisé pour les messages dynamiques
     *                                  (date CACI, jours restants, n° de commande, ...)
     * @return array{type: string, icon: string, message: string} Description du statut
     */
    public static function getStatusDescription(string $statusEnum, ?object $souscription = null): array
    {
        $type = self::getStatusBadgeClass($statusEnum);
        switch ($statusEnum) {
            case self::STATUS_NOT_SUBSCRIBED:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-user-plus',
                    'message' => Text::_('COM_GDA_STATUS_DESC_NOT_SUBSCRIBED'),
                ];

            case self::STATUS_CACI_MISSING:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-file-medical',
                    'message' => Text::_('COM_GDA_STATUS_DESC_CACI_FILE_MISSING'),
                ];

            case self::STATUS_CACI_EXPIRED:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-triangle-exclamation',
                    'message' => Text::sprintf(
                        'COM_GDA_STATUS_DESC_CACI_FILE_EXPIRED',
                        ToolsHelper::from_sqldate($souscription->date_caci ?? null)
                    ),
                ];

            case self::STATUS_CACI_EXPIRING_SOON:
                $joursRestants = self::getDaysBeforeExpiry($souscription->date_caci ?? null);
                return [
                    'type'    => $type,
                    'icon'    => 'fa-clock',
                    'message' => Text::sprintf(
                        'COM_GDA_STATUS_DESC_CACI_FILE_EXPIRING_SOON',
                        ToolsHelper::from_sqldate($souscription->date_caci ?? null),
                        max(0, $joursRestants)
                    ),
                ];

            case self::STATUS_CACI_VALID:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-hourglass-half',
                    'message' => Text::sprintf(
                        'COM_GDA_STATUS_DESC_CACI_FILE_VALID',
                        ToolsHelper::from_sqldate($souscription->date_caci ?? null)
                    ),
                ];

            case self::STATUS_PAYMENT_REQUIRED:
                $montantCotisation = CotisationService::getMontant((string) ($souscription->cotisation_code ?? ''));
                return [
                    'type'    => $type,
                    'icon'    => 'fa-credit-card',
                    'message' => Text::sprintf('COM_GDA_STATUS_DESC_PAYMENT_REQUIRED', $montantCotisation, (string) ($souscription->username)),
                ];

            case self::STATUS_PAYMENT_VALIDATING:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-hourglass-half',
                    'message' => Text::sprintf(
                        'COM_GDA_STATUS_DESC_PAYMENT_VALIDATING',
                        (string) ($souscription->id_order ?? '')
                    ),
                ];

            case self::STATUS_LICENCE_REQUIRED:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-id-card',
                    'message' => Text::_('COM_GDA_STATUS_DESC_LICENCE_REQUIRED'),
                ];

            case self::STATUS_COMPLETED:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-check-circle',
                    'message' => Text::_('COM_GDA_STATUS_DESC_COMPLETED'),
                ];

            default:
                return [
                    'type'    => $type,
                    'icon'    => 'fa-circle-info',
                    'message' => self::getStatusLabel($statusEnum),
                ];
        }
    }




    /**
     * Retourne la couleur de badge Bootstrap selon le statut.
     *
     * @param string $statusEnum Code du statut
     * @return string Classe Bootstrap (bg-danger, bg-warning, etc.)
     */
    public static function getStatusBadgeClass(string $statusEnum): string
    {
        switch ($statusEnum) {
            case self::STATUS_NOT_SUBSCRIBED:
            case self::STATUS_CACI_MISSING:
                return 'danger'; // rouge
            case self::STATUS_CACI_VALIDATING:
            case self::STATUS_PAYMENT_VALIDATING:
            case self::STATUS_LICENCE_REQUIRED:
                return 'info'; // bleu
            case self::STATUS_PAYMENT_REQUIRED:
            case self::STATUS_CACI_EXPIRED:
            case self::STATUS_CACI_EXPIRING_SOON:
                return 'warning'; // orange
            case self::STATUS_COMPLETED:
            case self::STATUS_CACI_VALID:
                return 'success'; // vert
            default:
                return 'secondary';
        }
    }
}
