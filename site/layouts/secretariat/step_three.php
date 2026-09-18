<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Service\CotisationService;

/**
 * @var array $displayData
 * - $displayData['items'] : souscriptions pretes pour declaration licence
 */

$items = $displayData['items'] ?? [];
?>

<div class="card mb-3">
  <div class="card-body">
    <?php if (empty($items)) : ?>
      <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP3_EMPTY') ?? 'Aucune licence a enregistrer pour le moment.' ?></p>
    <?php else : ?>
      <table class="table table-bordered table-striped secretariat-table">
        <thead>
          <tr>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ACTION') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?></th>
            <th><i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_NAME') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_EMAIL') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE_DE_NAISSANCE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_COTISATION') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CATEGORIE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ACTION') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item) : ?>
            <tr>
              <?php
                $licenceValue = trim((string) ($item->username ?? ''));
                $licencePrefix = strtoupper(substr($licenceValue, 0, 1));
                $licenceTooltip = '';
                $licenceClass = 'gda-licence-chip';

                if ($licencePrefix === 'A') {
                  $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_RENEWAL_HINT');
                } elseif ($licencePrefix === 'N') {
                  $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_CREATION_HINT');
                  $licenceClass .= ' gda-licence-chip--warning';
                }
              ?>
              <?php $hintDevaliderPaiement = Text::_('COM_GDA_SECRETARIAT_PAYMENT_DEVALIDATE_HINT'); ?>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-warning gda-btn-step js-unvalidate-payment"
                  data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape($hintDevaliderPaiement) ?>"
                  title="<?= $this->escape($hintDevaliderPaiement) ?>"
                  aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYMENT_DEVALIDATE')) ?>">
                  <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                </button>
              </td>
              <?php $pathPhoto = FileHelper::getImageSrc($item->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false); ?>
              <td class="text-center">
                <?php if (!empty($pathPhoto)) : ?>
                  <a
                    href="#"
                    class="js-image-preview-thumb"
                    data-image-src="<?= $this->escape($pathPhoto) ?>"
                    data-image-alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#imagePreviewModal"
                    aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?? 'Photo') ?>"
                  >
                    <img
                      src="<?= $this->escape($pathPhoto) ?>"
                      alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                      width="64"
                      height="64"
                      loading="lazy"
                      class="gda-preview-thumb gda-preview-thumb--64"
                    >
                  </a>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($licenceValue !== '') : ?>
                  <span
                    class="<?= $this->escape($licenceClass) ?>"
                    <?php if ($licenceTooltip !== '') : ?>
                      data-bs-toggle="tooltip"
                      data-bs-title="<?= $this->escape($licenceTooltip) ?>"
                      title="<?= $this->escape($licenceTooltip) ?>"
                    <?php endif; ?>><?= $this->escape($licenceValue) ?></span>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) ($item->id_profil ?? 0) ?>"><?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?></a>
              </td>
              <td><?= $this->escape((string) ($item->email ?? '')) ?></td>
              <td><?= HTMLHelper::date($item->date_de_naissance, 'd/m/Y') ?></td>
              <td><?= $this->escape(CotisationService::getLabel((string) ($item->cotisation_code ?? ''))) ?></td>
              <td><?= $this->escape((string) ($item->categorie ?? '')) ?></td>
              <td><?= HTMLHelper::date($item->date_souscription, 'd/m/Y H:i') ?></td>
              <?php $hintFinaliser = Text::_('COM_GDA_SECRETARIAT_INSCRIPTION_FINALIZE_HINT'); ?>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-success gda-btn-step js-finalize-inscription"
                  data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                  data-item-licence="<?= $this->escape((string) ($item->username ?? '')) ?>"
                  data-item-name="<?= $this->escape(trim((string) (($item->prenom ?? '') . ' ' . ($item->nom ?? '')))) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape($hintFinaliser) ?>"
                  title="<?= $this->escape($hintFinaliser) ?>"
                  aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_INSCRIPTION_FINALIZE')) ?>">
                  <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
