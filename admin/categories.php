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
<div class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow">Catalogue</p>
        <h1>Catégories</h1>
        <p class="muted">Organise les familles de produits et leur ordre d’affichage.</p>
    </div>
    <button class="button button-primary" id="btn-toggle-add-category" type="button">
        + Nouvelle catégorie
    </button>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="admin-card">
    <h2 class="admin-card-title">Catégories existantes (<?= count($categories) ?>)</h2>
    <?php if ($categories === []): ?>
        <p class="empty-state">Aucune catégorie n’a encore été créée.</p>
    <?php else: ?>
        <div class="category-admin-list">
            <?php foreach ($categories as $category): ?>
                <div class="category-admin-item">
                    <div class="category-admin-info">
                        <h3><?= e($category['name']) ?></h3>
                        <p class="muted">
                            <?= e($category['product_count']) ?> produit(s) · Ordre : <?= e($category['display_order']) ?>
                        </p>
                        <code><?= e($category['slug']) ?></code>
                    </div>

                    <div class="category-admin-actions">
                        <button class="button-icon-action edit" type="button" data-toggle-edit="<?= e($category['id']) ?>" title="Modifier" aria-label="Modifier <?= e($category['name']) ?>" aria-expanded="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <form method="post" action="<?= e(site_url('admin/categories.php')) ?>" data-confirm="Supprimer cette catégorie ?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="category_id" value="<?= e($category['id']) ?>">
                            <button class="button-icon-action delete" type="submit" title="Supprimer" aria-label="Supprimer <?= e($category['name']) ?>" <?= ((int) $category['product_count'] > 0) ? 'disabled' : '' ?>>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>

                    <form class="category-edit-form" id="edit-form-<?= e($category['id']) ?>" method="post" action="<?= e(site_url('admin/categories.php')) ?>" hidden>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="category_id" value="<?= e($category['id']) ?>">
                        <label class="form-field">
                            <span>Nom</span>
                            <input type="text" name="name" maxlength="120" value="<?= e($category['name']) ?>" required>
                        </label>
                        <label class="form-field">
                            <span>Ordre d'affichage</span>
                            <input type="number" name="display_order" min="0" max="4294967295" step="1" value="<?= e($category['display_order']) ?>" required>
                        </label>
                        <div class="form-actions">
                            <button class="button button-primary" type="submit">Enregistrer</button>
                            <button class="button button-secondary" type="button" data-toggle-edit="<?= e($category['id']) ?>">Annuler</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-card" id="add-category-section" hidden>
    <h2 class="admin-card-title">Ajouter une catégorie</h2>
    <form class="form-grid category-add-form" method="post" action="<?= e(site_url('admin/categories.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_category">
        <label class="form-field">
            <span>Nom</span>
            <input type="text" name="name" maxlength="120" value="<?= e($newCategoryName) ?>" required placeholder="Ex : Jardinage">
        </label>
        <label class="form-field">
            <span>Ordre d’affichage</span>
            <input type="number" name="display_order" min="0" max="4294967295" step="1" value="<?= e($newCategoryOrder) ?>" required>
        </label>
        <div class="form-actions">
            <button class="button button-primary" type="submit">Ajouter</button>
            <button class="button button-secondary" type="button" id="btn-cancel-add-category">Annuler</button>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addSection = document.getElementById('add-category-section');
    const addButton = document.getElementById('btn-toggle-add-category');
    const cancelAddButton = document.getElementById('btn-cancel-add-category');

    const setAddSectionOpen = (open) => {
        if (!addSection || !addButton) return;
        addSection.hidden = !open;
        addButton.setAttribute('aria-expanded', String(open));
        if (open) addSection.querySelector('input[name="name"]')?.focus();
    };

    addButton?.addEventListener('click', () => setAddSectionOpen(addSection.hidden));
    cancelAddButton?.addEventListener('click', () => setAddSectionOpen(false));

    document.querySelectorAll('[data-toggle-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const editForm = document.getElementById(`edit-form-${button.dataset.toggleEdit}`);
            if (!editForm) return;
            const open = editForm.hidden;
            editForm.hidden = !open;
            document.querySelectorAll(`[data-toggle-edit="${button.dataset.toggleEdit}"]`).forEach((control) => {
                control.setAttribute('aria-expanded', String(open));
            });
            if (open) editForm.querySelector('input[name="name"]')?.focus();
        });
    });
});
</script>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
