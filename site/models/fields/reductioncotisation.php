<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\CotisationService;

/**
 * Champ personnalisé qui liste les options de tarification proposables à l'adhérent.
 *
 * Les options ne sont plus codées en dur dans models/forms/adhesion.xml : elles sont déduites des
 * tarifs ACTIFS de #__gda_cotisation, administrés par le Bureau depuis l'onglet « Tarification »
 * de la vue Saisons.
 *
 * Une même option peut être portée par plusieurs tarifs (A et D sur « Normal », B et E sur
 * « Réduction Famille ») : elle est proposée dès qu'au moins un de ces tarifs est actif, la ligne
 * effectivement retenue étant choisie par CotisationService::getCode() en fonction de l'âge.
 */
class JFormFieldReductionCotisation extends ListField
{
    protected $type = 'ReductionCotisation';

    /**
     * Construire la liste des options de tarification.
     *
     * @return array Options du champ.
     * @throws RuntimeException Si le référentiel tarifaire est inaccessible.
     */
    protected function getOptions()
    {
        $options = [];
        $optionsReduction = CotisationService::getOptionsReduction();

        foreach ($optionsReduction as $valeur => $libelle) {
            $options[] = HTMLHelper::_('select.option', (string) $valeur, $libelle);
        }

        // Valeur historique hors liste (profil enregistré avec une réduction dont le tarif a
        // depuis été désactivé) : elle reste représentée, sinon un simple affichage du formulaire
        // suivi d'un enregistrement écraserait silencieusement la tarification vers le 1er choix.
        // Même garde-fou que layouts/secretariat/step_two.php.
        $valeurCourante = $this->value;

        if ($valeurCourante !== null && $valeurCourante !== '' && !isset($optionsReduction[(int) $valeurCourante])) {
            $options[] = HTMLHelper::_(
                'select.option',
                (string) (int) $valeurCourante,
                CotisationService::getLibelleOption((int) $valeurCourante)
            );
        }

        return array_merge(parent::getOptions(), $options);
    }
}
