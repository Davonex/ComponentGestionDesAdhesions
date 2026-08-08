<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\GdaLogger;


class NotificationMailService
{
  private const PROFILE_MAIL_MODE_CREATE = 'create';

  private const PROFILE_MAIL_MODE_UPDATE = 'update';

  private DatabaseInterface $db;

  private MailerFactoryInterface $mailerFactory;

  private GdaConfigService $configService;

  public function __construct(DatabaseInterface $db, MailerFactoryInterface $mailerFactory, GdaConfigService $configService)
  {
    $this->db = $db;
    $this->mailerFactory = $mailerFactory;
    $this->configService = $configService;
  }

  /**
   * Envoie le mail de finalisation d'adhesion.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function sendFinalizationEmail(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides pour envoi mail de finalisation.');
    }

    $data = $this->getFinalizationMailData($idProfil, $idCampagne);

    if (!$data || empty($data->email)) {
      throw new \RuntimeException('Destinataire introuvable pour le mail de finalisation.');
    }

    $htmlBody = $this->renderTemplateOrFallback('mail.finalization_html', (object) $data, true);
    $textBody = $this->renderTemplateOrFallback('mail.finalization_text', (object) $data, false);

    try {
      $app = Factory::getApplication();
      /** @var Mail $mailer */
      $mailer = $this->mailerFactory->createMailer();

      $senderMail = (string) $app->get('mailfrom');
      $senderName = (string) $app->get('fromname');
      $recipientMail = $this->resolveRecipientEmail((string) $data->email);

      $mailer->setSender([$senderMail, $senderName]);
      $mailer->addRecipient($recipientMail);
      $mailer->setSubject(Text::sprintf('COM_GDA_EMAIL_FINALIZE_SUBJECT', (string) ($data->campagne_titre ?? '')));
      $mailer->isHtml(true);
      $mailer->setBody($htmlBody);
      $mailer->AltBody = $textBody;
      $mailer->send();

      GdaLogger::INFO('Envoi mail finalisation (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . ')');

      return true;
    } catch (\Throwable $e) {
      GdaLogger::ERROR(
        'Echec envoi mail finalisation (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );

      return false;
    }
  }

  /**
   * Envoie le mail de mise a jour du profil adherent.
   *
   * @param int $idProfil
   *
   * @return bool
   */
  public function sendProfileUpdateEmail(int $idProfil): bool
  {
    return $this->sendProfileLifecycleEmail($idProfil, self::PROFILE_MAIL_MODE_UPDATE);
  }

  /**
   * Envoie le mail de creation du profil adherent.
   *
   * @param int $idProfil
   *
   * @return bool
   */
  public function sendProfileWelcomeEmail(int $idProfil): bool
  {
    return $this->sendProfileLifecycleEmail($idProfil, self::PROFILE_MAIL_MODE_CREATE);
  }

  /**
   * Envoie un mail de cycle de vie du profil (creation ou mise a jour).
   *
   * @param int $idProfil
   * @param string $mode
   *
   * @return bool
   */
  private function sendProfileLifecycleEmail(int $idProfil, string $mode): bool
  {
    if ($idProfil <= 0) {
      throw new \InvalidArgumentException('Identifiant invalide pour envoi mail profil.');
    }

    if (!in_array($mode, [self::PROFILE_MAIL_MODE_CREATE, self::PROFILE_MAIL_MODE_UPDATE], true)) {
      throw new \InvalidArgumentException('Mode de mail profil invalide.');
    }

    $data = $this->getProfileMailData($idProfil);

    if (!$data || empty($data->email)) {
      throw new \RuntimeException('Destinataire introuvable pour le mail profil.');
    }

    $displayData = (object) $data;
    $displayData->mode = $mode;

    $htmlBody = $this->renderTemplateOrFallback('mail.adhesion_html', $displayData, true);
    $textBody = $this->renderTemplateOrFallback('mail.adhesion_text', $displayData, false);

    try {
      $app = Factory::getApplication();
      /** @var Mail $mailer */
      $mailer = $this->mailerFactory->createMailer();

      $senderMail = (string) $app->get('mailfrom');
      $senderName = (string) $app->get('fromname');
      $recipientMail = $this->resolveRecipientEmail((string) $data->email);

      $mailer->setSender([$senderMail, $senderName]);
      $mailer->addRecipient($recipientMail);
      $mailer->setSubject(Text::_($this->getProfileMailSubjectKey($mode)));
      $mailer->isHtml(true);
      $mailer->setBody($htmlBody);
      $mailer->AltBody = $textBody;
      $mailer->send();

      return true;
    } catch (\Throwable $e) {
      GdaLogger::ERROR(
        'Echec envoi mail profil mode=' . $mode . ' (id_profil=' . $idProfil . '): ' . $e->getMessage()
      );

      return false;
    }
  }

  /**
   * Charge les donnees necessaires a la construction du mail de finalisation.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return object|null
   */
  private function getFinalizationMailData(int $idProfil, int $idCampagne): ?object
  {
    $query = $this->db->getQuery(true)
      ->select([
        $this->db->quoteName('p.civilite'),
        $this->db->quoteName('p.nom'),
        $this->db->quoteName('p.prenom'),
        $this->db->quoteName('u.email'),
        $this->db->quoteName('u.username'),
        $this->db->quoteName('c.titre', 'campagne_titre'),
      ])
      ->from($this->db->quoteName('#__gda_souscriptions', 's'))
      ->join('INNER', $this->db->quoteName('#__gda_profils', 'p') . ' ON ' . $this->db->quoteName('p.id_profil') . ' = ' . $this->db->quoteName('s.id_profil'))
      ->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('s.id_profil'))
      ->join('INNER', $this->db->quoteName('#__gda_campagnes', 'c') . ' ON ' . $this->db->quoteName('c.id_campagne') . ' = ' . $this->db->quoteName('s.id_campagne'))
      ->where($this->db->quoteName('s.id_profil') . ' = :id_profil')
      ->where($this->db->quoteName('s.id_campagne') . ' = :id_campagne')
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne);

    $this->db->setQuery($query);

    return $this->db->loadObject();
  }

  /**
   * Charge les donnees necessaires a la construction du mail de mise a jour profil.
   *
   * @param int $idProfil
   *
   * @return object|null
   */
  private function getProfileMailData(int $idProfil): ?object
  {
    $query = $this->db->getQuery(true)
      ->select([
        $this->db->quoteName('p.civilite'),
        $this->db->quoteName('p.nom'),
        $this->db->quoteName('p.prenom'),
        $this->db->quoteName('p.key', 'profil_key'),
        $this->db->quoteName('u.email'),
        $this->db->quoteName('u.username'),
        $this->db->quoteName('p.key'),
      ])
      ->from($this->db->quoteName('#__gda_profils', 'p'))
      ->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('p.id_profil'))
      ->where($this->db->quoteName('p.id_profil') . ' = :id_profil')
      ->bind(':id_profil', $idProfil);

    $this->db->setQuery($query);

    return $this->db->loadObject();
  }

  /**
   * Retourne la cle de sujet selon le mode du mail profil.
   *
   * @param string $mode
   *
   * @return string
   */
  private function getProfileMailSubjectKey(string $mode): string
  {
    return $mode === self::PROFILE_MAIL_MODE_CREATE
      ? 'COM_GDA_EMAIL_PROFILE_CREATE_SUBJECT'
      : 'COM_GDA_EMAIL_PROFILE_UPDATE_SUBJECT';
  }

  /**
   * Retourne l'email de destination en tenant compte d'un override dev optionnel.
   *
   * Cle de config attendue: DevMailOverride.
   *
   * @param string $defaultRecipient
   *
   * @return string
   */
  private function resolveRecipientEmail(string $defaultRecipient): string
  {
    $overrideRecipient = trim((string) ($this->configService->getValue('DevMailOverride') ?: ''));

    if ($overrideRecipient !== '') {
      if (!filter_var($overrideRecipient, FILTER_VALIDATE_EMAIL)) {
        Log::add(
          'DevMailOverride ignore car invalide: ' . $overrideRecipient,
          Log::WARNING,
          'com_gdadhesions'
        );

        return $defaultRecipient;
      }

      Log::add(
        'DevMailOverride actif, destinataire remplace par ' . $overrideRecipient,
        Log::INFO,
        'com_gdadhesions'
      );

      return $overrideRecipient;
    }

    return $defaultRecipient;
  }









  /**
   * Rend un template mail avec fallback de contenu si le layout est absent.
   *
   * @param string $layoutName
   * @param object<string, mixed> $displayData
   * @param bool $isHtml
   *
   * @return string
   */

  private function renderTemplateOrFallback(string $layoutName, object $displayData, bool $isHtml): string
  {
    try {
      $layout = new FileLayout($layoutName, JPATH_SITE . '/components/com_gdadhesions/layouts');
      $content = (string) $layout->render($displayData);

      if (trim($content) === '') {
        throw new \RuntimeException('Rendu vide du layout ' . $layoutName);
      }

      return $content;
    } catch (\Throwable $e) {
      Log::add(
        'Fallback template mail utilise (' . $layoutName . '): ' . $e->getMessage(),
        Log::WARNING,
        'com_gdadhesions'
      );

      return $isHtml ? $this->buildFallbackHtml($displayData) : $this->buildFallbackText($displayData);
    }
  }

  /**
   * Fallback HTML minimal si le template est introuvable.
   *
   * @param object $displayData
   *
   * @return string
   */
  private function buildFallbackHtml(object $displayData): string
  {
    $civilite = (string) ($displayData->civilite ?? '');
    $nom = (string) ($displayData->nom ?? '');
    $prenom = (string) ($displayData->prenom ?? '');
    $campagneTitre = (string) ($displayData->campagne_titre ?? '');
    $username = (string) ($displayData->username ?? '');

    return '<html><body>'
      . '<h2>' . Text::_('COM_GDA_EMAIL_FINALIZE_TITLE') . '</h2>'
      . '<p>' . Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', trim($civilite . ' ' . $nom . ' ' . $prenom)) . '</p>'
      . '<p>' . Text::sprintf('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LINE', $campagneTitre) . '</p>'
      . '<p>' . Text::sprintf('COM_GDA_EMAIL_FINALIZE_USERNAME_LINE', $username) . '</p>'
      . '<p>' . Text::_('COM_GDA_EMAIL_FINALIZE_BODY') . '</p>'
      . '<p>' . Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER') . '</p>'
      . '</body></html>';
  }

  /**
   * Fallback texte minimal si le template texte est introuvable.
   *
   * @param object $displayData
   *
   * @return string
   */
  private function buildFallbackText(object $displayData): string
  {
    $civilite = (string) ($displayData->civilite ?? '');
    $nom = (string) ($displayData->nom ?? '');
    $prenom = (string) ($displayData->prenom ?? '');
    $campagneTitre = (string) ($displayData->campagne_titre ?? '');
    $username = (string) ($displayData->username ?? '');

    return Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', trim($civilite . ' ' . $nom . ' ' . $prenom))
      . "\n\n"
      . Text::sprintf('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LINE', $campagneTitre)
      . "\n"
      . Text::sprintf('COM_GDA_EMAIL_FINALIZE_USERNAME_LINE', $username)
      . "\n\n"
      . Text::_('COM_GDA_EMAIL_FINALIZE_BODY')
      . "\n\n"
      . Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER');
  }
}
