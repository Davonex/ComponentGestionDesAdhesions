<?php

use Joomla\CMS\Language\Text;

/**
 * @var array $displayData
 * - $displayData['type_name'] : nature de la campagne (Sortie, Soirée, Boutique)
 */

$typeName = $displayData['type_name'];

?>

<div class="text-center text-muted py-5">
    <p class="fs-4"><i class="fa-solid fa-hourglass-half me-2" aria-hidden="true"></i><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_COMINGSOON') ?></p>
    <p><?= Text::sprintf('COM_GDA_CAMPAGNES_SUIVI_COMINGSOON_TYPE', htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8')) ?></p>
</div>
