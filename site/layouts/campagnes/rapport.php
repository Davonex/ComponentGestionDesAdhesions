<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Service\ReservationService;

/**
 * @var array $displayData
 * - $displayData['items']       : array de lignes, format dépendant de 'rapportType' (et de
 *   'isBoutique' quand rapportType === 'helloasso') :
 *     - 'reservation'                : cf. CampagnesModel::getRapport()
 *     - 'helloasso', !isBoutique     : cf. CampagnesModel::getRapportHelloAsso() (Event)
 *     - 'helloasso', isBoutique      : cf. CampagnesModel::getRapportHelloAssoBoutique() (Shop)
 * - $displayData['form']         : données jform_campagne (titre, event_helloasso)
 * - $displayData['rapportType']  : 'reservation' | 'helloasso'
 * - $displayData['isBoutique']   : true si la campagne est de nature Boutique
 * - $displayData['hasHelloAsso'] : true si la campagne a un event HelloAsso lié
 */

$items        = $displayData['items'];
$form         = $displayData['form'];
$rapportType  = $displayData['rapportType'] ?? 'reservation';
$isBoutique   = $displayData['isBoutique'] ?? false;
$hasHelloAsso = $displayData['hasHelloAsso'];
$isHelloAsso  = $rapportType === 'helloasso';

$statutBadges = [
    ReservationService::STATUT_ATTENTE   => ['bg-warning text-dark', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE'],
    ReservationService::STATUT_REFUSEE   => ['bg-danger', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_REFUSEE'],
    ReservationService::STATUT_CONFIRMEE => ['bg-success', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_CONFIRMEE'],
    ReservationService::STATUT_ANNULEE   => ['bg-secondary', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ANNULEE'],
];

?>

<div class="modal-header">
    <?php if ($isHelloAsso) : ?>
        <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '20', 'height' => '20', 'class' => 'me-2']); ?>
    <?php endif; ?>
    <h3 class="modal-title" modal-title><?= htmlspecialchars($form['titre']) ?></h3>
    <button type="button" id="closeModalForm" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div><!-- .modal-header -->

<div class="modal-body" id="modalRapportBody">
    <div id="rapportContent">
        <?php if (empty($items)) : ?>
            <p class="text-muted mb-0">
                <?= $isHelloAsso
                    ? Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_AUCUN_PAIEMENT')
                    : Text::_('COM_GDA_CAMPAGNE_RAPPORT_AUCUN_INSCRIT'); ?>
            </p>
        <?php elseif ($isHelloAsso && $isBoutique) : ?>
            <p><?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_MSG', count($items)) ?></p>
            <table id="rapportTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ACHETEUR') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_EMAIL') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_PRODUIT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_MONTANT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?= htmlspecialchars($item['Acheteur']) ?></td>
                            <td><?= htmlspecialchars($item['EmailAcheteur']) ?></td>
                            <td><?= htmlspecialchars($item['Produit']) ?></td>
                            <td><?= htmlspecialchars($item['Montant']) ?></td>
                            <?php // Déjà formatée en chaîne française par ToolsHelper::isoToUtcFormatted() : ne pas repasser par HTMLHelper::_('date', ...), qui attend une date SQL/parseable. ?>
                            <td><?= $item['Date'] !== '' ? htmlspecialchars($item['Date']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($isHelloAsso) : ?>
            <p><?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_MSG', count($items)) ?></p>
            <table id="rapportTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ADHERENT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_PAYEUR') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_EMAIL') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?= htmlspecialchars($item['User']) ?></td>
                            <td><?= htmlspecialchars($item['UserPaiment']) ?></td>
                            <td><?= htmlspecialchars($item['EmailPaiment']) ?></td>
                            <?php // Déjà formatée en chaîne française par ToolsHelper::isoToUtcFormatted() : ne pas repasser par HTMLHelper::_('date', ...), qui attend une date SQL/parseable. ?>
                            <td><?= $item['Date'] !== '' ? htmlspecialchars($item['Date']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', count(array_filter($items, static fn ($ligne) => ($ligne['statut'] ?? '') !== ReservationService::STATUT_ANNULEE))) ?></p>
            <table id="rapportTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ADHERENT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_NIVEAU') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_ROLE') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_STATUT') ?></th>
                        <th><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_COMMENTAIRE') ?></th>
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
                                <?php // Mêmes libellés/couleurs que la colonne Statut de l'onglet Suivi des inscriptions
                                // (groupes/detail.php) : attente / refusée / acceptée, décidées à la main. ?>
                                <?php [$statutClass, $statutKey] = $statutBadges[$item['statut']] ?? $statutBadges[ReservationService::STATUT_ATTENTE]; ?>
                                <span class="badge <?= $statutClass ?>"><?= Text::_($statutKey) ?></span>
                            </td>
                            <td class="small"><?= ($item['commentaire'] ?? '') !== '' ? nl2br($this->escape($item['commentaire'])) : '—' ?></td>
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
