<?php
declare(strict_types=1);

define('SITE_NAME', 'Berluxe');
define('SITE_SLOGAN', 'Construire mieux, vivre mieux'); // À confirmer par Roxane.

$basePath = getenv('BERLUXE_BASE_PATH');
define('APP_BASE_PATH', $basePath === false ? '/berluxe' : '/' . trim($basePath, '/'));

define('DB_HOST', getenv('BERLUXE_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('BERLUXE_DB_NAME') ?: 'berluxe');
define('DB_USER', getenv('BERLUXE_DB_USER') ?: 'root');
$databasePassword = getenv('BERLUXE_DB_PASSWORD');
define('DB_PASSWORD', $databasePassword === false ? '' : $databasePassword);
define('DB_CHARSET', 'utf8mb4');

$debugSetting = getenv('BERLUXE_DEBUG');
define('DEBUG', $debugSetting !== false && filter_var($debugSetting, FILTER_VALIDATE_BOOLEAN));

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
