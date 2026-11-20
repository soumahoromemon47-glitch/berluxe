<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$categories = db()->query(
    'SELECT id, name
     FROM categories
     ORDER BY display_order, name'
)->fetchAll();

$filterError = null;
$searchQuery = '';
$categoryId = null;
$minimumPrice = null;
$maximumPrice = null;
$availability = '';
$promotionOnly = false;

$rawSearch = $_GET['q'] ?? '';
if (!is_string($rawSearch)) {
    $filterError = 'La recherche est invalide.';
} else {
    $searchQuery = trim($rawSearch);
    if (preg_match('/^.{0,120}$/us', $searchQuery) !== 1) {
        $filterError = 'La recherche doit contenir au maximum 120 caractères.';
    }
}

$rawCategoryId = $_GET['category_id'] ?? '';
if (!is_string($rawCategoryId)) {
    $filterError = 'La catégorie sélectionnée est invalide.';
} elseif ($rawCategoryId !== '') {
    $parsedCategoryId = filter_var($rawCategoryId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $categoryIds = array_map('intval', array_column($categories, 'id'));
    if ($parsedCategoryId === false || !in_array($parsedCategoryId, $categoryIds, true)) {
        $filterError = 'La catégorie sélectionnée n’existe pas.';
    } else {
        $categoryId = $parsedCategoryId;
    }
}

$parsePriceFilter = static function (mixed $value): int|false|null {
    if ($value === '') {
        return null;
    }
    if (!is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 999999999999],
    ]);
};

$rawMinimumPrice = $_GET['min_price'] ?? '';
$rawMaximumPrice = $_GET['max_price'] ?? '';
$minimumPrice = $parsePriceFilter($rawMinimumPrice);
$maximumPrice = $parsePriceFilter($rawMaximumPrice);
if ($minimumPrice === false || $maximumPrice === false) {
    $filterError = 'Les limites de prix doivent être des montants entiers positifs.';
}
if ($minimumPrice !== null && $minimumPrice !== false
    && $maximumPrice !== null && $maximumPrice !== false
    && $minimumPrice > $maximumPrice
) {
    $filterError = 'Le prix minimum ne peut pas dépasser le prix maximum.';
}

$rawAvailability = $_GET['availability'] ?? '';
if (!is_string($rawAvailability) || !in_array($rawAvailability, ['', 'available', 'unavailable'], true)) {
    $filterError = 'Le filtre de disponibilité est invalide.';
} else {
    $availability = $rawAvailability;
}

$rawPromotion = $_GET['promotion'] ?? '';
if (!is_string($rawPromotion) || !in_array($rawPromotion, ['', '1'], true)) {
    $filterError = 'Le filtre de promotion est invalide.';
} else {
    $promotionOnly = $rawPromotion === '1';
}

$products = [];
if ($filterError === null) {
    $conditions = [];
    $parameters = [];
    $displayPriceSql = 'CASE
        WHEN p.is_on_promotion = 1 AND p.promotion_price IS NOT NULL
            THEN p.promotion_price
        ELSE p.retail_price
    END';

    if ($searchQuery !== '') {
        $literalSearch = strtr($searchQuery, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $conditions[] = "(p.name LIKE :search_name ESCAPE '!'
            OR p.description LIKE :search_description ESCAPE '!')";
        $parameters['search_name'] = '%' . $literalSearch . '%';
        $parameters['search_description'] = '%' . $literalSearch . '%';
    }
    if ($categoryId !== null) {
        $conditions[] = 'p.category_id = :category_id';
        $parameters['category_id'] = $categoryId;
    }
    if ($minimumPrice !== null) {
        $conditions[] = $displayPriceSql . ' >= :minimum_price';
        $parameters['minimum_price'] = $minimumPrice;
    }
    if ($maximumPrice !== null) {
        $conditions[] = $displayPriceSql . ' <= :maximum_price';
        $parameters['maximum_price'] = $maximumPrice;
    }
    if ($availability !== '') {
        $conditions[] = 'p.is_unavailable = :is_unavailable';
        $parameters['is_unavailable'] = $availability === 'unavailable' ? 1 : 0;
    }
    if ($promotionOnly) {
        $conditions[] = 'p.is_on_promotion = 1';
    }

    $sql = 'SELECT p.id, p.name, p.retail_price, p.wholesale_price,
                   p.wholesale_min_quantity, p.promotion_price,
                   p.is_featured, p.is_on_promotion, p.is_unavailable,
                   c.name AS category_name,
                   ' . $displayPriceSql . ' AS display_price,
                   (
                       SELECT image.file_name
                       FROM product_images AS image
                       WHERE image.product_id = p.id
                       ORDER BY image.display_order, image.id
                       LIMIT 1
                   ) AS cover_file_name
            FROM products AS p
            LEFT JOIN categories AS c ON c.id = p.category_id';
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY p.is_featured DESC, p.is_on_promotion DESC, p.created_at DESC, p.id DESC';

    $productQuery = db()->prepare($sql);
    $productQuery->execute($parameters);
    $products = $productQuery->fetchAll();
}

$variantsByProduct = [];
if ($products !== []) {
    $variantParameters = [];
    $productIdPlaceholders = [];
    foreach ($products as $index => $product) {
        $parameterName = 'product_id_' . $index;
        $productIdPlaceholders[] = ':' . $parameterName;
        $variantParameters[$parameterName] = (int) $product['id'];
    }

    $variantQuery = db()->prepare(
        'SELECT id, product_id, label
         FROM product_variants
         WHERE product_id IN (' . implode(', ', $productIdPlaceholders) . ')
         ORDER BY product_id, display_order, id'
    );
    $variantQuery->execute($variantParameters);
    foreach ($variantQuery->fetchAll() as $variant) {
        $variantsByProduct[(int) $variant['product_id']][] = $variant;
    }

    foreach ($products as &$product) {
        $product['variants'] = $variantsByProduct[(int) $product['id']] ?? [];
    }
    unset($product);
}

$showQuickAdd = true;
$pageTitle = 'Catalogue';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container storefront-main">
    <section class="storefront-heading">
        <p class="eyebrow">La sélection Berluxe</p>
        <h1>Catalogue</h1>
        <p class="muted">Trouve le produit qu’il te faut parmi nos catégories.</p>
    </section>

    <?php if ($filterError !== null): ?>
        <div class="notice notice-error" role="alert"><?= e($filterError) ?></div>
    <?php endif; ?>

    <?php if ($searchQuery === '' && $categoryId === null && $promotionOnly === false): ?>
        <section class="catalogue-banner" aria-label="Livraison et contact">
            <div class="catalogue-banner-content">
                <div class="catalogue-banner-text">
                    <p class="eyebrow">Découvrez Berluxe</p>
                    <h2>L'outillage et la maison,<br>au même endroit.</h2>
                    <p>Paiement à la livraison. Livraison à Abidjan et hors Abidjan.</p>
                </div>
                <div class="catalogue-banner-actions">
                    <a href="<?= e(site_url(CATALOGUE_PATH . '?promotion=1')) ?>" class="button button-primary">Voir les promos</a>
                    <a href="https://wa.me/2250102030405" target="_blank" rel="noopener" class="button button-secondary">Nous contacter</a>
                </div>
            </div>
        </section>
    <?php endif; ?>

        <div class="catalogue-toolbar">
        <p class="result-count"><?= count($products) ?> produit(s)</p>
        <button type="button" class="btn-open-filters" id="btn-open-filters" aria-controls="filters-drawer" aria-expanded="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            Filtrer
        </button>
    </div>

    <!-- L'overlay (fond sombre) -->
    <div class="filters-overlay" id="filters-overlay"></div>

    <!-- Le tiroir (drawer) -->
    <section class="filters-panel" id="filters-drawer" aria-label="Filtres du catalogue">
        <div class="filters-header">
            <h2>Filtres</h2>
            <button type="button" class="btn-close-filters" id="btn-close-filters" aria-label="Fermer les filtres">&times;</button>
        </div>

        <div class="filters-content">
            <form class="filters-grid" method="get" action="<?= e(site_url('public/catalogue.php')) ?>">
                <input type="hidden" name="q" value="<?= e($searchQuery) ?>">
                <label class="form-field">
                    <span>Catégorie</span>
                    <select name="category_id">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">
                    <span>Prix minimum (FCFA)</span>
                    <input type="number" name="min_price" min="0" max="999999999999" step="1" value="<?= $minimumPrice === null || $minimumPrice === false ? '' : e($minimumPrice) ?>">
                </label>
                <label class="form-field">
                    <span>Prix maximum (FCFA)</span>
                    <input type="number" name="max_price" min="0" max="999999999999" step="1" value="<?= $maximumPrice === null || $maximumPrice === false ? '' : e($maximumPrice) ?>">
                </label>
                <label class="form-field">
                    <span>Disponibilité</span>
                    <select name="availability">
                        <option value="">Tous les produits</option>
                        <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Disponibles</option>
                        <option value="unavailable" <?= $availability === 'unavailable' ? 'selected' : '' ?>>Indisponibles</option>
                    </select>
                </label>
                <label class="checkbox-field filter-promotion">
                    <input type="checkbox" name="promotion" value="1" <?= $promotionOnly ? 'checked' : '' ?>>
                    <span>En promotion</span>
                </label>
                <div class="filter-actions">
                    <button class="button button-primary" type="submit">Appliquer les filtres</button>
                    <a class="text-link" href="<?= e(site_url('public/catalogue.php')) ?>">Effacer</a>
                </div>
            </form>
        </div>
    </section>

    <section class="storefront-section" aria-label="Produits">
        <?php if ($filterError !== null): ?>
            <p class="empty-state">Corrige les filtres pour afficher le catalogue.</p>
        <?php elseif ($products === []): ?>
            <p class="empty-state">Aucun produit ne correspond à ces critères.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php require dirname(__DIR__) . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
