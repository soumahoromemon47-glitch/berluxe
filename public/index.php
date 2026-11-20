<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// ---------------------------------------------------------------------------
// Requête 1 : catégories (pour la section « Nos catégories »)
// ---------------------------------------------------------------------------
$categories = db()->query(
    'SELECT id, name
     FROM categories
     ORDER BY display_order, name'
)->fetchAll();

// ---------------------------------------------------------------------------
// Requête 2 : produits VEDETTE uniquement (pour le carrousel)
// ---------------------------------------------------------------------------
$featuredProducts = db()->query(
    'SELECT p.id, p.name, p.retail_price, p.wholesale_price,
            p.wholesale_min_quantity, p.promotion_price,
            p.is_featured, p.is_on_promotion, p.is_unavailable,
            c.name AS category_name,
            CASE
                WHEN p.is_on_promotion = 1 AND p.promotion_price IS NOT NULL
                    THEN p.promotion_price
                ELSE p.retail_price
            END AS display_price,
            (
                SELECT image.file_name
                FROM product_images AS image
                WHERE image.product_id = p.id
                ORDER BY image.display_order, image.id
                LIMIT 1
            ) AS cover_file_name
     FROM products AS p
     LEFT JOIN categories AS c ON c.id = p.category_id
     WHERE p.is_featured = 1
     ORDER BY p.created_at DESC
     LIMIT 4'
)->fetchAll();

// ---------------------------------------------------------------------------
// Requête 3 : produits en PROMOTION uniquement (hors vedettes déjà affichées)
// ---------------------------------------------------------------------------
$promotionProducts = db()->query(
    'SELECT p.id, p.name, p.retail_price, p.wholesale_price,
            p.wholesale_min_quantity, p.promotion_price,
            p.is_featured, p.is_on_promotion, p.is_unavailable,
            c.name AS category_name,
            p.promotion_price AS display_price,
            (
                SELECT image.file_name
                FROM product_images AS image
                WHERE image.product_id = p.id
                ORDER BY image.display_order, image.id
                LIMIT 1
            ) AS cover_file_name
     FROM products AS p
     LEFT JOIN categories AS c ON c.id = p.category_id
     WHERE p.is_on_promotion = 1
       AND p.promotion_price IS NOT NULL
       AND p.is_featured = 0
     ORDER BY p.created_at DESC
     LIMIT 4'
)->fetchAll();

// ---------------------------------------------------------------------------
// Requête 4 : derniers ajouts (grille « Sélection Berluxe »)
// On exclut les IDs déjà affichés dans le carrousel et les promos.
// ---------------------------------------------------------------------------
$excludedIds = array_merge(
    array_column($featuredProducts, 'id'),
    array_column($promotionProducts, 'id')
);

$excludedSql = '';
$excludedParams = [];
if ($excludedIds !== []) {
    $placeholders = implode(', ', array_fill(0, count($excludedIds), '?'));
    $excludedSql = ' AND p.id NOT IN (' . $placeholders . ')';
    $excludedParams = array_map('intval', $excludedIds);
}

$latestProductsStmt = db()->prepare(
    'SELECT p.id, p.name, p.retail_price, p.wholesale_price,
            p.wholesale_min_quantity, p.promotion_price,
            p.is_featured, p.is_on_promotion, p.is_unavailable,
            c.name AS category_name,
            CASE
                WHEN p.is_on_promotion = 1 AND p.promotion_price IS NOT NULL
                    THEN p.promotion_price
                ELSE p.retail_price
            END AS display_price,
            (
                SELECT image.file_name
                FROM product_images AS image
                WHERE image.product_id = p.id
                ORDER BY image.display_order, image.id
                LIMIT 1
            ) AS cover_file_name
     FROM products AS p
     LEFT JOIN categories AS c ON c.id = p.category_id
     WHERE 1 = 1' . $excludedSql . '
     ORDER BY p.created_at DESC
     LIMIT 8'
);
$latestProductsStmt->execute($excludedParams);
$latestProducts = $latestProductsStmt->fetchAll();

// ---------------------------------------------------------------------------
// Rendu
// ---------------------------------------------------------------------------
$pageTitle = 'Accueil';
$searchQuery = '';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="hero storefront-hero">
    <div class="container hero-content">
        <p class="eyebrow">La sélection Berluxe</p>
        <h1>Construire mieux, vivre mieux</h1>
        <p class="hero-description">
            Outillage, maison, mobilier et décoration : découvre les produits Berluxe et commande simplement.
        </p>

        <form class="hero-search-large" method="get" action="<?= e(site_url(CATALOGUE_PATH)) ?>" role="search">
            <input id="home-search" type="search" name="q" maxlength="120" placeholder="Que recherches-tu ? (ex: perceuse, canapé, lampe...)">
            <button class="button button-primary" type="submit">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            </button>
        </form>

        <div class="hero-cta-links">
            <a href="<?= e(site_url(CATALOGUE_PATH)) ?>" class="button button-primary">Voir le catalogue</a>
            <a href="<?= e(site_url(CATALOGUE_PATH . '?promotion=1')) ?>" class="button button-secondary">Voir les promos</a>
        </div>
    </div>
</section>


<div class="container storefront-main">
    <section class="trust-banner-section" aria-label="Les avantages Berluxe">
        <div class="trust-banner-grid">
            <div class="trust-banner-item">
                <span class="trust-banner-icon" aria-hidden="true">🚚</span>
                <div>
                    <strong>Livraison rapide</strong>
                    <p>Abidjan et hors Abidjan</p>
                </div>
            </div>
            <div class="trust-banner-item">
                <span class="trust-banner-icon" aria-hidden="true">💳</span>
                <div>
                    <strong>Paiement à la livraison</strong>
                    <p>Aucun paiement en ligne</p>
                </div>
            </div>
            <div class="trust-banner-item">
                <span class="trust-banner-icon" aria-hidden="true">✅</span>
                <div>
                    <strong>Qualité garantie</strong>
                    <p>Produits vérifiés</p>
                </div>
            </div>
            <div class="trust-banner-item">
                <span class="trust-banner-icon" aria-hidden="true">📞</span>
                <div>
                    <strong>Service client</strong>
                    <p>Roxane vous répond</p>
                </div>
            </div>
        </div>
    </section>

    <section class="storefront-section" aria-labelledby="categories-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Parcourir</p>
                <h2 id="categories-title">Nos catégories</h2>
            </div>
            <a class="button button-secondary btn-section-link" href="<?= e(site_url(CATALOGUE_PATH)) ?>" aria-label="Voir tout le catalogue">Voir tout</a>
        </div>
        <?php if ($categories === []): ?>
            <p class="empty-state">Les catégories arrivent bientôt.</p>
        <?php else: ?>
            <ul class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryIcons = [
                        'Outillage' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
                        'Mobilier' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"></path><path d="M4 10V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3"></path><path d="M2 10h20"></path></svg>',
                        'Maison et cuisine' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
                        'Décoration' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
                        'Électronique' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                    ];
                    $iconSvg = $categoryIcons[$category['name']] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';
                    ?>
                    <li class="category-item">
                        <a class="category-link" href="<?= e(site_url(CATALOGUE_PATH . '?category_id=' . (int) $category['id'])) ?>" aria-label="<?= e($category['name']) ?>">
                            <span class="category-icon"><?= $iconSvg ?></span>
                            <span class="category-name"><?= e($category['name']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Bannière 2 : Promotions -->
<section class="promo-banner promo-banner-dark">
    <div class="container promo-banner-content">
        <div class="promo-banner-text">
            <p class="eyebrow">Offres limitées</p>
            <h2>Jusqu'à -30% sur une sélection.</h2>
            <p>Profitez de nos meilleures offres avant qu'elles ne disparaissent.</p>
        </div>
        <a href="<?= e(site_url(CATALOGUE_PATH . '?promotion=1')) ?>" class="button button-secondary">Voir les promos</a>
    </div>
</section>

    <?php if ($featuredProducts !== []): ?>
    <section class="storefront-section" aria-labelledby="featured-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">À découvrir</p>
                <h2 id="featured-title">Sélection Berluxe</h2>
            </div>
            <a class="button button-secondary btn-section-link" href="<?= e(site_url(CATALOGUE_PATH)) ?>">Voir tout</a>
        </div>
        <div class="product-grid">
            <?php $showQuickAdd = true; ?>
            <?php foreach ($featuredProducts as $product): ?>
                <?php require dirname(__DIR__) . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <?php if ($promotionProducts !== []): ?>
        <section class="storefront-section" aria-labelledby="promotions-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Bonnes affaires</p>
                    <h2 id="promotions-title">Promotions du moment</h2>
                </div>
                <a class="button button-secondary btn-section-link" href="<?= e(site_url(CATALOGUE_PATH . '?promotion=1')) ?>" aria-label="Voir toutes les promotions">Voir tout</a>
            </div>
            <div class="product-grid">
                <?php $showQuickAdd = true; ?>
                <?php foreach ($promotionProducts as $product): ?>
                    <?php require dirname(__DIR__) . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Bannière 3 : Service client -->
<section class="promo-banner promo-banner-light">
    <div class="container promo-banner-content">
        <div class="promo-banner-text">
            <p class="eyebrow">Une question ?</p>
            <h2>Roxane est là pour vous aider.</h2>
            <p>Contactez-nous sur WhatsApp pour toute question sur nos produits.</p>
        </div>
        <a href="https://wa.me/2250102030405" target="_blank" rel="noopener" class="button button-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp
        </a>
    </div>
</section>

    <section class="storefront-section" aria-labelledby="latest-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Nouveautés</p>
                <h2 id="latest-title">Derniers ajouts</h2>
            </div>
            <a class="button button-secondary btn-section-link" href="<?= e(site_url(CATALOGUE_PATH)) ?>" aria-label="Explorer le catalogue">Voir tout</a>
        </div>
        <?php if ($latestProducts === []): ?>
            <p class="empty-state">Le catalogue est en préparation. Reviens bientôt découvrir nos produits.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php $showQuickAdd = true; ?>
                <?php foreach ($latestProducts as $product): ?>
                    <?php require dirname(__DIR__) . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
