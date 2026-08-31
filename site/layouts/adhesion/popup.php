<?php

use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use Joomla\CMS\Language\Text;

/**
 * @var array $displayData
 * - $displayData['item'] : données du formulaire
 * - $displayData['caci_validable'] : bool - SouscriptionService::isCaciValidable() calculé par
 *   AdhesionController::save() (fichier présent ET date valide au moins 9 mois à compter du 1er
 *   jour du mois de début de saison fédérale - même règle que le secrétariat)
 * - $displayData['caci_date_fr'] : date du CACI au format d/m/Y (chaîne vide si non renseignée)
 */

$item = $displayData['item'];
$saison = ConfHelper::getSaisonService()->getSaisonOuverte();
$urlHelloAsso = $saison->url ?? '#';

$isCaciValidable = (bool) ($displayData['caci_validable'] ?? true);
$dateCaciFr = (string) ($displayData['caci_date_fr'] ?? '');

// Message de rappel CACI, par ordre de priorité (même logique que le badge du secrétariat,
// layouts/secretariat/step_one.php) : fichier manquant d'abord (bloquant quelle que soit la
// date), puis date absente, puis date insuffisante.
$caciWarningMessage = '';
// if (!$isCaciValidable) {
//   if (empty($item['caci'])) {
//     $caciWarningMessage = Text::_('COM_GDA_ADHESION_POPUP_CACI_FICHIER_MANQUANT');
//   } elseif ($dateCaciFr === '') {
//     $caciWarningMessage = Text::_('COM_GDA_ADHESION_POPUP_CACI_DATE_MANQUANTE');
//   } else {
//     $caciWarningMessage = Text::_('COM_GDA_ADHESION_POPUP_CACI_DATE_INSUFFISANTE');
//   }
// }
?>

<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-circle-check me-2 text-success" aria-hidden="true"></i>
    <?= Text::sprintf('COM_GDA_ADHESION_SAVE_SUCCESS', '', $item['prenom'], $item['nom']) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body text-center px-4 py-3 mb-2">

  <p class="mb-2"><?= Text::sprintf('COM_GDA_ADHESION_POPUP_EMAIL', $this->escape($item['email'])) ?></p>

  <?php if (!$isCaciValidable) : ?>
    <!-- Rappel CACI : fichier absent ou date de validité insuffisante (voir priorité ci-dessus) -->
    <div class="alert alert-info border-0 rounded-3 p-3 mb-3 text-start">
      <p class="mb-0 text-center">
        <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>
        <strong><?= Text::_('COM_GDA_ADHESION_POPUP_CACI_TITLE') ?></strong><br>
        <span class="small"><?= $this->escape($caciWarningMessage) ?></span>
        <ul class="list-unstyled mb-0">
          </p>
          <p class="text-muted small mb-3 text-center"><?= Text::_('COM_GDA_ADHESION_POPUP_CACI_L1') ?></p>
          <li class="d-flex align-items-start mb-2">
            <span class="badge rounded-pill bg-primary me-2">-</span>
            <span class="small"><?= Text::_('COM_GDA_ADHESION_POPUP_CACI_L2') ?></span>
          </li>
          <li class="d-flex align-items-start mb-2">
            <span class="badge rounded-pill bg-primary me-2">-</span>
            <span class="small"><?= Text::_('COM_GDA_ADHESION_POPUP_CACI_L3') ?></span>
          </li>


        </ul>
    </div>
  <?php endif; ?>

  <?php if ($item["helloasso"] === "0") : ?>
    <!-- Séparateur -->
    <hr class="my-3">

    <!-- Section HelloAsso (memes textes que le mail de bienvenue/mise a jour, language/fr-FR/com_gdadhesions.ini) -->
    <div class="alert alert-info border-0 bg-light rounded-3 p-3 mb-3 text-start">
      <p class="mb-2 text-center"><strong><?= Text::_('COM_GDA_ADHESION_POPUP_LAST_STEP') ?></strong></p>
      <p class="text-muted small mb-3 text-center"><?= Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_INTRO') ?></p>

      <ol class="list-unstyled mb-0">
        <li class="d-flex align-items-start mb-2">
          <span class="badge rounded-pill bg-primary me-2">1</span>
          <span class="small"><?= Text::sprintf('COM_GDA_ADHESION_POPUP_STEP1', $this->escape(Text::_('COM_GDA_COTISATION_TARIF_' . $item['cotisation_code']))) ?></span>
        </li>
        <li class="d-flex align-items-start mb-2">
          <span class="badge rounded-pill bg-primary me-2">2</span>
          <span class="small"><?= Text::sprintf('COM_GDA_ADHESION_POPUP_STEP2', $this->escape($item['username'])) ?></span>
        </li>
        <li class="d-flex align-items-start mb-2">
          <span class="badge rounded-pill bg-primary me-2">3</span>
          <span class="small"><?= Text::_('COM_GDA_ADHESION_POPUP_STEP3') ?></span>
        </li>
        <li class="d-flex align-items-start">
          <span class="badge rounded-pill bg-primary me-2">4</span>
          <span class="small"><?= Text::_('COM_GDA_ADHESION_POPUP_STEP4') ?></span>
        </li>
      </ol>
    </div>

    <a href="<?= $this->escape($urlHelloAsso) ?>" class="HaAuthorizeButton" target="_blank" rel="noopener">
      <img src="<?= FileHelper::getHelloAssoLogoSrc() ?>" alt="HelloAsso" class="HaAuthorizeButtonLogo" />
      <span class="HaAuthorizeButtonTitle"><?= Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_CTA') ?></span>
    </a>

    <p class="text-muted small mt-3 mb-0">Vous serez redirigé vers la plateforme sécurisée HelloAsso.</p>


  <?php else : ?>


    <!-- Cas sans HelloAsso -->
    <div class="alert alert-light border rounded-3 p-3 mt-3 mb-0">
      <p class="mb-0 small text-muted">Votre adhésion sera validée après vérification par notre équipe. Vous recevrez un e-mail de confirmation définitive.</p>
    </div>


  <?php endif; ?>

</div>

<div class="modal-footer">
  <button type="button" class="btn btn-success" data-bs-dismiss="modal"><?= Text::_('JOK') ?></button>
</div>