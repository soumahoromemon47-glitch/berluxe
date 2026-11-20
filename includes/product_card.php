<article class="product-card">
    <a class="product-card-link" href="<?= e(site_url('public/produit.php?id=' . (int) $product['id'])) ?>">
        <?php if (!empty($product['cover_file_name'])): ?>
            <div class="product-card-image">
                <img
                    src="<?= e(site_url('uploads/products/' . rawurlencode((string) $product['cover_file_name']))) ?>"
                    alt="<?= e($product['name']) ?>"
                    loading="lazy"
                >
                <div class="product-badges" aria-label="Labels du produit">
                    <?php if ((int) $product['is_featured'] === 1): ?>
                        <span class="badge">⭐ Vedette</span>
                    <?php endif; ?>
                    <?php if ((int) $product['is_on_promotion'] === 1 && $product['promotion_price'] !== null): ?>
                        <span class="badge badge-promo">Promo</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="product-card-image product-image-placeholder" aria-hidden="true">Photo à venir</div>
        <?php endif; ?>

        <div class="product-card-content">
            <p class="product-card-category"><?= e($product['category_name'] ?? 'Sans catégorie') ?></p>
            <h3><?= e($product['name']) ?></h3>
            <p class="product-card-price"><?= e(format_price($product['display_price'])) ?></p>
        </div>
    </a>

    <?php if (!empty($showQuickAdd)): ?>
        <div class="product-card-actions">
            <?php if ((int) $product['is_unavailable'] !== 1): ?>
                <form class="cart-add-form" method="post" action="<?= e(site_url('public/cart_action.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="variant_id" value="">
                    <button class="button-add-to-cart" type="submit" title="Ajouter au panier" aria-label="Ajouter au panier">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                        <span>Ajouter</span>
                    </button>
                </form>
                <p class="cart-add-feedback" data-cart-add-feedback role="status" aria-live="polite" aria-hidden="true" hidden></p>
            <?php else: ?>
                <button class="button-unavailable" type="button" disabled>Indisponible</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>