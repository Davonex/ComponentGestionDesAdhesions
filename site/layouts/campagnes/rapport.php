<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['items']       : array de lignes (cf. CampagnesModel::getRapport())
 * - $displayData['form']        : données jform_campagne (titre, event_helloasso)
 * - $displayData['hasHelloAsso'] : true si la campagne encaisse via HelloAsso (rapport à venir)
 */

$items        = $displayData['items'];
$form         = $displayData['form'];
$hasHelloAsso = $displayData['hasHelloAsso'];

?>

<div class="modal-header">
    <?php if ($hasHelloAsso) : ?>
        <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '20', 'height' => '20']); ?>
    <?php endif; ?>
    <h3 class="modal-title" modal-title><?= htmlspecialchars($form['titre']) ?></h3>
    <button type="button" id="closeModalForm" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div><!-- .modal-header -->

<div class="modal-body" id="modalRapportBody">
    <div id="rapportContent">
        <?php if ($hasHelloAsso) : ?>
            <p class="text-muted mb-0"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_COMINGSOON') ?></p>
        <?php elseif (empty($items)) : ?>
            <p class="text-muted mb-0"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_AUCUN_INSCRIT') ?></p>
        <?php else : ?>
            <p><?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', count($items)) ?></p>
            <table id="rapportTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ADHERENT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_NIVEAU') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ROLE') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_STATUT') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['nom_complet']) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($item['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($item['niveau']) ?></td>
                            <td><?= htmlspecialchars($item['role']) ?></td>
                            <td><?= $item['date_reservation'] ? HTMLHelper::_('date', $item['date_reservation'], 'd M Y H:i') : '—' ?></td>
                            <td>
                                <?php if ($item['en_attente']) : ?>
                                    <span class="badge bg-warning text-dark">
                                        <?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_LISTE_ATTENTE', $item['rang_attente']) ?>
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-success"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_CONFIRMEE') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div><!-- .modal-body -->

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('JCLOSE') ?></button>
</div><!-- .modal-footer -->
