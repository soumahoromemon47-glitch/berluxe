<article class="product-card">
    <a class="product-card-link" href="<?= e(site_url('public/produit.php?id=' . (int) $product['id'])) ?>">
        <?php if (!empty($product['cover_file_name'])): ?>
            <div class="product-card-image">
                <img
                    src="<?= e(site_url('uploads/products/' . rawurlencode((string) $product['cover_file_name']))) ?>"
                    alt="<?= e($product['name']) ?>"
                    loading="lazy"
                >
            </div>
        <?php else: ?>
            <div class="product-card-image product-image-placeholder" aria-hidden="true">Photo à venir</div>
        <?php endif; ?>
        <div class="product-card-content">
            <div class="product-badges">
                <?php if ((int) $product['is_featured'] === 1): ?>
                    <span class="badge">Vedette</span>
                <?php endif; ?>
                <?php if ((int) $product['is_on_promotion'] === 1 && $product['promotion_price'] !== null): ?>
                    <span class="badge badge-promo">Promo</span>
                <?php endif; ?>
                <?php if ((int) $product['is_unavailable'] === 1): ?>
                    <span class="badge badge-unavailable">Indisponible</span>
                <?php endif; ?>
            </div>
            <p class="product-card-category"><?= e($product['category_name'] ?? 'Sans catégorie') ?></p>
            <h3><?= e($product['name']) ?></h3>
            <p class="product-card-price"><?= e(format_price($product['display_price'])) ?></p>
            <p class="product-card-wholesale">
                Gros : <?= e(format_price($product['wholesale_price'])) ?>
                dès <?= e($product['wholesale_min_quantity']) ?> unité(s)
            </p>
        </div>
    </a>
    <?php if (!empty($showQuickAdd)): ?>
        <?php if ((int) $product['is_unavailable'] !== 1): ?>
            <?php $productVariants = $product['variants'] ?? []; ?>
            <div class="catalogue-add-form">
                <form class="cart-add-form" method="post" action="<?= e(site_url('public/cart_action.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="quantity" value="1">
                    <?php if ($productVariants !== []): ?>
                        <label class="form-field">
                            <span>Variante</span>
                            <select name="variant_id">
                                <?php $first = true; ?>
                                <?php foreach ($productVariants as $variant): ?>
                                    <option value="<?= e($variant['id']) ?>" <?= $first ? 'selected' : '' ?>><?= e($variant['label']) ?></option>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else: ?>
                        <input type="hidden" name="variant_id" value="">
                    <?php endif; ?>
                    <button class="button button-primary button-icon" type="submit" title="Ajouter au panier" aria-label="Ajouter au panier">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.65 11.36A2 2 0 0 0 9.65 16h2.83a2 2 0 0 0 2-1.61l1.78-6.77a1 1 0 0 1 .11-.13l.23-.26a1 1 0 0 1 .18-.18l.26-.23a1 1 0 0 1 .13-.11L16.07 4H16a1 1 0 0 1-1-1V2a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v1a1 1 0 0 1-1 1H3.65l-.31 1.16a1 1 0 0 1-.37.37L1.65 6H2a1 1 0 0 0 1 1v1a1 1 0 0 1 1 1z"></path>
                        </svg>
                    </button>
                </form>
                <a class="button button-primary" href="<?= e(site_url('public/produit.php?id=' . (int) $product['id'])) ?>">Commander</a>
                <p class="cart-add-feedback" data-cart-add-feedback role="status" aria-live="polite" aria-atomic="true" hidden></p>
            </div>
        <?php else: ?>
            <div class="catalogue-add-form">
                <button class="button button-secondary" type="button" disabled>Indisponible</button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</article>
