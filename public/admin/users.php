<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Auth;

Auth::requireAdmin();
$user = Auth::getUser();

$pageTitle = 'Kelola User';
$pageClass = 'dashboard-page';
$basePath = '../';
$extraJs = [
    $basePath . 'assets/js/webauthn.js?v=' . time(),
    $basePath . 'assets/js/admin.js?v=' . time()
];
require_once __DIR__ . '/../../templates/header.php';
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
            <a href="users.php" class="nav-item active" style="background-color: #C0C0C0; color: #0A192F; border-radius: 24px; margin-bottom: 20px; padding: 12px 20px; font-weight: 600; display: flex; align-items: center; gap: 15px;">
                <span class="nav-icon" style="color: #0A192F; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
                </span> Kelola User
            </a>
            
            <a href="../logout.php" class="nav-item" style="color: white; margin-top: auto; padding: 12px 20px; font-weight: 500; display: flex; align-items: center; gap: 15px; opacity: 0.9; margin-bottom: 20px;">
                <span class="nav-icon" style="color: white; display: flex; align-items: center; justify-content: center; transform: rotate(180deg);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"></path></svg>
                </span> Logout
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-nav">
            <h2>Kelola User</h2>
        </header>

        <div class="content-wrapper">
            
            <!-- Biometric Prompt Section (Shown initially if not verified) -->
            <div id="biometricPrompt" class="biometric-prompt-container" style="display: flex; justify-content: center; align-items: center; min-height: 400px;">
                <div class="biometric-card glass-panel" style="background: rgba(10, 25, 47, 0.85); padding: 40px; border-radius: 16px; text-align: center; max-width: 400px; color: white;">
                    <div class="biometric-icon pulse" style="max-width: 80px; margin: 0 auto 20px auto; color: #E2E8F0;">
                    </div>
                    <h3 style="margin-bottom: 10px; font-size: 1.5rem; color: #FFFFFF;">Verifikasi Keamanan</h3>
                    <p style="color: #CBD5E1; margin-bottom: 20px;">Halaman ini berisi data sensitif. Silakan verifikasi identitas Anda menggunakan biometrik.</p>
                    <div class="biometric-actions" id="biometricInitial" style="display: flex; flex-direction: column; gap: 10px;">
                        <button id="btnStartVerify" class="btn btn-primary" style="width: 100%;">Verifikasi Sekarang</button>
                    </div>

                    <div id="emailVerificationSection" style="display: none; flex-direction: column; gap: 10px; margin-top: 20px;">
                        <input type="email" id="verifyEmailInput" class="form-control" placeholder="Masukkan Email" style="padding: 10px; border-radius: 8px; border: 1px solid #334155; background: rgba(255,255,255,0.05); color: white; width: 100%;">
                        <button id="btnSubmitEmail" class="btn btn-primary" style="width: 100%;">Lanjut ke Pendaftaran Fingerprint</button>
                    </div>

                    <div id="biometricRegisterSection" style="display: none; flex-direction: column; gap: 10px; margin-top: 20px;">
                        <p style="color: #CBD5E1; margin-bottom: 10px; font-size: 0.9rem;">Klik tombol di bawah ini untuk memunculkan Barcode (QR Code) dari sistem browser Anda.</p>
                        <button id="btnRegisterBiometric" class="btn btn-primary" style="width: 100%;">Tampilkan Barcode Pendaftaran</button>
                    </div>
                    <div class="biometric-card__status" style="margin-top: 15px;"></div>
                </div>
            </div>

            <!-- CRUD Content (Hidden initially) -->
            <div id="crudContent" class="panel" style="display: none;">
                <div class="panel-header flex-between">
                    <div class="search-box">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau email...">
                    </div>
                    <button id="btnAddUser" class="btn btn-primary">
                        <span class="icon">+</span> Tambah User
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Rows loaded via JS -->
                            <tr><td colspan="8" class="text-center">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination-controls" id="paginationControls">
                    <!-- Pagination generated via JS -->
                </div>
            </div>
            
        </div>
    </main>
</div>

<!-- Modal Create/Edit User -->
<div class="modal-overlay" id="userModalOverlay">
    <div id="userModal" class="modal">
        <div class="modal-content glass-panel" style="background: rgba(10, 25, 47, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); color: white;">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
                <h3 id="modalTitle" style="margin: 0; font-size: 1.25rem;">Tambah User</h3>
                <button class="close-modal" style="background: transparent; border: none; color: white; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>
            <form id="userForm" style="padding: 20px;">
                <input type="hidden" id="userId" name="id">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="userName" style="display: block; margin-bottom: 5px; color: #CBD5E1;">Nama Lengkap</label>
                    <input type="text" id="userName" name="name" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #334155; background: rgba(255,255,255,0.05); color: white;" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="userEmail" style="display: block; margin-bottom: 5px; color: #CBD5E1;">Email</label>
                    <input type="email" id="userEmail" name="email" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #334155; background: rgba(255,255,255,0.05); color: white;" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="userPhone" style="display: block; margin-bottom: 5px; color: #CBD5E1;">No. WhatsApp</label>
                    <input type="text" id="userPhone" name="phone" class="form-control" placeholder="628xxx" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #334155; background: rgba(255,255,255,0.05); color: white;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="userRole" style="display: block; margin-bottom: 5px; color: #CBD5E1;">Role</label>
                    <select id="userRole" name="role" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #334155; background: #0A192F; color: white;">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label class="toggle-switch" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="userStatus" name="is_active" value="1" checked>
                        <span class="toggle-label" style="color: #CBD5E1;">Status Aktif</span>
                    </label>
                </div>
                
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <button type="button" class="btn btn-secondary close-modal" style="background: transparent; color: white; border: 1px solid #334155; padding: 10px 20px; border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveUser" style="background: #E2E8F0; color: #0A192F; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Audit Log -->
<div class="modal-overlay" id="auditModalOverlay">
    <div id="auditModal" class="modal">
        <div class="modal-content glass-panel modal-lg" style="background: rgba(10, 25, 47, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); color: white;">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem;">Riwayat Perubahan (Audit Log)</h3>
                <button class="close-modal" style="background: transparent; border: none; color: white; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div id="auditLogContent" class="audit-timeline">
                    <!-- Audit logs loaded via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
