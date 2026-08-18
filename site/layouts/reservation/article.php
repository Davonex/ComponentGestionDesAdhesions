<?php

/**
 * Layout : corps du popup affichant l'article lié à une campagne (injecté dans #articleModal).
 *
 * @var array $displayData
 * - $displayData['article'] : article com_content, déjà passé par onContentPrepare
 *   (voir ReservationController::showArticle())
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$article = $displayData['article'];
?>

<div class="modal-header bg-gda-header text-header">
    <h5 class="modal-title mb-0">
        <i class="fa-solid fa-newspaper me-2" aria-hidden="true"></i>
        <?= htmlspecialchars((string) $article->title, ENT_QUOTES, 'UTF-8') ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
        aria-label="<?= htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8') ?>"></button>
</div>

<div class="modal-body p-4">
    <?php
    // Contenu déjà assaini par com_content et ses plugins : on l'affiche tel quel, l'échapper
    // détruirait la mise en forme de l'article.
    echo $article->text;
    ?>
</div>
