<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cart.php';

start_app_session();
$rawOrderNumber = $_GET['number'] ?? '';
$orderNumber = is_string($rawOrderNumber) ? $rawOrderNumber : '';
$isAuthorized = isset($_SESSION['last_order_number'])
    && is_string($_SESSION['last_order_number'])
    && hash_equals($_SESSION['last_order_number'], $orderNumber);

$order = false;
$orderItems = [];
if ($isAuthorized && preg_match('/^BLX-[0-9]{8}-[A-F0-9]{12}$/D', $orderNumber) === 1) {
    $orderQuery = db()->prepare(
        'SELECT order_number, customer_first_name, customer_last_name,
                customer_phone, commune_name_snapshot, delivery_zone, address_details,
                subtotal, delivery_fee, total, status, created_at
         FROM orders
         WHERE order_number = :order_number
         LIMIT 1'
    );
    $orderQuery->execute(['order_number' => $orderNumber]);
    $order = $orderQuery->fetch();

    if ($order !== false) {
        $itemsQuery = db()->prepare(
            'SELECT product_name_snapshot, variant_label_snapshot,
                    quantity, applied_price, price_type, is_promotion
             FROM order_items
             WHERE order_id = (
                 SELECT id FROM orders WHERE order_number = :order_number
             )
             ORDER BY id'
        );
        $itemsQuery->execute(['order_number' => $orderNumber]);
        $orderItems = $itemsQuery->fetchAll();
    }
}

if ($order === false) {
    http_response_code(404);
}

$pageTitle = $order === false ? 'Commande introuvable' : 'Commande confirmée';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container storefront-main">
    <?php if ($order === false): ?>
        <section class="storefront-heading">
            <p class="eyebrow">Berluxe</p>
            <h1>Commande introuvable</h1>
            <p class="muted">Cette confirmation n’est pas accessible depuis cette session.</p>
            <a class="button button-secondary" href="<?= e(site_url('public/index.php')) ?>">Retour à l’accueil</a>
        </section>
    <?php else: ?>
        <section class="storefront-heading">
            <p class="eyebrow">Merci pour ta confiance</p>
            <h1>Commande enregistrée</h1>
            <p class="muted">Ta commande est enregistrée sous le numéro <strong><?= e($order['order_number']) ?></strong>.</p>
            <p class="muted">Roxane te contactera sur WhatsApp pour confirmer la disponibilité. Le paiement se fera à la livraison.</p>
        </section>

        <section class="order-confirmation-card" aria-label="Récapitulatif de la commande">
            <h2>Récapitulatif</h2>
            <p><strong>Client :</strong> <?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></p>
            <p><strong>Téléphone WhatsApp :</strong> <?= e($order['customer_phone']) ?></p>
            <p><strong>Livraison :</strong> <?= e($order['commune_name_snapshot']) ?> — <?= $order['delivery_zone'] === 'abidjan' ? 'Abidjan' : 'Hors Abidjan' ?></p>
            <p><strong>Adresse :</strong><br><?= nl2br(e($order['address_details'])) ?></p>

            <ul class="checkout-items order-items">
                <?php foreach ($orderItems as $item): ?>
                    <li>
                        <span>
                            <?= e($item['product_name_snapshot']) ?>
                            <?= $item['variant_label_snapshot'] !== null ? ' — ' . e($item['variant_label_snapshot']) : '' ?>
                            × <?= e($item['quantity']) ?>
                            <small><?= $item['price_type'] === 'gros' ? 'Prix de gros' : 'Prix détail' ?><?= (int) $item['is_promotion'] === 1 ? ' · promotion' : '' ?></small>
                        </span>
                        <strong><?= e(format_price((int) $item['applied_price'] * (int) $item['quantity'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="cart-summary-row"><span>Sous-total</span><strong><?= e(format_price($order['subtotal'])) ?></strong></p>
            <p class="cart-summary-row"><span>Livraison</span><strong><?= e(format_price($order['delivery_fee'])) ?></strong></p>
            <p class="cart-summary-row cart-summary-total"><span>Total à payer à la livraison</span><strong><?= e(format_price($order['total'])) ?></strong></p>
        </section>
        <a class="button button-primary" href="<?= e(site_url('public/catalogue.php')) ?>">Continuer mes achats</a>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
