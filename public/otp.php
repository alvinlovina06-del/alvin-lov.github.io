<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Auth.php';

use App\Auth;

if (Auth::isLoggedIn()) {
    if (Auth::isAdmin()) {
        header('Location: admin/users.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

if (!isset($_SESSION['pending_user'])) {
    header('Location: login.php');
    exit;
}

$pendingUser = $_SESSION['pending_user'];
$maskedPhone = '';
if (!empty($pendingUser['phone'])) {
    $phoneLen = strlen($pendingUser['phone']);
    $maskedPhone = substr($pendingUser['phone'], 0, 4) . str_repeat('*', $phoneLen - 8) . substr($pendingUser['phone'], -4);
} else {
    $maskedPhone = '(Nomor telepon tidak tersedia)';
}

$pageTitle = 'Verifikasi OTP';
$pageClass = 'otp-page';
$extraJs = ['/uaskte/public/assets/js/otp.js'];
require_once __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    <div class="floating-shape shape-3"></div>

    <div class="login-card otp-card">
        <div class="login-header">
            <div class="logo-shield">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
            </div>
            <h1>Verifikasi OTP</h1>
        </div>

        <div class="login-body">
            <p class="description">Kode OTP telah dikirim ke WhatsApp Anda:<br><strong><?= htmlspecialchars($maskedPhone) ?></strong></p>
            
            <form id="otpForm" class="otp-form">
                <div class="otp-inputs">
                    <input type="text" maxlength="1" class="otp-input" autofocus>
                    <input type="text" maxlength="1" class="otp-input">
                    <input type="text" maxlength="1" class="otp-input">
                    <input type="text" maxlength="1" class="otp-input">
                    <input type="text" maxlength="1" class="otp-input">
                    <input type="text" maxlength="1" class="otp-input">
                </div>
                
                <div class="otp-timer">
                    Berlaku: <span id="timerDisplay">05:00</span>
                </div>
                
                <button type="button" id="btnResend" class="btn btn-secondary" disabled>
                    Kirim Ulang OTP (<span id="resendCountdown">60</span>s)
                </button>
            </form>

            <a href="login.php?action=cancel" class="back-link">Batal dan kembali ke login</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
