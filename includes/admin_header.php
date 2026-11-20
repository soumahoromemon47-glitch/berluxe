<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$adminUser = current_admin();
$adminFlash = consume_admin_flash();
$documentTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' | ' . SITE_NAME . ' Admin'
    : SITE_NAME . ' Admin';
$styleVersion = filemtime(dirname(__DIR__) . '/assets/css/style.css');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FAF7F2">
    <title><?= e($documentTitle) ?></title>
    <?php if (logo_url() !== null): ?>
    <link rel="icon" href="<?= e(logo_url()) ?>">
<?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('assets/css/style.css?v=' . $styleVersion)) ?>">
</head>
<body class="<?= isset($hideAdminHeader) && $hideAdminHeader === true ? 'admin-body' : 'admin-body admin-shell-body' ?>">
<?php if (isset($hideAdminHeader) && $hideAdminHeader === true): ?>
    <main class="admin-main admin-main-login">
<?php else: ?>
    <div class="admin-layout">
        <aside class="admin-sidebar" aria-label="Navigation administration">
            <div class="sidebar-brand">
                <a href="<?= e(site_url('admin/index.php')) ?>" aria-label="<?= e(SITE_NAME) ?> — tableau de bord">
                    <?php if (logo_url() !== null): ?>
                        <img src="<?= e(logo_url()) ?>" alt="<?= e(SITE_NAME) ?>">
                    <?php else: ?>
                        <?= e(SITE_NAME) ?>
                    <?php endif; ?>
                </a>
                <span>ESPACE ADMIN</span>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <a href="<?= e(site_url('admin/index.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'index.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'index.php' ? ' aria-current="page"' : '' ?>>Tableau de bord</a>
                <a href="<?= e(site_url('admin/commandes.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'commandes.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'commandes.php' ? ' aria-current="page"' : '' ?>>Commandes</a>
                <a href="<?= e(site_url('admin/produits.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'produits.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'produits.php' ? ' aria-current="page"' : '' ?>>Produits</a>
                <a href="<?= e(site_url('admin/categories.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'categories.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'categories.php' ? ' aria-current="page"' : '' ?>>Catégories</a>
                <a href="<?= e(site_url('admin/reglages.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'reglages.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'reglages.php' ? ' aria-current="page"' : '' ?>>Réglages</a>
                <a href="<?= e(site_url('admin/password.php')) ?>" class="sidebar-link<?= basename($_SERVER['PHP_SELF'] ?? '') === 'password.php' ? ' active' : '' ?>"<?= basename($_SERVER['PHP_SELF'] ?? '') === 'password.php' ? ' aria-current="page"' : '' ?>>Mot de passe</a>
            </nav>
            <div class="sidebar-footer">
                <form method="post" action="<?= e(site_url('admin/logout.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="sidebar-link logout" type="submit">Déconnexion</button>
                </form>
            </div>
        </aside>
        <div class="admin-content">
            <header class="admin-topbar">
                <div class="topbar-title"><?= e($pageTitle ?? 'Administration') ?></div>
                <div class="topbar-user">Bonjour, <strong><?= e($adminUser['name'] ?? '') ?></strong></div>
            </header>
            <main class="admin-main-inner">
<?php endif; ?><?php if ($adminFlash !== null): ?>
            <output class="notice notice-<?= e($adminFlash['type'] ?? 'success') ?>"><?= e($adminFlash['message'] ?? '') ?></output>
        <?php endif; ?>
