<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$errorMessage = null;
$settingsValues = [];
$newCommuneName = '';
$newCommuneZone = 'abidjan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } else {
        $action = $_POST['action'] ?? '';
        if (!is_string($action)) {
            $action = '';
        }

        if ($action === 'save_settings') {
            $settingFields = [
                'delivery_fee_abidjan' => [
                    'label' => 'les frais de livraison à Abidjan',
                    'min' => 0,
                ],
                'delivery_fee_outside_abidjan' => [
                    'label' => 'les frais de livraison hors Abidjan',
                    'min' => 0,
                ],
                'stock_alert_threshold' => [
                    'label' => 'le seuil de stock bas',
                    'min' => 1,
                ],
            ];
            $validatedSettings = [];
            $validationErrors = [];

            foreach ($settingFields as $key => $field) {
                $rawValue = $_POST[$key] ?? null;
                $settingsValues[$key] = is_string($rawValue) ? $rawValue : '';
                $validInteger = is_string($rawValue)
                    && preg_match('/^(0|[1-9][0-9]*)$/D', $rawValue) === 1
                    ? filter_var($rawValue, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => $field['min']],
                    ])
                    : false;

                if ($validInteger === false) {
                    $validationErrors[] = 'Vérifie ' . $field['label'] . '.';
                } else {
                    $validatedSettings[$key] = (string) $validInteger;
                }
            }

            if ($validationErrors !== []) {
                $errorMessage = implode(' ', $validationErrors);
            } else {
                $connection = db();
                $connection->beginTransaction();
                try {
                    $upsert = $connection->prepare(
                        'INSERT INTO settings (setting_key, setting_value)
                         VALUES (:setting_key, :setting_value)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                    );

                    foreach ($validatedSettings as $key => $value) {
                        $upsert->execute([
                            'setting_key' => $key,
                            'setting_value' => $value,
                        ]);
                    }

                    $connection->commit();
                } catch (PDOException $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    throw $exception;
                }

                set_admin_flash('success', 'Les réglages ont été enregistrés.');
                admin_redirect('admin/reglages.php');
            }
        } elseif ($action === 'add_commune') {
            $rawName = $_POST['commune_name'] ?? '';
            $rawZone = $_POST['zone'] ?? '';
            $newCommuneName = is_string($rawName) ? trim($rawName) : '';
            $newCommuneZone = is_string($rawZone) ? $rawZone : '';

            if (preg_match('/^.{1,140}$/us', $newCommuneName) !== 1) {
                $errorMessage = 'Saisis un nom de commune ou de quartier (140 caractères maximum).';
            } elseif (!in_array($newCommuneZone, ['abidjan', 'hors_abidjan'], true)) {
                $errorMessage = 'Choisis une zone de livraison valide.';
            } else {
                $duplicate = db()->prepare(
                    'SELECT id FROM communes WHERE zone = :zone AND name = :name LIMIT 1'
                );
                $duplicate->execute(['zone' => $newCommuneZone, 'name' => $newCommuneName]);

                if ($duplicate->fetchColumn() !== false) {
                    $errorMessage = 'Cette commune existe déjà dans cette zone.';
                } else {
                    $orderQuery = db()->prepare(
                        'SELECT COALESCE(MAX(display_order), 0) + 1
                         FROM communes
                         WHERE zone = :zone'
                    );
                    $orderQuery->execute(['zone' => $newCommuneZone]);
                    $nextOrder = (int) $orderQuery->fetchColumn();

                    try {
                        $insert = db()->prepare(
                            'INSERT INTO communes (name, zone, display_order, is_active)
                             VALUES (:name, :zone, :display_order, 1)'
                        );
                        $insert->execute([
                            'name' => $newCommuneName,
                            'zone' => $newCommuneZone,
                            'display_order' => $nextOrder,
                        ]);
                    } catch (PDOException $exception) {
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }
                        $errorMessage = 'Cette commune existe déjà dans cette zone.';
                    }

                    if ($errorMessage === null) {
                        set_admin_flash('success', 'La commune a été ajoutée.');
                        admin_redirect('admin/reglages.php');
                    }
                }
            }
        } elseif ($action === 'update_commune') {
            $rawId = $_POST['commune_id'] ?? null;
            $rawName = $_POST['commune_name'] ?? '';
            $rawZone = $_POST['zone'] ?? '';
            $rawOrder = $_POST['display_order'] ?? null;
            $communeId = filter_var($rawId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $communeName = is_string($rawName) ? trim($rawName) : '';
            $communeZone = is_string($rawZone) ? $rawZone : '';
            $displayOrder = is_string($rawOrder) && preg_match('/^(0|[1-9][0-9]*)$/D', $rawOrder) === 1
                ? filter_var($rawOrder, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                : false;

            if ($communeId === false) {
                $errorMessage = 'La commune sélectionnée est invalide.';
            } elseif (preg_match('/^.{1,140}$/us', $communeName) !== 1) {
                $errorMessage = 'Saisis un nom de commune ou de quartier (140 caractères maximum).';
            } elseif (!in_array($communeZone, ['abidjan', 'hors_abidjan'], true)) {
                $errorMessage = 'Choisis une zone de livraison valide.';
            } elseif ($displayOrder === false) {
                $errorMessage = 'L’ordre d’affichage doit être un nombre positif ou nul.';
            } else {
                $existing = db()->prepare('SELECT id FROM communes WHERE id = :id');
                $existing->execute(['id' => $communeId]);
                if ($existing->fetchColumn() === false) {
                    $errorMessage = 'Cette commune n’existe plus.';
                } else {
                    $duplicate = db()->prepare(
                        'SELECT id FROM communes
                         WHERE zone = :zone AND name = :name AND id <> :id
                         LIMIT 1'
                    );
                    $duplicate->execute([
                        'zone' => $communeZone,
                        'name' => $communeName,
                        'id' => $communeId,
                    ]);

                    if ($duplicate->fetchColumn() !== false) {
                        $errorMessage = 'Cette commune existe déjà dans cette zone.';
                    } else {
                        try {
                            $update = db()->prepare(
                                'UPDATE communes
                                 SET name = :name,
                                     zone = :zone,
                                     display_order = :display_order,
                                     is_active = :is_active
                                 WHERE id = :id'
                            );
                            $update->execute([
                                'name' => $communeName,
                                'zone' => $communeZone,
                                'display_order' => $displayOrder,
                                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                                'id' => $communeId,
                            ]);
                        } catch (PDOException $exception) {
                            if ($exception->getCode() !== '23000') {
                                throw $exception;
                            }
                            $errorMessage = 'Cette commune existe déjà dans cette zone.';
                        }

                        if ($errorMessage === null) {
                            set_admin_flash('success', 'La commune a été mise à jour.');
                            admin_redirect('admin/reglages.php');
                        }
                    }
                }
            }
        } else {
            http_response_code(400);
            $errorMessage = 'Action non reconnue.';
        }
    }
}

$settings = site_settings();
$deliveryAbidjan = $settingsValues['delivery_fee_abidjan']
    ?? required_setting($settings, 'delivery_fee_abidjan');
$deliveryOutside = $settingsValues['delivery_fee_outside_abidjan']
    ?? required_setting($settings, 'delivery_fee_outside_abidjan');
$stockThreshold = $settingsValues['stock_alert_threshold']
    ?? required_setting($settings, 'stock_alert_threshold');
$communes = db()->query(
    'SELECT id, name, zone, display_order, is_active
     FROM communes
     ORDER BY zone, display_order, name'
)->fetchAll();

$pageTitle = 'Réglages';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Administration Berluxe</p>
        <h1>Réglages</h1>
        <p class="form-intro">Gère les frais de livraison, les zones proposées et les alertes de stock.</p>
    </div>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="admin-card">
    <h2>Livraison et stock</h2>
    <form class="form-grid" method="post" action="<?= e(site_url('admin/reglages.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <label class="form-field">
            <span>Frais de livraison à Abidjan (FCFA)</span>
            <input type="number" name="delivery_fee_abidjan" min="0" step="1" value="<?= e($deliveryAbidjan) ?>" required>
        </label>
        <label class="form-field">
            <span>Frais de livraison hors Abidjan (FCFA)</span>
            <input type="number" name="delivery_fee_outside_abidjan" min="0" step="1" value="<?= e($deliveryOutside) ?>" required>
        </label>
        <label class="form-field">
            <span>Seuil d’alerte de stock bas</span>
            <input type="number" name="stock_alert_threshold" min="1" step="1" value="<?= e($stockThreshold) ?>" required>
        </label>
        <div class="form-actions">
            <button class="button button-primary" type="submit">Enregistrer les réglages</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="section-heading">
        <div>
            <h2>Communes et quartiers</h2>
            <p class="muted">Désactive une zone plutôt que de la supprimer pour conserver l’historique des commandes.</p>
        </div>
    </div>

    <div class="commune-list">
        <?php foreach ($communes as $commune): ?>
            <?php $formId = 'commune-form-' . (int) $commune['id']; ?>
            <article class="commune-row">
                <form id="<?= e($formId) ?>" method="post" action="<?= e(site_url('admin/reglages.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_commune">
                    <input type="hidden" name="commune_id" value="<?= e($commune['id']) ?>">
                </form>
                <div class="commune-fields">
                    <label class="form-field">
                        <span>Nom</span>
                        <input form="<?= e($formId) ?>" type="text" name="commune_name" maxlength="140" value="<?= e($commune['name']) ?>" required>
                    </label>
                    <label class="form-field">
                        <span>Zone</span>
                        <select form="<?= e($formId) ?>" name="zone" required>
                            <option value="abidjan" <?= $commune['zone'] === 'abidjan' ? 'selected' : '' ?>>Abidjan</option>
                            <option value="hors_abidjan" <?= $commune['zone'] === 'hors_abidjan' ? 'selected' : '' ?>>Hors Abidjan</option>
                        </select>
                    </label>
                    <label class="form-field order-field">
                        <span>Ordre</span>
                        <input form="<?= e($formId) ?>" type="number" name="display_order" min="0" step="1" value="<?= e($commune['display_order']) ?>" required>
                    </label>
                    <label class="checkbox-field">
                        <input form="<?= e($formId) ?>" type="checkbox" name="is_active" value="1" <?= (int) $commune['is_active'] === 1 ? 'checked' : '' ?>>
                        <span>Active</span>
                    </label>
                    <button class="button button-secondary" form="<?= e($formId) ?>" type="submit">Enregistrer</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="add-commune">
        <h3>Ajouter une commune ou un quartier</h3>
        <form class="form-grid commune-add-form" method="post" action="<?= e(site_url('admin/reglages.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_commune">
            <label class="form-field">
                <span>Nom</span>
                <input type="text" name="commune_name" maxlength="140" value="<?= e($newCommuneName) ?>" required>
            </label>
            <label class="form-field">
                <span>Zone</span>
                <select name="zone" required>
                    <option value="abidjan" <?= $newCommuneZone === 'abidjan' ? 'selected' : '' ?>>Abidjan</option>
                    <option value="hors_abidjan" <?= $newCommuneZone === 'hors_abidjan' ? 'selected' : '' ?>>Hors Abidjan</option>
                </select>
            </label>
            <div class="form-actions">
                <button class="button button-primary" type="submit">Ajouter</button>
            </div>
        </form>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
