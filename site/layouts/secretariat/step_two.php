<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['items'] : souscriptions validees etape 1 (caci_check = 1)
 */

/**   <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_EMAIL') ?? 'Email' ?></th>    <th>**/

$items = $displayData['items'] ?? [];
$licenceCostByCategorie = [
  'ADULTE' => CotisationService::getMontantLicence('ADULTE'),
  'JEUNE' => CotisationService::getMontantLicence('JEUNE'),
  'ENFANT' => CotisationService::getMontantLicence('ENFANT'),
];
// Memes choix que le champ "reduction" (Tarification) du formulaire d'adhesion : depuis la
// 0.9.17 la liste est construite depuis les tarifs ACTIFS de #__gda_cotisation, administres par
// le Bureau dans l'onglet "Tarification" de la vue Saisons. Desactiver un tarif la-bas le retire
// donc aussi d'ici. Les libelles sont deja traduits (valeurs, non cles de langue).
$reductionChoices = CotisationService::getOptionsReduction();


?>

<div class="card mb-3">
  <div class="card-body">
    <?php if (empty($items)) : ?>
      <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP2_EMPTY') ?? 'Aucune souscription en attente de validation de paiement.' ?></p>
    <?php else : ?>
      <table class="table table-bordered table-striped secretariat-table">
        <thead>
          <tr>
            <th class="align-middle">
              <!-- <?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ACTION') ?> -->
            </th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?></th>
            <th class="align-middle"><i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?></th>
            <th class="align-middle"><i class="fa-solid fa-file-medical me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_NAME') ?></th>

            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_VILLE')  ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_COTISATION')  ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_GROUPES') ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CATEGORIE')  ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE_AMOUNT') ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_MONTANT_COTISATION') ?></th>
            <!-- <th>id order</th> -->
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PAIEMENT') ?></th>
            <th class="align-middle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item) : ?>
            <?php
            // Montant fige au moment de la souscription : une correction de tarif par le Bureau
            // ne doit pas reecrire ce qui a ete reellement facture. Repli sur le tarif courant
            // pour les souscriptions anterieures a la 0.9.17.
            $CotisationAmount = CotisationService::getMontantFige(
              $item->cotisation_montant ?? null,
              (string) ($item->cotisation_code ?? '')
            );
            ?>
            <tr>
              <?php
              $licenceValue = trim((string) ($item->username ?? ''));
              $licencePrefix = strtoupper(substr($licenceValue, 0, 1));
              $licenceTooltip = '';
              $licenceClass = 'gda-licence-chip';

              if ($licencePrefix === 'A') {
                $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_RENEWAL_HINT');
              } elseif ($licencePrefix === 'N') {
                $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_CREATION_HINT');
                $licenceClass .= ' gda-licence-chip--warning';
              }
              ?>
              <?php $hintDevaliderCaci = Text::_('COM_GDA_SECRETARIAT_CACI_DEVALIDE_HINT'); ?>

              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-danger gda-btn-step js-unvalidate-caci"
                  data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape($hintDevaliderCaci) ?>"
                  title="<?= $this->escape($hintDevaliderCaci) ?>"
                  aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_CACI_DEVALIDE')) ?>">
                  <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                </button>
              </td>
              <?php $pathPhoto = FileHelper::getImageSrc($item->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false); ?>
              <td class="text-center">
                <?php if (!empty($pathPhoto)) : ?>
                  <a
                    href="#"
                    class="js-image-preview-thumb"
                    data-image-src="<?= $this->escape($pathPhoto) ?>"
                    data-image-alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#imagePreviewModal"
                    aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?? 'Photo') ?>">
                    <img
                      src="<?= $this->escape($pathPhoto) ?>"
                      alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                      width="64"
                      height="64"
                      loading="lazy"
                      class="gda-preview-thumb gda-preview-thumb--64">
                  </a>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($licenceValue !== '') : ?>
                  <span
                    class="<?= $this->escape($licenceClass) ?>"
                    <?php if ($licenceTooltip !== '') : ?>
                    data-bs-toggle="tooltip"
                    data-bs-title="<?= $this->escape($licenceTooltip) ?>"
                    title="<?= $this->escape($licenceTooltip) ?>"
                    <?php endif; ?>><?= $this->escape($licenceValue) ?></span>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <!-- CACI : badge date de fin de validite, lien vers le fichier dans la popup d'apercu -->
              <td class="text-center"><?= LayoutHelper::render('secretariat.caci_badge', ['item' => $item]) ?></td>
              <td>
                <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) ($item->id_profil ?? 0) ?>"><?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?></a>
              </td>
              <td>
                <?=  // Conatener CP + Ville et ajouter l'icon pour savoir si c'est au vald'yerre/de seine ou pas
                
                  ((isset($item->cotisation_code[1]) && $item->cotisation_code[1] === "1") ? '✅' : '❌') . ' ' . $this->escape(($item->code_postal ?? '') . ' ' . ($item->ville ?? ''))
                ?>
              </td>
              <!-- COTISATION (TARIFICATION) -->
              <td
                class="js-editable-cotisation"
                data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                data-current-reduction="<?= (int) ($item->reduction ?? 0) ?>"
                data-bs-toggle="tooltip"
                data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_COTISATION_EDIT_HINT')) ?>"
                style="cursor: pointer;"
                title="<?= Text::_('COM_GDA_SECRETARIAT_COTISATION_EDIT_HINT') ?>">
                <span class="cotisation-display"><?= $this->escape(CotisationService::getLabel((string) ($item->cotisation_code ?? ''))) ?></span>
                <select class="cotisation-input form-select form-select-sm d-none" aria-label="Tarification">
                  <?php $currentReduction = (int) ($item->reduction ?? 0); ?>
                  <?php foreach ($reductionChoices as $reductionValue => $reductionLabel) : ?>
                    <option value="<?= $reductionValue ?>" <?= $currentReduction === $reductionValue ? 'selected' : '' ?>><?= $this->escape($reductionLabel) ?></option>
                  <?php endforeach; ?>
                  <?php if (!array_key_exists($currentReduction, $reductionChoices)) : ?>
                    <!-- Valeur historique hors liste (ex: "Etudiant", dont le tarif C est inactif) : toujours
                         representee pour que la selection reflete la vraie valeur en base, sinon un simple
                         double-clic suivi d'un clic ailleurs sans intention de modifier ecraserait silencieusement
                         cette tarification vers le 1er choix de la liste (voir CARTOGRAPHIE §9). -->
                    <option value="<?= $currentReduction ?>" selected><?= $this->escape(CotisationService::getLibelleOption($currentReduction)) ?></option>
                  <?php endif; ?>
                </select>
              </td>
              <td class="text-start">
                <?php if (!empty($item->groupes) && is_array($item->groupes)) : ?>
                  <p class="list-recap mb-0">
                    <?php foreach ($item->groupes as $groupe) : ?>
                      <span class="btn btn-primary me-1 mb-1" data-value="<?= (int) ($groupe['id_groupe'] ?? 0) ?>">
                        <?= $this->escape((string) ($groupe['groupe_name'] ?? '')) ?>
                      </span>
                    <?php endforeach; ?>
                  </p>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <!-- CATEGORIE -->
              <td
                class="js-editable-categorie text-center"
                data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                data-current-categorie="<?= $this->escape((string) ($item->categorie ?? '')) ?>"
                data-bs-toggle="tooltip"
                data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_CATEGORIE_EDIT_HINT') ?? 'Double-clic pour editer') ?>"
                style="cursor: pointer;"
                title="<?= Text::_('COM_GDA_SECRETARIAT_CATEGORIE_EDIT_HINT') ?? 'Double-clic pour editer' ?>">
                <span class="categorie-display"><?= $this->escape((string) ($item->categorie ?? '')) ?></span>
                <select class="categorie-input form-select form-select-sm d-none" aria-label="Categorie">
                  <?php $currentCategorie = strtoupper((string) ($item->categorie ?? '')); ?>
                  <?php foreach (['ADULTE', 'JEUNE', 'ENFANT'] as $categorieOption) : ?>
                    <?php // Le montant brut reste disponible pour un calcul eventuel, mais c'est la
                          // chaine deja formatee par le serveur que le JS recopie : aucun formatage
                          // monetaire cote client (le decimal "48,50" ne survit pas a un parseInt). ?>
                    <option
                      value="<?= $categorieOption ?>"
                      data-licence-cost="<?= $this->escape(number_format($licenceCostByCategorie[$categorieOption], 2, '.', '')) ?>"
                      data-licence-cost-affiche="<?= $this->escape(CotisationService::formatMontant($licenceCostByCategorie[$categorieOption])) ?>"
                      <?= $currentCategorie === $categorieOption ? 'selected' : '' ?>><?= $categorieOption ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <!-- COUT DE LA LICENCE FFESSM -->
              <td class="js-licence-cost text-center"><?= $this->escape(CotisationService::formatMontant($licenceCostByCategorie[$currentCategorie] ?? 0)) ?></td>
              <!-- MONTANT DE LA COTISATION -->
              <td class="js-cotisation-montant text-center"><?= $this->escape(CotisationService::formatMontant($CotisationAmount)) ?></td>
              <!-- <td><?= (string) ($item->id_order) ?></td> -->
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-sm js-show-payement"
                  style="background-color:#49d38a; color:#fff; font-weight:600; border:none;"
                  data-item-id="<?= (int) ($item->id_profil) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne) ?>"
                  data-item-order="<?= (string) ($item->id_order) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_HINT') ?? 'Voir le détail du paiement HelloAsso') ?>">
                  <!-- <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="me-1" aria-hidden="true"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg> -->
                  <img width="20" height="20" src="<?= FileHelper::getHelloAssoLogoSrc() ?>" alt="COM_GDA_CAMPAGNE_HELLOASSO">
                  Payement
                </button>
              </td>
              <td><?= HTMLHelper::date($item->date_souscription, 'd/m/Y H:i') ?></td>
              <?php $hintValiderPaiement = Text::_('COM_GDA_SECRETARIAT_PAYMENT_VALIDATE_HINT'); ?>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-success gda-btn-step js-validate-payment"
                  data-item-id="<?= (int) ($item->id_profil ?? 0) ?>"
                  data-item-campagne="<?= (int) ($item->id_campagne ?? 0) ?>"
                  data-bs-toggle="tooltip"
                  data-bs-title="<?= $this->escape($hintValiderPaiement) ?>"
                  title="<?= $this->escape($hintValiderPaiement) ?>"
                  aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYMENT_VALIDATE')) ?>">
                  <i class="fa-regular fa-square-check" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>