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
        <section class="confirmation-error">
            <div class="error-icon">❌</div>
            <h1>Commande introuvable</h1>
            <p class="muted">Cette confirmation n'est pas accessible depuis cette session.</p>
            <a class="button button-primary" href="<?= e(site_url('public/index.php')) ?>">Retour à l'accueil</a>
        </section>
    <?php else: ?>
        <section class="confirmation-success">
            <div class="success-icon">✓</div>
            <h1>Merci pour ta commande !</h1>
            <p class="muted">Ta commande a bien été enregistrée sous le numéro :</p>
            <p class="order-number-highlight"><?= e($order['order_number']) ?></p>
            <p class="muted">Roxane te contactera sur WhatsApp pour confirmer la disponibilité. Le paiement se fera à la livraison.</p>
        </section>

        <section class="confirmation-recap">
            <h2>Récapitulatif de la commande</h2>
            
            <div class="recap-client-info">
                <p><strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong></p>
                <p><?= e($order['customer_phone']) ?></p>
                <p><?= e($order['commune_name_snapshot']) ?> — <?= $order['delivery_zone'] === 'abidjan' ? 'Abidjan' : 'Hors Abidjan' ?></p>
                <p class="muted" style="font-size: 0.9rem;"><?= nl2br(e($order['address_details'])) ?></p>
            </div>

            <ul class="checkout-items order-items">
                <?php foreach ($orderItems as $item): ?>
                    <li>
                        <span>
                            <?= e($item['product_name_snapshot']) ?>
                            <small>× <?= e($item['quantity']) ?></small>
                        </span>
                        <strong><?= e(format_price((int) $item['applied_price'] * (int) $item['quantity'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="checkout-totals">
                <div class="checkout-total-row"><span>Sous-total</span><span><?= e(format_price($order['subtotal'])) ?></span></div>
                <div class="checkout-total-row"><span>Livraison</span><span><?= e(format_price($order['delivery_fee'])) ?></span></div>
                <div class="checkout-total-row grand-total"><span>Total à payer</span><span><?= e(format_price($order['total'])) ?></span></div>
            </div>
        </section>

        <div class="confirmation-actions">
            <a class="button button-primary" href="<?= e(site_url('public/catalogue.php')) ?>">Continuer mes achats</a>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

