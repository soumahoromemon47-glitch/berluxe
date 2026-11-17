<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cart.php';

$cartLines = cart_catalog_lines();
$cartSubtotal = cart_subtotal($cartLines);
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
                                <small>
                                    Prix de <?= $line['pricing']['price_type'] === 'gros' ? 'gros' : 'détail' ?>
                                    <?= $line['pricing']['is_promotion'] ? ' · promotion incluse' : '' ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <form class="cart-line-quantity" method="post" action="<?= e(site_url('public/cart_action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= e($line['product_id']) ?>">
                            <input type="hidden" name="variant_id" value="<?= e($line['variant_id'] ?? '') ?>">
                            <label class="form-field">
                                <span>Quantité</span>
                                <input
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="<?= e(CART_MAX_QUANTITY) ?>"
                                    step="1"
                                    value="<?= e($line['quantity']) ?>"
                                    required
                                >
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
                            <button class="text-link cart-remove" type="submit">Retirer</button>
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
