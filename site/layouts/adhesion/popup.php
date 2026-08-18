<?php

use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use Joomla\CMS\Language\Text;

/**
 * @var array $displayData
 * - $displayData['item'] : données du formulaire
 */

$item = $displayData['item'];
$saison = ConfHelper::getSaisonService()->getSaisonOuverte();
$urlHelloAsso = $saison->url ?? '#';
?>

<div class="text-center px-4 py-3">

  <!-- Icône de succès -->
  <div class="mb-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10" style="width:64px;height:64px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="text-success" viewBox="0 0 16 16">
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM6.97 11.03a.75.75 0 0 0 1.07 0l3.992-3.992a.75.75 0 1 0-1.06-1.06L7.5 9.439 5.53 7.47a.75.75 0 0 0-1.06 1.06l2.5 2.5z"/>
      </svg>
    </span>
  </div>

  <!-- Message principal -->
  <h4 class="fw-semibold text-success mb-2"><?php echo Text::sprintf('COM_GDA_ADHESION_SAVE_SUCCESS', '',  $item['prenom'], $item['nom']); ?></h4>
  <p class="text-muted mb-1">Votre demande d'adhésion a bien été prise en compte.</p>
  <p class="mb-0">Un e-mail de confirmation a été envoyé à</p>
  <p class="fw-bold mb-3"><?php echo htmlspecialchars($item['email']); ?></p>

  <?php if ($item["helloasso"] === "0") : ?>
    <!-- Séparateur -->
    <hr class="my-3">

    <!-- Section HelloAsso -->
    <div class="alert alert-info border-0 bg-light rounded-3 p-3 mb-3">
      <p class="mb-2"><strong>Dernière étape !</strong></p>
      <p class="text-muted small mb-0">Pour finaliser votre adhésion, procédez au paiement sur HelloAsso en cliquant ci-dessous.</p>
    

        <p class="text-muted small mb-0">Dans le formulaire d'HelloAsso, merci d'utiliser ce numéro de licence : <strong><?php echo htmlspecialchars($item['username']); ?></strong></p>

    </div>

    <a href="<?php echo htmlspecialchars($urlHelloAsso); ?>" class="HaAuthorizeButton" target="_blank" rel="noopener">
      <img src="<?= FileHelper::getHelloAssoLogoSrc() ?>" alt="HelloAsso" class="HaAuthorizeButtonLogo" />
      <span class="HaAuthorizeButtonTitle">Finaliser mon adhésion</span>
    </a>

    <p class="text-muted small mt-3 mb-0">Vous serez redirigé vers la plateforme sécurisée HelloAsso.</p>

    
  <?php else : ?>


    <!-- Cas sans HelloAsso -->
    <div class="alert alert-light border rounded-3 p-3 mt-3 mb-0">
      <p class="mb-0 small text-muted">Votre adhésion sera validée après vérification par notre équipe. Vous recevrez un e-mail de confirmation définitive.</p>
    </div>


  <?php endif; ?>

</div>