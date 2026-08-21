<?php

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['membres'] : array<int, object{id_profil, civilite, nom, prenom, photo, fonction, ordre_bureau}>
 */

$membres = $displayData['membres'];
?>

<div class="gda-trombi-header text-center mb-4">
    <h2 class="fw-bold mb-2"><?= Text::_('COM_GDA_TROMBINOSCOPE_BUREAU_TITLE') ?></h2>
    <!-- <p class="gda-trombi-subtitle mb-0"><?= Text::_('COM_GDA_TROMBINOSCOPE_BUREAU_SUBTITLE') ?></p> -->
</div>

<?php if (empty($membres)) : ?>
    <p class="gda-trombi-empty text-center py-5 mb-0"><?= Text::_('COM_GDA_TROMBINOSCOPE_BUREAU_EMPTY') ?></p>
<?php else : ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($membres as $membre) : ?>
            <?php
            $pathPhoto = FileHelper::getImageSrc($membre->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
            $civilite  = trim((string) ($membre->civilite ?? ''));
            $nom       = trim((string) ($membre->nom ?? ''));
            $prenom    = trim((string) ($membre->prenom ?? ''));
            $fullName  = trim(($civilite !== '' ? $civilite : 'M.') . ' ' . $nom . ' ' . $prenom);
            // Repli quand aucune photo n'est disponible : initiales prenom + nom.
            $initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
            // Convention : plusieurs fonctions séparées par ';' (même convention que `roles` dans
            // #__gda_role_de_campagne), affichées chacune sur sa propre ligne.
            $fonctions = array_filter(array_map('trim', explode(';', (string) ($membre->fonction ?? ''))));
            ?>
            <div class="col">
                <article class="card h-100 text-center gda-trombi-card">
                    <div class="gda-trombi-photo-wrap">
                        <?php if (!empty($pathPhoto)) : ?>
                            <img
                                src="<?= $this->escape($pathPhoto) ?>"
                                alt="<?= $this->escape($fullName) ?>"
                                loading="lazy"
                                class="gda-trombi-photo">
                        <?php else : ?>
                            <div class="gda-trombi-photo--empty" role="img"
                                 aria-label="<?= $this->escape($fullName) ?>">
                                <?= $this->escape($initiales !== '' ? $initiales : '?') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body d-flex flex-column justify-content-center px-3 py-3">
                        <div class="gda-trombi-name"><?= $this->escape($fullName) ?></div>
                        <?php foreach ($fonctions as $fonction) : ?>
                            <div class="gda-trombi-role fst-italic mt-1"><?= $this->escape($fonction) ?></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
