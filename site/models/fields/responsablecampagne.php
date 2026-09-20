<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\UsersHelper;

/**
 * Champ personnalisé qui liste les personnes pouvant être désignées responsable d'une campagne
 * Formation / Loisir (comptes actifs des groupes « Membre du Bureau » et « Responsable de Groupe »,
 * voir UsersHelper::getResponsablesCampagne()). Le responsable reçoit un e-mail à chaque nouvelle
 * demande d'inscription, à l'adresse de son compte Joomla (CampagnesModel::notifierActiviteInscription()).
 *
 * La valeur enregistrée est l'identifiant du compte (#__users.id) ; une campagne sans responsable
 * (valeur vide) ne notifie personne.
 */
class JFormFieldResponsableCampagne extends ListField
{
    protected $type = 'ResponsableCampagne';

    /**
     * Récupère les options : « Prénom NOM (adresse e-mail) », triées par nom.
     *
     * @return array<int, object> Options de la liste, précédées de l'option vide (« Aucun »).
     */
    protected function getOptions()
    {
        $options = [HTMLHelper::_('select.option', '', Text::_('COM_GDA_CAMPAGNE_RESPONSABLE_AUCUN'))];

        foreach (UsersHelper::getResponsablesCampagne() as $compte) {
            $nomComplet = trim((string) $compte->prenom . ' ' . (string) $compte->nom);
            $libelle    = $nomComplet !== '' ? $nomComplet : (string) $compte->name;

            $options[] = HTMLHelper::_('select.option', (int) $compte->id, $libelle . ' (' . $compte->email . ')');
        }

        return array_merge(parent::getOptions(), $options);
    }
}
