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
    <meta name="theme-color" content="#0a0b10">
    <title><?= e($documentTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('assets/css/style.css?v=' . $styleVersion)) ?>">
</head>
<body class="admin-body">
    <header class="site-header">
        <div class="container admin-header-content">
            <a class="brand" href="<?= e(site_url($adminUser === null ? 'admin/login.php' : 'admin/index.php')) ?>">
                <span class="brand-name"><?= e(SITE_NAME) ?></span>
                <span class="admin-label">Administration</span>
            </a>
            <?php if ($adminUser !== null): ?>
                <nav class="admin-nav" aria-label="Navigation administration">
                    <a href="<?= e(site_url('admin/index.php')) ?>">Tableau de bord</a>
                    <a href="<?= e(site_url('admin/categories.php')) ?>">Catégories</a>
                    <a href="<?= e(site_url('admin/produits.php')) ?>">Produits</a>
                    <a href="<?= e(site_url('admin/commandes.php')) ?>">Commandes</a>
                    <a href="<?= e(site_url('admin/reglages.php')) ?>">Réglages</a>
                    <a href="<?= e(site_url('admin/password.php')) ?>">Mot de passe</a>
                </nav>
                <form class="logout-form" method="post" action="<?= e(site_url('admin/logout.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="button button-secondary" type="submit">Déconnexion</button>
                </form>
            <?php else: ?>
                <a class="public-link" href="<?= e(site_url('public/index.php')) ?>">Voir la boutique</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="container admin-main">
        <?php if ($adminFlash !== null): ?>
            <output class="notice notice-<?= e($adminFlash['type'] ?? 'success') ?>"><?= e($adminFlash['message'] ?? '') ?></output>
        <?php endif; ?>
