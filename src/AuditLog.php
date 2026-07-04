<?php
/**
 * AuditLog - Audit Logging Service
 * 
 * Mencatat semua perubahan data (create, update, delete) ke tabel audit_logs.
 * Menyimpan IP address dan user agent untuk tracking keamanan.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

class AuditLog
{
    /**
     * Catat aktivitas ke tabel audit_logs
     * Log an action to the audit_logs table
     *
     * @param string      $table     Nama tabel yang diubah (e.g., 'users')
     * @param int         $recordId  ID record yang terpengaruh
     * @param string      $action    Jenis aksi: 'CREATE', 'UPDATE', 'DELETE'
     * @param array|null  $oldValues Nilai lama sebelum perubahan (null untuk CREATE)
     * @param array|null  $newValues Nilai baru setelah perubahan (null untuk DELETE)
     * @param int|null    $userId    ID user yang melakukan aksi (null = system/guest)
     * @return bool True jika berhasil dicatat
     */
    public static function log(
        string $table,
        int $recordId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): bool {
        try {
            $pdo = getDBConnection();

            // Ambil IP address dan user agent dari request
            $ipAddress = self::getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Jika userId tidak diberikan, ambil dari session
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = (int) $_SESSION['user_id'];
            }

            $sql = "INSERT INTO audit_logs 
                    (table_name, record_id, action, old_values, new_values, changed_by, ip_address, user_agent, created_at) 
                    VALUES 
                    (:table_name, :record_id, :action, :old_values, :new_values, :changed_by, :ip_address, :user_agent, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':table_name'  => $table,
                ':record_id'   => $recordId,
                ':action'      => strtoupper($action),
                ':old_values'  => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                ':new_values'  => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                ':changed_by'  => $userId,
                ':ip_address'  => $ipAddress,
                ':user_agent'  => mb_substr($userAgent, 0, 500),
            ]);

            return true;
        } catch (\PDOException $e) {
            // Log error tapi jangan hentikan aplikasi — audit log failure bukan critical
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[AuditLog] Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Ambil audit log berdasarkan tabel dan record ID
     * Get audit logs for a specific record
     *
     * @param string $table    Nama tabel
     * @param int    $recordId ID record
     * @return array Daftar audit log entries
     */
    public static function getByRecord(string $table, int $recordId): array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT al.*, u.name AS user_name 
                    FROM audit_logs al 
                    LEFT JOIN users u ON al.changed_by = u.id 
                    WHERE al.table_name = :table_name AND al.record_id = :record_id 
                    ORDER BY al.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':table_name' => $table,
                ':record_id'  => $recordId,
            ]);

            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[AuditLog] getByRecord Error: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Ambil audit log terbaru
     * Get recent audit log entries
     *
     * @param int $limit Jumlah maksimal record yang diambil (default 50)
     * @return array Daftar audit log entries terbaru
     */
    public static function getRecent(int $limit = 50): array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT al.*, u.name AS user_name 
                    FROM audit_logs al 
                    LEFT JOIN users u ON al.changed_by = u.id 
                    ORDER BY al.created_at DESC 
                    LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[AuditLog] getRecent Error: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Dapatkan IP address client yang sebenarnya
     * Get real client IP address (handles proxies)
     *
     * @return string IP address
     */
    private static function getClientIP(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For bisa berisi multiple IPs, ambil yang pertama
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
