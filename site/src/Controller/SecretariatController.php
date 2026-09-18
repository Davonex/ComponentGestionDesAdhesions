<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Service\SouscriptionService;

class SecretariatController extends BaseController
{
  /**
   * Rend un layout Joomla et transforme une erreur de chargement en exception lisible.
   *
   * @param string $layoutName
   * @param array<string, mixed> $displayData
   *
   * @return string
   */
  private function renderLayoutOrFail(string $layoutName, array $displayData): string
  {
    try {
      $layout = new FileLayout($layoutName);
      $html = $layout->render($displayData);

      // FileLayout::render() retourne une chaine vide si le layout est introuvable.
      // On transforme ce cas en exception explicite pour faciliter le debug (Linux case-sensitive).
      if ($html === '') {
        $debugDetails = trim((string) $layout->renderDebugMessages());
        $suffix = $debugDetails !== '' ? ' | ' . $debugDetails : '';

        throw new \RuntimeException(Text::sprintf('COM_GDA_LAYOUT_NOT_FOUND', $layoutName) . $suffix);
      }

      return $html;
    } catch (\Throwable $e) {
      throw new \RuntimeException(Text::sprintf('COM_GDA_LAYOUT_NOT_FOUND', $layoutName), 500, $e);
      // return ( '<b>Erreur de rendu du layout [' . $layoutName . ']: ' . $e->getMessage() . '</b>' );


      // throw new \RuntimeException(Text::sprintf('COM_GDA_LAYOUT_NOT_FOUND', $layoutName), 500, $e);
    }
  }

  /**
   * Nom d'affichage de l'utilisateur connecté qui effectue l'action (pour traçabilité des logs).
   * Basé sur l'identité Joomla réelle plutôt que sur l'état de session 'session' alimenté par
   * DisplayController::display(), car les tâches ajax n'y passent pas systématiquement.
   */
  private function getActingUserName(): string
  {
    $actingUser = Factory::getApplication()->getIdentity();

    return $actingUser && $actingUser->id ? $actingUser->name : 'unknown';
  }

  /**
   * Vérifie que l'utilisateur connecté est membre du Bureau, sinon lève une exception. La vue
   * Secrétariat n'est accessible qu'au Bureau, mais ce contrôle ne protège pas les tâches ajax
   * (appelables directement) : chacune doit revérifier l'appartenance au Bureau elle-même.
   *
   * @return void
   * @throws \RuntimeException Si l'utilisateur connecté n'est pas membre du Bureau.
   */
  private function guardBureauMember(): void
  {
    if (!UsersHelper::isBureauMember()) {
      throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }
  }

  /**
   * Ajax: suppression definitive d'un adherent (profil + user).
   */
  public function deleteAdherent(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      if ($idProfil <= 0 || $idCampagne <= 0) {
        throw new \InvalidArgumentException('Données invalides: id_profil et id_campagne sont obligatoires.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $deletedInfo = $model->deleteAdherentDefinitif($idProfil);

      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Adherent supprimé (id_profil=' . $idProfil . '): ' . ($deletedInfo['display_name'] ?? '') . ' (' . ($deletedInfo['username'] ?? '') .
          ')'
      );

      $response = new JsonResponse();
      $response->success = true;
      $response->message = Text::sprintf(
        'COM_GDA_SECRETARIAT_DELETE_SUCCESS',
        $deletedInfo['display_name'] ?? '',
        $deletedInfo['username'] ?? ''
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: retire la validation CACI d'une souscription.
   */
  public function unvalidateCaci(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->unvalidateCaci($idProfil, $idCampagne);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = 'Le CACI a été de-valide';
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'CACI dé-validé (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la dé-validation du CACI (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: met a jour la categorie d'une souscription.
   */
  public function updateCategorie(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $categorie = strtoupper(trim((string) $input->getString('categorie', '')));

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->updateCategorie($idProfil, $idCampagne, $categorie);

      $response = new JsonResponse();
      $response->success = true;
      $response->message = 'La catégorie a été mise à jour';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: corrige la tarification (reduction) d'un adherent et recalcule le code de
   * cotisation qui en decoule (age + localisation), via SecretariatModel::updateCotisationCode().
   *
   * @return void
   */
  public function updateCotisationCode(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $reduction = $input->getInt('reduction', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $data = $model->updateCotisationCode($idProfil, $idCampagne, $reduction);

      $response = new JsonResponse();
      $response->success = true;
      $response->message = 'La tarification a été mise à jour';
      $response->data = $data;
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Tarification corrigée (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $data['cotisation_code']
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la correction de la tarification (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: met a jour la date CACI d'une souscription.
   */
  public function updateDateCaci(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $dateCaci = trim((string) $input->getString('date_caci', ''));

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->updateDateCaci($idProfil, $dateCaci);
      $caciFile = $model->getCaciFile($idProfil);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);

      $name = $user?->name ?? '';

      $souscriptionService = new SouscriptionService(Factory::getContainer()->get(DatabaseInterface::class));

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "La date du CACI de <b>$name</b> a été mise à jour";
      $response->data = ['is_caci_validable' => $souscriptionService->isCaciValidable($dateCaci, $caciFile)];
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }
    echo $response;
    $app->close();
  }

  /**
   * Ajax: valide le CACI d'une souscription.
   */
  public function validateCaci(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->validateCaci($idProfil, $idCampagne);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "Le CACI de <b>$name</b> a été validé";
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'CACI validé (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la validation du CACI (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: valide le paiement d'une souscription.
   */
  public function validatePayment(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    // Pré-initialise les variables pour éviter les warnings "possible undefined" si
    // une exception survient avant leur affectation dans le bloc try.
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->validatePayment($idProfil, $idCampagne);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "Le paiement de <b>$name</b> a été validé";
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Paiement validé (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la validation du paiement (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }
    echo $response;
    $app->close();
  }

  /**
   * Ajax: retire la validation du paiement d'une souscription.
   */
  public function unvalidatePayment(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->unvalidatePayment($idProfil, $idCampagne);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "Le paiement de <b>$name</b> a été dé-validé";
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Paiement dé-validé (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la dé-validation du paiement (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }
    echo $response;
    $app->close();
  }

  /**
   * Ajax: finalise l'inscription d'une souscription.
   */
  public function finalizeInscription(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $licence = trim((string) $input->getString('licence', ''));

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->finalizeInscription($idProfil, $idCampagne, $licence !== '' ? $licence : null);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      /** @var \Joomla\CMS\User\User $user */
      $user = $userFactory->loadUserById($idProfil);

      /** @var string $name */
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "L'inscription de <b>$name</b> a été finalisée";
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Inscription finalisée (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la finalisation de l\'inscription (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }


    echo $response;
    $app->close();
  }

  /**
   * Ajax: retire la finalisation d'une inscription.
   */
  public function unfinalizeInscription(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $name = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $model->unfinalizeInscription($idProfil, $idCampagne);

      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      $name = $user?->name ?? '';

      $response = new JsonResponse();
      $response->success = true;
      $response->message = "L'inscription de <b>$name</b> a été dé-finalisée";
      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          'Inscription dé-finalisée (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $name
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          'Erreur lors de la dé-finalisation de l\'inscription (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $e->getMessage()
      );
    }


    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le contenu de l'etape 1 (souscriptions avec caci_check = 0).
   */
  public function stepOne(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $saison = ConfHelper::getSaisonService()->getSaisonCourante();

      if (!$saison) {
        throw new \RuntimeException('Aucune saison courante définie.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $items = $model->getSouscriptionsAValider(
        (int) $saison->id_campagne,
        ['cotisation_check' => false, 'caci_check' => false, 'licence_check' => false]
      );

      $html = $this->renderLayoutOrFail('secretariat.step_one', ['items' => $items ?? []]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }
    // mettre une pause de 1 secondes pour simuler un temps de chargement long
    // sleep(1);
    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le contenu de l'etape 2 (souscriptions avec caci_check = 1).
   */
  public function stepTwo(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $saison = ConfHelper::getSaisonService()->getSaisonCourante();

      if (!$saison) {
        throw new \RuntimeException('Aucune saison courante définie.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $items = $model->getSouscriptionsAValider(
        (int) $saison->id_campagne,
        ['cotisation_check' => false, 'caci_check' => true, 'licence_check' => false]
      );

      $html = $this->renderLayoutOrFail('secretariat.step_two', ['items' => $items ?? []]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }
    // mettre une pause de 1 secondes pour simuler un temps de chargement long
    // sleep(1);
    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le contenu de l'etape 3 (souscriptions avec paiement valide et licence non enregistree).
   */
  public function stepThree(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $saison = ConfHelper::getSaisonService()->getSaisonCourante();

      if (!$saison) {
        throw new \RuntimeException('Aucune saison courante définie.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $items = $model->getLicenceAEnregistrer(
        (int) $saison->id_campagne,
        ['cotisation_check' => true, 'caci_check' => true, 'licence_check' => false]
      );

      $html = $this->renderLayoutOrFail('secretariat.step_three', ['items' => $items ?? []]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le contenu de l'etape 4 (souscriptions finalisees).
   */
  public function inscriptionsFinalises(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $saison = ConfHelper::getSaisonService()->getSaisonCourante();

      if (!$saison) {
        throw new \RuntimeException('Aucune saison courante définie.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $items = $model->getLicenceAEnregistrer(
        (int) $saison->id_campagne,
        ['cotisation_check' => true, 'caci_check' => true, 'licence_check' => true]
      );

      $html = $this->renderLayoutOrFail('secretariat.finalize', ['items' => $items ?? []]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le détail du paiement HelloAsso d'une souscription. Réutilisé par deux
   * contextes distincts (même bouton .js-show-payement) : le Secrétariat consultant le paiement
   * d'un adhérent en cours de traitement, et le dashboard Accueil où l'adhérent consulte le
   * sien — d'où le contrôle d'accès ci-dessous plutôt qu'un simple accès Bureau : l'id_profil
   * demandé doit être celui de l'appelant, sauf pour un membre du Bureau qui peut consulter
   * n'importe quel adhérent.
   *
   * @return void
   * @throws \RuntimeException Si l'appelant n'est ni le propriétaire du profil demandé, ni membre du Bureau.
   */
  public function getPayement(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();

      $input = $app->input;
      $idProfil   = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $idOrder    = $input->getString('id_order', '');

      $currentUser = $app->getIdentity();
      $estProprietaire = $currentUser !== null && (int) $currentUser->id > 0 && (int) $currentUser->id === $idProfil;

      if (!$estProprietaire && !UsersHelper::isBureauMember()) {
        throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');

      // cotisation_code et licence sont relus en base par le modèle (id_profil/id_campagne
      // suffisent) : on ne fait plus confiance aux data-item-* postés par le JS.
      $report = $model->getPayement($idProfil, $idCampagne, $idOrder);

      $html = $this->renderLayoutOrFail('secretariat.payement', [
        'report' => $report,
      ]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data    = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: charge le contenu de l'etape "Paiements HelloAsso orphelins" de la campagne courante.
   *
   * @return void
   */
  public function stepOrphelins(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $saison = ConfHelper::getSaisonService()->getSaisonCourante();

      if (!$saison) {
        throw new \RuntimeException('Aucune saison courante définie.');
      }

      $forceRefresh = $app->input->getBool('force_refresh', false);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $result = $model->getPaiementsOrphelins($saison, $forceRefresh);

      $html = $this->renderLayoutOrFail('secretariat.paiements_orphelins', [
        'lignes'          => $result['lignes'] ?? [],
        'candidats'       => $result['candidats'] ?? [],
        'id_campagne'     => (int) $saison->id_campagne,
        'nb_non_associes' => (int) ($result['nb_non_associes'] ?? 0),
      ]);

      $nbAutoAssocies = (int) ($result['nb_auto_associes'] ?? 0);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      // Message affiché uniquement si le rafraîchissement a réellement associé automatiquement des
      // paiements par correspondance exacte de licence (voir RapprochementPaiementService::
      // autoAssocierParLicenceExacte()) : sinon un chargement/rafraîchissement normal reste muet.
      $response->message = $nbAutoAssocies > 0
        ? Text::plural('COM_GDA_SECRETARIAT_ORPHELINS_AUTO_ASSOCIATE_SUCCESS', $nbAutoAssocies)
        : '';

      if ($nbAutoAssocies > 0) {
        GdaLogger::info(
          '[' . $this->getActingUserName() . '] ' .
            "Rafraîchissement orphelins : $nbAutoAssocies association(s) automatique(s) (id_campagne=" . (int) $saison->id_campagne . ')'
        );
      }
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: associe manuellement une commande HelloAsso orpheline à un adhérent de la campagne courante.
   *
   * @return void
   */
  public function associerPaiementOrphelin(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $idOrder = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $idOrder = trim((string) $input->getString('id_order', ''));
      $idAncienProfil = $input->getInt('id_ancien_profil', 0);

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $name = $model->associerPaiementOrphelin($idProfil, $idCampagne, $idOrder, $idAncienProfil > 0 ? $idAncienProfil : null);

      $response = new JsonResponse();
      $response->success = true;
      $response->message = Text::sprintf('COM_GDA_SECRETARIAT_ORPHELINS_ASSOCIATE_SUCCESS', $idOrder, $name);

      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          "Paiement orphelin associé (id_order=$idOrder, id_profil=$idProfil, id_campagne=$idCampagne): $name"
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          "Erreur lors de l'association d'un paiement orphelin (id_profil=$idProfil, id_campagne=$idCampagne, id_order=$idOrder): " . $e->getMessage()
      );
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: dissocie une commande HelloAsso d'un adhérent (option "Aucun" du sélecteur de correction),
   * sans l'attribuer à personne d'autre.
   *
   * @return void
   */
  public function dissocierPaiementOrphelin(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    $idProfil = 0;
    $idCampagne = 0;
    $idOrder = '';

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $input = $app->input;
      $idProfil = $input->getInt('id_profil', 0);
      $idCampagne = $input->getInt('id_campagne', 0);
      $idOrder = trim((string) $input->getString('id_order', ''));

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $name = $model->dissocierPaiementOrphelin($idProfil, $idCampagne, $idOrder);

      $response = new JsonResponse();
      $response->success = true;
      $response->message = Text::sprintf('COM_GDA_SECRETARIAT_ORPHELINS_DISSOCIATE_SUCCESS', $idOrder, $name);

      GdaLogger::info(
        '[' . $this->getActingUserName() . '] ' .
          "Paiement orphelin dissocié (id_order=$idOrder, id_profil=$idProfil, id_campagne=$idCampagne): $name"
      );
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
      GdaLogger::error(
        '[' . $this->getActingUserName() . '] ' .
          "Erreur lors de la dissociation d'un paiement orphelin (id_profil=$idProfil, id_campagne=$idCampagne, id_order=$idOrder): " . $e->getMessage()
      );
    }

    echo $response;
    $app->close();
  }

  /**
   * Ajax: affiche le détail simplifié d'une commande HelloAsso encore orpheline (aucun adhérent
   * connu, donc pas de comparaison avec une cotisation attendue - contrairement à getPayement()).
   *
   * @return void
   */
  public function getDetailCommandeOrpheline(): void
  {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();

    try {
      $this->checkToken();
      $this->guardBureauMember();

      $idOrder = trim((string) $app->input->getString('id_order', ''));

      if ($idOrder === '') {
        throw new \InvalidArgumentException('Commande invalide.');
      }

      /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $model */
      $model = $this->getModel('secretariat', 'site');
      $detail = $model->getDetailCommandeOrpheline($idOrder);

      $html = $this->renderLayoutOrFail('secretariat.detail_commande', ['detail' => $detail]);

      $response = new JsonResponse();
      $response->success = true;
      $response->data = base64_encode($html);
      $response->message = '';
    } catch (\Throwable $e) {
      $response = new JsonResponse();
      $response->success = false;
      $response->message = 'Erreur: ' . $e->getMessage();
    }

    echo $response;
    $app->close();
  }
}
