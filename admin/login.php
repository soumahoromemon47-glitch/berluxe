<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (current_admin() !== null) {
    $admin = current_admin();
    admin_redirect($admin['must_change_password'] ? 'admin/password.php' : 'admin/index.php');
}

$pageTitle = 'Connexion';
$errorMessage = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token($postedToken)) {
        http_response_code(400);
        $errorMessage = 'La demande a expiré. Recharge cette page et réessaie.';
    } else {
        $postedUsername = $_POST['username'] ?? '';
        $postedPassword = $_POST['password'] ?? '';
        $username = is_string($postedUsername) ? trim($postedUsername) : '';
        $password = is_string($postedPassword) ? $postedPassword : '';

        if (strlen($username) > 80 || strlen($password) > 4096) {
            password_verify('invalid-input', DUMMY_ADMIN_PASSWORD_HASH);
            $admin = null;
        } else {
            $admin = authenticate_admin($username, $password);
        }

        if ($admin === null) {
            $errorMessage = 'Identifiant ou mot de passe invalide, ou accès temporairement bloqué.';
        } else {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_must_change_password'] = $admin['must_change_password'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            admin_redirect($admin['must_change_password'] ? 'admin/password.php' : 'admin/index.php');
        }
    }
}

require dirname(__DIR__) . '/includes/admin_header.php';
?>
<section class="admin-card login-card">
    <p class="eyebrow">Espace sécurisé</p>
    <h1>Connexion</h1>
    <p class="form-intro">Connecte-toi pour gérer la boutique Berluxe.</p>

    <?php if ($errorMessage !== null): ?>
        <div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <form class="form-stack" method="post" action="<?= e(site_url('admin/login.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label class="form-field">
            <span>Identifiant</span>
            <input type="text" name="username" value="<?= e($username) ?>" maxlength="80" autocomplete="username" required autofocus>
        </label>
        <label class="form-field">
            <span>Mot de passe</span>
            <input type="password" name="password" maxlength="4096" autocomplete="current-password" required>
        </label>
        <button class="button button-primary" type="submit">Se connecter</button>
    </form>
</section>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
