<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Response\JsonResponse;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\GroupesModel;

/**
 * Tasks ajax de la vue Groupes : chargement d'un onglet, modification des groupes d'un adhérent.
 *
 * La consultation de la vue est ouverte aux Moniteurs, Responsables de Groupe et membres du Bureau
 * (View/Groupes/HtmlView.php::display()) ; la modification de la composition des groupes est
 * réservée aux Responsables de Groupe.
 */
class GroupesController extends AjaxController
{
    /**
     * Ajax : contenu d'un onglet de la vue Groupes (vues Détail et Vignette) pour la saison
     * courante, chargé à l'ouverture de l'onglet.
     *
     * Paramètres : id_groupe (identifiant du groupe, 0 pour « Tous les groupes », -1 pour « Sans groupe »).
     *
     * @return void
     */
    public function onglet(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardEncadrant();

            $onglet = $this->getGroupesModel()->getOngletGroupe(
                $this->input->getInt('id_groupe', GroupesModel::ID_GROUPE_TOUS),
                $this->getIdSaisonCourante()
            );

            $Response = new JsonResponse([
                'html' => base64_encode(LayoutHelper::render('groupes.onglet', [
                    'groupe'              => $onglet,
                    'peutModifierGroupes' => UsersHelper::isResponsableGroupe(),
                ])),
            ]);

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : remplace les groupes d'un adhérent pour la saison courante (colonne Groupes de
     * l'onglet « Tous les groupes ») et renvoie la cellule re-rendue.
     *
     * Paramètres : id_profil, id_groupes[] (identifiants des groupes publiés retenus).
     *
     * @return void
     */
    public function updateGroupesAdherent(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $idProfil = 0;

        try {
            $this->checkToken();
            $this->guardResponsableGroupe();

            $idProfil = $this->input->getInt('id_profil', 0);
            $idsGroupes = array_map('intval', $this->input->get('id_groupes', [], 'array'));

            $idCampagne = $this->getIdSaisonCourante();
            $model = $this->getGroupesModel();
            $groupes = $model->updateGroupesAdherent($idProfil, $idCampagne, $idsGroupes);

            $adherent = (object) ['id_profil' => $idProfil, 'groupes' => $groupes];

            GdaLogger::info(sprintf(
                '[%s] Groupes de l\'adhérent id_profil=%d mis à jour depuis la vue Groupes : %s',
                $app->getIdentity()->name,
                $idProfil,
                implode(', ', array_column($groupes, 'groupe_name'))
            ));

            $Response = new JsonResponse([
                'html' => base64_encode(LayoutHelper::render('groupes.cellule_groupes', [
                    'adherent' => $adherent,
                    'editable' => true,
                ])),
                // Nouveaux compteurs des onglets, liste {id_groupe, nb} (groupes.js les reporte
                // sur la barre d'onglets et la liste déroulante).
                'comptes' => array_map(
                    static fn (object $onglet): array => ['id_groupe' => $onglet->id_groupe, 'nb' => $onglet->nb_adherents],
                    $model->getOngletsGroupes($idCampagne)
                ),
            ], Text::_('COM_GDA_GROUPES_COMPOSITION_UPDATED'));

            echo $Response;
        } catch (\Exception $e) {
            GdaLogger::error(sprintf(
                '[%s] Erreur lors de la mise à jour des groupes de l\'adhérent id_profil=%d : %s',
                $app->getIdentity()->name,
                $idProfil,
                $e->getMessage()
            ));

            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Modèle Groupes du site.
     *
     * @return GroupesModel Le modèle.
     */
    private function getGroupesModel(): GroupesModel
    {
        /** @var GroupesModel $model */
        $model = $this->getModel('groupes', 'site');

        return $model;
    }
}
