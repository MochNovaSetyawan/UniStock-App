<?php

declare(strict_types=1);

// ============================================================
// UNISTOCK – Application Bootstrap
// Entry point: require this file instead of individual includes
// ============================================================

define('APP_ROOT', dirname(__FILE__));

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

// Auto-detect APP_URL based on current folder name (portable across machines),
// with an environment override for Docker/production deployments.
$configuredAppUrl = env_value('APP_URL');
if ($configuredAppUrl !== null) {
    define('APP_URL', rtrim($configuredAppUrl, '/'));
} elseif (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $folder   = basename(dirname(__FILE__));
    define('APP_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . $folder);
} else {
    define('APP_URL', 'http://localhost/' . basename(dirname(__FILE__)));
}

define('UPLOAD_PATH', APP_ROOT . '/assets/img/uploads/');
define('UPLOAD_URL',  APP_URL  . '/assets/img/uploads/');

// Database credentials
define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_NAME', env_value('DB_NAME', 'unistock'));

// Runtime settings
date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ── PSR-4 Autoloader ────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    // Namespace prefix: App\  →  app/
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Boot session ─────────────────────────────────────────────
App\Core\Session::start();
