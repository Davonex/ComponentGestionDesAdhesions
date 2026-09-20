<?php

/**
 * Layout : encart "Boutique" du dashboard adhérent (campagnes de nature Boutique).
 *
 * Boutique n'a pas de mécanisme de réservation (voir CampagnesModel::getHelloAssoFormTypeParNature()) :
 * simple vitrine des articles en vente sur le formulaire "Shop" HelloAsso lié, avec un lien pour
 * aller acheter sur HelloAsso - pas de statut d'inscription à afficher, contrairement à
 * dash_campagnes_reservables.php.
 *
 * @var array $displayData
 * - $displayData['campagnes'] : AccueilModel::getCampagnesBoutique(), triées par date de fin croissante
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;

$campagnes = $displayData['campagnes'] ?? [];

// Encart entièrement masqué s'il n'y a aucune campagne Boutique ouverte : un bloc vide n'apporte
// rien au dashboard, même motif que dash_campagnes_reservables.php.
if (empty($campagnes)) {
    return;
}

// Image de repli, à la fois pour un article sans photo et pour une photo dont l'URL HelloAsso ne
// charge pas (observé sur le sandbox) : PHP ne peut pas vérifier bon marché qu'une URL distante
// charge réellement, le repli se fait donc côté client via l'attribut "onerror" de chaque <img>.
$defaultItemImage = FileHelper::getDefaultItemImageSrc();

// Badge de disponibilité d'un article, cf. AccueilModel::resoudreArticlesBoutique() - estimation
// par date de vente, pas un stock réel (l'API HelloAsso n'expose aucun compteur de stock).
$badgeDisponibilite = static function (string $disponibilite): array {
    if ($disponibilite === 'bientot') {
        return ['bg-warning text-dark', 'fa-clock', Text::_('COM_GDA_ACCUEIL_BOUTIQUE_BIENTOT')];
    }

    if ($disponibilite === 'termine') {
        return ['bg-secondary', 'fa-ban', Text::_('COM_GDA_ACCUEIL_BOUTIQUE_TERMINE')];
    }

    return ['bg-success', 'fa-circle-check', Text::_('COM_GDA_ACCUEIL_BOUTIQUE_DISPONIBLE')];
};
?>

<div class="card bg-gda-white" id="gda-boutique-card">

    <div class="card-header d-flex align-items-center">
        <button class="btn btn-sm p-0 me-2 toggle-card"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#boutiqueCard"
            aria-expanded="true">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        <i class="fa-solid fa-bag-shopping me-2" aria-hidden="true"></i><?= Text::_('COM_GDA_ACCUEIL_BOUTIQUE_TITRE') ?>

        <button type="button" id="btnRefreshBoutique" class="btn btn-sm btn-outline-light ms-auto"
            title="<?= $this->escape(Text::_('COM_GDA_ACCUEIL_BOUTIQUE_RAFRAICHIR')) ?>">
            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        </button>
    </div>

    <div class="collapse show" id="boutiqueCard">
        <div class="card-body">
            <?php foreach ($campagnes as $campagne) : ?>
                <div class="mb-2">
                    <span class="fw-bold"><?= $this->escape((string) $campagne->titre) ?></span>
                    <div class="text-muted small">
                        <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                        <?= Text::sprintf('COM_GDA_RESERVATION_JUSQUAU', HTMLHelper::_('date', $campagne->date_fin, 'd M Y')) ?>
                    </div>
                </div>

                <?php if (empty($campagne->articles)) : ?>
                    <p class="text-muted small mb-3"><?= Text::_('COM_GDA_ACCUEIL_BOUTIQUE_AUCUN_ARTICLE') ?></p>
                <?php else : ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($campagne->articles as $article) : ?>
                            <?php [$badgeClass, $badgeIcon, $badgeLabel] = $badgeDisponibilite($article['disponibilite']); ?>
                            <?php $photoSrc = !empty($article['photoUrl']) ? (string) $article['photoUrl'] : $defaultItemImage; ?>
                            <div class="gda-boutique-article text-center">
                                <?php // onerror bascule sur l'image par défaut si l'URL HelloAsso ne charge pas (sandbox) ;
                                // "this.onerror=null" évite une boucle si default_item.png lui-même venait à manquer. ?>
                                <img src="<?= $this->escape($photoSrc) ?>"
                                    alt="<?= $this->escape((string) $article['label']) ?>"
                                    class="gda-boutique-article-photo" loading="lazy"
                                    onerror="this.onerror=null;this.src='<?= $this->escape(addslashes($defaultItemImage)) ?>';">

                                <div class="small fw-semibold text-truncate" title="<?= $this->escape((string) $article['label']) ?>">
                                    <?= $this->escape((string) $article['label']) ?>
                                </div>
                                <div class="small text-muted"><?= $this->escape((string) $article['prix']) ?></div>
                                <?php // Quantité affichée seulement quand un maximum est configuré côté HelloAsso
                                // (article['quantite'] !== null, cf. AccueilModel::resoudreArticlesBoutique()) et
                                // qu'il en reste : à 0, le badge "Vente terminée" suffit, pas besoin de "Il reste 0". ?>
                                <?php if ($article['quantite'] !== null && $article['quantite'] > 0) : ?>
                                    <div class="small text-muted"><?= Text::sprintf('COM_GDA_ACCUEIL_BOUTIQUE_QUANTITE_RESTANTE', $article['quantite']) ?></div>
                                <?php endif; ?>
                                <span class="badge <?= $badgeClass ?>" title="<?= $this->escape($badgeLabel) ?>">
                                    <i class="fa-solid <?= $badgeIcon ?>" aria-hidden="true"></i>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                            <!-- BOUTON ACHETER -->
                <?php if (!empty($campagne->url_boutique)) : ?>
                    <div class="d-flex justify-content-end mb-3">
                        <a href="<?= $this->escape((string) $campagne->url_boutique) ?>"
                            class="btn btn-sm btn-success" target="_blank" rel="noopener">
                            <i class="fa-solid fa-cart-shopping me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_ACCUEIL_BOUTIQUE_ACHETER') ?>
                             <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '16', 'height' => '16']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

</div>
