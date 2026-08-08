<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;



/**
 * @var array $displayData
 * - $displayData['campagne'] : campagne
 * - $displayData['task']  : Controler joomla task
 */

$items = $displayData['items'];
$form  = $displayData['form'];




// $cssClass = $classes[$item->active] ?? 'campagne-default';

?>


<div class="modal-header">
    <?php
    if ($form['event_helloasso'] !== "null") {
                        echo  HTMLHelper::_('image', 'https://api.helloasso.com/v5/img/logo-ha.svg', Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '20', 'height' => '20']);
                    } 
    ?>
  <h3 class="modal-title" modal-title> <?=    htmlspecialchars($form['titre'])?> </h3>
  <button type="button" id="closeModalForm" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div><!-- .modal-header -->

<div class="modal-body" id="modalRapportBody">
  <div id="rapportContent">
    <p> il y a <?= count($items) ?> inscrits a cette campagne.</p>
    <table id="rapportTable" class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>Nom Prénom</th>
          <th>Payeur</th>
          <th>Email</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item) : ?>
        <tr>
          <td><?= htmlspecialchars($item['User'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['UserPaiment'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['EmailPaiment'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['Date'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div><!-- .modal-body -->


<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
</div><!-- .modal-footer -->



<!-- 

 const container = document.getElementById('rapportContent');

    if (!container) {
        console.debug('L\'element "#rapportContent" est introuvable');
        return;
    }

    // Efface le contenu precedent avant de reconstruire le rapport
    container.innerHTML = '';

    const rows = Array.isArray(obj) ? obj : [];

    if (rows.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Aucun inscrit trouve.</p>';
        return;
    }

    const escapeHtml = function (value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const table = document.createElement('table');
    table.className = 'table table-striped table-hover align-middle';

    table.innerHTML = `
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prenom</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            ${rows.map(function (payer) {
                return `<tr>
                    <td>${escapeHtml(payer.lastName)}</td>
                    <td>${escapeHtml(payer.firstName)}</td>
                    <td>${escapeHtml(payer.email)}</td>
                </tr>`;
            }).join('')}
        </tbody>
    `;

    container.appendChild(table); -->