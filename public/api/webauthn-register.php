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
        // Step 1: Get Registration Options
        $options = $webAuthnService->getRegistrationOptions((int)$user['user_id'], $user['name']);
        echo json_encode($options);
        
    } elseif ($method === 'POST') {
        // Step 2: Verify Registration
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        $result = $webAuthnService->verifyRegistration((int)$user['user_id'], $input);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Biometrik berhasil didaftarkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mendaftarkan biometrik']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (\Exception $e) {
    error_log("WebAuthn Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server']);
}
