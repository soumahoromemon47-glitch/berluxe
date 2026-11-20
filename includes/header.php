<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart.php';

$logoUrl = logo_url();
$cartCount = cart_total_quantity();
$cartCountLabel = number_format($cartCount, 0, ',', ' ');
$cartAriaLabel = $cartCount === 0 ? 'Panier vide' : 'Panier, ' . $cartCountLabel;
if ($cartCount === 1) {
    $cartAriaLabel .= ' article';
} elseif ($cartCount > 1) {
    $cartAriaLabel .= ' articles';
}
$styleVersion = filemtime(dirname(__DIR__) . '/assets/css/style.css');
$documentTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' | ' . SITE_NAME
    : SITE_NAME;

$metaDescription = isset($metaDescription) && $metaDescription !== ''
    ? $metaDescription
    : SITE_SLOGAN . ' — ' . SITE_NAME . ', boutique en ligne à Abidjan. Outillage, maison, mobilier et décoration. Paiement à la livraison.';

$faviconUrl = $logoUrl;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FAF7F2">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <title><?= e($documentTitle) ?></title>
    <?php if ($faviconUrl !== null): ?>
        <link rel="icon" href="<?= e($faviconUrl) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('assets/css/style.css?v=' . $styleVersion)) ?>">
</head>
<body>
    <div class="top-announcement-bar">
        <div class="top-announcement-track">
            <span>🚚 Livraison à Abidjan et hors Abidjan</span>
            <span>💳 Paiement à la livraison</span>
            <span>✨ Construire mieux, vivre mieux</span>
            <span aria-hidden="true">🚚 Livraison à Abidjan et hors Abidjan</span>
            <span aria-hidden="true">💳 Paiement à la livraison</span>
            <span aria-hidden="true">✨ Construire mieux, vivre mieux</span>
            <span aria-hidden="true">🚚 Livraison à Abidjan et hors Abidjan</span>
            <span aria-hidden="true">💳 Paiement à la livraison</span>
            <span aria-hidden="true">✨ Construire mieux, vivre mieux</span>
        </div>
    </div>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="<?= e(site_url('public/index.php')) ?>" aria-label="<?= e(SITE_NAME) ?> — accueil">
                <?php if ($logoUrl !== null): ?>
                    <img class="brand-logo" src="<?= e($logoUrl) ?>" alt="<?= e(SITE_NAME) ?>">
                <?php else: ?>
                    <span class="brand-name"><?= e(SITE_NAME) ?></span>
                <?php endif; ?>
            </a>

            <nav class="site-nav" aria-label="Navigation principale">
                <a href="<?= e(site_url('public/index.php')) ?>">Accueil</a>
                <a href="<?= e(site_url(CATALOGUE_PATH)) ?>">Catalogue</a>
            </nav>

            <div class="header-actions">
                <button class="site-search-toggle" type="button" aria-label="Ouvrir la recherche" aria-expanded="false" aria-controls="header-search-form" data-search-toggle>
                    <svg class="site-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <circle cx="10.8" cy="10.8" r="6.8"></circle>
                        <path d="m16 16 4.5 4.5"></path>
                    </svg>
                </button>
                <form id="header-search-form" class="site-search site-search-popover" method="get" action="<?= e(site_url(CATALOGUE_PATH)) ?>" role="search" data-search-popover>
                    <label class="visually-hidden" for="site-search-input">Rechercher un produit</label>
                    <input id="site-search-input" type="search" name="q" maxlength="120" value="<?= e(isset($searchQuery) && is_string($searchQuery) ? $searchQuery : '') ?>" placeholder="Rechercher...">
                    <button class="button button-primary" type="submit">OK</button>
                </form>
                <a class="site-cart-link" href="<?= e(site_url(PANIER_PATH)) ?>" aria-label="<?= e($cartAriaLabel) ?>" title="Panier" data-cart-link>
                    <svg class="site-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M3 3h2l2.4 11.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 7H6"></path>
                        <circle cx="10" cy="20" r="1"></circle>
                        <circle cx="18" cy="20" r="1"></circle>
                    </svg>
                    <span class="site-cart-count" data-cart-count-badge aria-hidden="true"<?= $cartCount === 0 ? ' hidden' : '' ?>>
                        <?= $cartCount > 99 ? '99+' : e($cartCount) ?>
                    </span>
                </a>
            </div>
        </div>

    </header>
    <main>
