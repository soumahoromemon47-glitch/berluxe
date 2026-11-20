<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cart.php';

$cartLines = cart_catalog_lines();
$cartSubtotal = cart_subtotal($cartLines);
$coverByProduct = [];
$communes = [];
$deliveryFees = ['abidjan' => null, 'hors_abidjan' => null];
if ($cartLines !== []) {
    $productIds = array_values(array_unique(array_map(
        static fn(array $line): int => (int) $line['product_id'],
        $cartLines
    )));
    $placeholders = [];
    $parameters = [];
    foreach ($productIds as $index => $productId) {
        $key = 'product_' . $index;
        $placeholders[] = ':' . $key;
        $parameters[$key] = $productId;
    }
    $imageQuery = db()->prepare(
        'SELECT product_id, file_name
         FROM product_images
         WHERE product_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY product_id, display_order, id'
    );
    $imageQuery->execute($parameters);
    foreach ($imageQuery->fetchAll() as $image) {
        $coverByProduct[(int) $image['product_id']] ??= (string) $image['file_name'];
    }

    $communes = db()->query(
        'SELECT id, name, zone
         FROM communes
         WHERE is_active = 1
         ORDER BY zone, display_order, name'
    )->fetchAll();
    $settings = site_settings();
    $deliveryFees = [
        'abidjan' => cart_amount(required_setting($settings, 'delivery_fee_abidjan'), 'livraison à Abidjan'),
        'hors_abidjan' => cart_amount(required_setting($settings, 'delivery_fee_outside_abidjan'), 'livraison hors Abidjan'),
    ];
}
$flash = consume_cart_flash();
$pageTitle = 'Mon panier';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container storefront-main">
    <section class="storefront-heading">
        <p class="eyebrow">Votre sélection</p>
        <h1>Mon panier</h1>
        <p class="muted">Les prix sont recalculés depuis le catalogue au moment de la commande.</p>
    </section>

    <?php if ($flash !== null): ?>
        <div class="notice notice-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($cartLines === []): ?>
        <section class="empty-state">
            <p>Ton panier est vide pour le moment.</p>
            <a class="button button-primary" href="<?= e(site_url('public/catalogue.php')) ?>">Découvrir le catalogue</a>
        </section>
    <?php else: ?>
        <section class="cart-layout" aria-label="Contenu du panier">
            <div class="cart-lines">
                <?php foreach ($cartLines as $line): ?>
                    <article class="cart-line">
                        <a class="cart-line-image" href="<?= e(site_url('public/produit.php?id=' . (int) $line['product_id'])) ?>" aria-label="Voir <?= e($line['product_name']) ?>">
                            <?php if (isset($coverByProduct[$line['product_id']])): ?>
                                <img src="<?= e(site_url('uploads/products/' . rawurlencode($coverByProduct[$line['product_id']]))) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span aria-hidden="true">BL</span>
                            <?php endif; ?>
                        </a>
                        <div class="cart-line-product">
                            <h2><?= e($line['product_name']) ?></h2>
                            <?php if ($line['variant_label'] !== null): ?>
                                <p class="muted">Variante : <?= e($line['variant_label']) ?></p>
                            <?php endif; ?>
                            <?php if (!$line['is_valid']): ?>
                                <p class="cart-line-warning">Cet article ou cette variante n’est plus disponible dans le catalogue.</p>
                            <?php elseif (!$line['is_available']): ?>
                                <p class="cart-line-warning">Ce produit est actuellement indisponible.</p>
                            <?php endif; ?>
                        </div>

                        <?php if (is_array($line['pricing'])): ?>
                            <div class="cart-line-price">
                                <span>Prix unitaire</span>
                                <strong><?= e(format_price($line['pricing']['unit_price'])) ?></strong>
                                <small><?= $line['pricing']['is_promotion'] ? ' · promotion incluse' : '' ?></small>
                            </div>
                        <?php endif; ?>

                        <form class="cart-line-quantity" method="post" action="<?= e(site_url('public/cart_action.php')) ?>" data-cart-quantity-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= e($line['product_id']) ?>">
                            <input type="hidden" name="variant_id" value="<?= e($line['variant_id'] ?? '') ?>">
                            <label class="form-field">
                                <span>Quantité</span>
                                <div class="quantity-stepper" data-quantity-stepper>
                                    <button type="button" data-quantity-decrease aria-label="Diminuer la quantite">&minus;</button>
                                    <input type="number" name="quantity" min="1" max="<?= e(CART_MAX_QUANTITY) ?>" step="1" value="<?= e($line['quantity']) ?>" aria-label="Quantite" required>
                                    <button type="button" data-quantity-increase aria-label="Augmenter la quantite">+</button>
                                </div>
                            </label>
                            <button class="button button-secondary" type="submit">Mettre à jour</button>
                        </form>

                        <?php if (is_array($line['pricing']) && $line['pricing']['line_total'] !== null): ?>
                            <p class="cart-line-total">
                                <span>Total de la ligne</span>
                                <strong><?= e(format_price($line['pricing']['line_total'])) ?></strong>
                            </p>
                        <?php elseif (is_array($line['pricing'])): ?>
                            <p class="cart-line-warning">Le montant dépasse la limite autorisée. Réduis la quantité.</p>
                        <?php endif; ?>

                        <form method="post" action="<?= e(site_url('public/cart_action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= e($line['product_id']) ?>">
                            <input type="hidden" name="variant_id" value="<?= e($line['variant_id'] ?? '') ?>">
                            <button class="text-link cart-remove" type="submit" aria-label="Supprimer <?= e($line['product_name']) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></svg>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary">
                <h2>Récapitulatif</h2>
                <?php if ($cartSubtotal === false): ?>
                    <p class="cart-line-warning">Corrige les articles indisponibles ou les quantités avant de commander.</p>
                    <p class="cart-summary-row"><span>Sous-total</span><strong>À recalculer</strong></p>
                <?php else: ?>
                    <p class="cart-summary-row"><span>Sous-total</span><strong><?= e(format_price($cartSubtotal)) ?></strong></p>
                    <label class="form-field cart-delivery-field">
                        <span>Estimer la livraison</span>
                        <select data-cart-delivery-preview data-subtotal="<?= e($cartSubtotal) ?>" data-maximum="<?= e(ORDER_AMOUNT_MAX) ?>">
                            <option value="">Choisir une commune</option>
                            <?php foreach ($communes as $commune): ?>
                                <option value="<?= e($commune['id']) ?>" data-fee="<?= e($deliveryFees[$commune['zone']]) ?>" data-name="<?= e($commune['name']) ?>">
                                    <?= e($commune['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="cart-summary-row"><span data-cart-delivery-label>Frais de livraison</span><strong data-cart-delivery-output>Choisis une commune</strong></p>
                    <p class="cart-summary-row cart-summary-total"><span>Total estimé</span><strong data-cart-total-output>Choisis une commune</strong></p>
                    <p class="muted">Les frais de livraison seront calculés selon la commune choisie à l’étape suivante.</p>
                <?php endif; ?>
                <?php if ($cartSubtotal === false): ?>
                    <button class="button button-primary" type="button" disabled>Corriger le panier</button>
                <?php else: ?>
                    <a class="button button-primary" href="<?= e(site_url('public/commande.php')) ?>">Passer la commande</a>
                <?php endif; ?>
                <a class="text-link" href="<?= e(site_url('public/catalogue.php')) ?>">Continuer mes achats</a>
            </aside>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
