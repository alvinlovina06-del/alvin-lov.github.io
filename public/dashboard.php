<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AuditLog.php';
require_once __DIR__ . '/../src/UserModel.php';

use App\Auth;
use App\AuditLog;
use App\UserModel;

Auth::requireLogin();

$user = Auth::getUser();
$user = Auth::getUser();

$pageTitle = 'Dashboard';
$pageClass = 'dashboard-page';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" style="background-color: #0A192F;"> <!-- Navy background for sidebar -->
        <div class="sidebar-header" style="padding: 30px 24px; display: flex; align-items: center; gap: 15px; border-bottom: none;">
            <div style="border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; position: relative; flex-shrink: 0; box-shadow: 0 4px 10px rgba(192,192,192,0.3);">
                <div style="transform: rotate(45deg); display: flex; align-items: center; justify-content: center;">
                    <!-- Insert icon or text for logo -->
                </div>
            </div>
            <h2 style="color: white; font-size: 1.25rem; font-weight: 600; margin: 0; line-height: 1.2;">KTE USER MANAGEMENT</h2>
        </div>

        <nav class="sidebar-nav" style="padding: 10px 20px; display: flex; flex-direction: column; height: calc(100vh - 120px);">
            <a href="dashboard.php" class="nav-item active" style="background-color: #C0C0C0; color: #0A192F; border-radius: 24px; margin-bottom: 20px; padding: 12px 20px; font-weight: 600; display: flex; align-items: center; gap: 15px;">
                <span class="nav-icon" style="color: #0A192F; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"></path></svg>
                </span> Dashboard
            
            <a href="logout.php" class="nav-item" style="color: white; margin-top: auto; padding: 12px 20px; font-weight: 500; display: flex; align-items: center; gap: 15px; opacity: 0.9; margin-bottom: 20px;">
                <span class="nav-icon" style="color: white; display: flex; align-items: center; justify-content: center; transform: rotate(180deg);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"></path></svg>
                </span> Logout
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-nav d-mobile">
        </header>

        <div class="content-wrapper">
            <div class="welcome-section text-center" style="margin-bottom: 2rem;">
                <h1>Selamat datang, <?= htmlspecialchars($user['name']) ?>!</h1>
                <p>Semoga hari Anda menyenangkan.</p>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
