<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/cart.php';

$rawProductId = $_GET['id'] ?? '';
$productId = is_string($rawProductId)
    ? filter_var($rawProductId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($productId === false) {
    http_response_code(400);
    $product = null;
} else {
    $productQuery = db()->prepare(
        'SELECT p.id, p.category_id, p.name, p.description, p.retail_price,
                p.promotion_price,
                p.is_featured, p.is_on_promotion, p.is_unavailable,
                c.name AS category_name
         FROM products AS p
         LEFT JOIN categories AS c ON c.id = p.category_id
         WHERE p.id = :id
         LIMIT 1'
    );
    $productQuery->execute(['id' => $productId]);
    $product = $productQuery->fetch();

    if ($product === false) {
        http_response_code(404);
        $product = null;
    }
}

$images = [];
$variants = [];
$similarProducts = [];
if ($product !== null) {
    $imageQuery = db()->prepare(
        'SELECT file_name, alt_text
         FROM product_images
         WHERE product_id = :product_id
         ORDER BY display_order, id'
    );
    $imageQuery->execute(['product_id' => $product['id']]);
    $images = $imageQuery->fetchAll();

    $variantQuery = db()->prepare(
        'SELECT id, label
         FROM product_variants
         WHERE product_id = :product_id
         ORDER BY display_order, id'
    );
    $variantQuery->execute(['product_id' => $product['id']]);
    $variants = $variantQuery->fetchAll();

    if ($product['category_id'] !== null) {
        $similarQuery = db()->prepare(
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
             WHERE p.category_id = :category_id AND p.id <> :product_id
             ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
             LIMIT 4'
        );
        $similarQuery->execute([
            'category_id' => $product['category_id'],
            'product_id' => $product['id'],
        ]);
        $similarProducts = $similarQuery->fetchAll();
    }
}

$pageTitle = $product === null ? 'Produit introuvable' : (string) $product['name'];
$searchQuery = '';
$cartCsrfToken = $product !== null ? csrf_token() : '';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container storefront-main">
    <?php if ($product === null): ?>
        <section class="storefront-heading">
            <p class="eyebrow">Catalogue Berluxe</p>
            <h1>Produit introuvable</h1>
            <p class="muted">Ce produit n’est pas disponible ou le lien est invalide.</p>
            <a class="button button-secondary" href="<?= e(site_url('public/catalogue.php')) ?>">Retour au catalogue</a>
        </section>
    <?php else: ?>
        <?php $isOnPromotion = (int) $product['is_on_promotion'] === 1 && $product['promotion_price'] !== null; ?>
        <?php $isUnavailable = (int) $product['is_unavailable'] === 1; ?>
        <p class="breadcrumb">
            <a href="<?= e(site_url('public/catalogue.php')) ?>">Catalogue</a>
            <span aria-hidden="true">/</span>
            <?php if ($product['category_name'] !== null): ?>
                <a href="<?= e(site_url('public/catalogue.php?category_id=' . (int) $product['category_id'])) ?>"><?= e($product['category_name']) ?></a>
                <span aria-hidden="true">/</span>
            <?php endif; ?>
            <span><?= e($product['name']) ?></span>
        </p>

        <section class="product-detail-layout">
            <div class="product-gallery" aria-label="Photos du produit">
                <?php if ($images === []): ?>
                    <div class="product-image-placeholder product-image-placeholder-large">Photo à venir</div>
                <?php else: ?>
                    <figure class="product-gallery-main">
                        <img src="<?= e(site_url('uploads/products/' . rawurlencode((string) $images[0]['file_name']))) ?>" alt="<?= e($images[0]['alt_text'] ?? $product['name']) ?>" data-product-main-image>
                    </figure>
                    <?php if (count($images) > 1): ?>
                        <div class="product-gallery-thumbnails" aria-label="Choisir une photo">
                            <?php foreach ($images as $index => $image): ?>
                                <button class="product-gallery-thumbnail<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-product-thumbnail data-image-src="<?= e(site_url('uploads/products/' . rawurlencode((string) $image['file_name']))) ?>" data-image-alt="<?= e($image['alt_text'] ?? $product['name']) ?>" aria-label="Afficher la photo <?= $index + 1 ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <img src="<?= e(site_url('uploads/products/' . rawurlencode((string) $image['file_name']))) ?>" alt="" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="product-detail-copy">
                <p class="eyebrow"><?= e($product['category_name'] ?? 'Catalogue Berluxe') ?></p>
                <h1><?= e($product['name']) ?></h1>
                <div class="product-badges">
                    <?php if ((int) $product['is_featured'] === 1): ?>
                        <span class="badge">Vedette</span>
                    <?php endif; ?>
                    <?php if ($isOnPromotion): ?>
                        <span class="badge badge-promo">Promo</span>
                    <?php endif; ?>
                    <?php if ($isUnavailable): ?>
                        <span class="badge badge-unavailable">Indisponible</span>
                    <?php endif; ?>
                </div>

                <div class="detail-prices">
                    <?php if ($isOnPromotion): ?>
                        <p class="price-current"><?= e(format_price($product['promotion_price'])) ?></p>
                        <p class="price-regular"><del><?= e(format_price($product['retail_price'])) ?></del> <span>prix détail</span></p>
                    <?php else: ?>
                        <p class="price-current"><?= e(format_price($product['retail_price'])) ?></p>
                        <p class="price-regular">Prix détail</p>
                    <?php endif; ?>
                </div>

                <?php if ($product['description'] !== null && trim((string) $product['description']) !== ''): ?>
                    <div class="product-description">
                        <h2>Description</h2>
                        <p><?= nl2br(e($product['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="availability-note <?= $isUnavailable ? 'availability-note-unavailable' : '' ?>">
                    <?= $isUnavailable
                        ? 'Ce produit est actuellement indisponible.'
                        : 'Disponible à la commande. Le stock est confirmé par téléphone lors de la validation.' ?>
                </div>
                <?php if (!$isUnavailable): ?>
                    <form class="cart-add-form" method="post" action="<?= e(site_url('public/cart_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($cartCsrfToken) ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                        <?php if ($variants !== []): ?>
                            <label class="form-field product-variant-field">
                                <span>Option</span>
                                <select name="variant_id" required>
                                    <option value="">Choisir une option</option>
                                    <?php foreach ($variants as $variant): ?>
                                        <option value="<?= e($variant['id']) ?>"><?= e($variant['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php else: ?>
                            <input type="hidden" name="variant_id" value="">
                        <?php endif; ?>
                        <label class="form-field">
                            <span>Quantité</span>
                            <div class="quantity-stepper" data-quantity-stepper>
                                <button type="button" data-quantity-decrease aria-label="Diminuer la quantite">&minus;</button>
                                <input type="number" name="quantity" min="1" max="<?= e(CART_MAX_QUANTITY) ?>" step="1" value="1" aria-label="Quantite" required>
                                <button type="button" data-quantity-increase aria-label="Augmenter la quantite">+</button>
                            </div>
                        </label>
                        <button class="button button-primary product-add-button" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h2l2.4 11.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                            Ajouter au panier
                        </button>
                        <p class="cart-add-feedback" data-cart-add-feedback role="status" aria-live="polite" aria-atomic="true" hidden></p>
                    </form>
                <?php else: ?>
                    <button class="button button-primary product-cart-placeholder" type="button" disabled>
                        Produit indisponible
                    </button>
                <?php endif; ?>
                <ul class="product-trust-list" aria-label="Informations de livraison et de paiement">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3z"/><path d="M3 10h18M7 15h3"/></svg>Paiement &agrave; la livraison</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v11H3zM14 11h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>Livraison &agrave; Abidjan</li>
                </ul>
                <a class="text-link" href="<?= e(site_url('public/catalogue.php')) ?>">Continuer mes achats</a>
            </div>
        </section>
        <?php if ($similarProducts !== []): ?>
            <section class="storefront-section related-products" aria-labelledby="related-products-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">À découvrir</p>
                        <h2 id="related-products-title">Vous aimerez aussi</h2>
                    </div>
                </div>
                <div class="product-grid">
                    <?php $showQuickAdd = false; ?>
                    <?php foreach ($similarProducts as $product): ?>
                        <?php require dirname(__DIR__) . '/includes/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
