<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/UserModel.php';
require_once __DIR__ . '/../../src/AuditLog.php';

use App\Auth;
use App\UserModel;
use App\AuditLog;

header('Content-Type: application/json');

// Only Admins can access this API
if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userModel = new UserModel();
$auditLog = new AuditLog();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // GET /api/users-crud.php?id=1 -> get single user
        // GET /api/users-crud.php?action=audit&id=1 -> get audit log
        // GET /api/users-crud.php?search=x&page=1 -> get all users
        
        if (isset($_GET['action']) && $_GET['action'] === 'audit' && isset($_GET['id'])) {
            $logs = $auditLog->getByRecord('users', (int)$_GET['id']);
            echo json_encode(['success' => true, 'data' => $logs]);
            exit;
        }
        
        if (isset($_GET['id'])) {
            $user = $userModel->getById((int)$_GET['id']);
            if ($user) {
                echo json_encode(['success' => true, 'data' => $user]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            exit;
        }
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = $_GET['search'] ?? '';
        $limit = 10;
        
        $usersResult = $userModel->getAll($page, $limit, $search);
        
        echo json_encode([
            'success' => true,
            'users' => $usersResult['data'],
            'total' => $usersResult['total'],
            'totalPages' => $usersResult['total_pages'],
            'perPage' => $usersResult['limit']
        ]);
        
    } elseif ($method === 'POST') {
        // Create user
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name']) || empty($input['email'])) {
            echo json_encode(['success' => false, 'message' => 'Nama dan email wajib diisi']);
            exit;
        }
        
        // Check if email exists
        if ($userModel->getByEmail($input['email'])) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar']);
            exit;
        }
        
        $userId = $userModel->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'] ?? 'user',
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ]);
        
        echo json_encode(['success' => true, 'message' => 'User berhasil ditambahkan', 'id' => $userId]);
        
    } elseif ($method === 'PUT') {
        // Update user
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID User tidak valid']);
            exit;
        }
        
        // Check if email is being changed and if it exists
        $existingUser = $userModel->getById($id);
        if ($existingUser['email'] !== $input['email']) {
            if ($userModel->getByEmail($input['email'])) {
                echo json_encode(['success' => false, 'message' => 'Email sudah digunakan oleh user lain']);
                exit;
            }
        }
        
        $updateData = [
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'] ?? 'user',
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ];
        
        $userModel->update($id, $updateData);
        
        echo json_encode(['success' => true, 'message' => 'User berhasil diupdate']);
        
    } elseif ($method === 'DELETE') {
        // Soft Delete user
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID User tidak valid']);
            exit;
        }
        
        // Prevent deleting yourself
        $currentUser = Auth::getUser();
        if ($currentUser['user_id'] == $id) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak bisa menghapus akun Anda sendiri']);
            exit;
        }
        
        $userModel->delete($id);
        echo json_encode(['success' => true, 'message' => 'User berhasil dinonaktifkan/dihapus']);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (\Exception $e) {
    error_log("API Error (users-crud): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server']);
}
