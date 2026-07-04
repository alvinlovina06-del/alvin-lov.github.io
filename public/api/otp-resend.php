<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../src/OTPService.php';

use App\OTPService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['pending_user'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit;
}

$pendingUser = $_SESSION['pending_user'];
$otpService = new OTPService();

try {
    $otp = $otpService->generate((int)$pendingUser['id']);
    
    if (!empty($pendingUser['phone'])) {
        $otpService->sendViaWhatsApp($pendingUser['phone'], $otp);
    } else {
        error_log("Resend: No phone number for user {$pendingUser['id']}. OTP is: $otp");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Kode OTP baru telah dikirim ke WhatsApp Anda'
    ]);
} catch (\Exception $e) {
    error_log("OTP Resend Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengirim OTP baru. Silakan coba beberapa saat lagi.'
    ]);
}
