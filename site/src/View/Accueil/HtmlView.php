<?php

namespace NCB\Component\Gda\Site\View\Accueil;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\AccueilModel;
use NCB\Component\Gda\Site\Model\AdhesionModel;

class HtmlView extends BaseHtmlView
{
    /**
     * Prépare toutes les données du dashboard de l'espace Adhérents.
     *
     * Tout l'accès aux données est réuni ici : le template ne fait plus que des
     * LayoutHelper::render() sur les propriétés exposées.
     *
     * @param string|null $tpl Nom du sous-gabarit à rendre.
     * @return void
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        /** @var AccueilModel $accueilModel */
        $accueilModel = $this->getModel();

        $this->user = $app->getIdentity();

        // Rôle affiché dans l'en-tête (Bureau / Responsable de Groupe / Moniteur / Adhérent).
        $this->userRole = UsersHelper::getCurrentUserRole();

        /** @var AdhesionModel $adhesionModel */
        $adhesionModel = new AdhesionModel();
        $this->profil = $adhesionModel->getProfil();

        // Saison de suivi courante : pilote le suivi CACI/licence, indépendamment de l'ouverture
        // des inscriptions.
        $this->saisonCourante = ConfHelper::getSaisonService()->getSaisonCourante();

        $this->souscription = null;
        $this->statusEnum   = AdhesionStatusHelper::STATUS_NOT_SUBSCRIBED;

        if ($this->saisonCourante !== null && $this->user !== null && $this->user->id > 0) {
            $this->souscription = $accueilModel->getAdhesionStatus(
                (int) $this->user->id,
                (int) $this->saisonCourante->id_campagne
            );
            $this->statusEnum = AdhesionStatusHelper::getStatusEnum($this->souscription);
        }

        // Campagnes Formation et Loisir ouvertes, avec l'état de réservation de l'adhérent connecté,
        // réparties par nature : un encart repliable par nature dans le template.
        $campagnesReservables = $accueilModel->getCampagnesReservables($this->user);
        $idTypeLoisir = (int) ConfHelper::getValue('IdTypeLoisir');

        $this->campagnesLoisir = array_filter(
            $campagnesReservables,
            static fn ($campagne) => (int) $campagne->id_type === $idTypeLoisir
        );
        $this->campagnesFormation = array_filter(
            $campagnesReservables,
            static fn ($campagne) => (int) $campagne->id_type !== $idTypeLoisir
        );

        // Campagnes de nature Boutique, enrichies de leurs articles HelloAsso (cache du service).
        $this->campagnesBoutique = $accueilModel->getCampagnesBoutique();

        parent::display($tpl);
    }
}

