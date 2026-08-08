<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tab');

?>

<style>
    .com-gdadhesions-configuration pre.com-gdadhesions-releasenotes,
    .com-gdadhesions-configuration pre.com-gdadhesions-log {
        max-height: 32rem;
        overflow-y: auto;
        padding: 0.75rem 1rem;
        background-color: #1e1e1e;
        color: #e8e8e8;
        border: 1px solid #3a3a3a;
        border-radius: 0.25rem;
    }
</style>

<div class="com-gdadhesions-configuration">
    <h2><?php echo Text::_('COM_GDA_CONFIGURATION_TITLE'); ?></h2>

    <form action="<?php echo Route::_('index.php?option=com_gdadhesions&task=configuration.save'); ?>" method="post" id="adminForm" name="adminForm" class="form-validate">

        <?php echo HTMLHelper::_('uitab.startTabSet', 'configurationTabs', ['active' => 'helloasso']); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'configurationTabs', 'helloasso', Text::_('COM_GDA_CONFIGURATION_TAB_HELLOASSO')); ?>
            <p class="alert alert-info">
                <?php echo Text::_('COM_GDA_HELLOASSO_INTRO'); ?>
            </p>

            <?php if ($this->secretConfigured) : ?>
                <p class="alert alert-success"><?php echo Text::_('COM_GDA_HELLOASSO_SECRET_CONFIGURED'); ?></p>
            <?php else : ?>
                <p class="alert alert-warning"><?php echo Text::_('COM_GDA_HELLOASSO_SECRET_MISSING'); ?></p>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <?php echo $this->form->renderField('helloasso_client_id'); ?>
                    <?php echo $this->form->renderField('helloasso_client_secret'); ?>
                    <?php echo $this->form->renderField('helloasso_base_url'); ?>
                    <?php echo $this->form->renderField('helloasso_organization_slug'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'configurationTabs', 'mail', Text::_('COM_GDA_CONFIGURATION_TAB_MAIL')); ?>
            <p class="alert alert-info">
                <?php echo Text::_('COM_GDA_CONFIGURATION_DEVMAIL_INTRO'); ?>
            </p>

            <div class="row">
                <div class="col-lg-8">
                    <?php echo $this->form->renderField('devmailoverride'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'configurationTabs', 'releasenotes', Text::_('COM_GDA_CONFIGURATION_TAB_RELEASENOTES')); ?>
            <?php if ($this->releaseNotes !== '') : ?>
                <pre class="com-gdadhesions-releasenotes"><?php echo $this->escape($this->releaseNotes); ?></pre>
            <?php else : ?>
                <p class="alert alert-info"><?php echo Text::_('COM_GDA_CONFIGURATION_RELEASENOTES_EMPTY'); ?></p>
            <?php endif; ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'configurationTabs', 'log', Text::_('COM_GDA_CONFIGURATION_TAB_LOG')); ?>
            <p class="alert alert-info"><?php echo Text::_('COM_GDA_CONFIGURATION_LOG_INTRO'); ?></p>
            <?php if ($this->logContent !== '') : ?>
                <pre class="com-gdadhesions-log"><?php echo $this->escape($this->logContent); ?></pre>
            <?php else : ?>
                <p class="alert alert-info"><?php echo Text::_('COM_GDA_CONFIGURATION_LOG_EMPTY'); ?></p>
            <?php endif; ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <?php echo Text::_('JSAVE'); ?>
            </button>
            <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_gdadhesions'); ?>">
                <?php echo Text::_('JCANCEL'); ?>
            </a>
        </div>

        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
