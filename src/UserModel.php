<?php
/**
 * UserModel - User CRUD Model
 * 
 * Mengelola operasi CRUD untuk tabel users.
 * Menggunakan SHA-512 hash untuk email dan mencatat semua perubahan ke AuditLog.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

class UserModel
{
    /**
     * Ambil semua user dengan pagination
     * Get all users with pagination support
     *
     * @param int $page   Halaman saat ini (default 1)
     * @param int $limit  Jumlah data per halaman (default 10)
     * @param string $search  Kata kunci pencarian (optional)
     * @return array ['data' => array, 'total' => int, 'page' => int, 'limit' => int, 'total_pages' => int]
     */
    public static function getAll(int $page = 1, int $limit = 10, string $search = ''): array
    {
        try {
            $pdo = getDBConnection();
            $offset = ($page - 1) * $limit;

            // Base query - hanya tampilkan user yang aktif
            $whereClause = "WHERE is_active = 1";
            $params = [];

            // Tambahkan search filter jika ada
            if (!empty($search)) {
                $whereClause .= " AND (name LIKE :search OR email LIKE :search2)";
                $params[':search']  = "%{$search}%";
                $params[':search2'] = "%{$search}%";
            }

            // Count total records
            $countSql = "SELECT COUNT(*) as total FROM users {$whereClause}";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];

            // Fetch paginated data
            $sql = "SELECT id, name, email, email_hash, role, avatar_url as avatar, google_id, phone, 
                           is_active, created_at, updated_at 
                    FROM users 
                    {$whereClause} 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll();
            $totalPages = (int) ceil($total / $limit);

            return [
                'data'        => $data,
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => $totalPages,
            ];
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] getAll Error: " . $e->getMessage());
            }
            return [
                'data'        => [],
                'total'       => 0,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => 0,
            ];
        }
    }

    /**
     * Ambil user berdasarkan ID
     * Get single user by ID
     *
     * @param int $id User ID
     * @return array|null Data user atau null jika tidak ditemukan
     */
    public static function getById(int $id): ?array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT id, name, email, email_hash, role, avatar_url as avatar, google_id, phone, 
                           is_active, created_at, updated_at 
                    FROM users 
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] getById Error: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Cari user berdasarkan email
     * Find user by email address
     *
     * @param string $email Alamat email
     * @return array|null Data user atau null
     */
    public static function getByEmail(string $email): ?array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT id, name, email, email_hash, role, avatar_url as avatar, google_id, phone, 
                           is_active, created_at, updated_at 
                    FROM users 
                    WHERE email = :email AND is_active = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':email' => $email]);

            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] getByEmail Error: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Cari user berdasarkan email hash (SHA-512)
     * Find user by SHA-512 email hash
     *
     * @param string $hash SHA-512 hash dari email
     * @return array|null Data user atau null
     */
    public static function getByEmailHash(string $hash): ?array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT id, name, email, email_hash, role, avatar_url as avatar, google_id, phone, 
                           is_active, created_at, updated_at 
                    FROM users 
                    WHERE email_hash = :hash AND is_active = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':hash' => $hash]);

            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] getByEmailHash Error: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Cari user berdasarkan Google ID
     * Find user by Google OAuth ID
     *
     * @param string $googleId Google OAuth user ID
     * @return array|null Data user atau null
     */
    public static function getByGoogleId(string $googleId): ?array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT id, name, email, email_hash, role, avatar_url as avatar, google_id, phone, 
                           is_active, created_at, updated_at 
                    FROM users 
                    WHERE google_id = :google_id AND is_active = 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':google_id' => $googleId]);

            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] getByGoogleId Error: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Buat user baru
     * Create a new user with SHA-512 email hashing
     *
     * @param array $data Data user: name, email, role, avatar, google_id, phone
     * @return int|false ID user baru atau false jika gagal
     */
    public static function create(array $data): int|false
    {
        try {
            $pdo = getDBConnection();

            // Hash email dengan SHA-512
            $emailHash = hash('sha512', strtolower(trim($data['email'])));

            $sql = "INSERT INTO users (name, email, email_hash, role, avatar_url, google_id, phone, is_active, created_at, updated_at) 
                    VALUES (:name, :email, :email_hash, :role, :avatar, :google_id, :phone, 1, NOW(), NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name'       => $data['name'] ?? '',
                ':email'      => $data['email'] ?? '',
                ':email_hash' => $emailHash,
                ':role'       => $data['role'] ?? 'user',
                ':avatar'     => $data['avatar'] ?? null,
                ':google_id'  => $data['google_id'] ?? null,
                ':phone'      => $data['phone'] ?? null,
            ]);

            $newId = (int) $pdo->lastInsertId();

            // Catat ke audit log
            AuditLog::log('users', $newId, 'CREATE', null, [
                'name'      => $data['name'] ?? '',
                'email'     => $data['email'] ?? '',
                'role'      => $data['role'] ?? 'user',
                'google_id' => $data['google_id'] ?? null,
                'phone'     => $data['phone'] ?? null,
            ]);

            return $newId;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] create Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update data user
     * Update user data with re-hashing email if changed
     *
     * @param int   $id   User ID
     * @param array $data Data yang akan diupdate
     * @return bool True jika berhasil
     */
    public static function update(int $id, array $data): bool
    {
        try {
            $pdo = getDBConnection();

            // Ambil data lama untuk audit log
            $oldUser = self::getById($id);
            if (!$oldUser) {
                return false;
            }

            // Build dynamic SET clause
            $setClauses = [];
            $params = [':id' => $id];

            if (isset($data['name'])) {
                $setClauses[] = "name = :name";
                $params[':name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $setClauses[] = "email = :email";
                $setClauses[] = "email_hash = :email_hash";
                $params[':email'] = $data['email'];
                // Re-hash email jika berubah
                $params[':email_hash'] = hash('sha512', strtolower(trim($data['email'])));
            }

            if (isset($data['role'])) {
                $setClauses[] = "role = :role";
                $params[':role'] = $data['role'];
            }

            if (isset($data['avatar'])) {
                $setClauses[] = "avatar_url = :avatar";
                $params[':avatar'] = $data['avatar'];
            }

            if (isset($data['google_id'])) {
                $setClauses[] = "google_id = :google_id";
                $params[':google_id'] = $data['google_id'];
            }

            if (isset($data['phone'])) {
                $setClauses[] = "phone = :phone";
                $params[':phone'] = $data['phone'];
            }

            if (array_key_exists('is_active', $data)) {
                $setClauses[] = "is_active = :is_active";
                $params[':is_active'] = $data['is_active'];
            }

            if (empty($setClauses)) {
                return false; // Tidak ada yang diupdate
            }

            // Selalu update timestamp
            $setClauses[] = "updated_at = NOW()";

            $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Catat ke audit log dengan old values
            AuditLog::log('users', $id, 'UPDATE', $oldUser, $data);

            return true;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] update Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Soft delete user (set is_active = 0)
     * Soft delete user by deactivating instead of removing from database
     *
     * @param int $id User ID yang akan dihapus
     * @return bool True jika berhasil
     */
    public static function delete(int $id): bool
    {
        try {
            $pdo = getDBConnection();

            // Ambil data lama untuk audit log
            $oldUser = self::getById($id);
            if (!$oldUser) {
                return false;
            }

            $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            // Catat ke audit log
            AuditLog::log('users', $id, 'DELETE', $oldUser, ['is_active' => 0]);

            return true;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] delete Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Hitung total user aktif
     * Count total active users
     *
     * @return int Jumlah user aktif
     */
    public static function count(): int
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetch()['total'];
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[UserModel] count Error: " . $e->getMessage());
            }
            return 0;
        }
    }
}
