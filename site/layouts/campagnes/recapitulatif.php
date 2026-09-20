<?php

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Model\CampagnesModel;
use NCB\Component\Gda\Site\Service\ReservationService;

/**
 * Récapitulatif des réservations Formation : adhérents en lignes, campagnes en colonnes, dernier
 * statut au croisement — voir CampagnesModel::getRecapitulatifFormations().
 *
 * @var array $displayData
 * - $displayData['campagnes'] : object[] (id_campagne, titre, date_evenement, active)
 * - $displayData['adherents'] : object[] (id_profil, civilite, nom, prenom, photo, statuts[id_campagne => statut])
 */

defined('_JEXEC') or die;

$campagnes = $displayData['campagnes'] ?? [];
$adherents = $displayData['adherents'] ?? [];

if (empty($adherents)) {
    echo '<p class="text-muted">' . Text::_('COM_GDA_CAMPAGNES_RECAP_VIDE') . '</p>';

    return;
}

$statutBadges = [
    ReservationService::STATUT_ATTENTE   => ['bg-warning text-dark', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE'],
    ReservationService::STATUT_REFUSEE   => ['bg-danger', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_REFUSEE'],
    ReservationService::STATUT_CONFIRMEE => ['bg-success', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_CONFIRMEE'],
    ReservationService::STATUT_ANNULEE   => ['bg-secondary', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ANNULEE'],
];

// Sous-types réellement présents, dans l'ordre de la liste fermée du modèle.
$sousTypes = array_values(array_filter(
    CampagnesModel::SOUS_TYPES_FORMATION,
    static fn ($sousType) => in_array($sousType, array_column($campagnes, 'sous_type'), true)
));

// Rôles réellement utilisés (texte libre, renommable par le Bureau) : la liste du filtre vient des
// places existantes et non de la configuration actuelle des campagnes.
$roles = [];
foreach ($adherents as $adherent) {
    foreach ($adherent->places as $placesCampagne) {
        foreach ($placesCampagne as [$role]) {
            $roles[$role] = true;
        }
    }
}
$roles = array_keys($roles);
sort($roles, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3" id="campagneRecapFilters"
    data-badges="<?= $this->escape(json_encode(array_map(static fn ($badge) => [$badge[0], Text::_($badge[1])], $statutBadges))) ?>">
    <?php if (!empty($sousTypes)) : ?>
        <div class="d-flex align-items-center gap-2">
            <label for="campagneRecapFilterSousType" class="form-label mb-0 text-nowrap"><?= Text::_('COM_GDA_CAMPAGNE_F_SOUS_TYPE') ?></label>
            <select class="form-select w-auto" id="campagneRecapFilterSousType">
                <option value=""><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_FILTRE_TOUS') ?></option>
                <?php foreach ($sousTypes as $sousType) : ?>
                    <option value="<?= $this->escape($sousType) ?>"><?= $this->escape(Text::_('COM_GDA_CAMPAGNE_SOUS_TYPE_' . strtoupper($sousType))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <?php if (!empty($roles)) : ?>
        <div class="d-flex align-items-center gap-2">
            <label for="campagneRecapFilterRole" class="form-label mb-0 text-nowrap"><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_FILTRE_ROLE') ?></label>
            <div style="min-width: 16rem;">
                <select id="campagneRecapFilterRole" multiple placeholder="<?= $this->escape(Text::_('COM_GDA_CAMPAGNES_SUIVI_FILTRE_TOUS')) ?>">
                    <?php foreach ($roles as $role) : ?>
                        <option value="<?= $this->escape($role) ?>"><?= $this->escape($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle gda-suivi-compact">
        <thead>
            <tr>
                <th><?= Text::_('COM_GDA_CAMPAGNES_RECAP_ADHERENT') ?></th>
                <?php foreach ($campagnes as $campagne) : ?>
                    <th class="text-center" data-campagne="<?= (int) $campagne->id_campagne ?>" data-sous-type="<?= $this->escape((string) $campagne->sous_type) ?>">
                        <?= $this->escape($campagne->titre) ?>
                        <?php if (!empty($campagne->date_evenement) && $campagne->date_evenement !== '0000-00-00 00:00:00') : ?>
                            <br><small class="text-muted fw-normal"><?= $this->escape(date('d/m/Y', strtotime($campagne->date_evenement))) ?></small>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($adherents as $adherent) : ?>
                <?php
                $pathPhoto = FileHelper::getImageSrc($adherent->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
                $civilite = trim($adherent->civilite);
                $fullName = trim(($civilite !== '' ? $civilite : 'M.') . ' ' . $adherent->nom . ' ' . $adherent->prenom);
                ?>
                <tr data-recap-adherent>
                    <td class="text-nowrap">
                        <?php if (!empty($pathPhoto)) : ?>
                            <img src="<?= $this->escape($pathPhoto) ?>" alt="" width="40" height="40" loading="lazy" class="gda-preview-thumb me-2">
                        <?php endif; ?>
                        <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) $adherent->id_profil ?>"><?= $this->escape($fullName) ?></a>
                    </td>
                    <?php foreach ($campagnes as $campagne) : ?>
                        <td class="text-center" data-campagne="<?= (int) $campagne->id_campagne ?>"
                            data-places="<?= $this->escape(json_encode($adherent->places[(int) $campagne->id_campagne] ?? [])) ?>">
                            <?php $statut = $adherent->statuts[(int) $campagne->id_campagne] ?? null; ?>
                            <?php if ($statut !== null && isset($statutBadges[$statut])) : ?>
                                <span data-recap-statut class="badge <?= $statutBadges[$statut][0] ?>"><?= Text::_($statutBadges[$statut][1]) ?></span>
                            <?php else : ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
