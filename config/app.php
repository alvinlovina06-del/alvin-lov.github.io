<?php
/**
 * Application Configuration
 * Load environment variables and define app constants
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load Composer autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->load();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// App constants
define('APP_NAME', $_ENV['APP_NAME'] ?? 'UASKTE App');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/uaskte/public');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

// Google OAuth
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? APP_URL . '/callback.php');

// Fonnte WhatsApp
define('FONNTE_TOKEN', $_ENV['FONNTE_TOKEN'] ?? '');

// Database config (loaded via database.php)
require_once __DIR__ . '/database.php';

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set('Asia/Jakarta');
