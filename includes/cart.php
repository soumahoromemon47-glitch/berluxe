<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const CART_MAX_QUANTITY = 4294967295;
const ORDER_AMOUNT_MAX = 999999999999;

function cart_item_key(int $productId, ?int $variantId): string
{
    return $productId . ':' . ($variantId ?? 0);
}

function cart_session_items(): array
{
    start_app_session();
    $items = $_SESSION['cart_items'] ?? [];

    if (!is_array($items)) {
        throw new RuntimeException('Le panier de cette session est invalide.');
    }

    $normalizedItems = [];
    foreach ($items as $item) {
        if (!is_array($item)
            || !isset($item['product_id'], $item['quantity'])
            || !is_int($item['product_id'])
            || $item['product_id'] < 1
            || !is_int($item['quantity'])
            || $item['quantity'] < 1
            || $item['quantity'] > CART_MAX_QUANTITY
        ) {
            throw new RuntimeException('Un article du panier est invalide.');
        }

        $variantId = $item['variant_id'] ?? null;
        if ($variantId !== null && (!is_int($variantId) || $variantId < 1)) {
            throw new RuntimeException('Une variante du panier est invalide.');
        }

        $key = cart_item_key($item['product_id'], $variantId);
        $normalizedItems[$key] = [
            'product_id' => $item['product_id'],
            'variant_id' => $variantId,
            'quantity' => $item['quantity'],
        ];
    }

    return $normalizedItems;
}

function cart_total_quantity(): int
{
    $total = 0;
    foreach (cart_session_items() as $item) {
        if ($item['quantity'] > PHP_INT_MAX - $total) {
            throw new RuntimeException('Le nombre d’articles du panier dépasse la limite autorisée.');
        }

        $total += $item['quantity'];
    }

    return $total;
}

function cart_add_line(int $productId, ?int $variantId, int $quantity): void
{
    $items = cart_session_items();
    $key = cart_item_key($productId, $variantId);
    $currentQuantity = $items[$key]['quantity'] ?? 0;

    if ($quantity < 1 || $quantity > CART_MAX_QUANTITY - $currentQuantity) {
        throw new InvalidArgumentException('La quantité demandée est invalide.');
    }

    $items[$key] = [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'quantity' => $currentQuantity + $quantity,
    ];
    $_SESSION['cart_items'] = $items;
}

function cart_update_line(int $productId, ?int $variantId, int $quantity): void
{
    $items = cart_session_items();
    $key = cart_item_key($productId, $variantId);

    if (!isset($items[$key])) {
        throw new InvalidArgumentException('Cet article ne figure plus dans le panier.');
    }
    if ($quantity < 1 || $quantity > CART_MAX_QUANTITY) {
        throw new InvalidArgumentException('La quantité demandée est invalide.');
    }

    $items[$key]['quantity'] = $quantity;
    $_SESSION['cart_items'] = $items;
}

function cart_remove_line(int $productId, ?int $variantId): void
{
    $items = cart_session_items();
    $key = cart_item_key($productId, $variantId);

    if (!isset($items[$key])) {
        throw new InvalidArgumentException('Cet article ne figure plus dans le panier.');
    }

    unset($items[$key]);
    $_SESSION['cart_items'] = $items;
}

function cart_amount(mixed $value, string $label): int
{
    if (!is_string($value) && !is_int($value)) {
        throw new RuntimeException('Le montant de ' . $label . ' est invalide.');
    }

    $amount = filter_var((string) $value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => ORDER_AMOUNT_MAX],
    ]);
    if ($amount === false) {
        throw new RuntimeException('Le montant de ' . $label . ' est invalide.');
    }

    return $amount;
}

function cart_price_for_product(array $product, int $quantity): array
{
    if ($quantity < 1 || $quantity > CART_MAX_QUANTITY) {
        throw new InvalidArgumentException('La quantité demandée est invalide.');
    }

    $priceType = $quantity >= (int) $product['wholesale_min_quantity'] ? 'gros' : 'detail';
    $isPromotion = $priceType === 'detail'
        && (int) $product['is_on_promotion'] === 1
        && $product['promotion_price'] !== null;

    if ($priceType === 'gros') {
        $unitPrice = cart_amount($product['wholesale_price'], 'gros');
    } elseif ($isPromotion) {
        $unitPrice = cart_amount($product['promotion_price'], 'promotionnel');
    } else {
        $unitPrice = cart_amount($product['retail_price'], 'détail');
    }

    $lineTotal = $unitPrice === 0 || $quantity <= intdiv(ORDER_AMOUNT_MAX, $unitPrice)
        ? $unitPrice * $quantity
        : null;

    return [
        'unit_price' => $unitPrice,
        'price_type' => $priceType,
        'is_promotion' => $isPromotion,
        'line_total' => $lineTotal,
    ];
}

function cart_catalog_lines(bool $forUpdate = false): array
{
    $cartItems = cart_session_items();
    if ($cartItems === []) {
        return [];
    }

    $productIds = array_values(array_unique(array_column($cartItems, 'product_id')));
    $placeholders = [];
    $parameters = [];
    foreach ($productIds as $index => $productId) {
        $parameter = 'product_' . $index;
        $placeholders[] = ':' . $parameter;
        $parameters[$parameter] = $productId;
    }
    $inClause = implode(', ', $placeholders);
    $lockClause = $forUpdate ? ' FOR UPDATE' : '';

    $productQuery = db()->prepare(
        'SELECT id, name, retail_price, wholesale_price, wholesale_min_quantity,
                promotion_price, is_on_promotion, is_unavailable
         FROM products
         WHERE id IN (' . $inClause . ')' . $lockClause
    );
    $productQuery->execute($parameters);
    $productsById = [];
    foreach ($productQuery->fetchAll() as $product) {
        $productsById[(int) $product['id']] = $product;
    }

    $variantQuery = db()->prepare(
        'SELECT id, product_id, label
         FROM product_variants
         WHERE product_id IN (' . $inClause . ')
         ORDER BY display_order, id' . $lockClause
    );
    $variantQuery->execute($parameters);
    $variantsByProduct = [];
    $variantsById = [];
    foreach ($variantQuery->fetchAll() as $variant) {
        $variant['id'] = (int) $variant['id'];
        $variant['product_id'] = (int) $variant['product_id'];
        $variantsByProduct[$variant['product_id']][] = $variant;
        $variantsById[$variant['id']] = $variant;
    }

    $lines = [];
    foreach ($cartItems as $key => $item) {
        $product = $productsById[$item['product_id']] ?? null;
        $productVariants = $variantsByProduct[$item['product_id']] ?? [];
        $variant = $item['variant_id'] === null
            ? null
            : ($variantsById[$item['variant_id']] ?? null);
        $variantMatchesProduct = $variant === null
            ? $item['variant_id'] === null && $productVariants === []
            : $variant['product_id'] === $item['product_id'];
        $isValid = $product !== null && $variantMatchesProduct;
        $isAvailable = $isValid && (int) $product['is_unavailable'] !== 1;
        $pricing = $isAvailable
            ? cart_price_for_product($product, $item['quantity'])
            : null;

        $lines[] = [
            'key' => $key,
            'product_id' => $item['product_id'],
            'variant_id' => $item['variant_id'],
            'quantity' => $item['quantity'],
            'product' => $product,
            'product_name' => $product === null ? 'Produit supprimé' : (string) $product['name'],
            'variant_label' => $variantMatchesProduct && $variant !== null
                ? (string) $variant['label']
                : null,
            'is_valid' => $isValid,
            'is_available' => $isAvailable,
            'pricing' => $pricing,
        ];
    }

    return $lines;
}

function cart_subtotal(array $lines): int|false
{
    $subtotal = 0;
    foreach ($lines as $line) {
        if (!$line['is_valid']
            || !$line['is_available']
            || !is_array($line['pricing'])
            || $line['pricing']['line_total'] === null
        ) {
            return false;
        }

        $lineTotal = $line['pricing']['line_total'];
        if ($lineTotal > ORDER_AMOUNT_MAX - $subtotal) {
            return false;
        }
        $subtotal += $lineTotal;
    }

    return $subtotal;
}

function set_cart_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error'], true)) {
        throw new InvalidArgumentException('Type de notification invalide.');
    }

    start_app_session();
    $_SESSION['cart_flash'] = ['type' => $type, 'message' => $message];
}

function consume_cart_flash(): ?array
{
    start_app_session();
    if (!isset($_SESSION['cart_flash'])) {
        return null;
    }

    $flash = $_SESSION['cart_flash'];
    unset($_SESSION['cart_flash']);

    return is_array($flash) ? $flash : null;
}

function cart_redirect(string $path): void
{
    header('Location: ' . site_url($path), true, 303);
    exit;
}
