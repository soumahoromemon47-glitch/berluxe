<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$admin = current_admin();
$settings = site_settings();
$stockThreshold = (int) required_setting($settings, 'stock_alert_threshold');

// Calculs statistiques
$ordersToProcess = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('nouvelle', 'confirmee')")->fetchColumn();
$totalProducts = (int) db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalRevenue = (float) db()->query("SELECT SUM(total) FROM orders WHERE status = 'livree'")->fetchColumn();
$lowStockQuery = db()->prepare('SELECT id, name, stock FROM products WHERE stock <= :threshold ORDER BY stock ASC LIMIT 5');
$lowStockQuery->execute(['threshold' => $stockThreshold]);
$lowStockProducts = $lowStockQuery->fetchAll();

$pageTitle = 'Tableau de bord';
require dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="dashboard-header">
    <h2>Aperçu de la boutique</h2>
    <p class="muted">Voici l'activité de Berluxe aujourd'hui.</p>
</div>

<!-- Cartes de statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
            <span>Commandes à traiter</span>
            <strong><?= e($ordersToProcess) ?></strong>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛍️</div>
        <div class="stat-info">
            <span>Total Produits</span>
            <strong><?= e($totalProducts) ?></strong>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <span>Chiffre d'affaires</span>
            <strong><?= format_price((string)$totalRevenue) ?></strong>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon">⚠️</div>
        <div class="stat-info">
            <span>Stocks bas</span>
            <strong><?= count($lowStockProducts) ?></strong>
        </div>
    </div>
</div>

<!-- Tableau des stocks bas -->
<div class="admin-card" style="margin-top: 32px;">
    <div class="section-heading">
        <h3>À réapprovisionner</h3>
        <a href="<?= e(site_url('admin/reglages.php')) ?>" class="text-link">Gérer les réglages</a>
    </div>
    <?php if ($lowStockProducts === []): ?>
        <p class="empty-state">Tous les stocks sont bons !</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr><th>Produit</th><th>Stock restant</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockProducts as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td><span class="stock-badge"><?= e($p['stock']) ?> restants</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
/* Styles spécifiques au Dashboard */
.dashboard-header { margin-bottom: 24px; }
.dashboard-header h2 { font-family: var(--font-heading); font-size: 2rem; margin: 0 0 4px; color: var(--color-text); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
.stat-card { background: white; border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #EAEAEA; }
.stat-icon { font-size: 2rem; background: #FAF7F2; width: 60px; height: 60px; display: grid; place-items: center; border-radius: 12px; }
.stat-info span { display: block; font-size: 0.9rem; color: var(--color-muted); margin-bottom: 4px; }
.stat-info strong { display: block; font-size: 1.6rem; font-weight: 800; color: var(--color-text); }
.stat-card.warning .stat-icon { background: #FBECEA; }
.stat-card.warning strong { color: #B23A2E; }
.admin-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #EAEAEA; }
.section-heading { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.section-heading h3 { font-family: var(--font-heading); font-size: 1.3rem; margin: 0; color: var(--color-brand); }
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th { text-align: left; padding: 12px; font-size: 0.8rem; color: var(--color-muted); text-transform: uppercase; border-bottom: 1px solid #EAEAEA; }
.admin-table td { padding: 16px 12px; border-bottom: 1px solid #EAEAEA; }
.admin-table tr:last-child td { border-bottom: none; }
.stock-badge { background: #FBECEA; color: #B23A2E; padding: 4px 12px; border-radius: 999px; font-weight: 600; font-size: 0.8rem; }
</style>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>

