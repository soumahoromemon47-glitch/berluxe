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
<div class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow">Gestion</p>
        <h1>Commandes</h1>
        <p class="muted">Consulte les commandes et suis leur statut de traitement.</p>
    </div>
</div>

<?php if ($filterError !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($filterError) ?></div>
<?php endif; ?>

<div class="orders-filter-bar" aria-label="Filtrer les commandes par statut">
    <a href="<?= e(site_url('admin/commandes.php')) ?>" class="filter-pill <?= $statusFilter === '' ? 'active' : '' ?>" <?= $statusFilter === '' ? 'aria-current="page"' : '' ?>>Toutes</a>
    <?php foreach (ORDER_STATUS_LABELS as $status => $label): ?>
        <a href="<?= e(site_url('admin/commandes.php?status=' . urlencode($status))) ?>" class="filter-pill <?= $statusFilter === $status ? 'active' : '' ?>" <?= $statusFilter === $status ? 'aria-current="page"' : '' ?>>
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<section class="admin-card orders-card">
    <?php if ($orders === []): ?>
        <p class="empty-state">Aucune commande ne correspond à ce filtre.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="order-table admin-table-modern">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client</th>
                        <th>Commune</th>
                        <th>Statut</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php $createdAt = new DateTimeImmutable((string) $order['created_at']); ?>
                        <tr>
                            <td><span class="order-number-badge"><?= e($order['order_number']) ?></span></td>
                            <td>
                                <strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong><br>
                                <span class="muted order-customer-phone"><?= e($order['customer_phone']) ?></span>
                            </td>
                            <td><?= e($order['commune_name_snapshot']) ?></td>
                            <td><span class="status-badge status-<?= e($order['status']) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
                            <td><strong><?= e(format_price($order['total'])) ?></strong></td>
                            <td><span class="muted"><?= e($createdAt->format('d/m/Y H:i')) ?></span></td>
                            <td>
                                <a class="button-icon-action edit" href="<?= e(site_url('admin/commande.php?id=' . (int) $order['id'])) ?>" title="Voir le détail" aria-label="Voir le détail de la commande <?= e($order['order_number']) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>