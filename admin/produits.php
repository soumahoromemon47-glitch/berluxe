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
<div class="page-heading">
    <div>
        <p class="eyebrow">Catalogue</p>
        <h1>Produits</h1>
        <p class="form-intro">Gère les prix, le stock, les variantes et les photos du catalogue.</p>
    </div>
    <a class="button button-primary" href="<?= e(site_url('admin/produit_form.php')) ?>">Ajouter un produit</a>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="admin-card">
    <div class="catalog-toolbar">
        <h2>Catalogue</h2>
        <form method="get" action="<?= e(site_url('admin/produits.php')) ?>">
            <label class="form-field">
                <span>Filtrer par catégorie</span>
                <select name="category_id" data-auto-submit>
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button class="button button-secondary" type="submit">Filtrer</button></noscript>
        </form>
    </div>

    <?php if ($products === []): ?>
        <p class="empty-state">Aucun produit dans cette sélection. Ajoute ton premier produit au catalogue.</p>
    <?php else: ?>
        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <article class="product-row">
                    <div class="product-summary">
                        <div>
                            <h3><?= e($product['name']) ?></h3>
                            <p class="muted"><?= e($product['category_name'] ?? 'Sans catégorie') ?></p>
                        </div>
                        <div class="product-badges">
                            <?php if ((int) $product['is_featured'] === 1): ?>
                                <span class="badge">Vedette</span>
                            <?php endif; ?>
                            <?php if ((int) $product['is_on_promotion'] === 1): ?>
                                <span class="badge badge-promo">Promo</span>
                            <?php endif; ?>
                            <?php if ((int) $product['is_unavailable'] === 1): ?>
                                <span class="badge badge-unavailable">Indisponible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <dl class="product-meta">
                        <div><dt>Prix détail</dt><dd><?= e(format_price($product['retail_price'])) ?></dd></div>
                        <div><dt>Prix de gros (dès <?= e($product['wholesale_min_quantity']) ?>)</dt><dd><?= e(format_price($product['wholesale_price'])) ?></dd></div>
                        <?php if ((int) $product['is_on_promotion'] === 1 && $product['promotion_price'] !== null): ?>
                            <div><dt>Prix promo</dt><dd><?= e(format_price($product['promotion_price'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Stock</dt><dd><?= e($product['stock']) ?></dd></div>
                    </dl>
                    <div class="product-actions">
                        <a class="button button-secondary" href="<?= e(site_url('admin/produit_form.php?id=' . (int) $product['id'])) ?>">Modifier</a>
                        <form method="post" action="<?= e(site_url('admin/produits.php')) ?>" data-confirm="Supprimer ce produit et ses photos ?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                            <button class="button button-danger" type="submit">Supprimer</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
