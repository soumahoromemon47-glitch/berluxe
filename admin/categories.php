<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_admin();

$errorMessage = null;
$newCategoryName = '';
$newCategoryOrder = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_category') {
            $rawId = $_POST['category_id'] ?? '';
            $categoryId = $rawId === ''
                ? null
                : filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $rawName = $_POST['name'] ?? '';
            $rawOrder = $_POST['display_order'] ?? '';
            $name = is_string($rawName) ? trim($rawName) : '';
            $newCategoryName = $categoryId === null ? $name : '';
            $newCategoryOrder = $categoryId === null && is_string($rawOrder) ? $rawOrder : '';
            $displayOrder = is_string($rawOrder) && preg_match('/^(0|[1-9][0-9]*)$/D', $rawOrder) === 1
                ? filter_var($rawOrder, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 4294967295],
                ])
                : false;

            if ($categoryId === false) {
                $errorMessage = 'La catégorie sélectionnée est invalide.';
            } elseif (preg_match('/^.{1,120}$/us', $name) !== 1) {
                $errorMessage = 'Saisis un nom de catégorie (120 caractères maximum).';
            } elseif ($displayOrder === false) {
                $errorMessage = 'L’ordre d’affichage doit être un nombre positif ou nul.';
            } else {
                $connection = db();
                if ($categoryId !== null) {
                    $existing = $connection->prepare('SELECT id FROM categories WHERE id = :id');
                    $existing->execute(['id' => $categoryId]);
                    if ($existing->fetchColumn() === false) {
                        $errorMessage = 'Cette catégorie n’existe plus.';
                    }
                }

                if ($errorMessage === null) {
                    $slug = unique_catalog_slug($connection, 'categories', $name, $categoryId);
                    if ($categoryId === null) {
                        $save = $connection->prepare(
                            'INSERT INTO categories (name, slug, display_order)
                             VALUES (:name, :slug, :display_order)'
                        );
                        $save->execute([
                            'name' => $name,
                            'slug' => $slug,
                            'display_order' => $displayOrder,
                        ]);
                        set_admin_flash('success', 'La catégorie a été ajoutée.');
                    } else {
                        $save = $connection->prepare(
                            'UPDATE categories
                             SET name = :name, slug = :slug, display_order = :display_order
                             WHERE id = :id'
                        );
                        $save->execute([
                            'name' => $name,
                            'slug' => $slug,
                            'display_order' => $displayOrder,
                            'id' => $categoryId,
                        ]);
                        set_admin_flash('success', 'La catégorie a été mise à jour.');
                    }
                    admin_redirect('admin/categories.php');
                }
            }
        } elseif ($action === 'delete_category') {
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($categoryId === false) {
                $errorMessage = 'La catégorie sélectionnée est invalide.';
            } else {
                $productCount = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
                $productCount->execute(['id' => $categoryId]);
                if ((int) $productCount->fetchColumn() > 0) {
                    $errorMessage = 'Réaffecte d’abord les produits de cette catégorie avant de la supprimer.';
                } else {
                    $delete = db()->prepare('DELETE FROM categories WHERE id = :id');
                    $delete->execute(['id' => $categoryId]);
                    if ($delete->rowCount() !== 1) {
                        $errorMessage = 'Cette catégorie n’existe plus.';
                    } else {
                        set_admin_flash('success', 'La catégorie a été supprimée.');
                        admin_redirect('admin/categories.php');
                    }
                }
            }
        } else {
            http_response_code(400);
            $errorMessage = 'Action non reconnue.';
        }
    }
}

$categories = db()->query(
    'SELECT c.id, c.name, c.slug, c.display_order, COUNT(p.id) AS product_count
     FROM categories AS c
     LEFT JOIN products AS p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.slug, c.display_order
     ORDER BY c.display_order, c.name'
)->fetchAll();

$pageTitle = 'Catégories';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Catalogue</p>
        <h1>Catégories</h1>
        <p class="form-intro">Organise les familles de produits et leur ordre d’affichage.</p>
    </div>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="admin-card">
    <h2>Catégories existantes</h2>
    <?php if ($categories === []): ?>
        <p class="empty-state">Aucune catégorie n’a encore été créée.</p>
    <?php else: ?>
        <div class="catalog-list">
            <?php foreach ($categories as $category): ?>
                <article class="catalog-row">
                    <form class="catalog-edit-form" method="post" action="<?= e(site_url('admin/categories.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="category_id" value="<?= e($category['id']) ?>">
                        <label class="form-field">
                            <span>Nom</span>
                            <input type="text" name="name" maxlength="120" value="<?= e($category['name']) ?>" required>
                        </label>
                        <label class="form-field order-field">
                            <span>Ordre</span>
                            <input type="number" name="display_order" min="0" max="4294967295" step="1" value="<?= e($category['display_order']) ?>" required>
                        </label>
                        <div class="catalog-meta">
                            <span class="muted"><?= e($category['product_count']) ?> produit(s)</span>
                            <code><?= e($category['slug']) ?></code>
                        </div>
                        <button class="button button-secondary" type="submit">Enregistrer</button>
                    </form>
                    <form class="catalog-delete-form" method="post" action="<?= e(site_url('admin/categories.php')) ?>" data-confirm="Supprimer cette catégorie ?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?= e($category['id']) ?>">
                        <button class="button button-danger" type="submit">Supprimer</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-card">
    <h2>Ajouter une catégorie</h2>
    <form class="form-grid category-add-form" method="post" action="<?= e(site_url('admin/categories.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_category">
        <label class="form-field">
            <span>Nom</span>
            <input type="text" name="name" maxlength="120" value="<?= e($newCategoryName) ?>" required>
        </label>
        <label class="form-field">
            <span>Ordre d’affichage</span>
            <input type="number" name="display_order" min="0" max="4294967295" step="1" value="<?= e($newCategoryOrder) ?>" required>
        </label>
        <div class="form-actions">
            <button class="button button-primary" type="submit">Ajouter la catégorie</button>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
