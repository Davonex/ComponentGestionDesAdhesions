<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\ListModel;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\SaisonService;

/**
 * Modèle de la vue "Saisons" (gestion des campagnes de type Saison, réservée au Bureau).
 * Délègue toute la logique métier à SaisonService.
 */
class SaisonsModel extends ListModel
{
    protected $context = 'com_gdadhesions.saisons';

    /**
     * Saison où `courante = 1`, ou null si aucune n'est déclarée.
     */
    public function getSaisonCourante(): ?object
    {
        return ConfHelper::getSaisonService()->getSaisonCourante();
    }

    /**
     * Liste de toutes les saisons (campagnes de type Saison, non effacées).
     *
     * @return object[]
     */
    public function getListeSaisons(): array
    {
        return ConfHelper::getSaisonService()->getListeSaisons();
    }

    /**
     * Une saison par son identifiant.
     */
    public function getSaison(?int $idCampagne): ?object
    {
        return SaisonService::getSaison($idCampagne);
    }

    /**
     * Formulaire d'édition de la saison courante, pré-rempli avec les valeurs de `$data`
     * (voir HtmlView::display(), qui construit ce tableau à partir de la saison courante).
     */
    public function getFormCourante(array $data = []): Form
    {
        $form = $this->loadForm(
            'com_gdadhesions.saison_courante',
            'saison_courante',
            ['control' => 'jform_saison', 'load_data' => false]
        );

        if (empty($form)) {
            throw new \RuntimeException('Unable to load form: com_gdadhesions.saison_courante', 500);
        }

        if (!empty($data)) {
            $form->bind($data);
        }

        return $form;
    }

    /**
     * Formulaire minimal de création d'une nouvelle saison (modal "Ajouter").
     */
    public function getFormAjout(): Form
    {
        $form = $this->loadForm(
            'com_gdadhesions.saison_ajout',
            'saison_ajout',
            ['control' => 'jform_saison_ajout', 'load_data' => false]
        );

        if (empty($form)) {
            throw new \RuntimeException('Unable to load form: com_gdadhesions.saison_ajout', 500);
        }

        return $form;
    }

    /**
     * Sauvegarde groupée des champs de contenu de la saison courante.
     */
    public function sauvegarderCourante(int $idCampagne, array $data): bool
    {
        return ConfHelper::getSaisonService()->sauvegarderCourante($idCampagne, $data);
    }

    /**
     * Crée une nouvelle saison avec les informations minimum.
     */
    public function creerSaison(array $data): int
    {
        return ConfHelper::getSaisonService()->creerSaison($data);
    }

    /**
     * Ouvre ou ferme une saison aux inscriptions.
     */
    public function toggleActive(int $idCampagne, bool $active): bool
    {
        return ConfHelper::getSaisonService()->toggleActive($idCampagne, $active);
    }

    /**
     * Déclare ou retire une saison comme saison courante.
     */
    public function toggleCourante(int $idCampagne, bool $courante): bool
    {
        return ConfHelper::getSaisonService()->toggleCourante($idCampagne, $courante);
    }

    /**
     * Référentiel tarifaire complet (cotisations club + licences FFESSM), pour l'onglet
     * « Tarification ». Inclut les lignes inactives : c'est cet écran qui les réactive.
     *
     * @return object[] Lignes du référentiel, triées par ordre d'affichage.
     * @throws \RuntimeException Si le référentiel est inaccessible.
     */
    public function getTarifs(): array
    {
        return CotisationService::getLignesAdmin($this->getDatabase());
    }

    /**
     * Édition inline d'une case du référentiel tarifaire.
     *
     * @param int    $idTarif Identifiant de la ligne.
     * @param string $champ   Champ à modifier.
     * @param string $valeur  Nouvelle valeur, telle que saisie.
     * @return object La ligne relue après écriture.
     * @throws \InvalidArgumentException Si le champ ou la valeur sont invalides.
     * @throws \RuntimeException Si la ligne est introuvable ou l'écriture échoue.
     */
    public function updateTarif(int $idTarif, string $champ, string $valeur): object
    {
        return CotisationService::updateLigne($idTarif, $champ, $valeur, $this->getDatabase());
    }
}
