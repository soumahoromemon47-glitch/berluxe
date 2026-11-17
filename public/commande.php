<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cart.php';

start_app_session();
$cartLines = cart_catalog_lines();
if ($cartLines === []) {
    set_cart_flash('error', 'Ajoute au moins un produit avant de passer commande.');
    cart_redirect('public/panier.php');
}

$cartSubtotal = cart_subtotal($cartLines);
$communes = db()->query(
    'SELECT id, name, zone
     FROM communes
     WHERE is_active = 1
     ORDER BY zone, display_order, name'
)->fetchAll();
$communesById = [];
foreach ($communes as $commune) {
    $communesById[(int) $commune['id']] = $commune;
}

$settings = site_settings();
$deliveryFees = [
    'abidjan' => cart_amount(required_setting($settings, 'delivery_fee_abidjan'), 'livraison à Abidjan'),
    'hors_abidjan' => cart_amount(required_setting($settings, 'delivery_fee_outside_abidjan'), 'livraison hors Abidjan'),
];

if (!isset($_SESSION['checkout_token']) || !is_string($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
$checkoutToken = $_SESSION['checkout_token'];
$errorMessage = null;
$formValues = [
    'customer_last_name' => '',
    'customer_first_name' => '',
    'customer_phone' => '',
    'commune_id' => '',
    'address_details' => '',
];
$selectedCommune = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $field => $defaultValue) {
        $rawValue = $_POST[$field] ?? $defaultValue;
        $formValues[$field] = is_string($rawValue) ? trim($rawValue) : '';
    }

    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } elseif (!is_string($_POST['checkout_token'] ?? null)
        || !hash_equals($checkoutToken, $_POST['checkout_token'])
    ) {
        http_response_code(400);
        $errorMessage = 'Cette commande a déjà été envoyée ou le formulaire a expiré. Recharge la page.';
    } else {
        $validationErrors = [];
        if (preg_match('/^[^\r\n]{1,100}$/u', $formValues['customer_last_name']) !== 1) {
            $validationErrors[] = 'Saisis un nom de 100 caractères maximum.';
        }
        if (preg_match('/^[^\r\n]{1,100}$/u', $formValues['customer_first_name']) !== 1) {
            $validationErrors[] = 'Saisis un prénom de 100 caractères maximum.';
        }

        $phoneDigits = preg_replace('/\D/', '', $formValues['customer_phone']);
        if (preg_match('/^\+?[0-9][0-9\s().-]*[0-9]$/D', $formValues['customer_phone']) !== 1
            || !is_string($phoneDigits)
            || strlen($phoneDigits) < 8
            || strlen($phoneDigits) > 15
        ) {
            $validationErrors[] = 'Saisis un numéro WhatsApp valide (8 à 15 chiffres, indicatif international facultatif).';
        }

        if (preg_match('/^.{1,2000}$/us', $formValues['address_details']) !== 1) {
            $validationErrors[] = 'Les précisions d’adresse sont obligatoires (2 000 caractères maximum).';
        }

        $parsedCommuneId = filter_var($formValues['commune_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($parsedCommuneId === false || !isset($communesById[$parsedCommuneId])) {
            $validationErrors[] = 'Choisis une commune de livraison valide.';
        } else {
            $selectedCommune = $communesById[$parsedCommuneId];
        }

        if ($cartSubtotal === false) {
            $validationErrors[] = 'Un article du panier est indisponible ou le montant doit être corrigé.';
        } elseif ($selectedCommune !== null
            && $deliveryFees[$selectedCommune['zone']] > ORDER_AMOUNT_MAX - $cartSubtotal
        ) {
            $validationErrors[] = 'Le total de la commande dépasse la limite autorisée.';
        }

        if ($validationErrors !== []) {
            $errorMessage = implode(' ', $validationErrors);
        } else {
            $connection = db();
            $connection->beginTransaction();
            try {
                $communeQuery = $connection->prepare(
                    'SELECT id, name, zone
                     FROM communes
                     WHERE id = :id AND is_active = 1
                     LIMIT 1
                     FOR UPDATE'
                );
                $communeQuery->execute(['id' => $parsedCommuneId]);
                $lockedCommune = $communeQuery->fetch();
                if ($lockedCommune === false) {
                    throw new InvalidArgumentException('Cette commune n’est plus disponible. Choisis-en une autre.');
                }

                $orderLines = cart_catalog_lines(true);
                $subtotal = cart_subtotal($orderLines);
                if ($orderLines === [] || $subtotal === false) {
                    throw new InvalidArgumentException('Un article du panier a changé ou n’est plus disponible. Vérifie ton panier.');
                }

                $feeSettingKey = $lockedCommune['zone'] === 'abidjan'
                    ? 'delivery_fee_abidjan'
                    : 'delivery_fee_outside_abidjan';
                $feeQuery = $connection->prepare(
                    'SELECT setting_value
                     FROM settings
                     WHERE setting_key = :setting_key
                     LIMIT 1
                     FOR UPDATE'
                );
                $feeQuery->execute(['setting_key' => $feeSettingKey]);
                $feeValue = $feeQuery->fetchColumn();
                if ($feeValue === false) {
                    throw new RuntimeException('Le réglage des frais de livraison est absent.');
                }
                $deliveryFee = cart_amount($feeValue, 'livraison');
                if ($deliveryFee > ORDER_AMOUNT_MAX - $subtotal) {
                    throw new InvalidArgumentException('Le total de la commande dépasse la limite autorisée.');
                }
                $total = $subtotal + $deliveryFee;
                $orderNumber = 'BLX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));

                $orderInsert = $connection->prepare(
                    'INSERT INTO orders (
                        order_number, customer_last_name, customer_first_name, customer_phone,
                        commune_id, commune_name_snapshot, delivery_zone, address_details,
                        subtotal, delivery_fee, total
                    ) VALUES (
                        :order_number, :customer_last_name, :customer_first_name, :customer_phone,
                        :commune_id, :commune_name_snapshot, :delivery_zone, :address_details,
                        :subtotal, :delivery_fee, :total
                    )'
                );
                $orderInsert->execute([
                    'order_number' => $orderNumber,
                    'customer_last_name' => $formValues['customer_last_name'],
                    'customer_first_name' => $formValues['customer_first_name'],
                    'customer_phone' => preg_replace('/[\s().-]/', '', $formValues['customer_phone']),
                    'commune_id' => $lockedCommune['id'],
                    'commune_name_snapshot' => $lockedCommune['name'],
                    'delivery_zone' => $lockedCommune['zone'],
                    'address_details' => $formValues['address_details'],
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                ]);
                $orderId = (int) $connection->lastInsertId();

                $itemInsert = $connection->prepare(
                    'INSERT INTO order_items (
                        order_id, product_id, product_name_snapshot, variant_label_snapshot,
                        quantity, applied_price, price_type, is_promotion
                    ) VALUES (
                        :order_id, :product_id, :product_name_snapshot, :variant_label_snapshot,
                        :quantity, :applied_price, :price_type, :is_promotion
                    )'
                );
                foreach ($orderLines as $line) {
                    $itemInsert->execute([
                        'order_id' => $orderId,
                        'product_id' => $line['product_id'],
                        'product_name_snapshot' => $line['product']['name'],
                        'variant_label_snapshot' => $line['variant_label'],
                        'quantity' => $line['quantity'],
                        'applied_price' => $line['pricing']['unit_price'],
                        'price_type' => $line['pricing']['price_type'],
                        'is_promotion' => $line['pricing']['is_promotion'] ? 1 : 0,
                    ]);
                }

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
                $_SESSION['cart_items'] = [];
                $_SESSION['last_order_number'] = $orderNumber;
                unset($_SESSION['checkout_token']);
                cart_redirect('public/confirmation.php?number=' . rawurlencode($orderNumber));
            }
        }
    }
}

$deliveryFeePreview = $selectedCommune === null
    ? null
    : $deliveryFees[$selectedCommune['zone']];
$estimatedTotal = $deliveryFeePreview !== null
    && $cartSubtotal !== false
    && $deliveryFeePreview <= ORDER_AMOUNT_MAX - $cartSubtotal
    ? $cartSubtotal + $deliveryFeePreview
    : null;
$estimatedTotalLabel = $estimatedTotal === null
    ? ($selectedCommune === null ? 'Choisis une commune' : 'Montant trop élevé')
    : format_price($estimatedTotal);
$pageTitle = 'Finaliser la commande';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container storefront-main">
    <section class="storefront-heading">
        <p class="eyebrow">Paiement à la livraison</p>
        <h1>Finaliser la commande</h1>
        <p class="muted">Aucun compte ni paiement en ligne n’est nécessaire. Roxane confirmera la disponibilité par téléphone.</p>
    </section>

    <?php if ($errorMessage !== null): ?>
        <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($cartSubtotal === false): ?>
        <div class="notice notice-error" role="alert">
            Ton panier contient un article indisponible ou dont le montant doit être corrigé.
            <a class="text-link" href="<?= e(site_url('public/panier.php')) ?>">Vérifier le panier</a>
        </div>
    <?php else: ?>
        <div class="checkout-layout">
            <section class="checkout-form-panel" aria-labelledby="delivery-title">
                <h2 id="delivery-title">Coordonnées et livraison</h2>
                <form class="form-stack" method="post" action="<?= e(site_url('public/commande.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">

                    <div class="checkout-name-fields">
                        <label class="form-field">
                            <span>Nom</span>
                            <input type="text" name="customer_last_name" maxlength="100" autocomplete="family-name" value="<?= e($formValues['customer_last_name']) ?>" required>
                        </label>
                        <label class="form-field">
                            <span>Prénom</span>
                            <input type="text" name="customer_first_name" maxlength="100" autocomplete="given-name" value="<?= e($formValues['customer_first_name']) ?>" required>
                        </label>
                    </div>
                    <label class="form-field">
                        <span>Téléphone WhatsApp</span>
                        <input
                            type="tel"
                            name="customer_phone"
                            maxlength="30"
                            autocomplete="tel"
                            inputmode="tel"
                            value="<?= e($formValues['customer_phone']) ?>"
                            placeholder="+225 01 02 03 04 05"
                            required
                        >
                        <small>Indicatif international facultatif, 8 à 15 chiffres.</small>
                    </label>
                    <label class="form-field">
                        <span>Commune</span>
                        <select name="commune_id" data-delivery-preview required>
                            <option value="">Choisir une commune</option>
                            <?php foreach ($communes as $commune): ?>
                                <option
                                    value="<?= e($commune['id']) ?>"
                                    data-delivery-fee="<?= e($deliveryFees[$commune['zone']]) ?>"
                                    data-commune-name="<?= e($commune['name']) ?>"
                                    <?= $formValues['commune_id'] === (string) $commune['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($commune['name']) ?> — <?= $commune['zone'] === 'abidjan' ? 'Abidjan' : 'Hors Abidjan' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Précisions d’adresse</span>
                        <textarea name="address_details" rows="4" maxlength="2000" autocomplete="street-address" required><?= e($formValues['address_details']) ?></textarea>
                        <small>Indique le quartier, un repère ou toute information utile au livreur.</small>
                    </label>
                    <button class="button button-primary" type="submit">Confirmer la commande</button>
                </form>
            </section>

            <aside class="cart-summary checkout-summary">
                <h2>Récapitulatif</h2>
                <ul class="checkout-items">
                    <?php foreach ($cartLines as $line): ?>
                        <li>
                            <span>
                                <?= e($line['product_name']) ?>
                                <?= $line['variant_label'] !== null ? ' — ' . e($line['variant_label']) : '' ?>
                                × <?= e($line['quantity']) ?>
                            </span>
                            <strong><?= e(format_price($line['pricing']['line_total'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="cart-summary-row"><span>Sous-total</span><strong><?= e(format_price($cartSubtotal)) ?></strong></p>
                <p class="cart-summary-row">
                    <span data-delivery-label>Livraison<?= $selectedCommune === null ? '' : ' (' . e($selectedCommune['name']) . ')' ?></span>
                    <strong data-delivery-fee-output><?= $deliveryFeePreview === null ? 'Selon la commune' : e(format_price($deliveryFeePreview)) ?></strong>
                </p>
                <p class="cart-summary-row cart-summary-total">
                    <span>Total estimé</span>
                    <strong
                        data-checkout-total
                        data-subtotal="<?= e($cartSubtotal) ?>"
                        data-maximum="<?= e(ORDER_AMOUNT_MAX) ?>"
                    ><?= e($estimatedTotalLabel) ?></strong>
                </p>
                <p class="muted">Le stock sera confirmé par téléphone. Le paiement se fait à la livraison.</p>
            </aside>
        </div>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
