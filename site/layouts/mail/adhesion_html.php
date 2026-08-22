<?php

/**
 * @package     com_gdadhesions
 * @subpackage  layouts
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
/** @var object $displayData */

$civilite = trim((string) ($displayData->civilite ?? ''));
$nom = trim((string) ($displayData->nom ?? ''));
$prenom = trim((string) ($displayData->prenom ?? ''));
$username = trim((string) ($displayData->username ?? ''));
$profilKey = trim((string) ($displayData->key ?? ''));
$mode = trim((string) ($displayData->mode ?? 'update'));
$helloasso = trim((string) ($displayData->helloasso ?? '0'));
$cotisationCode = trim((string) ($displayData->cotisation_code ?? ''));
$fullName = trim($civilite . ' ' . $prenom . ' ' . $nom);
$profileUrl = Uri::root() . ltrim(Route::_('index.php?option=com_gdadhesions&view=adhesion&key=' . $profilKey, false), '/');
$titleKey = $mode === 'create' ? 'COM_GDA_EMAIL_PROFILE_CREATE_TITLE' : 'COM_GDA_EMAIL_PROFILE_UPDATE_TITLE';
$bodyKey = $mode === 'create' ? 'COM_GDA_EMAIL_PROFILE_CREATE_BODY' : 'COM_GDA_EMAIL_PROFILE_UPDATE_BODY';
$urlHelloAsso = ConfHelper::getSaisonService()->getSaisonOuverte()->url ?? '#';
?>

<html><body>
<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <h2 style="margin: 0 0 16px;"><?= $this->escape(Text::_($titleKey)) ?></h2>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::sprintf('COM_GDA_EMAIL_PROFILE_LIFECYCLE_INTRO', $fullName)) ?></p>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::_($bodyKey)) ?></p>


  

  <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_USERNAME_LABEL')) ?></td>
      <td style="padding: 8px; border: 1px solid #e5e7eb;"><?= $this->escape($username) ?></td>
    </tr>
  </table>
  
  <?php if (!UsersHelper::userExists($username) || UsersHelper::isBlocked($username)) : ?>
    <p style="margin: 0 0 18px;">
        <a href="<?= $this->escape($profileUrl) ?>" style="background-color: #3d6e79; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
          <?= $this->escape(Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_LINK_LABEL')) ?>
        </a>
  </p>
  <?php endif; ?>

  <?php if ($helloasso === '0') : ?>
    <!-- Section HelloAsso (memes instructions que la popup de confirmation, adhesion.popup) -->
    <div style="margin: 20px 0; padding: 16px; background-color: #f3f4f6; border-radius: 6px;">
      <p style="margin: 0 0 8px; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_ADHESION_POPUP_LAST_STEP')) ?></p>
      <p style="margin: 0 0 12px; color: #6b7280; font-size: 14px;"><?= $this->escape(Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_INTRO')) ?></p>
      <ol style="margin: 0 0 16px; padding-left: 20px; color: #6b7280; font-size: 14px;">
        <li style="margin-bottom: 6px;"><?= Text::sprintf('COM_GDA_ADHESION_POPUP_STEP1', $this->escape(Text::_('COM_GDA_COTISATION_TARIF_' . $cotisationCode))) ?></li>
        <li style="margin-bottom: 6px;"><?= Text::sprintf('COM_GDA_ADHESION_POPUP_STEP2', $this->escape($username)) ?></li>
        <li style="margin-bottom: 6px;"><?= $this->escape(Text::_('COM_GDA_ADHESION_POPUP_STEP3')) ?></li>
        <li><?= $this->escape(Text::_('COM_GDA_ADHESION_POPUP_STEP4')) ?></li>
      </ol>
      <p style="text-align: center; margin: 0;">
        <a href="<?= $this->escape($urlHelloAsso) ?>" style="background-color: #3d6e79; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
          <img src="<?= $this->escape(FileHelper::getHelloAssoLogoSrc()) ?>" alt="HelloAsso" width="20" height="20" style="vertical-align: middle; margin-right: 8px;">
          <?= $this->escape(Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_CTA')) ?>
        </a>
      </p>
    </div>
  <?php endif; ?>

  <p style="margin: 20px 0 0; font-size: 12px; color: #6b7280;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_FOOTER')) ?></p>
</div>
</body></html>