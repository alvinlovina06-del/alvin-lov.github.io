<?php
/**
 * Logout Handler
 * Hapus session dan redirect ke login
 */

require_once __DIR__ . '/../config/app.php';

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

header('Location: login.php');
exit;
