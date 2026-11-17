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
                p.wholesale_price, p.wholesale_min_quantity, p.promotion_price,
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
                    <?php foreach ($images as $image): ?>
                        <figure class="product-gallery-image">
                            <img
                                src="<?= e(site_url('uploads/products/' . rawurlencode((string) $image['file_name']))) ?>"
                                alt="<?= e($image['alt_text'] ?? $product['name']) ?>"
                            >
                        </figure>
                    <?php endforeach; ?>
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
                    <p class="price-wholesale">
                        Prix de gros : <?= e(format_price($product['wholesale_price'])) ?>
                        dès <?= e($product['wholesale_min_quantity']) ?> unité(s).
                    </p>
                </div>

                <?php if ($product['description'] !== null && trim((string) $product['description']) !== ''): ?>
                    <div class="product-description">
                        <h2>Description</h2>
                        <p><?= nl2br(e($product['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($variants !== []): ?>
                    <div class="product-variants">
                        <h2>Variantes</h2>
                        <ul class="variant-list">
                            <?php foreach ($variants as $variant): ?>
                                <li><?= e($variant['label']) ?></li>
                            <?php endforeach; ?>
                        </ul>
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
                            <label class="form-field">
                                <span>Variante</span>
                                <select name="variant_id" required>
                                    <option value="">Choisir une variante</option>
                                    <?php foreach ($variants as $variant): ?>
                                        <option value="<?= e($variant['id']) ?>"><?= e($variant['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <label class="form-field">
                            <span>Quantité</span>
                            <input type="number" name="quantity" min="1" max="<?= e(CART_MAX_QUANTITY) ?>" step="1" value="1" required>
                        </label>
                        <button class="button button-primary" type="submit">Ajouter au panier</button>
                        <p class="cart-add-feedback" data-cart-add-feedback role="status" aria-live="polite" aria-atomic="true" hidden></p>
                    </form>
                <?php else: ?>
                    <button class="button button-primary product-cart-placeholder" type="button" disabled>
                        Produit indisponible
                    </button>
                <?php endif; ?>
                <a class="text-link" href="<?= e(site_url('public/catalogue.php')) ?>">Continuer mes achats</a>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
