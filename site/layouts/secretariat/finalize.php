<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['items'] : souscriptions finalisees
 */

$items = $displayData['items'] ?? [];
?>

<div class="card mb-3">
  <div class="card-body">
    <?php if (empty($items)) : ?>
      <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP4_EMPTY') ?? 'Aucune adhésion finalisée pour le moment.' ?></p>
    <?php else : ?>
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ACTION') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PAIEMENT') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_NAME') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_EMAIL') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE_DE_NAISSANCE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_COTISATION') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CATEGORIE') ?></th>
            <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE') ?></th>
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
              <td class="text-start">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-warning js-unfinalize-inscription"
                  data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_INSCRIPTION_UNFINALIZE_HINT') ?? 'Retirer la finalisation de l\'inscription') ?>"
                  title="<?= Text::_('COM_GDA_SECRETARIAT_INSCRIPTION_UNFINALIZE_HINT') ?? 'Retirer la finalisation de l\'inscription' ?>">
                  <?= Text::_('COM_GDA_SECRETARIAT_INSCRIPTION_UNFINALIZE') ?? 'Dé-finaliser inscription' ?>
                </button>
              </td>
              <?php $pathPhoto = FileHelper::getImageSrc($item->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto'); ?>
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
              <?php $pathCaci = FileHelper::getImageSrc($item->caci, 'CaciPath'); ?>
              <td class="text-center">
                <?php if (!empty($pathCaci)) : ?>
                  <a
                    href="#"
                    class="js-image-preview-thumb"
                    data-image-src="<?= $this->escape($pathCaci) ?>"
                    data-image-alt="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'CACI') ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#imagePreviewModal"
                    aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'CACI') ?>"
                  >
                    <img
                      src="<?= $this->escape($pathCaci) ?>"
                      alt="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'CACI') ?>"
                      width="32"
                      height="32"
                      loading="lazy"
                      class="gda-preview-thumb gda-preview-thumb--32"
                    >
                  </a>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <!-- PAIEMENT -->
              <td class="text-center">
                <?php if (!empty($item->id_order)) : ?>
                  <button
                    type="button"
                    class="btn btn-sm js-show-payement"
                    style="background-color:#49d38a; color:#fff; font-weight:600; border:none;"
                    data-item-id="<?= (int) ($item->id_profil) ?>"
                    data-item-campagne="<?= (int) ($item->id_campagne) ?>"
                    data-item-order="<?= (string) ($item->id_order) ?>"
                    data-item-username="<?= $this->escape((string) ($item->username)) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_HINT') ?? 'Voir le détail du paiement HelloAsso') ?>">
                    <img width="20" height="20" src="https://api.helloasso.com/v5/img/logo-ha.svg" alt="COM_GDA_CAMPAGNE_HELLOASSO">
                    <?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PAIEMENT') ?>
                  </button>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <!-- LICENCE -->
              <td class="text-center">
                <?php if ($licenceValue !== '') : ?>
                  <span class="<?= $this->escape($licenceClass) ?>" >
                    <?= $this->escape($licenceValue) ?></span>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?></td>
              <td><?= $this->escape((string) ($item->email ?? '')) ?></td>
              <td><?= HTMLHelper::date($item->date_de_naissance, 'd/m/Y') ?></td>
              <td><?= $this->escape(Text::_('COM_GDA_COTISATION_TARIF_' . ($item->cotisation_code ?? ''))) ?></td>
              <td><?= $this->escape((string) ($item->categorie ?? '')) ?></td>
              <td><?= HTMLHelper::date($item->date_souscription, 'd/m/Y H:i') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>