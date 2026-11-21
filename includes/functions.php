<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string
{
    $basePath = APP_BASE_PATH === '/' ? '' : rtrim(APP_BASE_PATH, '/');
    $relativePath = ltrim($path, '/');

    if ($relativePath === '') {
        return $basePath === '' ? '/' : $basePath;
    }

    return $basePath . '/' . $relativePath;
}

function format_price(string|int|float $amount): string
{
    if (!is_numeric($amount)) {
        throw new InvalidArgumentException('Le montant doit être numérique.');
    }

    return number_format((float) $amount, 0, ',', ' ') . ' FCFA';
}

function logo_url(): ?string
{
    foreach (['png', 'svg', 'webp', 'jpg', 'jpeg'] as $extension) {
        $filePath = dirname(__DIR__) . '/assets/img/logo.' . $extension;

        if (is_file($filePath)) {
            return site_url('assets/img/logo.' . $extension);
        }
    }

    return null;
}

function site_settings(): array
{
    require_once __DIR__ . '/db.php';

    $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];

    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

function required_setting(array $settings, string $key): string
{
    if (!array_key_exists($key, $settings)) {
        throw new RuntimeException('Le réglage requis est absent : ' . $key);
    }

    return $settings[$key];
}

function category_image_url(?string $fileName): ?string
{
    if (
        $fileName === null
        || $fileName === ''
        || basename($fileName) !== $fileName
        || !is_file(dirname(__DIR__) . '/uploads/categories/' . $fileName)
    ) {
        return null;
    }

    return site_url('uploads/categories/' . rawurlencode($fileName));
}
