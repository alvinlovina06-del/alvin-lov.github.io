<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/WebAuthnService.php';

use App\Auth;
use App\WebAuthnService;

header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::getUser();

$webAuthnService = new WebAuthnService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Step 1: Get Authentication Options
        $options = $webAuthnService->getAuthenticationOptions((int)$user['user_id']);
        
        if (empty($options['allowCredentials'])) {
            echo json_encode(['error' => 'No credentials found for this user']);
            exit;
        }
        
        echo json_encode($options);
        
    } elseif ($method === 'POST') {
        // Step 2: Verify Authentication
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        $result = $webAuthnService->verifyAuthentication((int)$user['user_id'], $input);
        
        if ($result) {
            // Set session flag indicating biometric verification is complete
            $_SESSION['biometric_verified'] = true;
            echo json_encode(['success' => true, 'message' => 'Verifikasi biometrik berhasil']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Verifikasi biometrik gagal']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (\Exception $e) {
    error_log("WebAuthn Auth Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server']);
}
