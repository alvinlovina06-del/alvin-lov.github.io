<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/GoogleOAuth.php';

use App\Auth;
use App\GoogleOAuth;

if (Auth::isLoggedIn()) {
    if (Auth::isAdmin()) {
        header('Location: admin/users.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'google') {
    $googleOAuth = new GoogleOAuth();
    header('Location: ' . $googleOAuth->getAuthUrl());
    exit;
}

$pageTitle = 'Login';
$pageClass = 'login-page';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <div class="login-card">
        <div class="login-header text-center" style="text-align: center;">
            <img src="assets/images/logo.png" alt="KTE Logo" class="brand-logo" style="max-width: 150px; display: block; margin: 0 auto 20px auto;">
            <h1 style="color: white;">KTE USER MANAGEMENT</h1>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php
                if ($_GET['error'] === 'email_not_found') {
                    echo 'Akses ditolak: Email <strong>' . htmlspecialchars($_GET['email'] ?? 'Anda') . '</strong> tidak terdaftar dalam sistem.';
                } else if ($_GET['error'] === 'invalid_state') {
                    echo 'Sesi login tidak valid. Silakan coba lagi.';
                } else {
                    echo 'Terjadi kesalahan saat login.';
                }
                ?>
            </div>
        <?php endif; ?>
            
            <a href="login.php?action=google" class="btn btn-google">
                <svg class="google-icon" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Login dengan Google
            </a>
            <div class="login-body">
            <p class="description" style="color: white; margin-top: 15px; text-align: center;">Silakan login menggunakan akun Google Anda.</p>
        </div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
