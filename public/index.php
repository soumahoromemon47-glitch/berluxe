<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// ---------------------------------------------------------------------------
// Requête 1 : catégories (pour la section « Nos catégories »)
// ---------------------------------------------------------------------------
$categories = db()->query(
    'SELECT id, name, image
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
            EXISTS (SELECT 1 FROM product_variants AS pv WHERE pv.product_id = p.id) AS has_variants,
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
            EXISTS (SELECT 1 FROM product_variants AS pv WHERE pv.product_id = p.id) AS has_variants,
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
            EXISTS (SELECT 1 FROM product_variants AS pv WHERE pv.product_id = p.id) AS has_variants,
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
                    <?php $imageUrl = category_image_url($category['image'] ?? null); ?>
                    <li class="category-item">
                        <a class="category-link" href="<?= e(site_url(CATALOGUE_PATH . '?category_id=' . (int) $category['id'])) ?>" aria-label="<?= e($category['name']) ?>">
                            <span class="category-image-wrapper">
                                <?php if ($imageUrl !== null): ?>
                                    <img src="<?= e($imageUrl) ?>" alt="<?= e($category['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                                <?php endif; ?>
                            </span>
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
        <a href="https://wa.me/2250576596103" target="_blank" rel="noopener" class="button button-primary">
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
