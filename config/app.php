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

// Check multiple paths for Composer autoloader (useful for different hosting environments)
$autoloadPaths = [
    APP_ROOT . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'
];

$vendorFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $vendorFound = true;
        break;
    }
}

if (!$vendorFound) {
    die('Composer autoload.php not found. Please run "composer install".');
}

// Check multiple paths for .env file
$envPath = APP_ROOT;
if (!file_exists($envPath . '/.env')) {
    if (file_exists(__DIR__ . '/../.env')) {
        $envPath = __DIR__ . '/..';
    } elseif (file_exists(dirname($_SERVER['DOCUMENT_ROOT']) . '/.env')) {
        $envPath = dirname($_SERVER['DOCUMENT_ROOT']);
    } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
        $envPath = $_SERVER['DOCUMENT_ROOT'];
    }
}

// Load .env file
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->safeLoad();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- KONFIGURASI SERVER ---
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($httpHost == 'localhost' || $httpHost == '127.0.0.1') {
    // KONFIGURASI LOCALHOST KAMU
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'uaskte_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', 'http://localhost/uaskte/public/');
} else {
    // KONFIGURASI HOSTING (Sesuai Screenshot 2026-07-04 214734.png)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ecss2721_alvin'); // Sesuaikan (nama_masing-masing)
    define('DB_USER', 'ecss2721_uas');
    define('DB_PASS', 'e7#XbA1{H9\#');
    define('BASE_URL', 'https://ecscu24.xyz/alvin/'); // Sesuaikan (nama_masing-masing)
}
// --------------------------

// Dynamic APP_URL fallback
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultUrl = $protocol . '://' . $host . dirname($_SERVER['SCRIPT_NAME']);

// App constants
define('APP_NAME', $_ENV['APP_NAME'] ?? 'UASKTE App');
define('APP_URL', defined('BASE_URL') ? rtrim(BASE_URL, '/') : ($_ENV['APP_URL'] ?? $defaultUrl));
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
