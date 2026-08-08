<?php

/**
 * @package     Joomla.Site
 * @subpackage  Layout
 *
 * @copyright   (C) 2014 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;


/**
 * Layout personnalisé pour un champ du formulaire d'adhésion
 * /components/com_gdadhesions/layouts/form/renderfieldadhesion.php
 *
 */

use Joomla\CMS\Factory;

/**
 * @var array $displayData Les données du formulaire
 */
extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   array   $options      Optional parameters
 * @var   string  $id           The id of the input this label is for
 * @var   string  $name         The name of the input this label is for
 * @var   string  $label        The html code for the label
 * @var   string  $input        The input field html code
 * @var   string  $description  An optional description to use as in–line help text
 * @var   string  $descClass    The class name to use for the description
 */

if (!empty($options['showonEnabled'])) {
    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = Factory::getApplication();
    /** @var \Joomla\CMS\Document\HtmlDocument $doc */
    $doc = $app->getDocument();
    $wa = $doc->getWebAssetManager();
    $wa->useScript('showon');
}

$class           = empty($options['class']) ? '' : ' ' . $options['class'];
$rel             = empty($options['rel']) ? '' : ' ' . $options['rel'];
$id              = ($id ?? $name) . '-desc';
$hideLabel       = !empty($options['hiddenLabel']);
$hideDescription = empty($options['hiddenDescription']) ? false : $options['hiddenDescription'];
$descClass       = ($options['descClass'] ?? '') ?: (!empty($options['inlineHelp']) ? 'hide-aware-inline-help d-none' : '');

// $input = $displayData['field']->input;

if (!empty($parentclass)) {
    $class .= ' ' . $parentclass;
}

?>
<div class="control-group<?php echo $class; ?>"<?php echo $rel; ?>>
    <?php if ($hideLabel) : ?>
        <div class="visually-hidden"><?php echo $label; ?></div>
    <?php else : ?>
        <div class="control-label"><?php echo $label; ?></div>
    <?php endif; ?>
    <div class="controls">
        <?php echo $input; ?>
        <?php if (!$hideDescription && !empty($description)) : ?>
            <div id="<?php echo $id; ?>" class="<?php echo $descClass ?>">
                <small class="form-text">
                    <?php echo $description; ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>
