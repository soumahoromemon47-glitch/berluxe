<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require_once dirname(__DIR__) . '/includes/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$action = $_POST['action'] ?? '';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$isAjaxAddRequest = is_string($action)
    && $action === 'add'
    && is_string($acceptHeader)
    && stripos($acceptHeader, 'application/json') !== false;
$respondWithJson = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
};

if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    $message = 'La demande a expiré. Recharge la page et réessaie.';
    if ($isAjaxAddRequest) {
        $respondWithJson([
            'success' => false,
            'message' => $message,
            'count' => cart_total_quantity(),
        ], 403);
    }

    set_cart_flash('error', $message);
    cart_redirect('public/panier.php');
}

$parsePositiveInteger = static function (mixed $value, int $maximum): int|false {
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => $maximum],
    ]);
};

$rawProductId = $_POST['product_id'] ?? null;
$productId = $parsePositiveInteger($rawProductId, PHP_INT_MAX);
$rawVariantId = $_POST['variant_id'] ?? '';
$variantId = $rawVariantId === ''
    ? null
    : $parsePositiveInteger($rawVariantId, PHP_INT_MAX);

$actionError = null;
try {
    if (!is_string($action) || $productId === false || $variantId === false) {
        throw new InvalidArgumentException('Les informations de l’article sont invalides.');
    }

    if ($action === 'add') {
        $rawQuantity = $_POST['quantity'] ?? null;
        $quantity = $parsePositiveInteger($rawQuantity, CART_MAX_QUANTITY);
        if ($quantity === false) {
            throw new InvalidArgumentException('Saisis une quantité entière supérieure à zéro.');
        }

        $productQuery = db()->prepare(
            'SELECT id, is_unavailable
             FROM products
             WHERE id = :id
             LIMIT 1'
        );
        $productQuery->execute(['id' => $productId]);
        $product = $productQuery->fetch();
        if ($product === false) {
            throw new InvalidArgumentException('Ce produit n’existe plus.');
        }
        if ((int) $product['is_unavailable'] === 1) {
            throw new InvalidArgumentException('Ce produit est actuellement indisponible.');
        }

        $variantQuery = db()->prepare(
            'SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             ORDER BY display_order, id'
        );
        $variantQuery->execute(['product_id' => $productId]);
        $productVariantIds = array_map('intval', $variantQuery->fetchAll(PDO::FETCH_COLUMN));
        if ($productVariantIds !== [] && $variantId === null) {
            throw new InvalidArgumentException('Choisis une option pour ce produit.');
        }
        if ($variantId !== null && !in_array($variantId, $productVariantIds, true)) {
            throw new InvalidArgumentException('Cette option ne correspond pas au produit sélectionné.');
        }

        cart_add_line($productId, $variantId, $quantity);
        if (!$isAjaxAddRequest) {
            set_cart_flash('success', 'Le produit a été ajouté au panier.');
        }
    } elseif ($action === 'update') {
        $rawQuantity = $_POST['quantity'] ?? null;
        $quantity = $parsePositiveInteger($rawQuantity, CART_MAX_QUANTITY);
        if ($quantity === false) {
            throw new InvalidArgumentException('Saisis une quantité entière supérieure à zéro.');
        }

        cart_update_line($productId, $variantId, $quantity);
        set_cart_flash('success', 'La quantité a été mise à jour.');
    } elseif ($action === 'remove') {
        cart_remove_line($productId, $variantId);
        set_cart_flash('success', 'Le produit a été retiré du panier.');
    } else {
        throw new InvalidArgumentException('Cette action sur le panier est inconnue.');
    }
} catch (InvalidArgumentException $exception) {
    if ($isAjaxAddRequest) {
        $actionError = $exception->getMessage();
    } else {
        set_cart_flash('error', $exception->getMessage());
    }
}

if ($isAjaxAddRequest) {
    if ($actionError !== null) {
        $respondWithJson([
            'success' => false,
            'message' => $actionError,
            'count' => cart_total_quantity(),
        ], 422);
    }

    $respondWithJson([
        'success' => true,
        'message' => 'Le produit a été ajouté au panier.',
        'count' => cart_total_quantity(),
    ]);
}

cart_redirect('public/panier.php');
