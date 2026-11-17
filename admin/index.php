<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$admin = current_admin();
if ($admin === null) {
    throw new RuntimeException('La session administrateur est introuvable.');
}

$settings = site_settings();
$stockThreshold = filter_var(
    required_setting($settings, 'stock_alert_threshold'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($stockThreshold === false) {
    throw new RuntimeException('Le seuil d’alerte de stock est invalide.');
}

$ordersToProcess = (int) db()
    ->query("SELECT COUNT(*) FROM orders WHERE status IN ('nouvelle', 'confirmee')")
    ->fetchColumn();

$lowStockQuery = db()->prepare(
    'SELECT id, name, stock
     FROM products
     WHERE stock <= :threshold
     ORDER BY stock ASC, name ASC
     LIMIT 10'
);
$lowStockQuery->execute(['threshold' => $stockThreshold]);
$lowStockProducts = $lowStockQuery->fetchAll();

$countQuery = db()->prepare('SELECT COUNT(*) FROM products WHERE stock <= :threshold');
$countQuery->execute(['threshold' => $stockThreshold]);
$lowStockCount = (int) $countQuery->fetchColumn();

$pageTitle = 'Tableau de bord';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Administration Berluxe</p>
        <h1>Tableau de bord</h1>
        <p class="form-intro">Bonjour <?= e($admin['name']) ?>, voici les points à suivre.</p>
    </div>
</div>

<section class="dashboard-grid" aria-label="Résumé de la boutique">
    <article class="dashboard-card">
        <p class="dashboard-label">Commandes à traiter</p>
        <p class="dashboard-number"><?= e($ordersToProcess) ?></p>
        <span>Nouvelles ou confirmées</span>
        <a class="text-link" href="<?= e(site_url('admin/commandes.php')) ?>">Gérer les commandes</a>
    </article>
    <article class="dashboard-card">
        <p class="dashboard-label">Produits en stock bas</p>
        <p class="dashboard-number"><?= e($lowStockCount) ?></p>
        <span>Seuil actuel : <?= e($stockThreshold) ?></span>
    </article>
</section>

<section class="admin-card">
    <div class="section-heading">
        <div>
            <h2>À réapprovisionner</h2>
            <p class="muted">Les produits dont le stock est inférieur ou égal au seuil défini.</p>
        </div>
        <a class="button button-secondary" href="<?= e(site_url('admin/reglages.php')) ?>">Modifier les réglages</a>
    </div>
    <?php if ($lowStockProducts === []): ?>
        <p class="empty-state">Aucun produit en stock bas pour le moment.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Produit</th><th>Stock</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockProducts as $product): ?>
                        <tr>
                            <td><?= e($product['name']) ?></td>
                            <td><?= e($product['stock']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
