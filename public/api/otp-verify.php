<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/OTPService.php';

use App\Auth;
use App\OTPService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$otp = (string)($input['otp'] ?? '');

if (empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'OTP wajib diisi']);
    exit;
}

if (!isset($_SESSION['pending_user'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid, silakan login ulang']);
    exit;
}

$pendingUser = $_SESSION['pending_user'];
$otpService = new OTPService();

try {
    $verifyResult = $otpService->verify((int)$pendingUser['id'], $otp);

    if (isset($verifyResult['success']) && $verifyResult['success'] === true) {
        // OTP is valid! Log the user in
        Auth::login($pendingUser);
        unset($_SESSION['pending_user']);
        
        // Redirect logic
        $redirectUrl = 'dashboard.php';
        if ($pendingUser['role'] === 'admin') {
            $redirectUrl = 'admin/users.php'; // Could go straight to admin if they are an admin
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP berhasil diverifikasi',
            'redirect' => $redirectUrl
        ]);
    } else {
        // Return error message from OTPService (e.g. "OTP salah", "OTP kadaluarsa")
        echo json_encode([
            'success' => false,
            'message' => $verifyResult['message'] ?? 'Verifikasi OTP gagal'
        ]);
    }
} catch (\Throwable $e) {
    error_log("OTP Verify Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem internal.'
    ]);
}
