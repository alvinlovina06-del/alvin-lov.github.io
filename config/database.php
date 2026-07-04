<?php
/**
 * Database Configuration
 * Koneksi PDO MySQL dengan error handling
 */

declare(strict_types=1);

function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? 'uaskte_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

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
