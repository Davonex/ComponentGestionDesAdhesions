<?php

use Joomla\CMS\Layout\LayoutHelper;

/**
 * @var array $displayData
 * - $displayData['inscrits'] : object{id_groupe: 0, groupe_name, icon, adherents} — voir
 *   CampagnesModel::getInscritsCampagne(), même forme qu'un groupe de GroupesModel afin de
 *   réutiliser tel quel le rendu de la vue Groupes (photo, nom, CACI, date CACI, statut).
 */

$inscrits = $displayData['inscrits'];

?>

<?= LayoutHelper::render('groupes.detail', ['groupe' => $inscrits, 'showRole' => true]) ?>
