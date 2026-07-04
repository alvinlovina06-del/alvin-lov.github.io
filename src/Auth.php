<?php
/**
 * Auth - Session-based Authentication Helper
 * 
 * Mengelola autentikasi pengguna berbasis session.
 * Menyediakan fungsi login, logout, cek role, dan redirect.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

class Auth
{
    /**
     * Cek apakah user sudah login
     * Check if user is currently logged in via session
     *
     * @return bool True jika user sudah login
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Cek apakah user yang login adalah admin
     * Check if current logged-in user has admin role
     *
     * @return bool True jika user adalah admin
     */
    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Redirect ke halaman login jika belum login
     * Require user to be logged in, otherwise redirect to login page
     *
     * @return void
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    /**
     * Redirect ke dashboard jika bukan admin
     * Require admin role, otherwise redirect to dashboard
     *
     * @return void
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!self::isAdmin()) {
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        }
    }

    /**
     * Set session data saat user login
     * Store user data in session upon successful login
     *
     * @param array $userData Data user dari database (user_id, name, email, role, avatar)
     * @return void
     */
    public static function login(array $userData): void
    {
        // Regenerate session ID untuk mencegah session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userData['id'] ?? $userData['user_id'] ?? null;
        $_SESSION['name']    = $userData['name'] ?? '';
        $_SESSION['email']   = $userData['email'] ?? '';
        $_SESSION['role']    = $userData['role'] ?? 'user';
        $_SESSION['avatar']  = $userData['avatar'] ?? '';
        $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');
    }

    /**
     * Hapus session dan logout user
     * Destroy session and log user out
     *
     * @return void
     */
    public static function logout(): void
    {
        // Hapus semua data session
        $_SESSION = [];

        // Hapus session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Ambil data user yang sedang login dari session
     * Get current logged-in user data from session
     *
     * @return array|null Data user atau null jika belum login
     */
    public static function getUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $_SESSION['user_id'],
            'name'    => $_SESSION['name'] ?? '',
            'email'   => $_SESSION['email'] ?? '',
            'role'    => $_SESSION['role'] ?? 'user',
            'avatar'  => $_SESSION['avatar'] ?? '',
            'logged_in_at' => $_SESSION['logged_in_at'] ?? null,
        ];
    }
}
