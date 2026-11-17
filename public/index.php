<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$categories = db()->query(
    'SELECT id, name
     FROM categories
     ORDER BY display_order, name'
)->fetchAll();

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
     WHERE p.is_featured = 1 OR p.is_on_promotion = 1
     ORDER BY p.is_featured DESC, p.is_on_promotion DESC, p.created_at DESC
     LIMIT 8'
)->fetchAll();
$heroSlides = array_slice($featuredProducts, 0, 4);

define('CATALOGUE_PATH', 'public/catalogue.php');

$pageTitle = 'Accueil';
$searchQuery = '';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="hero storefront-hero">
    <div class="container hero-content">
        <p class="eyebrow"><?= e(SITE_SLOGAN) ?></p>
        <h1>Tout pour construire et mieux vivre.</h1>
        <p class="hero-description">
            Outillage, maison, mobilier et décoration : découvre les produits Berluxe et commande simplement.
        </p>
        <form class="hero-search" method="get" action="<?= e(site_url(CATALOGUE_PATH)) ?>" role="search">
            <label class="visually-hidden" for="home-search">Rechercher un produit</label>
            <input id="home-search" type="search" name="q" maxlength="120" placeholder="Que recherches-tu ?">
            <button class="button button-primary" type="submit">Rechercher</button>
        </form>
        <div class="trust-points">
            <span>Paiement à la livraison</span>
            <span>Livraison à Abidjan et hors Abidjan</span>
        </div>
    </div>
</section>

<?php if ($heroSlides !== []): ?>
    <section class="home-carousel-section" aria-label="Sélection de produits">
        <div class="container">
            <section class="product-carousel" data-carousel aria-label="Produits à découvrir">
                <div class="carousel-slides">
                    <?php foreach ($heroSlides as $index => $product): ?>
                        <article
                            class="carousel-slide"
                            data-carousel-slide
                            aria-roledescription="diapositive"
                            aria-label="Diapositive <?= $index + 1 ?> sur <?= count($heroSlides) ?>"
                            <?= $index === 0 ? '' : 'hidden' ?>
                        >
                            <a class="carousel-slide-link" href="<?= e(site_url('public/produit.php?id=' . (int) $product['id'])) ?>">
                                <div class="carousel-slide-visual">
                                    <?php if (!empty($product['cover_file_name'])): ?>
                                        <img
                                            src="<?= e(site_url('uploads/products/' . rawurlencode((string) $product['cover_file_name']))) ?>"
                                            alt=""
                                            <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                                        >
                                    <?php else: ?>
                                        <div class="product-image-placeholder" aria-hidden="true">Photo à venir</div>
                                    <?php endif; ?>
                                </div>
                                <div class="carousel-slide-copy">
                                    <p class="eyebrow"><?= e($product['category_name'] ?? 'Sélection Berluxe') ?></p>
                                    <h2><?= e($product['name']) ?></h2>
                                    <p class="carousel-slide-price"><?= e(format_price($product['display_price'])) ?></p>
                                    <span class="carousel-slide-cta">Découvrir le produit <span aria-hidden="true">→</span></span>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($heroSlides) > 1): ?>
                    <div class="carousel-controls" aria-label="Commandes du carrousel">
                        <button class="carousel-arrow" type="button" aria-label="Diapositive précédente" data-carousel-prev>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                                <path d="m15 18-6-6 6-6"></path>
                            </svg>
                        </button>
                        <div class="carousel-dots" aria-label="Choisir une diapositive">
                            <?php foreach ($heroSlides as $index => $_product): ?>
                                <button
                                    class="carousel-dot"
                                    type="button"
                                    aria-label="Afficher la diapositive <?= $index + 1 ?>"
                                    aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                    data-carousel-go="<?= $index ?>"
                                ></button>
                            <?php endforeach; ?>
                        </div>
                        <span class="carousel-status" data-carousel-status aria-live="polite" aria-atomic="true">
                            1 / <?= count($heroSlides) ?>
                        </span>
                        <button class="carousel-arrow" type="button" aria-label="Diapositive suivante" data-carousel-next>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="container storefront-main">
    <section class="storefront-section" aria-labelledby="categories-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Parcourir</p>
                <h2 id="categories-title">Nos catégories</h2>
            </div>
            <a class="text-link" href="<?= e(site_url(CATALOGUE_PATH)) ?>">Voir tout le catalogue</a>
        </div>
        <?php if ($categories === []): ?>
            <p class="empty-state">Les catégories arrivent bientôt.</p>
        <?php else: ?>
            <ul class="category-links">
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a class="category-pill" href="<?= e(site_url('public/catalogue.php?category_id=' . (int) $category['id'])) ?>">
                            <?= e($category['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="storefront-section" aria-labelledby="featured-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">À découvrir</p>
                <h2 id="featured-title">Sélection Berluxe</h2>
            </div>
            <a class="text-link" href="<?= e(site_url(CATALOGUE_PATH)) ?>">Explorer le catalogue</a>
        </div>
        <?php if ($featuredProducts === []): ?>
            <p class="empty-state">Le catalogue est en préparation. Reviens bientôt découvrir nos produits.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php $showQuickAdd = true; ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <?php require_once dirname(__DIR__) . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
