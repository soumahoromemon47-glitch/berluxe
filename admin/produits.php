<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_admin();

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } elseif (($_POST['action'] ?? '') === 'delete_product') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($productId === false) {
            $errorMessage = 'Le produit sélectionné est invalide.';
        } else {
            $connection = db();
            $connection->beginTransaction();
            try {
                $existing = $connection->prepare('SELECT id FROM products WHERE id = :id FOR UPDATE');
                $existing->execute(['id' => $productId]);
                if ($existing->fetchColumn() === false) {
                    $connection->rollBack();
                    $errorMessage = 'Ce produit n’existe plus.';
                } else {
                    $imagesQuery = $connection->prepare(
                        'SELECT file_name FROM product_images WHERE product_id = :product_id'
                    );
                    $imagesQuery->execute(['product_id' => $productId]);
                    $imageFileNames = array_column($imagesQuery->fetchAll(), 'file_name');

                    $delete = $connection->prepare('DELETE FROM products WHERE id = :id');
                    $delete->execute(['id' => $productId]);
                    $connection->commit();

                    $failedFiles = delete_product_image_files($imageFileNames);
                    if ($failedFiles === []) {
                        set_admin_flash('success', 'Le produit a été supprimé.');
                    } else {
                        set_admin_flash(
                            'error',
                            'Le produit a été supprimé, mais certaines photos n’ont pas pu être effacées.'
                        );
                    }
                    admin_redirect('admin/produits.php');
                }
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            }
        }
    } else {
        http_response_code(400);
        $errorMessage = 'Action non reconnue.';
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY display_order, name')->fetchAll();
$rawCategoryFilter = $_GET['category_id'] ?? '';
$categoryFilter = null;
if ($rawCategoryFilter !== '') {
    $categoryFilter = filter_var($rawCategoryFilter, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($categoryFilter === false) {
        http_response_code(400);
        $errorMessage = 'Le filtre de catégorie est invalide.';
        $categoryFilter = null;
    }
}

$sql = 'SELECT p.id, p.name, p.retail_price, p.wholesale_price,
               p.wholesale_min_quantity, p.promotion_price, p.stock,
               p.is_featured, p.is_on_promotion, p.is_unavailable,
               c.name AS category_name
        FROM products AS p
        LEFT JOIN categories AS c ON c.id = p.category_id';
$parameters = [];
if ($categoryFilter !== null) {
    $sql .= ' WHERE p.category_id = :category_id';
    $parameters['category_id'] = $categoryFilter;
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC';
$productQuery = db()->prepare($sql);
$productQuery->execute($parameters);
$products = $productQuery->fetchAll();

$pageTitle = 'Produits';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow">Catalogue</p>
        <h1>Produits</h1>
        <p class="muted">Gère ton catalogue, les prix et le stock.</p>
    </div>
    <a class="button button-primary" href="<?= e(site_url('admin/produit_form.php')) ?>">+ Ajouter un produit</a>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="products-filter-bar" aria-label="Filtrer les produits par catégorie">
    <a href="<?= e(site_url('admin/produits.php')) ?>" class="filter-pill <?= $categoryFilter === null ? 'active' : '' ?>" <?= $categoryFilter === null ? 'aria-current="page"' : '' ?>>Tous</a>
    <?php foreach ($categories as $category): ?>
        <a href="<?= e(site_url('admin/produits.php?category_id=' . (int) $category['id'])) ?>"
           class="filter-pill <?= $categoryFilter === (int) $category['id'] ? 'active' : '' ?>" <?= $categoryFilter === (int) $category['id'] ? 'aria-current="page"' : '' ?>>
            <?= e($category['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<section class="admin-card products-card">
    <?php if ($products === []): ?>
        <p class="empty-state">Aucun produit dans cette catégorie. Commence par en ajouter un.</p>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table-modern products-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>Statut</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><div class="product-name-cell"><strong><?= e($product['name']) ?></strong></div></td>
                            <td><?= e($product['category_name'] ?? 'Sans catégorie') ?></td>
                            <td>
                                <?php if ((int) $product['is_on_promotion'] === 1 && $product['promotion_price'] !== null): ?>
                                    <span class="product-old-price"><?= e(format_price($product['retail_price'])) ?></span><br>
                                    <strong class="product-sale-price"><?= e(format_price($product['promotion_price'])) ?></strong>
                                <?php else: ?>
                                    <strong><?= e(format_price($product['retail_price'])) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $stock = (int) $product['stock']; ?>
                                <span class="stock-badge <?= $stock <= 5 ? 'low' : 'ok' ?>">
                                    <?= e($stock) ?> en stock
                                </span>
                            </td>
                            <td>
                                <div class="product-status-badges">
                                    <?php if ((int) $product['is_featured'] === 1): ?>
                                        <span class="badge badge-featured">Vedette</span>
                                    <?php endif; ?>
                                    <?php if ((int) $product['is_on_promotion'] === 1): ?>
                                        <span class="badge badge-promo">Promo</span>
                                    <?php endif; ?>
                                    <?php if ((int) $product['is_unavailable'] === 1): ?>
                                        <span class="badge badge-unavailable">Indisponible</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button-icon-action edit" href="<?= e(site_url('admin/produit_form.php?id=' . (int) $product['id'])) ?>" title="Modifier" aria-label="Modifier <?= e($product['name']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <form method="post" action="<?= e(site_url('admin/produits.php')) ?>" data-confirm="Supprimer ce produit et toutes ses photos ?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                        <button class="button-icon-action delete" type="submit" title="Supprimer" aria-label="Supprimer <?= e($product['name']) ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>