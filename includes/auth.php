<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_LOCK_MINUTES = 15;
const DUMMY_ADMIN_PASSWORD_HASH = '$2y$10$zVbzDZvM/hFmanoO4JnDOOKK9iuRO1ygQdPscj9kzTHghsW7qIVn.';

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    $cookiePath = APP_BASE_PATH === '/' ? '/' : rtrim(APP_BASE_PATH, '/') . '/';
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';

    session_name('BERLUXESESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');

    if (!session_start()) {
        throw new RuntimeException('Impossible de démarrer la session.');
    }
}

function csrf_token(): string
{
    start_app_session();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function is_valid_csrf_token(mixed $token): bool
{
    start_app_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function current_admin(): ?array
{
    start_app_session();

    if (!isset($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['admin_id'],
        'username' => (string) ($_SESSION['admin_username'] ?? ''),
        'name' => (string) ($_SESSION['admin_name'] ?? ''),
        'must_change_password' => !empty($_SESSION['admin_must_change_password']),
    ];
}

function require_admin(bool $allowPasswordChange = false): void
{
    $admin = current_admin();

    if ($admin === null) {
        admin_redirect('admin/login.php');
    }

    if (!$allowPasswordChange && $admin['must_change_password']) {
        admin_redirect('admin/password.php');
    }
}

function authenticate_admin(string $username, string $password): ?array
{
    $connection = db();
    $connection->beginTransaction();

    $statement = $connection->prepare(
        'SELECT id, username, password_hash, full_name, must_change_password,
                failed_login_attempts,
                (locked_until IS NOT NULL AND locked_until > CURRENT_TIMESTAMP) AS is_locked,
                (locked_until IS NOT NULL AND locked_until <= CURRENT_TIMESTAMP) AS lock_expired
         FROM admins
         WHERE username = :username
         LIMIT 1
         FOR UPDATE'
    );
    $statement->execute(['username' => $username]);
    $account = $statement->fetch();

    if ($account !== false && (int) $account['lock_expired'] === 1) {
        $reset = $connection->prepare(
            'UPDATE admins
             SET failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id'
        );
        $reset->execute(['id' => $account['id']]);
        $account['failed_login_attempts'] = 0;
        $account['is_locked'] = 0;
    }

    $passwordHash = $account === false
        ? DUMMY_ADMIN_PASSWORD_HASH
        : (string) $account['password_hash'];
    $passwordMatches = password_verify($password, $passwordHash);
    $isLocked = $account !== false && (int) $account['is_locked'] === 1;

    if ($account !== false && !$isLocked && $passwordMatches) {
        $reset = $connection->prepare(
            'UPDATE admins
             SET failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id'
        );
        $reset->execute(['id' => $account['id']]);
        $connection->commit();

        return [
            'id' => (int) $account['id'],
            'username' => (string) $account['username'],
            'name' => (string) $account['full_name'],
            'must_change_password' => (bool) $account['must_change_password'],
        ];
    }

    $attempts = (int) $account['failed_login_attempts'] + 1;
    $lockedUntil = $attempts >= ADMIN_LOGIN_MAX_ATTEMPTS
        ? (new DateTimeImmutable())
            ->modify('+' . ADMIN_LOGIN_LOCK_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s')
        : null;

    $update = $connection->prepare(
        'UPDATE admins
         SET failed_login_attempts = :attempts,
              locked_until = :locked_until
         WHERE id = :id'
    );
    $update->execute([
        'attempts' => $attempts,
        'locked_until' => $lockedUntil,
        'id' => $account['id'],
    ]);

    $connection->commit();
    return null;
}

function admin_redirect(string $path): void
{
    header('Location: ' . site_url($path), true, 303);
    exit;
}

function set_admin_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error'], true)) {
        throw new InvalidArgumentException('Type de notification invalide.');
    }

    start_app_session();
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function consume_admin_flash(): ?array
{
    start_app_session();

    if (!isset($_SESSION['admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function destroy_app_session(): void
{
    start_app_session();
    $_SESSION = [];

    $cookie = session_get_cookie_params();
    $options = [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => 'Lax',
    ];
    if ($cookie['domain'] !== '') {
        $options['domain'] = $cookie['domain'];
    }

    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', $options);
    }

    session_destroy();
}
