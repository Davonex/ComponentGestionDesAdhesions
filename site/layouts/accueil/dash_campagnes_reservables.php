<?php

/**
 * Layout : encart de réservation du dashboard adhérent pour une nature de campagne (Formation ou
 * Loisir : même rendu, seuls le titre, l'icône et les identifiants changent). Les popups partagés
 * sont dans accueil/dash_reservation_modals.php, à rendre une seule fois.
 *
 * @var array $displayData
 * - $displayData['campagnes'] : campagnes d'une seule nature (AccueilModel::getCampagnesReservables(), filtrées), triées par date de fin croissante
 * - $displayData['user']      : utilisateur connecté
 * - $displayData['cardId']    : identifiant DOM de l'encart repliable (ex. 'formationsCard')
 * - $displayData['titleKey']  : clé de langue du titre
 * - $displayData['icon']      : classes FontAwesome de l'icône du titre
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$campagnes = $displayData['campagnes'] ?? [];
$user      = $displayData['user'];
$cardId    = $displayData['cardId'];
$titleKey  = $displayData['titleKey'];
$icon      = $displayData['icon'];

// Encart entièrement masqué s'il n'y a aucune campagne ouverte : un bloc vide n'apporte rien
// au dashboard.
if (empty($campagnes)) {
    return;
}
?>

<div class="card bg-gda-white">

    <div class="card-header d-flex align-items-center">
        <button class="btn btn-sm p-0 me-2 toggle-card"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?= $this->escape($cardId) ?>"
            aria-expanded="true">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        <i class="<?= $this->escape($icon) ?> me-2" aria-hidden="true"></i><?= Text::_($titleKey) ?>
    </div>

    <div class="collapse show" id="<?= $this->escape($cardId) ?>">
        <div class="card-body">
            <?php foreach ($campagnes as $formation) : ?>
                <?= LayoutHelper::render('accueil.dash_campagne_reservable_ligne', [
                    'formation' => $formation,
                    'user'      => $user,
                ]) ?>
            <?php endforeach; ?>
        </div>
    </div>

</div>
