<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_admin();

$rawProductId = $_GET['id'] ?? '';
$productId = null;
if ($rawProductId !== '') {
    $productId = filter_var($rawProductId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($productId === false) {
        http_response_code(400);
        exit('Identifiant de produit invalide.');
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY display_order, name')->fetchAll();
$product = [
    'category_id' => isset($categories[0]) ? (string) $categories[0]['id'] : '',
    'name' => '',
    'description' => '',
    'retail_price' => '',
    'wholesale_price' => '',
    'wholesale_min_quantity' => '1',
    'promotion_price' => '',
    'stock' => '0',
    'is_featured' => 0,
    'is_on_promotion' => 0,
    'is_unavailable' => 0,
];
$existingImages = [];
$variantText = '';

if ($productId !== null) {
    $productQuery = db()->prepare(
        'SELECT id, category_id, name, description, retail_price, wholesale_price,
                wholesale_min_quantity, promotion_price, stock,
                is_featured, is_on_promotion, is_unavailable
         FROM products
         WHERE id = :id'
    );
    $productQuery->execute(['id' => $productId]);
    $existingProduct = $productQuery->fetch();
    if ($existingProduct === false) {
        http_response_code(404);
        exit('Produit introuvable.');
    }

    $product = $existingProduct;
    foreach (['category_id', 'retail_price', 'wholesale_price', 'wholesale_min_quantity', 'promotion_price', 'stock'] as $field) {
        $product[$field] = $product[$field] === null ? '' : (string) $product[$field];
    }

    $imageQuery = db()->prepare(
        'SELECT id, file_name, alt_text, display_order
         FROM product_images
         WHERE product_id = :product_id
         ORDER BY display_order, id'
    );
    $imageQuery->execute(['product_id' => $productId]);
    $existingImages = $imageQuery->fetchAll();

    $variantQuery = db()->prepare(
        'SELECT label FROM product_variants WHERE product_id = :product_id ORDER BY display_order, id'
    );
    $variantQuery->execute(['product_id' => $productId]);
    $variantText = implode("\n", array_column($variantQuery->fetchAll(), 'label'));
}

$errorMessage = null;
$uploadedImages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } elseif (($_POST['action'] ?? '') !== 'save_product') {
        http_response_code(400);
        $errorMessage = 'Action non reconnue.';
    } else {
        foreach (['name', 'description', 'category_id', 'retail_price', 'wholesale_price', 'wholesale_min_quantity', 'promotion_price', 'stock'] as $field) {
            $rawValue = $_POST[$field] ?? '';
            $product[$field] = is_string($rawValue) ? trim($rawValue) : '';
        }
        $product['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        $product['is_on_promotion'] = isset($_POST['is_on_promotion']) ? 1 : 0;
        $product['is_unavailable'] = isset($_POST['is_unavailable']) ? 1 : 0;

        $rawVariants = $_POST['variants'] ?? '';
        $variantText = is_string($rawVariants) ? $rawVariants : '';
        $validationErrors = [];

        if (preg_match('/^.{1,180}$/us', $product['name']) !== 1) {
            $validationErrors[] = 'Saisis un nom de produit (180 caractères maximum).';
        }
        if (strlen($product['description']) > 20000 || preg_match('//u', $product['description']) !== 1) {
            $validationErrors[] = 'La description est trop longue ou contient un texte invalide.';
        }

        $categoryId = filter_var($product['category_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($categoryId === false) {
            $validationErrors[] = 'Choisis une catégorie.';
        } else {
            $categoryCheck = db()->prepare('SELECT id FROM categories WHERE id = :id');
            $categoryCheck->execute(['id' => $categoryId]);
            if ($categoryCheck->fetchColumn() === false) {
                $validationErrors[] = 'La catégorie sélectionnée n’existe plus.';
            }
        }

        $parseInteger = static function (string $value, int $minimum, int $maximum): int|false {
            if (preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
                return false;
            }

            return filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => $minimum, 'max_range' => $maximum],
            ]);
        };
        $retailPrice = $parseInteger($product['retail_price'], 1, 999999999999);
        $wholesalePrice = $parseInteger($product['wholesale_price'], 1, 999999999999);
        $wholesaleMinQuantity = $parseInteger($product['wholesale_min_quantity'], 1, 4294967295);
        $stock = $parseInteger($product['stock'], 0, 4294967295);

        if ($retailPrice === false) {
            $validationErrors[] = 'Le prix détail doit être un nombre entier supérieur à zéro.';
        }
        if ($wholesalePrice === false) {
            $validationErrors[] = 'Le prix de gros doit être un nombre entier supérieur à zéro.';
        }
        if ($wholesaleMinQuantity === false) {
            $validationErrors[] = 'Le seuil de gros doit être un nombre entier supérieur à zéro.';
        }
        if ($stock === false) {
            $validationErrors[] = 'Le stock doit être un nombre entier positif ou nul.';
        }

        $promotionPrice = null;
        if ((int) $product['is_on_promotion'] === 1) {
            $promotionPrice = $parseInteger($product['promotion_price'], 1, 999999999999);
            if ($promotionPrice === false) {
                $validationErrors[] = 'Saisis un prix promotionnel entier supérieur à zéro.';
            } elseif ($retailPrice !== false && $promotionPrice >= $retailPrice) {
                $validationErrors[] = 'Le prix promotionnel doit être inférieur au prix détail.';
            }
        } else {
            $product['promotion_price'] = '';
        }

        $variantLabels = [];
        $variantLines = preg_split('/\R/u', $variantText);
        if ($variantLines === false) {
            $validationErrors[] = 'La liste des variantes est invalide.';
        } else {
            foreach ($variantLines as $variantLine) {
                $label = trim($variantLine);
                if ($label === '') {
                    continue;
                }
                if (preg_match('/^.{1,180}$/us', $label) !== 1) {
                    $validationErrors[] = 'Chaque variante doit contenir au maximum 180 caractères.';
                    break;
                }
                if (in_array($label, $variantLabels, true)) {
                    $validationErrors[] = 'Chaque variante ne peut apparaître qu’une seule fois.';
                    break;
                }
                $variantLabels[] = $label;
            }
        }

        $rawRemoveIds = $_POST['remove_images'] ?? [];
        $removeImageIds = [];
        if (!is_array($rawRemoveIds)) {
            $validationErrors[] = 'La sélection des photos à retirer est invalide.';
        } else {
            foreach ($rawRemoveIds as $rawRemoveId) {
                $removeId = filter_var($rawRemoveId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if ($removeId === false || $productId === null) {
                    $validationErrors[] = 'La sélection des photos à retirer est invalide.';
                    break;
                }
                $removeImageIds[] = (int) $removeId;
            }
            $removeImageIds = array_values(array_unique($removeImageIds));
        }

        if ($productId !== null && $removeImageIds !== []) {
            $existingImageIds = array_map('intval', array_column($existingImages, 'id'));
            if (array_diff($removeImageIds, $existingImageIds) !== []) {
                $validationErrors[] = 'Une photo sélectionnée n’appartient pas à ce produit.';
            }
        }

        try {
            $uploadedImages = collect_product_image_uploads($_FILES['images'] ?? []);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $validationErrors[] = $exception->getMessage();
        }

        if ($validationErrors !== []) {
            $errorMessage = implode(' ', array_unique($validationErrors));
        } else {
            $connection = db();
            $removedFileNames = [];
            if ($removeImageIds !== []) {
                foreach ($existingImages as $image) {
                    if (in_array((int) $image['id'], $removeImageIds, true)) {
                        $removedFileNames[] = $image['file_name'];
                    }
                }
            }

            $storedFileNames = [];
            try {
                foreach ($uploadedImages as $image) {
                    $storedFileNames[] = store_resized_product_image($image);
                }
            } catch (InvalidArgumentException | RuntimeException $exception) {
                delete_product_image_files($storedFileNames);
                $errorMessage = $exception->getMessage();
            }

            if ($errorMessage === null) {
                $connection->beginTransaction();
                try {
                    $slug = unique_catalog_slug($connection, 'products', $product['name'], $productId);
                    $productValues = [
                        'category_id' => $categoryId,
                        'name' => $product['name'],
                        'slug' => $slug,
                        'description' => $product['description'] === '' ? null : $product['description'],
                        'retail_price' => $retailPrice,
                        'wholesale_price' => $wholesalePrice,
                        'wholesale_min_quantity' => $wholesaleMinQuantity,
                        'promotion_price' => $promotionPrice === null ? null : $promotionPrice,
                        'stock' => $stock,
                        'is_featured' => $product['is_featured'],
                        'is_on_promotion' => $product['is_on_promotion'],
                        'is_unavailable' => $product['is_unavailable'],
                    ];

                    if ($productId === null) {
                        $save = $connection->prepare(
                            'INSERT INTO products
                                (category_id, name, slug, description, retail_price, wholesale_price,
                                 wholesale_min_quantity, promotion_price, stock, is_featured,
                                 is_on_promotion, is_unavailable)
                             VALUES
                                (:category_id, :name, :slug, :description, :retail_price, :wholesale_price,
                                 :wholesale_min_quantity, :promotion_price, :stock, :is_featured,
                                 :is_on_promotion, :is_unavailable)'
                        );
                        $save->execute($productValues);
                        $savedProductId = (int) $connection->lastInsertId();
                    } else {
                        $save = $connection->prepare(
                            'UPDATE products
                             SET category_id = :category_id,
                                 name = :name,
                                 slug = :slug,
                                 description = :description,
                                 retail_price = :retail_price,
                                 wholesale_price = :wholesale_price,
                                 wholesale_min_quantity = :wholesale_min_quantity,
                                 promotion_price = :promotion_price,
                                 stock = :stock,
                                 is_featured = :is_featured,
                                 is_on_promotion = :is_on_promotion,
                                 is_unavailable = :is_unavailable
                             WHERE id = :id'
                        );
                        $productValues['id'] = $productId;
                        $save->execute($productValues);
                        $savedProductId = $productId;
                    }

                    foreach ($removeImageIds as $removeId) {
                        $removeImage = $connection->prepare(
                            'DELETE FROM product_images
                             WHERE id = :id AND product_id = :product_id'
                        );
                        $removeImage->execute([
                            'id' => $removeId,
                            'product_id' => $savedProductId,
                        ]);
                    }

                    $removeVariants = $connection->prepare(
                        'DELETE FROM product_variants WHERE product_id = :product_id'
                    );
                    $removeVariants->execute(['product_id' => $savedProductId]);
                    $insertVariant = $connection->prepare(
                        'INSERT INTO product_variants (product_id, label, display_order)
                         VALUES (:product_id, :label, :display_order)'
                    );
                    foreach ($variantLabels as $order => $label) {
                        $insertVariant->execute([
                            'product_id' => $savedProductId,
                            'label' => $label,
                            'display_order' => $order,
                        ]);
                    }

                    $imageOrderQuery = $connection->prepare(
                        'SELECT COALESCE(MAX(display_order) + 1, 0)
                         FROM product_images
                         WHERE product_id = :product_id'
                    );
                    $imageOrderQuery->execute(['product_id' => $savedProductId]);
                    $imageOrder = (int) $imageOrderQuery->fetchColumn();
                    $insertImage = $connection->prepare(
                        'INSERT INTO product_images (product_id, file_name, display_order)
                         VALUES (:product_id, :file_name, :display_order)'
                    );
                    foreach ($storedFileNames as $fileName) {
                        $insertImage->execute([
                            'product_id' => $savedProductId,
                            'file_name' => $fileName,
                            'display_order' => $imageOrder,
                        ]);
                        $imageOrder++;
                    }

                    $connection->commit();
                } catch (PDOException $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    delete_product_image_files($storedFileNames);
                    if (($exception->errorInfo[1] ?? null) !== 1062) {
                        throw $exception;
                    }
                    $errorMessage = 'Une variante ou une adresse de produit en double empêche l’enregistrement.';
                }

                if ($errorMessage === null) {
                    $failedFiles = delete_product_image_files($removedFileNames);
                    if ($failedFiles === []) {
                        set_admin_flash(
                            'success',
                            $productId === null ? 'Le produit a été ajouté.' : 'Le produit a été mis à jour.'
                        );
                    } else {
                        set_admin_flash(
                            'error',
                            'Le produit a été enregistré, mais certaines anciennes photos n’ont pas pu être effacées.'
                        );
                    }
                    admin_redirect('admin/produits.php');
                }
            }
        }
    }
}

$pageTitle = $productId === null ? 'Ajouter un produit' : 'Modifier un produit';
$formAction = $productId === null
    ? site_url('admin/produit_form.php')
    : site_url('admin/produit_form.php?id=' . $productId);
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Catalogue</p>
        <h1><?= e($pageTitle) ?></h1>
    </div>
    <a class="button button-secondary" href="<?= e(site_url('admin/produits.php')) ?>">Retour aux produits</a>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<form class="admin-card product-form" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_product">

    <section class="form-section">
        <h2>Informations du produit</h2>
        <div class="form-grid">
            <label class="form-field">
                <span>Nom du produit</span>
                <input type="text" name="name" maxlength="180" value="<?= e($product['name']) ?>" required>
            </label>
            <label class="form-field">
                <span>Catégorie</span>
                <select name="category_id" required>
                    <option value="">Choisir une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= (string) $product['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($categories === []): ?>
                    <small>Crée d’abord une catégorie depuis la page Catégories.</small>
                <?php endif; ?>
            </label>
            <label class="form-field form-field-wide">
                <span>Description</span>
                <textarea name="description" rows="5" maxlength="20000"><?= e($product['description']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="form-section">
        <h2>Prix et stock</h2>
        <div class="form-grid">
            <label class="form-field">
                <span>Prix détail (FCFA)</span>
                <input type="number" name="retail_price" min="1" max="999999999999" step="1" value="<?= e($product['retail_price']) ?>" required>
            </label>
            <label class="form-field">
                <span>Prix de gros (FCFA)</span>
                <input type="number" name="wholesale_price" min="1" max="999999999999" step="1" value="<?= e($product['wholesale_price']) ?>" required>
            </label>
            <label class="form-field">
                <span>Quantité minimale pour le prix de gros</span>
                <input type="number" name="wholesale_min_quantity" min="1" max="4294967295" step="1" value="<?= e($product['wholesale_min_quantity']) ?>" required>
            </label>
            <label class="form-field">
                <span>Stock en quantité</span>
                <input type="number" name="stock" min="0" max="4294967295" step="1" value="<?= e($product['stock']) ?>" required>
            </label>
            <label class="form-field">
                <span>Prix promotionnel (FCFA)</span>
                <input type="number" name="promotion_price" min="1" max="999999999999" step="1" value="<?= e($product['promotion_price']) ?>">
                <small>Utilisé seulement si la case « En promotion » est cochée.</small>
            </label>
        </div>
        <div class="checkbox-grid">
            <label class="checkbox-field">
                <input type="checkbox" name="is_featured" value="1" <?= (int) $product['is_featured'] === 1 ? 'checked' : '' ?>>
                <span>Produit vedette</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="is_on_promotion" value="1" <?= (int) $product['is_on_promotion'] === 1 ? 'checked' : '' ?>>
                <span>En promotion</span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="is_unavailable" value="1" <?= (int) $product['is_unavailable'] === 1 ? 'checked' : '' ?>>
                <span>Indisponible</span>
            </label>
        </div>
    </section>

    <section class="form-section">
        <h2>Variantes</h2>
        <label class="form-field">
            <span>Couleurs, modèles ou autres choix</span>
            <textarea name="variants" rows="4" placeholder="Une variante par ligne"><?= e($variantText) ?></textarea>
            <small>Laisse vide si le produit n’a pas de variante.</small>
        </label>
    </section>

    <section class="form-section">
        <h2>Photos</h2>
        <label class="form-field">
            <span>Ajouter des photos</span>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            <small>JPG, PNG ou WebP, 5 Mo maximum par photo. Les images seront redimensionnées à 1 600 px maximum.</small>
        </label>

        <?php if ($existingImages !== []): ?>
            <div class="existing-images">
                <?php foreach ($existingImages as $image): ?>
                    <label class="existing-image">
                        <img src="<?= e(site_url('uploads/products/' . rawurlencode((string) $image['file_name']))) ?>" alt="<?= e($image['alt_text'] ?? $product['name']) ?>">
                        <span class="checkbox-field">
                            <input type="checkbox" name="remove_images[]" value="<?= e($image['id']) ?>">
                            <span>Retirer cette photo</span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="form-actions">
        <button class="button button-primary" type="submit"><?= $productId === null ? 'Ajouter le produit' : 'Enregistrer les modifications' ?></button>
        <a class="button button-secondary" href="<?= e(site_url('admin/produits.php')) ?>">Annuler</a>
    </div>
</form>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
