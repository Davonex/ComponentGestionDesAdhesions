<?php

namespace NCB\Component\Gda\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Service\BrevetService;

/**
 * Modèle de la vue « Brevets » (Bureau) : administration du référentiel FFESSM et rattachement
 * des brevets saisis par les adhérents.
 *
 * Façade fine au-dessus de BrevetService, seul détenteur des accès à #__gda_brevets et
 * #__gda_mapping_brevets : aucune requête n'est écrite ici.
 */
class BrevetsModel extends ListModel
{
    private ?BrevetService $brevetService = null;

    /**
     * BrevetService en lazy loading, avec la base issue du conteneur global : les services du
     * composant sont enregistrés dans le conteneur du *composant* (services/provider.php), que
     * Factory::getContainer() ne renvoie pas. Même pattern que ProfilModel::getBrevetService().
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService(Factory::getContainer()->get(DatabaseInterface::class));
        }

        return $this->brevetService;
    }

    /**
     * Référentiel FFESSM complet (onglet 1).
     *
     * @return object[]
     */
    public function getMappings(): array
    {
        return $this->getBrevetService()->getMappings();
    }

    /**
     * Brevets de tous les adhérents avec leur correspondance résolue (onglet 2).
     *
     * @return object[]
     */
    public function getBrevetsAvecMapping(): array
    {
        return $this->getBrevetService()->getBrevetsAvecMapping();
    }

    /**
     * Activités distinctes du référentiel, pour les listes déroulantes des deux onglets.
     *
     * @return string[]
     */
    public function getActivites(): array
    {
        return $this->getBrevetService()->getActivitesReferentiel();
    }

    public function createMapping(array $donnees): object
    {
        return $this->getBrevetService()->createMapping($donnees);
    }

    public function updateMappingChamp(int $idMapping, string $champ, string $valeur): void
    {
        $this->getBrevetService()->updateMappingChamp($idMapping, $champ, $valeur);
    }

    public function countBrevetsLies(int $idMapping): int
    {
        return $this->getBrevetService()->countBrevetsLies($idMapping);
    }

    /** @return string le libellé supprimé, pour le message de confirmation */
    public function deleteMapping(int $idMapping): string
    {
        return $this->getBrevetService()->deleteMapping($idMapping);
    }

    /** @return object {adherent, ancien_nom, nom}, pour le message de confirmation */
    public function updateNomBrevet(int $idBrevet, string $nom): object
    {
        return $this->getBrevetService()->updateNomBrevet($idBrevet, $nom);
    }

    public function attacherMapping(int $idBrevet, int $idMapping): object
    {
        return $this->getBrevetService()->attacherMapping($idBrevet, $idMapping);
    }
}
