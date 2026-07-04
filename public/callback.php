<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/GoogleOAuth.php';
require_once __DIR__ . '/../src/UserModel.php';
require_once __DIR__ . '/../src/OTPService.php';

use App\GoogleOAuth;
use App\UserModel;
use App\OTPService;

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header('Location: login.php');
    exit;
}

$googleOAuth = new GoogleOAuth();
$userModel = new UserModel();
$otpService = new OTPService();

try {
    // 1. Validate State (CSRF)
    if (!$googleOAuth->validateState($_GET['state'])) {
        header('Location: login.php?error=invalid_state');
        exit;
    }

    // 2. Handle Callback and Get Profile
    $profile = $googleOAuth->handleCallback($_GET['code'], $_GET['state']);
    $email = $profile['email'];

    // 3. Check if user exists in our database
    $user = $userModel->getByEmail($email);

    // Auto-create or update alvinlovina06@gmail.com as admin
    if ($email === 'alvinlovina06@gmail.com') {
        if (!$user) {
            // Create user as admin if they don't exist
            $newId = $userModel->create([
                'name' => $profile['name'] ?? 'Admin',
                'email' => $email,
                'role' => 'admin',
                'avatar' => $profile['avatar'] ?? null,
                'google_id' => $profile['google_id'] ?? null,
                'phone' => ''
            ]);
            $user = $userModel->getById($newId);
        } else if ($user['role'] !== 'admin') {
            // Update to admin if they are not already
            $userModel->update((int)$user['id'], ['role' => 'admin']);
            $user['role'] = 'admin';
        }
    } else if ($email === '24n40002@student.unika.ac.id') {
        if (!$user) {
            // Create user as regular user if they don't exist
            $newId = $userModel->create([
                'name' => $profile['name'] ?? 'User',
                'email' => $email,
                'role' => 'user',
                'avatar' => $profile['avatar'] ?? null,
                'google_id' => $profile['google_id'] ?? null,
                'phone' => ''
            ]);
            $user = $userModel->getById($newId);
        } else if ($user['role'] === 'admin') {
            // Downgrade to user if they were set as admin previously
            $userModel->update((int)$user['id'], ['role' => 'user']);
            $user['role'] = 'user';
        }
    }

    if (!$user) {
        // Email not found in DB
        header('Location: login.php?error=email_not_found&email=' . urlencode($email));
        exit;
    }

    if (!$user['is_active']) {
        header('Location: login.php?error=account_inactive');
        exit;
    }

    // Update avatar and Google ID if empty
    $updateData = [];
    if (empty($user['google_id'])) {
        $updateData['google_id'] = $profile['google_id'];
    }
    if ($user['avatar_url'] !== $profile['avatar']) {
        $updateData['avatar_url'] = $profile['avatar'];
    }
    if (!empty($updateData)) {
        $userModel->update((int)$user['id'], $updateData);
    }

    // 4. Generate and Send OTP
    $otp = $otpService->generate((int)$user['id']);
    
    if (!empty($user['phone'])) {
        $otpService->sendViaWhatsApp($user['phone'], $otp);
    } else {
        // Fallback for demo: log OTP to error log if phone is not set
        error_log("No phone number for user {$user['id']}. OTP is: $otp");
    }

    // 5. Save pending auth state to session
    $_SESSION['pending_user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
        'avatar_url' => $profile['avatar'],
        'phone' => $user['phone']
    ];

    // 6. Redirect to OTP verification page
    header('Location: otp.php');
    exit;

} catch (\Exception $e) {
    error_log("OAuth Error: " . $e->getMessage());
    header('Location: login.php?error=oauth_failed');
    exit;
}
