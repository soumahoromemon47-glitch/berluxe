<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_admin();

$filterError = null;
$statusFilter = '';
$rawStatus = $_GET['status'] ?? '';
if (!is_string($rawStatus) || !in_array($rawStatus, array_merge([''], array_keys(ORDER_STATUS_LABELS)), true)) {
    http_response_code(400);
    $filterError = 'Le filtre de statut est invalide.';
} else {
    $statusFilter = $rawStatus;
}

$orders = [];
if ($filterError === null) {
    $sql = 'SELECT id, order_number, customer_first_name, customer_last_name,
                   customer_phone, commune_name_snapshot, total, status, created_at
            FROM orders';
    $parameters = [];
    if ($statusFilter !== '') {
        $sql .= ' WHERE status = :status';
        $parameters['status'] = $statusFilter;
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';
    $ordersQuery = db()->prepare($sql);
    $ordersQuery->execute($parameters);
    $orders = $ordersQuery->fetchAll();
}

$pageTitle = 'Commandes';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Administration Berluxe</p>
        <h1>Commandes</h1>
        <p class="form-intro">Consulte les commandes et suis leur statut de traitement.</p>
    </div>
</div>

<?php if ($filterError !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($filterError) ?></div>
<?php endif; ?>

<section class="admin-card">
    <div class="catalog-toolbar">
        <h2>Historique</h2>
        <form method="get" action="<?= e(site_url('admin/commandes.php')) ?>">
            <label class="form-field">
                <span>Filtrer par statut</span>
                <select name="status" data-auto-submit>
                    <option value="">Tous les statuts</option>
                    <?php foreach (ORDER_STATUS_LABELS as $status => $label): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button class="button button-secondary" type="submit">Filtrer</button></noscript>
        </form>
    </div>

    <?php if ($orders === []): ?>
        <p class="empty-state">Aucune commande ne correspond à ce filtre.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Commune</th>
                        <th>Statut</th>
                        <th>Total</th>
                        <th>Date et heure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php $createdAt = new DateTimeImmutable((string) $order['created_at']); ?>
                        <tr>
                            <td>
                                <a class="order-number-link" href="<?= e(site_url('admin/commande.php?id=' . (int) $order['id'])) ?>">
                                    <?= e($order['order_number']) ?>
                                </a>
                            </td>
                            <td>
                                <?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?><br>
                                <span class="muted"><?= e($order['customer_phone']) ?></span>
                            </td>
                            <td><?= e($order['commune_name_snapshot']) ?></td>
                            <td><span class="status-badge status-<?= e($order['status']) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
                            <td><?= e(format_price($order['total'])) ?></td>
                            <td><?= e($createdAt->format('d/m/Y à H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
