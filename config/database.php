<?php
/**
 * Database Configuration
 * Koneksi PDO MySQL dengan error handling
 */

declare(strict_types=1);

function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost');
        $port = defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? '3306');
        $name = defined('DB_NAME') ? DB_NAME : ($_ENV['DB_NAME'] ?? 'uaskte_db');
        $user = defined('DB_USER') ? DB_USER : ($_ENV['DB_USER'] ?? 'root');
        $pass = defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASS'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                die("Database connection failed: " . $e->getMessage());
            }
            die("Database connection failed. Please check configuration.");
        }
    }
    
    return $pdo;
}
