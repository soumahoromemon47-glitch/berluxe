<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_admin();

$rawOrderId = $_GET['id'] ?? '';
$orderId = is_string($rawOrderId)
    ? filter_var($rawOrderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($orderId === false) {
    http_response_code(400);
    exit('Identifiant de commande invalide.');
}

$existsQuery = db()->prepare('SELECT id FROM orders WHERE id = :id LIMIT 1');
$existsQuery->execute(['id' => $orderId]);
if ($existsQuery->fetchColumn() === false) {
    http_response_code(404);
    exit('Commande introuvable.');
}

$errorMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } elseif (($_POST['action'] ?? '') !== 'update_status') {
        http_response_code(400);
        $errorMessage = 'Action non reconnue.';
    } else {
        $newStatus = $_POST['status'] ?? '';
        if (!is_string($newStatus) || !array_key_exists($newStatus, ORDER_STATUS_LABELS)) {
            $errorMessage = 'Choisis un statut de commande valide.';
        } else {
            $connection = db();
            $connection->beginTransaction();
            try {
                $orderLock = $connection->prepare(
                    'SELECT status, stock_deducted
                     FROM orders
                     WHERE id = :id
                     LIMIT 1
                     FOR UPDATE'
                );
                $orderLock->execute(['id' => $orderId]);
                $lockedOrder = $orderLock->fetch();
                if ($lockedOrder === false) {
                    throw new RuntimeException('La commande a disparu pendant sa mise à jour.');
                }

                $stockWasDeducted = (int) $lockedOrder['stock_deducted'] === 1;
                $deductStock = $newStatus === 'livree'
                    && $lockedOrder['status'] !== 'livree'
                    && !$stockWasDeducted;
                if ($deductStock) {
                    $itemQuery = $connection->prepare(
                        'SELECT product_id, quantity
                         FROM order_items
                         WHERE order_id = :order_id
                         ORDER BY product_id, id
                         FOR UPDATE'
                    );
                    $itemQuery->execute(['order_id' => $orderId]);
                    $orderLines = $itemQuery->fetchAll();
                    if ($orderLines === []) {
                        throw new InvalidArgumentException('Une commande sans produit ne peut pas être marquée livrée.');
                    }

                    $productQuantities = [];
                    foreach ($orderLines as $item) {
                        if ($item['product_id'] === null) {
                            continue;
                        }

                        $productId = filter_var((string) $item['product_id'], FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
                        ]);
                        $quantity = filter_var((string) $item['quantity'], FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 1, 'max_range' => ORDER_STOCK_MAX],
                        ]);
                        if ($productId === false || $quantity === false) {
                            throw new RuntimeException('Une ligne de commande contient un identifiant ou une quantité invalide.');
                        }

                        $currentQuantity = $productQuantities[$productId] ?? 0;
                        $productQuantities[$productId] = $quantity > ORDER_STOCK_MAX - $currentQuantity
                            ? ORDER_STOCK_MAX + 1
                            : $currentQuantity + $quantity;
                    }

                    if ($productQuantities !== []) {
                        $placeholders = [];
                        $parameters = [];
                        foreach (array_keys($productQuantities) as $index => $productId) {
                            $parameter = 'product_' . $index;
                            $placeholders[] = ':' . $parameter;
                            $parameters[$parameter] = $productId;
                        }

                        $productLock = $connection->prepare(
                            'SELECT id, name, stock
                             FROM products
                             WHERE id IN (' . implode(', ', $placeholders) . ')
                             ORDER BY id
                             FOR UPDATE'
                        );
                        $productLock->execute($parameters);
                        $productsById = [];
                        foreach ($productLock->fetchAll() as $product) {
                            $productsById[(int) $product['id']] = $product;
                        }

                        $stockUpdate = $connection->prepare(
                            'UPDATE products
                             SET stock = stock - :quantity
                             WHERE id = :id AND stock >= :required_quantity'
                        );
                        foreach ($productQuantities as $productId => $quantity) {
                            if (!isset($productsById[$productId])) {
                                throw new RuntimeException('Un produit lié à la commande est introuvable.');
                            }

                            $product = $productsById[$productId];
                            $availableStock = (int) $product['stock'];
                            if ($quantity > $availableStock) {
                                throw new InvalidArgumentException(
                                    'Stock insuffisant pour « ' . $product['name'] . ' » : '
                                    . $availableStock . ' disponible(s), ' . $quantity
                                    . ' requis. Corrige le stock dans le catalogue avant de marquer cette commande livrée.'
                                );
                            }

                            $stockUpdate->execute([
                                'quantity' => $quantity,
                                'id' => $productId,
                                'required_quantity' => $quantity,
                            ]);
                            if ($stockUpdate->rowCount() !== 1) {
                                throw new RuntimeException('Le stock a changé pendant la mise à jour de la commande.');
                            }
                        }
                    }
                }

                $stockDeducted = $stockWasDeducted || $deductStock;
                $statusUpdate = $connection->prepare(
                    'UPDATE orders
                     SET status = :status, stock_deducted = :stock_deducted
                     WHERE id = :id'
                );
                $statusUpdate->execute([
                    'status' => $newStatus,
                    'stock_deducted' => $stockDeducted ? 1 : 0,
                    'id' => $orderId,
                ]);
                $connection->commit();
            } catch (InvalidArgumentException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                $errorMessage = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            } catch (RuntimeException $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            }

            if ($errorMessage === null) {
                $successMessage = $deductStock
                    ? 'La commande est livrée et le stock a été déduit une seule fois.'
                    : 'Le statut de la commande a été mis à jour.';
                set_admin_flash('success', $successMessage);
                admin_redirect('admin/commande.php?id=' . $orderId);
            }
        }
    }
}

$orderQuery = db()->prepare(
    'SELECT id, order_number, customer_last_name, customer_first_name,
            customer_phone, commune_name_snapshot, delivery_zone,
            address_details, subtotal, delivery_fee, total, status,
            stock_deducted, created_at, updated_at
     FROM orders
     WHERE id = :id
     LIMIT 1'
);
$orderQuery->execute(['id' => $orderId]);
$order = $orderQuery->fetch();
if ($order === false) {
    http_response_code(404);
    exit('Commande introuvable.');
}

$itemsQuery = db()->prepare(
    'SELECT oi.product_id, oi.product_name_snapshot, oi.variant_label_snapshot,
            oi.quantity, oi.applied_price, oi.price_type, oi.is_promotion,
            p.stock AS current_stock
     FROM order_items AS oi
     LEFT JOIN products AS p ON p.id = oi.product_id
     WHERE oi.order_id = :order_id
     ORDER BY oi.id'
);
$itemsQuery->execute(['order_id' => $orderId]);
$orderItems = $itemsQuery->fetchAll();

$pageTitle = 'Commande ' . (string) $order['order_number'];
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="order-detail-page">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Gestion des commandes</p>
            <h1>Commande <?= e($order['order_number']) ?></h1>
            <p class="form-intro">
                Créée le <?= e((new DateTimeImmutable((string) $order['created_at']))->format('d/m/Y à H:i')) ?>
            </p>
        </div>
        <div class="order-actions print-hidden">
            <a class="button button-secondary" href="<?= e(site_url('admin/commandes.php')) ?>">Retour aux commandes</a>
            <button class="button button-secondary" type="button" data-print>Imprimer / Enregistrer en PDF</button>
        </div>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="admin-card order-status-card">
        <div class="order-status-heading">
            <div>
                <p class="dashboard-label">Statut actuel</p>
                <span class="status-badge status-<?= e($order['status']) ?>"><?= e(order_status_label((string) $order['status'])) ?></span>
            </div>
            <p class="stock-deduction-note">
                Stock <?= (int) $order['stock_deducted'] === 1 ? 'déjà déduit' : 'pas encore déduit' ?>.
                <?php if ((int) $order['stock_deducted'] === 1): ?>
                    Une annulation ultérieure ne rétablit pas le stock.
                <?php else: ?>
                    La déduction intervient uniquement au passage au statut « Livrée ».
                <?php endif; ?>
            </p>
        </div>
        <form class="order-status-form print-hidden" method="post" action="<?= e(site_url('admin/commande.php?id=' . $orderId)) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_status">
            <label class="form-field">
                <span>Changer le statut</span>
                <select name="status" required>
                    <?php foreach (ORDER_STATUS_LABELS as $status => $label): ?>
                        <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary" type="submit">Enregistrer le statut</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Informations client et livraison</h2>
        <dl class="order-info-grid">
            <div><dt>Nom</dt><dd><?= e($order['customer_last_name']) ?></dd></div>
            <div><dt>Prénom</dt><dd><?= e($order['customer_first_name']) ?></dd></div>
            <div><dt>Téléphone WhatsApp</dt><dd><a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a></dd></div>
            <div><dt>Commune</dt><dd><?= e($order['commune_name_snapshot']) ?> — <?= $order['delivery_zone'] === 'abidjan' ? 'Abidjan' : 'Hors Abidjan' ?></dd></div>
            <div class="order-address"><dt>Précisions d’adresse</dt><dd><?= nl2br(e($order['address_details'])) ?></dd></div>
        </dl>
    </section>

    <section class="admin-card">
        <h2>Produits commandés</h2>
        <?php if ($orderItems === []): ?>
            <p class="empty-state">Cette commande ne contient aucune ligne.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Prix appliqué</th>
                            <th>Type de prix</th>
                            <th>Total ligne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <?php $lineTotal = (int) $item['applied_price'] * (int) $item['quantity']; ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['product_name_snapshot']) ?></strong>
                                    <?php if ($item['variant_label_snapshot'] !== null): ?>
                                        <br><span class="muted">Variante : <?= e($item['variant_label_snapshot']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['product_id'] === null): ?>
                                        <br><span class="muted">Produit supprimé du catalogue; aucun stock associé.</span>
                                    <?php else: ?>
                                        <br><span class="muted">Stock actuel : <?= e($item['current_stock']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($item['quantity']) ?></td>
                                <td><?= e(format_price($item['applied_price'])) ?><?= (int) $item['is_promotion'] === 1 ? ' · promotion' : '' ?></td>
                                <td><?= $item['price_type'] === 'gros' ? 'Gros' : 'Détail' ?></td>
                                <td><?= e(format_price($lineTotal)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card order-totals-card">
        <h2>Montants</h2>
        <dl class="order-totals">
            <div><dt>Sous-total</dt><dd><?= e(format_price($order['subtotal'])) ?></dd></div>
            <div><dt>Frais de livraison</dt><dd><?= e(format_price($order['delivery_fee'])) ?></dd></div>
            <div class="order-grand-total"><dt>Total</dt><dd><?= e(format_price($order['total'])) ?></dd></div>
        </dl>
        <p class="muted">Paiement à la livraison.</p>
    </section>

    <p class="print-only">Commande <?= e($order['order_number']) ?> — Imprimée le <?= e((new DateTimeImmutable())->format('d/m/Y à H:i')) ?></p>
</div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
