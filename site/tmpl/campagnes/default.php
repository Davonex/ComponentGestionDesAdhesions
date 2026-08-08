<?php

\defined('_JEXEC');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;


//Bootstrap 5 est déjà inclus par le template Joomla 5 (ex: Cassiopeia).
// use Joomla\CMS\HTML\Helpers\Bootstrap;
// Bootstrap::framework();

// HTMLHelper::_(
//     'stylesheet',
//     'com_gdadhesions/campagnes-override.css', 
//     ['version' => 'auto', 'relative' => true]
// );

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();


$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('core');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.campagne');
$wa->useScript('form.validate');

// tom-select
$wa->useStyle('com_gdadhesions.tom-select');
$wa->useScript('com_gdadhesions.tom-select');

//datatables
$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

$CampagneOpen = false;
$IdCampagneActive = null;


$app = Factory::getApplication();

$task = 'sauver';

$layoutName = 'campagnes.table';
$layoutData = [
    'campagnes' => $this->lstCampagnes,
    'task' => $task,
    'form' => $this->form,
];

$layoutErrorMessage = null;

try {
    $layoutHtml = LayoutHelper::render($layoutName, $layoutData);
} catch (\RuntimeException $e) {
    $layoutHtml = '';
    $layoutErrorMessage = Text::sprintf('COM_GDA_LAYOUT_NOT_FOUND', $layoutName);
}
?>

<!-- <form action="<?php echo Route::_('index.php?option=com_gdadhesions'); ?>"
    method="post" name="adminForm" id="adminForm" enctype="multipart/form-data"> -->



<?php if ($layoutErrorMessage !== null) : ?>
    <div class="alert alert-danger" role="alert">
        <?= $this->escape($layoutErrorMessage) ?>
    </div>
<?php else : ?>
    <?= $layoutHtml ?>
<?php endif; ?>








<?php if (!$CampagneOpen) : ?>

    <a class="btn btn-success float-end" type="button"
        id="openForm"
        data-bs-toggle="modal" data-bs-target="#modalForm"
        data-toggle="tooltip" data-placement="top"
        data-empty
        title="<?php echo Text::_('COM_GDA_CAMPAGNE_NEW_TOOLTIP'); ?>"
        href="#">
        <span class="fa-solid fa-plus"></span> <?php echo Text::_('COM_GDA_CAMPAGNE_NEW'); ?>
    </a>

<?php endif; ?>



<script type="module">
    document.addEventListener('DOMContentLoaded', function() {

        LstModal("modalForm", "campagne");
        multiselectInit('jform_campagne_id_groupes')

    });
</script>