<?php

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * Badge CACI d'une souscription : icone + date de fin de validite, colore selon le statut.
 * Si le fichier CACI existe, le badge est un lien qui l'ouvre dans la popup d'apercu
 * (#imagePreviewModal, handler js-image-preview-thumb).
 *
 * Partage par les onglets Enregistrement (step_three.php) et Adhesions finalisees (finalize.php).
 *
 * @var array $displayData
 * - $displayData['item'] : ligne de SecretariatModel::getSouscriptionsAValider()
 *   (caci, date_caci deja au format d/m/Y, caci_status)
 */

$item = $displayData['item'];

$pathCaci = FileHelper::getImageSrc($item->caci ?? null, 'CaciPath', '', false);
$dateCaciAffiche = trim((string) ($item->date_caci ?? ''));
$caciStatusEnum = (string) ($item->caci_status ?? AdhesionStatusHelper::STATUS_CACI_MISSING);
$caciStatusLabel = AdhesionStatusHelper::getStatusLabel($caciStatusEnum);
$caciStatusClass = AdhesionStatusHelper::getStatusBadgeClass($caciStatusEnum);
$caciBadgeContent = '<i class="fa-solid fa-file-medical me-1" aria-hidden="true"></i>'
  . ($dateCaciAffiche !== '' ? $this->escape($dateCaciAffiche) : '—');
?>
<?php if (!empty($pathCaci)) : ?>
  <a
    href="#"
    class="badge bg-<?= $this->escape($caciStatusClass) ?> js-image-preview-thumb"
    data-image-src="<?= $this->escape($pathCaci) ?>"
    data-image-alt="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI')) ?>"
    data-bs-toggle="modal"
    data-bs-target="#imagePreviewModal"
    title="<?= $this->escape($caciStatusLabel) ?>"
    aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') . ' - ' . $caciStatusLabel) ?>"><?= $caciBadgeContent ?></a>
<?php else : ?>
  <span class="badge bg-<?= $this->escape($caciStatusClass) ?>" title="<?= $this->escape($caciStatusLabel) ?>"><?= $caciBadgeContent ?></span>
<?php endif; ?>
