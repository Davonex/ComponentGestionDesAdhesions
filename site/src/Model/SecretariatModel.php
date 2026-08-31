<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\HelloAssoService;
use NCB\Component\Gda\Site\Service\NotificationMailService;
use NCB\Component\Gda\Site\Service\SouscriptionService;
use NCB\Component\Gda\Site\Helper\GdaLogger;

/**
 * Secretariat Model
 *
 * @since  1.0.0
 */
class SecretariatModel extends ListModel
{
  /**
   * Model context string.
   *
   * @var    string
   * @since  1.0.0
   */
  protected $context = 'com_gdadhesions.secretariat';

  /**
   * Propriété privée pour stocker l'instance de l'application
   */
  private $app = null;

  /**
   * Getter pour obtenir l'instance de l'application (lazy loading)
   */
  private function getApp()
  {
    if ($this->app === null) {
      $this->app = Factory::getApplication();
    }
    return $this->app;
  }

  /**
   * Propriété privée pour stocker l'instance de HelloAssoService
   */
  private ?HelloAssoService $helloAsso = null;

  /**
   * Getter pour obtenir l'instance de HelloAssoService (lazy loading)
   */
  private function getHelloAsso(): HelloAssoService
  {
    if ($this->helloAsso === null) {
      $this->helloAsso = new HelloAssoService();
    }
    return $this->helloAsso;
  }

  /**
   * Getter pour obtenir le service de notification mail.
   */
  private function getNotificationMailService(): NotificationMailService
  {
    return new NotificationMailService(
      $this->getDatabase(),
      Factory::getContainer()->get(MailerFactoryInterface::class),
      ConfHelper::getConfigService()
    );
  }


  /**
   * Méthode pour obtenir une liste d’objets qui doivent être validés par le secrétariat.
   *
   * @param   int                $idCampagne  L'identifiant de la campagne.
   * @param   array<string, bool> $options    Filtres : cotisation_check, caci_check, licence_check.
   *                                          Présence de la clé = filtre actif (recherche = 0).
   *
   * @return  array|null
   *
   * @since  1.0.0
   */
  public function getSouscriptionsAValider(int $idCampagne, array $options = []): ?array
  {
    // si $options['cotisation_check'] === true alors $value_cotisation_check = 1 sinon 0
    $value_cotisation_check = $options['cotisation_check'] === false ? 0 : 1;
    $value_caci_check       = $options['caci_check'] === false       ? 0 : 1;
    $value_licence_check    = $options['licence_check'] === false    ? 0 : 1;

    // jointure avec la table _profils et souscriptions et users pour recuperer tous les infos pour alimenter la vue scretariat
    $db = $this->getDatabase();

    $query = $db->getQuery(true);
    $selection = array(
      $db->quoteName('s.id_campagne'),
      $db->quoteName('s.date_souscription'),
      $db->quoteName('s.last_update'),
      $db->quoteName('s.cotisation_code'),
      $db->quoteName('s.id_order'),
      $db->quoteName('s.categorie'),
      'p.*',
      $db->quoteName('u.email'),
      $db->quoteName('u.username'),
    );

    $query->select($selection)
      ->from($db->quoteName('#__gda_souscriptions', 's'))
      ->join('INNER', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('s.id_profil') . ' = ' . $db->quoteName('p.id_profil'))
      ->join('INNER', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('u.id'))
      ->where($db->quoteName('s.id_campagne') . ' = :value_id_campagne');
    // cotisation_check = false et caci_check =false pour filtrer les souscriptions qui n'ont pas encore été traitées par le secrétariat
    $query->where($db->quoteName('s.caci_check') . ' = :value_caci_check');
    $query->where($db->quoteName('s.cotisation_check') . ' = :value_cotisation_check');
    $query->where($db->quoteName('s.licence_check') . ' = :value_licence_check');

    $query->bind(':value_id_campagne', $idCampagne);
    $query->bind(':value_cotisation_check', $value_cotisation_check);
    $query->bind(':value_caci_check', $value_caci_check);
    $query->bind(':value_licence_check', $value_licence_check);



    $db->setQuery($query);

    try {
      $results = $db->loadObjectList() ?: [];
      $souscriptionService = new SouscriptionService($db);

      foreach ($results as &$item) {
        $item->date_caci = ToolsHelper::from_sqldate($item->date_caci);
        $item->last_update = ToolsHelper::from_sqldate($item->last_update);
        $item->groupes = [];
        $item->is_caci_validable = $souscriptionService->isCaciValidable($item->date_caci, $item->caci);
      }


      if (!empty($results)) {
        $profilIds = [];

        foreach ($results as $ele) {
          $profilIds[] = (int) ($ele->id_profil ?? 0);
        }

        $profilIds = array_values(array_unique(array_filter($profilIds)));

        if (!empty($profilIds)) {
          $inProfilIds = implode(',', $profilIds);

          $groupesQuery = $db->getQuery(true)
            ->select([
              $db->quoteName('cg.id_profil'),
              $db->quoteName('cg.id_groupe'),
              $db->quoteName('g.groupe_name'),
            ])
            ->from($db->quoteName('#__gda_composition_groupes', 'cg'))
            ->join('LEFT', $db->quoteName('#__gda_groupes', 'g') . ' ON ' . $db->quoteName('cg.id_groupe') . ' = ' . $db->quoteName('g.id_groupe'))
            ->where($db->quoteName('cg.id_campagne') . ' = :value_id_campagne')
            ->where($db->quoteName('cg.id_profil') . ' IN (' . $inProfilIds . ')')
            ->bind(':value_id_campagne', $idCampagne);

          $db->setQuery($groupesQuery);
          $groupesRows = $db->loadObjectList() ?: [];

          $groupesByProfil = [];

          foreach ($groupesRows as $groupeRow) {
            $profilId = (int) ($groupeRow->id_profil ?? 0);

            if ($profilId <= 0) {
              continue;
            }

            $groupesByProfil[$profilId][] = [
              'id_groupe' => (int) ($groupeRow->id_groupe ?? 0),
              'groupe_name' => (string) ($groupeRow->groupe_name ?? ''),
            ];
          }

          foreach ($results as &$item) {
            $item->groupes = $groupesByProfil[(int) ($item->id_profil ?? 0)] ?? [];
          }
          unset($item);
        }
      }
    } catch (\RuntimeException $e) {
      throw new \RuntimeException($e->getMessage(), 500, $e);
    }

    return $results ?: null;
  }

  /**
   * Recupere les souscriptions pretes pour la declaration licence (etape 3).
   *
   * @param int                $idCampagne L'identifiant de la campagne.
   * @param array<string, bool> $options   Filtres : cotisation_check, caci_check, licence_check.
   *
   * @return array|null
   */
  public function getLicenceAEnregistrer(int $idCampagne, array $options = []): ?array
  {
    return $this->getSouscriptionsAValider($idCampagne, $options);
  }

  /**
   * Supprime definitivement un adherent et ses dependances metier.
   *
   * Suppression transactionnelle avec rollback si une etape echoue.
   *
   * @param int $idProfil Identifiant du profil/utilisateur Joomla.
   *
   * @return array<string, string>
   */
  public function deleteAdherentDefinitif(int $idProfil): array
  {
    if ($idProfil <= 0) {
      throw new \InvalidArgumentException('Identifiant profil invalide.');
    }

    $db = $this->getDatabase();

    $queryUser = $db->getQuery(true)
      ->select([
        $db->quoteName('u.id'),
        $db->quoteName('u.username'),
        $db->quoteName('p.civilite'),
        $db->quoteName('p.nom'),
        $db->quoteName('p.prenom'),
        $db->quoteName('p.photo'),
        $db->quoteName('p.caci'),
      ])
      ->from($db->quoteName('#__users', 'u'))
      ->join('LEFT', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('u.id'))
      ->where($db->quoteName('u.id') . ' = :id_profil')
      ->bind(':id_profil', $idProfil);

    $db->setQuery($queryUser);
    $adherent = $db->loadObject();

    if (!$adherent) {
      throw new \RuntimeException('Adhérent introuvable.');
    }

    $displayName = trim((string) (($adherent->civilite ?? 'M.') . ' ' . ($adherent->nom ?? '') . ' ' . ($adherent->prenom ?? '')));
    $username = trim((string) ($adherent->username ?? ''));

    try {
      $db->transactionStart();

      try {
        $deleteSouscriptions = $db->getQuery(true)
          ->delete($db->quoteName('#__gda_souscriptions'))
          ->where($db->quoteName('id_profil') . ' = :id_profil')
          ->bind(':id_profil', $idProfil);

        $db->setQuery($deleteSouscriptions);
        $db->execute();
      } catch (\Throwable $e) {
        throw new \RuntimeException('Suppression impossible dans #__gda_souscriptions: ' . $e->getMessage(), 500, $e);
      }

      try {
        $deleteCompositionGroupes = $db->getQuery(true)
          ->delete($db->quoteName('#__gda_composition_groupes'))
          ->where($db->quoteName('id_profil') . ' = :id_profil')
          ->bind(':id_profil', $idProfil);

        $db->setQuery($deleteCompositionGroupes);
        $db->execute();
      } catch (\Throwable $e) {
        throw new \RuntimeException('Suppression impossible dans #__gda_composition_groupes: ' . $e->getMessage(), 500, $e);
      }

      // Le schema comporte aussi une FK #__gda_brevets -> #__gda_profils.
      try {
        $deleteBrevets = $db->getQuery(true)
          ->delete($db->quoteName('#__gda_brevets'))
          ->where($db->quoteName('id_profil') . ' = :id_profil')
          ->bind(':id_profil', $idProfil);

        $db->setQuery($deleteBrevets);
        $db->execute();
      } catch (\Throwable $e) {
        throw new \RuntimeException('Suppression impossible dans #__gda_brevets: ' . $e->getMessage(), 500, $e);
      }

      try {
        // Pas d'erreur si 0 ligne affectee : le profil #__gda_profils peut legitimement etre
        // absent pour ce user.id (compte cree/importe sans profil metier, ou profil deja
        // supprime lors d'une manipulation precedente). L'objectif ici reste la suppression
        // complete du compte Joomla, qui se fait plus bas quel que soit ce cas.
        $deleteProfil = $db->getQuery(true)
          ->delete($db->quoteName('#__gda_profils'))
          ->where($db->quoteName('id_profil') . ' = :id_profil')
          ->bind(':id_profil', $idProfil);

        $db->setQuery($deleteProfil);
        $db->execute();
      } catch (\Throwable $e) {
        throw new \RuntimeException('Suppression impossible dans #__gda_profils: ' . $e->getMessage(), 500, $e);
      }

      try {
        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserById($idProfil);

        if (!$user || (int) $user->id !== (int) $idProfil) {
          throw new \RuntimeException('Utilisateur Joomla introuvable.');
        }

        if (!$user->delete()) {
          throw new \RuntimeException((string) $user->getError());
        }
      } catch (\Throwable $e) {
        throw new \RuntimeException('Suppression impossible dans #__users via API Joomla: ' . $e->getMessage(), 500, $e);
      }

      $db->transactionCommit();

      // Supprimer les fichiers associés (photo, caci) en post-commit pour éviter les problèmes de rollback
      try {
        if (!empty($adherent->photo)) {
          FileHelper::deleteFile($adherent->photo, 'ProfilPhotoPath');
        }
        if (!empty($adherent->caci)) {
          FileHelper::deleteFile($adherent->caci, 'CaciPath');
        }
      } catch (\Throwable $fileEx) {
        GdaLogger::warning('Suppression fichiers adherent echouee (id_profil=' . $idProfil . '): ' . $fileEx->getMessage());
      }

      return [
        'display_name' => $displayName,
        'username' => $username,
      ];
    } catch (\Throwable $e) {
      $db->transactionRollback();
      throw $e;
    }
  }

  /**
   * Met a jour la date CACI dans la table profils.
   *
   * @param int $idProfil Identifiant du profil.
   * @param string $dateCaci Date au format DD/MM/YYYY.
   *
   * @return bool
   */
  public function updateDateCaci(int $idProfil, string $dateCaci): bool
  {
    if ($idProfil <= 0) {
      throw new \InvalidArgumentException('Identifiant profil invalide.');
    }

    $dateCaci = trim($dateCaci);

    $db = $this->getDatabase();
    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_profils'))
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->bind(':id_profil', $idProfil);

    // Champ vide autorise: on enregistre NULL en base.
    if ($dateCaci === '') {
      $query->set($db->quoteName('date_caci') . ' = NULL');
    } else {
      // Validation stricte du format DD/MM/YYYY avant conversion.
      if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateCaci)) {
        throw new \InvalidArgumentException('Format date invalide (attendu DD/MM/YYYY).');
      }

      $dateSql = ToolsHelper::to_sqldate($dateCaci);

      if ($dateSql === null) {
        throw new \InvalidArgumentException('Date invalide.');
      }

      $query
        ->set($db->quoteName('date_caci') . ' = :date_caci')
        ->bind(':date_caci', $dateSql);
    }

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune ligne mise a jour.');
    }

    return true;
  }

  /**
   * Récupère le nom du fichier CACI d'un profil (colonne #__gda_profils.caci).
   *
   * Utilisé par SecretariatController::updateDateCaci() pour transmettre le fichier à
   * SouscriptionService::isCaciValidable() : l'endpoint AJAX ne reçoit que la nouvelle date, pas
   * le fichier (inchangé par cette action), il doit donc le relire pour appliquer la règle
   * complète (date + fichier chargé).
   *
   * @param int $idProfil Identifiant du profil.
   * @return string|null Nom du fichier CACI, ou null si absent/profil introuvable.
   * @throws \RuntimeException Si la requête échoue.
   */
  public function getCaciFile(int $idProfil): ?string
  {
    $db = $this->getDatabase();
    $query = $db->getQuery(true)
      ->select($db->quoteName('caci'))
      ->from($db->quoteName('#__gda_profils'))
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->bind(':id_profil', $idProfil);

    $db->setQuery($query);

    try {
      $caci = $db->loadResult();
    } catch (\RuntimeException $e) {
      throw new \RuntimeException($e->getMessage(), 500, $e);
    }

    return $caci !== null && $caci !== '' ? (string) $caci : null;
  }

  /**
   * Met a jour la categorie d'une souscription.
   *
   * @param int $idProfil Identifiant du profil.
   * @param int $idCampagne Identifiant de la campagne.
   * @param string $categorie Categorie (ADULTE, JEUNE, ENFANT).
   *
   * @return bool
   */
  public function updateCategorie(int $idProfil, int $idCampagne, string $categorie): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $categorie = strtoupper(trim($categorie));
    $allowedCategories = ['ADULTE', 'JEUNE', 'ENFANT'];

    if (!in_array($categorie, $allowedCategories, true)) {
      throw new \InvalidArgumentException('Categorie invalide.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('categorie') . ' = :categorie')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':categorie', $categorie)
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }

    return true;
  }

  /**
   * Retire la validation CACI d'une souscription.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function unvalidateCaci(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('caci_check') . ' = 0')
      ->set($db->quoteName('date_caci_check') . ' = NULL')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }

    return true;
  }

  /**
   * Valide le CACI d'une souscription et met a jour last_update.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function validateCaci(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('caci_check') . ' = 1')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->set($db->quoteName('date_caci_check') . ' = :date_caci_check')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne)
      ->bind(':date_caci_check', $now);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }

    return true;
  }

  /**
   * Valide le paiement d'une souscription et met a jour last_update.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function validatePayment(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('cotisation_check') . ' = 1')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->set($db->quoteName('date_cotisation_check') . ' = :date_cotisation_check')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne)
      ->bind(':date_cotisation_check', $now);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }

    return true;
  }

  /**
   * Retire la validation du paiement d'une souscription et met a jour last_update.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function unvalidatePayment(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('cotisation_check') . ' = 0')
      ->set($db->quoteName('licence_check') . ' = 0')
      ->set($db->quoteName('date_cotisation_check') . ' = NULL')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }
    return true;
  }

  /**
   * Finalise l'inscription d'une souscription et met a jour last_update.
   *
   * @param int $idProfil
   * @param int $idCampagne
   * @param string|null $licenceNumber Numero de licence FFESSM a enregistrer si le membre est nouveau.
   *
   * @return bool
   */
  public function finalizeInscription(int $idProfil, int $idCampagne, ?string $licenceNumber = null): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();


    $userQuery = $db->getQuery(true)
      ->select($db->quoteName(['id', 'username']))
      ->from($db->quoteName('#__users'))
      ->where($db->quoteName('id') . ' = :id_profil')
      ->bind(':id_profil', $idProfil);

    $db->setQuery($userQuery);
    $user = $db->loadObject();

    if (!$user) {
      return false;
      // throw new \RuntimeException('Utilisateur introuvable.');
    }

    $currentUsername = trim((string) ($user->username ?? ''));
    $currentPrefix = strtoupper(substr($currentUsername, 0, 1));
    $normalizedLicence = $licenceNumber !== null ? strtoupper(trim($licenceNumber)) : null;

    if ($currentPrefix === 'N') {
      if ($normalizedLicence === null || $normalizedLicence === '') {
        throw new \InvalidArgumentException('Le numéro de licence est obligatoire pour finaliser cette inscription.');
      }

      if (!preg_match('/^A-[0-9]{2}-[0-9]{6,7}$/', $normalizedLicence)) {
        throw new \InvalidArgumentException('Format de licence invalide. Attendu: A-00-000000 ou A-00-0000000.');
      }
    }

    try {
      $db->transactionStart();
      if ($currentPrefix === 'N') {
        //change la licence [username] de l'adherent
        $updateUserQuery = $db->getQuery(true)
          ->update($db->quoteName('#__users'))
          ->set($db->quoteName('username') . ' = :username')
          ->where($db->quoteName('id') . ' = :user_id')
          ->bind(':username', $normalizedLicence)
          ->bind(':user_id', $idProfil);

        $db->setQuery($updateUserQuery);
        $db->execute();
      }

      // Maintenant, active ce user afin qu'il puisse se connecter avec son nouveau username/licence
      // Avec les fonctions de l'API Joomla, on peut activer le compte en mettant le champ 'block' à 0
      $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
      $user = $userFactory->loadUserById($idProfil);
      if (!$user || (int) $user->id !== (int) $idProfil) {
        return false;
        // throw new \RuntimeException('Utilisateur Joomla introuvable pour activation.');
      } else if ($user->get('block') !== 0) {
         // Activer le user²
        $user->set('block', 0);
        $user->save();
      }




      /** 
       *  met a jour la souscription pour finaliser l'inscription (cotisation_check, caci_check, licence_check)
       */
      $query = $db->getQuery(true)
        ->update($db->quoteName('#__gda_souscriptions'))
        ->set($db->quoteName('cotisation_check') . ' = 1')
        ->set($db->quoteName('caci_check') . ' = 1')
        ->set($db->quoteName('licence_check') . ' = 1')
        ->set($db->quoteName('last_update') . ' = :last_update')
        ->set($db->quoteName('date_licence_check') . ' = :date_licence_check')
        ->where($db->quoteName('id_profil') . ' = :id_profil')
        ->where($db->quoteName('id_campagne') . ' = :id_campagne')
        ->bind(':last_update', $now)
        ->bind(':date_licence_check', $now)
        ->bind(':id_profil', $idProfil)
        ->bind(':id_campagne', $idCampagne);

      $db->setQuery($query);
      $db->execute();

      if ((int) $db->getAffectedRows() === 0) {
        throw new \RuntimeException('Aucune souscription mise a jour.');
      }

      /**
       * Fin de validite de la licence : 31/12 de l'annee suivante si l'enregistrement a lieu
       * a partir de septembre, sinon 31/12 de l'annee en cours (saison federale en cours).
       * La cle de re-edition du dossier n'a plus lieu d'etre pour un nouvel adherent finalise.
       */
      $dateFinLicence = AdhesionStatusHelper::computeDateFinValiditeLicence($now);

      $profilQuery = $db->getQuery(true)
        ->update($db->quoteName('#__gda_profils'))
        ->set($db->quoteName('date_licence') . ' = :date_licence')
        ->where($db->quoteName('id_profil') . ' = :id_profil')
        ->bind(':date_licence', $dateFinLicence)
        ->bind(':id_profil', $idProfil);

      if ($currentPrefix === 'N') {
        $profilQuery->set($db->quoteName('key') . ' = NULL');
      }

      $db->setQuery($profilQuery);
      $db->execute();

      $db->transactionCommit();

      // Envoi non bloquant du mail de confirmation apres commit DB.
      try {
        $notificationMailService = $this->getNotificationMailService();
        $mailSent = $notificationMailService->sendFinalizationEmail($idProfil, $idCampagne);

        if (!$mailSent) {
          GdaLogger::info(
            'Finalisation ok mais mail non envoye (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . ').',
          );
        }
      } catch (\Throwable $mailException) {
        GdaLogger::warning(
          'Finalisation ok mais exception mail (id_profil=' . $idProfil . ', id_campagne=' . $idCampagne . '): ' . $mailException->getMessage()
        );
      }
    } catch (\Throwable $e) {
      $db->transactionRollback();
      throw $e;
    }

    return true;
  }

  /**
   * Retire la finalisation d'une inscription et met a jour last_update.
   *
   * @param int $idProfil
   * @param int $idCampagne
   *
   * @return bool
   */
  public function unfinalizeInscription(int $idProfil, int $idCampagne): bool
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      throw new \InvalidArgumentException('Identifiants invalides.');
    }

    $db = $this->getDatabase();
    $now = Factory::getDate()->toSql();

    $query = $db->getQuery(true)
      ->update($db->quoteName('#__gda_souscriptions'))
      ->set($db->quoteName('licence_check') . ' = 0')
      ->set($db->quoteName('date_licence_check') . ' = NULL')
      ->set($db->quoteName('last_update') . ' = :last_update')
      ->where($db->quoteName('id_profil') . ' = :id_profil')
      ->where($db->quoteName('id_campagne') . ' = :id_campagne')
      ->bind(':last_update', $now)
      ->bind(':id_profil', $idProfil)
      ->bind(':id_campagne', $idCampagne);

    $db->setQuery($query);
    $db->execute();

    if ((int) $db->getAffectedRows() === 0) {
      throw new \RuntimeException('Aucune souscription mise a jour.');
    }

    return true;
  }

  /**
   * Construit le rapport de paiement HelloAsso d'un adhérent pour une campagne, prêt à afficher
   * (layouts/secretariat/payement.php n'a plus qu'à en formater/échapper les propriétés).
   *
   * cotisation_code et la licence (username) sont relus en base ici, jamais reçus du client (le
   * contrôleur ne les poste plus) : plus fiable pour un rapport financier affiché au secrétariat.
   *
   * @param int    $idProfil   Identifiant du profil
   * @param int    $idCampagne Identifiant de la campagne
   * @param string $idOrder    Identifiant de commande déjà connu ('0' si inconnu)
   * @return object Rapport de paiement, voir buildPaymentReport()
   */
  public function getPayement(int $idProfil, int $idCampagne, string $idOrder): object
  {
    $adherent = $this->getAdherentPourPayement($idProfil, $idCampagne);

    if ($adherent === null) {
      throw new \RuntimeException('Adhérent ou souscription introuvable.');
    }

    // Si l'id_order n'est pas encore connu, on tente de le trouver dans HelloAsso (recherche
    // toujours en direct et persistée si trouvée, voir SouscriptionService::resolveIdOrder()) :
    // point d'extension unique, plus de logique de résolution dupliquée ici.
    $idOrder = (new SouscriptionService($this->getDatabase()))->resolveIdOrder($idProfil, $idCampagne, $idOrder, $adherent->username);

    if ($idOrder === '0' || $idOrder === '') {
      return $this->buildPaymentReport(null, [], $adherent);
    }

    // Récupère le détail complet de la commande HelloAsso (une commande peut regrouper plusieurs
    // adhérents : on retrouve ensuite l'item qui correspond à celui-ci, pas systématiquement items[0]).
    $orderDetails = $this->getHelloAsso()->getOrderDetails($idOrder);

    if (empty($orderDetails['payments'])) {
      return $this->buildPaymentReport(null, $orderDetails, $adherent);
    }

    $item = $this->getHelloAsso()->findItemForAdherent(
      $orderDetails['items'] ?? [],
      $adherent->username,
      $adherent->nom,
      $adherent->prenom
    );

    return $this->buildPaymentReport($item, $orderDetails, $adherent);
  }

  /**
   * Charge cotisation_code (#__gda_souscriptions) et nom/prenom/username (#__gda_profils/#__users)
   * pour un couple id_profil/id_campagne - source de vérité pour getPayement(), jamais les valeurs
   * postées par le client.
   */
  private function getAdherentPourPayement(int $idProfil, int $idCampagne): ?object
  {
    if ($idProfil <= 0 || $idCampagne <= 0) {
      return null;
    }

    $db = $this->getDatabase();
    $query = $db->getQuery(true)
      ->select([
        $db->quoteName('s.cotisation_code'),
        $db->quoteName('p.nom'),
        $db->quoteName('p.prenom'),
        $db->quoteName('u.username'),
      ])
      ->from($db->quoteName('#__gda_souscriptions', 's'))
      ->join('INNER', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
      ->join('INNER', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('s.id_profil'))
      ->where($db->quoteName('s.id_profil') . ' = :id_profil')
      ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
      ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
      ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

    $db->setQuery($query);

    return $db->loadObject() ?: null;
  }

  /**
   * Construit le rapport de paiement à 5 lignes (Montant catalogue / Réduction / Total payé /
   * Cotisation attendue / Restant dû ou Trop versé) à partir de l'item HelloAsso de l'adhérent et
   * des données club. Fonction pure (pas de DB/HTTP) : c'est ici que vivent les règles métier.
   *
   * - montant_catalogue = item.initialAmount (prix affiché avant réduction)
   * - reduction_montant = item.discount.amount
   * - total_paye = somme de item.payments[].shareAmount : argent RÉELLEMENT reçu pour cet item,
   *   pas "montant_catalogue - reduction" (qui suppose un paiement intégral et ne serait plus
   *   correct pour un paiement partiel/échelonné)
   * - difference = cotisation_montant - total_paye (positif = restant dû, négatif = trop versé)
   *
   * @param array|null $item         Item HelloAsso de l'adhérent (HelloAssoService::findItemForAdherent()), ou null si aucune commande/paiement.
   * @param array      $orderDetails Commande HelloAsso complète (HelloAssoService::getOrderDetails()), ou [] si non résolue.
   * @param object     $adherent     Résultat de getAdherentPourPayement().
   */
  private function buildPaymentReport(?array $item, array $orderDetails, object $adherent): object
  {
    // CotisationService::getMontant() retourne un montant en EUROS (ex: 205), contrairement aux
    // montants HelloAsso ($totalPaye, $grossAmount, $discountAmount ci-dessous) qui sont en centimes.
    $cotisationCode = trim((string) ($adherent->cotisation_code ?? ''));
    $cotisationConnue = $cotisationCode !== '';
    $cotisationMontant = $cotisationConnue ? CotisationService::getMontant($cotisationCode) : 0;
    $cotisationLabel = $cotisationConnue ? Text::_('COM_GDA_COTISATION_TARIF_' . $cotisationCode) : '';

    $orderFound = !empty($orderDetails['payments']) && $item !== null;

    if (!$orderFound) {
      return (object) [
        'order_found' => false,
        'id_order' => '',
        'date' => '',
        'libelle_choisi' => '',
        'statut' => '',
        'beneficiaire_nom' => trim((string) ($adherent->prenom ?? '') . ' ' . (string) ($adherent->nom ?? '')),
        'beneficiaire_licence' => (string) ($adherent->username ?? ''),
        'payeur_nom' => '',
        'payeur_email' => '',
        'montant_catalogue' => 0.0,
        'reduction_code' => null,
        'reduction_montant' => 0.0,
        'total_paye' => 0.0,
        'cotisation_code' => $cotisationCode !== '' ? $cotisationCode : null,
        'cotisation_label' => $cotisationLabel,
        'cotisation_montant' => (float) $cotisationMontant,
        'cotisation_connue' => $cotisationConnue,
        'difference' => $cotisationConnue ? (float) $cotisationMontant : 0.0,
        'receipt_url' => '',
        'fiscal_receipt_url' => '',
      ];
    }

    $payer = $orderDetails['payer'] ?? [];
    $grossAmount = (int) ($item['initialAmount'] ?? ($item['amount'] ?? 0));
    $discountAmount = (int) ($item['discount']['amount'] ?? 0);

    $totalPaye = 0;
    foreach (($item['payments'] ?? []) as $itemPayment) {
      $totalPaye += (int) ($itemPayment['shareAmount'] ?? 0);
    }

    $receiptUrl = '';
    foreach (($orderDetails['payments'] ?? []) as $pay) {
      if ($receiptUrl === '' && !empty($pay['paymentReceiptUrl'])) {
        $receiptUrl = (string) $pay['paymentReceiptUrl'];
      }
    }

    $date = '';
    $dateStr = (string) ($orderDetails['date'] ?? '');

    if ($dateStr !== '') {
      try {
        $date = (new \DateTime($dateStr))->format('d/m/Y H:i');
      } catch (\Exception $e) {
        $date = '';
      }
    }

    return (object) [
      'order_found' => true,
      'id_order' => (string) ($orderDetails['id'] ?? ''),
      'date' => $date,
      'libelle_choisi' => (string) ($item['name'] ?? ''),
      'statut' => Text::_('COM_GDA_SECRETARIAT_PAYEMENT_STATE_' . strtoupper((string) ($item['state'] ?? 'unknown'))),
      'beneficiaire_nom' => trim((string) ($item['user']['firstName'] ?? '') . ' ' . (string) ($item['user']['lastName'] ?? '')),
      'beneficiaire_licence' => (string) ($adherent->username ?? ''),
      'payeur_nom' => trim((string) ($payer['firstName'] ?? '') . ' ' . (string) ($payer['lastName'] ?? '')),
      'payeur_email' => (string) ($payer['email'] ?? ''),
      'montant_catalogue' => $grossAmount / 100,
      'reduction_code' => $discountAmount > 0 ? trim((string) ($item['discount']['code'] ?? '')) : null,
      'reduction_montant' => $discountAmount / 100,
      'total_paye' => $totalPaye / 100,
      'cotisation_code' => $cotisationCode !== '' ? $cotisationCode : null,
      'cotisation_label' => $cotisationLabel,
      'cotisation_montant' => (float) $cotisationMontant,
      'cotisation_connue' => $cotisationConnue,
      'difference' => $cotisationConnue ? $cotisationMontant - ($totalPaye / 100) : 0.0,
      'receipt_url' => $receiptUrl,
      'fiscal_receipt_url' => (string) ($orderDetails['fiscalReceiptUrl'] ?? ''),
    ];
  }
}
