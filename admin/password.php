<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_admin(true);

$admin = current_admin();
if ($admin === null) {
    throw new RuntimeException('La session administrateur est introuvable.');
}

$pageTitle = 'Changer le mot de passe';
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
            $errorMessage = 'Les champs du formulaire sont invalides.';
        } elseif (strlen($newPassword) < 12) {
            $errorMessage = 'Le nouveau mot de passe doit contenir au moins 12 caractères.';
        } elseif (strlen($newPassword) > 4096) {
            $errorMessage = 'Le nouveau mot de passe est trop long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'La confirmation ne correspond pas au nouveau mot de passe.';
        } else {
            $statement = db()->prepare('SELECT password_hash FROM admins WHERE id = :id');
            $statement->execute(['id' => $admin['id']]);
            $storedHash = $statement->fetchColumn();

            if (!is_string($storedHash) || !password_verify($currentPassword, $storedHash)) {
                $errorMessage = 'Le mot de passe actuel est incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($newHash === false) {
                    throw new RuntimeException('Impossible de chiffrer le nouveau mot de passe.');
                }

                $update = db()->prepare(
                    'UPDATE admins
                     SET password_hash = :password_hash,
                         must_change_password = 0,
                         failed_login_attempts = 0,
                         locked_until = NULL
                     WHERE id = :id'
                );
                $update->execute([
                    'password_hash' => $newHash,
                    'id' => $admin['id'],
                ]);

                $_SESSION['admin_must_change_password'] = false;
                set_admin_flash('success', 'Ton mot de passe a été modifié.');
                admin_redirect('admin/index.php');
            }
        }
    }
}

require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow">Sécurité</p>
        <h1>Mot de passe</h1>
        <p class="muted"><?= $admin['must_change_password'] ? 'Le mot de passe provisoire doit être remplacé avant de continuer.' : 'Modifie ton mot de passe pour sécuriser ton compte.' ?></p>
    </div>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="password-card-wrapper">
    <section class="admin-card password-card">
        <div class="password-header">
            <div class="password-icon" aria-hidden="true">🔒</div>
            <h2><?= $admin['must_change_password'] ? 'Choisis un nouveau mot de passe' : 'Changer le mot de passe' ?></h2>
            <p>Choisis un mot de passe solide d'au moins 12 caractères.</p>
        </div>

        <form class="form-stack" method="post" action="<?= e(site_url('admin/password.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label class="form-field">
                <span>Mot de passe actuel</span>
                <input type="password" name="current_password" autocomplete="current-password" required placeholder="••••••••••••">
            </label>

            <label class="form-field">
                <span>Nouveau mot de passe</span>
                <input type="password" name="new_password" minlength="12" maxlength="4096" autocomplete="new-password" required placeholder="Minimum 12 caractères">
                <small>Utilise des lettres, des chiffres et des caractères spéciaux.</small>
            </label>

            <label class="form-field">
                <span>Confirmer le nouveau mot de passe</span>
                <input type="password" name="confirm_password" minlength="12" maxlength="4096" autocomplete="new-password" required placeholder="Retape le nouveau mot de passe">
            </label>

            <button class="button button-primary" type="submit">
                Enregistrer le mot de passe
            </button>
        </form>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>