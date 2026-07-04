/**
 * ============================================================
 * UASKTE App — Admin CRUD Panel
 * Handles user management: list, create, edit, delete, search,
 * pagination, and audit log display.
 * ============================================================
 */

/* ── State ───────────────────────────────────────────────── */
let currentPage    = 1;
let currentSearch  = '';
let editingUserId  = null;
let debounceTimer  = null;


/* ── Load Users (with pagination & search) ───────────────── */

/**
 * Fetch and render the users table.
 * @param {number} page   — Page number (1-based)
 * @param {string} search — Search query string
 */
async function loadUsers(page = 1, search = '') {
  currentPage   = page;
  currentSearch = search;

  try {
    const params = new URLSearchParams({ page, search });
    const data   = await fetchAPI(`/uaskte/public/api/users-crud.php?${params}`, {
      method: 'GET',
    });

    renderUsersTable(data.users || []);
    renderPagination(data.totalPages || 1, page);

    // Update info text
    const infoEl = document.querySelector('.table-footer__info');
    if (infoEl && data.total !== undefined) {
      const from = data.users && data.users.length > 0 ? (page - 1) * (data.perPage || 10) + 1 : 0;
      const to   = from + (data.users ? data.users.length : 0) - 1;
      infoEl.textContent = `Showing ${from}–${to} of ${data.total} users`;
    }
  } catch (error) {
    // fetchAPI already shows an error toast
    console.error('[Admin] Failed to load users:', error);
  }
}


/* ── Render Table ────────────────────────────────────────── */

/**
 * Render user rows into the data table.
 * @param {Array} users — Array of user objects
 */
function renderUsersTable(users) {
  const tbody = document.querySelector('.data-table tbody');
  if (!tbody) return;

  if (!users || users.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6">
          <div class="empty-state">
            <div class="empty-state__icon">👥</div>
            <div class="empty-state__title">No users found</div>
            <div class="empty-state__desc">Try adjusting your search or create a new user.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = users.map((user) => `
    <tr>
      <td data-label="User">
        <div class="flex items-center gap-3">
          <div class="avatar-placeholder">${getInitials(user.name)}</div>
          <div>
            <div class="text-primary" style="font-weight:500;">${escapeHtml(user.name)}</div>
            <div class="text-muted" style="font-size:var(--text-xs);">${escapeHtml(user.email)}</div>
          </div>
        </div>
      </td>
      <td data-label="Phone">
        ${user.phone ? escapeHtml(user.phone) : '<span class="text-muted">-</span>'}
      </td>
      <td data-label="Role">
        <span class="badge badge-${user.role === 'admin' ? 'admin' : 'user'}">
          ${user.role === 'admin' ? '👑' : '👤'} ${escapeHtml(user.role)}
        </span>
      </td>
      <td data-label="Status">
        <span class="badge badge-${user.is_active ? 'success' : 'danger'}">
          ${user.is_active ? 'Active' : 'Inactive'}
        </span>
      </td>
      <td data-label="Created" class="mono text-secondary" style="font-size:var(--text-xs);">
        ${formatDate(user.created_at)}
      </td>
      <td data-label="Actions" class="actions-cell">
        <button class="btn btn-sm btn-outline" onclick="openEditModal('${user.id}')" title="Edit">
          ✏️ Edit
        </button>
        <button class="btn btn-sm btn-outline" onclick="loadAuditLog('${user.id}')" title="Audit Log">
          📋 Log
        </button>
        <button class="btn btn-sm btn-danger" onclick="deleteUser('${user.id}')" title="Delete">
          🗑️
        </button>
      </td>
    </tr>
  `).join('');
}


/* ── Pagination ──────────────────────────────────────────── */

/**
 * Render pagination buttons.
 * @param {number} totalPages  — Total number of pages
 * @param {number} currentPage — Current active page
 */
function renderPagination(totalPages, currentPage) {
  const container = document.querySelector('.pagination');
  if (!container) return;

  let html = '';

  // Previous button
  html += `<button class="pagination__btn" ${currentPage <= 1 ? 'disabled' : ''} onclick="loadUsers(${currentPage - 1}, '${escapeAttr(currentSearch)}')">&laquo;</button>`;

  // Page buttons (show max 5 pages with ellipsis)
  const maxVisible = 5;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage   = Math.min(totalPages, startPage + maxVisible - 1);

  if (endPage - startPage < maxVisible - 1) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }

  if (startPage > 1) {
    html += `<button class="pagination__btn" onclick="loadUsers(1, '${escapeAttr(currentSearch)}')">1</button>`;
    if (startPage > 2) {
      html += `<span class="pagination__btn" style="border:none;cursor:default;">…</span>`;
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    html += `<button class="pagination__btn ${i === currentPage ? 'active' : ''}" onclick="loadUsers(${i}, '${escapeAttr(currentSearch)}')">${i}</button>`;
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += `<span class="pagination__btn" style="border:none;cursor:default;">…</span>`;
    }
    html += `<button class="pagination__btn" onclick="loadUsers(${totalPages}, '${escapeAttr(currentSearch)}')">${totalPages}</button>`;
  }

  // Next button
  html += `<button class="pagination__btn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="loadUsers(${currentPage + 1}, '${escapeAttr(currentSearch)}')">&raquo;</button>`;

  container.innerHTML = html;
}


/* ── Modal Controls ──────────────────────────────────────── */

/**
 * Open the user form modal in "Create" mode.
 */
function openCreateModal() {
  editingUserId = null;
  resetForm();

  const title = document.getElementById('modalTitle');
  if (title) title.textContent = 'Tambah User';

  const submitBtn = document.getElementById('btnSaveUser');
  if (submitBtn) submitBtn.textContent = 'Simpan Data';

  openModal();
}

/**
 * Open the user form modal in "Edit" mode, pre-populated.
 * @param {string} userId — The ID of the user to edit
 */
async function openEditModal(userId) {
  editingUserId = userId;
  resetForm();

  try {
    const data = await fetchAPI(`/uaskte/public/api/users-crud.php?id=${userId}`, {
      method: 'GET',
    });

    const user = data.data || data.user || data;

    // Populate form fields
    const nameInput   = document.querySelector('#userName');
    const emailInput  = document.querySelector('#userEmail');
    const phoneInput  = document.querySelector('#userPhone');
    const roleInput   = document.querySelector('#userRole');
    const statusInput = document.querySelector('#userStatus');

    if (nameInput)   nameInput.value   = user.name  || '';
    if (emailInput)  emailInput.value  = user.email || '';
    if (phoneInput)  phoneInput.value  = user.phone || '';
    if (roleInput)   roleInput.value   = user.role  || 'user';
    if (statusInput) statusInput.checked = user.is_active == 1;

    const title = document.getElementById('modalTitle');
    if (title) title.textContent = 'Edit User';

    const submitBtn = document.getElementById('btnSaveUser');
    if (submitBtn) submitBtn.textContent = 'Simpan Perubahan';

    openModal();
  } catch (error) {
    console.error('[Admin] Failed to fetch user:', error);
  }
}

/**
 * Show the modal overlay.
 */
function openModal() {
  const overlay = document.querySelector('.modal-overlay');
  if (overlay) overlay.classList.add('active');
}

/**
 * Hide the modal overlay.
 */
function closeModal() {
  const overlay = document.querySelector('.modal-overlay');
  if (overlay) overlay.classList.remove('active');
  editingUserId = null;
}

/**
 * Reset all form fields and clear validation errors.
 */
function resetForm() {
  const form = document.querySelector('#userForm');
  if (form) form.reset();

  // Clear error classes
  document.querySelectorAll('.form-group__input.error').forEach((el) => {
    el.classList.remove('error');
  });
}


/* ── Save User (Create / Update) ─────────────────────────── */

/**
 * Handle form submission for creating or editing a user.
 * @param {Event} event — The submit event
 */
async function saveUser(event) {
  if (event) event.preventDefault();

  // Validate form
  if (!validateForm()) return;

  const nameInput   = document.querySelector('#userName');
  const emailInput  = document.querySelector('#userEmail');
  const phoneInput  = document.querySelector('#userPhone');
  const roleInput   = document.querySelector('#userRole');
  const statusInput = document.querySelector('#userStatus');

  const userData = {
    name:      nameInput   ? nameInput.value.trim()  : '',
    email:     emailInput  ? emailInput.value.trim() : '',
    phone:     phoneInput  ? phoneInput.value.trim() : '',
    role:      roleInput   ? roleInput.value         : 'user',
    is_active: statusInput ? (statusInput.checked ? 1 : 0) : 1
  };

  try {
    let result;

    if (editingUserId) {
      // Update existing user (PUT)
      userData.id = editingUserId;
      result = await fetchAPI(`/uaskte/public/api/users-crud.php?id=${editingUserId}`, {
        method: 'PUT',
        body: userData,
      });
    } else {
      // Create new user (POST)
      result = await fetchAPI('/uaskte/public/api/users-crud.php', {
        method: 'POST',
        body: userData,
      });
    }

    if (result.success) {
      showToast(
        editingUserId ? 'User updated successfully!' : 'User created successfully!',
        'success'
      );
      closeModal();
      loadUsers(currentPage, currentSearch);
    } else {
      showToast(result.message || 'Failed to save user.', 'error');
    }
  } catch (error) {
    // fetchAPI already shows the error toast
    console.error('[Admin] Failed to save user:', error);
  }
}


/* ── Delete User ─────────────────────────────────────────── */

/**
 * Delete a user after confirmation.
 * @param {string} userId — The ID of the user to delete
 */
async function deleteUser(userId) {
  // Use a styled confirm dialog if available, otherwise browser confirm
  const confirmed = confirm('Are you sure you want to delete this user? This action cannot be undone.');
  if (!confirmed) return;

  try {
    const result = await fetchAPI(`/uaskte/public/api/users-crud.php?id=${userId}`, {
      method: 'DELETE',
      body: { id: userId },
    });

    if (result.success) {
      showToast('User deleted successfully.', 'success');
      loadUsers(currentPage, currentSearch);
    } else {
      showToast(result.message || 'Failed to delete user.', 'error');
    }
  } catch (error) {
    console.error('[Admin] Failed to delete user:', error);
  }
}


/* ── Audit Log ───────────────────────────────────────────── */

/**
 * Fetch and display the audit log for a specific user.
 * @param {string} userId — The user ID to fetch logs for
 */
async function loadAuditLog(userId) {
  const timeline = document.querySelector('.audit-timeline');
  if (!timeline) {
    // If no timeline container on the page, show in a modal or alert
    showToast('Audit log panel not found on this page.', 'info');
    return;
  }

  try {
    const data = await fetchAPI(`/uaskte/public/api/audit-log.php?user_id=${userId}`, {
      method: 'GET',
    });

    const logs = data.logs || data.audit || [];

    if (!logs.length) {
      timeline.innerHTML = `
        <div class="empty-state">
          <div class="empty-state__icon">📋</div>
          <div class="empty-state__title">No audit logs</div>
          <div class="empty-state__desc">No activity has been recorded for this user yet.</div>
        </div>
      `;
      return;
    }

    // Action type → CSS modifier
    const actionTypes = {
      create: 'create',
      insert: 'create',
      update: 'update',
      edit:   'update',
      delete: 'delete',
      remove: 'delete',
      login:  'login',
      auth:   'login',
    };

    timeline.innerHTML = logs.map((log) => {
      const type = actionTypes[log.action?.toLowerCase()] || 'update';
      return `
        <div class="timeline-item timeline-item--${type}">
          <div class="timeline-item__dot"></div>
          <div class="timeline-item__card">
            <div class="timeline-item__header">
              <span class="timeline-item__action">${escapeHtml(log.action || 'Unknown')}</span>
              <span class="timeline-item__time">${formatDate(log.created_at || log.timestamp)}</span>
            </div>
            <div class="timeline-item__detail">
              ${escapeHtml(log.description || log.detail || 'No details available.')}
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Scroll to timeline if needed
    timeline.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (error) {
    console.error('[Admin] Failed to load audit log:', error);
  }
}


/* ── Form Validation ─────────────────────────────────────── */

/**
 * Validate the user form before submission.
 * @returns {boolean} — true if valid
 */
function validateForm() {
  let isValid = true;

  const nameInput  = document.querySelector('#userName');
  const emailInput = document.querySelector('#userEmail');
  const roleInput  = document.querySelector('#userRole');

  // Clear previous errors
  [nameInput, emailInput, roleInput].forEach((el) => {
    if (el) el.classList.remove('error');
  });

  // Name: required
  if (nameInput && !nameInput.value.trim()) {
    nameInput.classList.add('error');
    isValid = false;
  }

  // Email: required & valid format
  if (emailInput) {
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
      emailInput.classList.add('error');
      isValid = false;
    }
  }

  // Role: required
  if (roleInput && !roleInput.value) {
    roleInput.classList.add('error');
    isValid = false;
  }

  if (!isValid) {
    showToast('Please fill in all required fields correctly.', 'warning');
  }

  return isValid;
}


/* ── Search with Debounce ────────────────────────────────── */

/**
 * Handle search input with a 300ms debounce.
 * @param {Event} event — Input event
 */
function handleSearch(event) {
  const value = event.target.value.trim();

  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }

  debounceTimer = setTimeout(() => {
    loadUsers(1, value);
  }, 300);
}


/* ── Utility Helpers ─────────────────────────────────────── */

/**
 * Escape HTML special characters to prevent XSS.
 * @param {string} str — Raw string
 * @returns {string}   — Escaped string
 */
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

/**
 * Escape a string for use inside an HTML attribute.
 * @param {string} str — Raw string
 * @returns {string}   — Escaped string
 */
function escapeAttr(str) {
  if (!str) return '';
  return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

/**
 * Get initials from a full name for the avatar placeholder.
 * @param {string} name — Full name
 * @returns {string}    — Up to 2-character initials
 */
function getInitials(name) {
  if (!name) return '?';
  return name
    .split(' ')
    .map((word) => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}


/* ── DOM Ready Initialization ────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
  // Load initial user list
  loadUsers();

  // Search input event
  const searchInput = document.querySelector('.search-input');
  if (searchInput) {
    searchInput.addEventListener('input', handleSearch);
  }

  // Form submit
  const userForm = document.querySelector('#userForm');
  if (userForm) {
    userForm.addEventListener('submit', saveUser);
  }

  // Modal close button
  const closeBtns = document.querySelectorAll('.close-modal');
  closeBtns.forEach(btn => {
    btn.addEventListener('click', closeModal);
  });

  // Modal open button for Add User
  const btnAddUser = document.getElementById('btnAddUser');
  if (btnAddUser) {
    btnAddUser.addEventListener('click', openCreateModal);
  }

  // Close modal on backdrop click
  const modalOverlay = document.querySelector('.modal-overlay');
  if (modalOverlay) {
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        closeModal();
      }
    });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeModal();
    }
  });

  // Biometric Verification Flow
  const btnStartVerify = document.getElementById('btnStartVerify');
  const biometricInitial = document.getElementById('biometricInitial');
  const emailVerificationSection = document.getElementById('emailVerificationSection');
  const verifyEmailInput = document.getElementById('verifyEmailInput');
  const btnSubmitEmail = document.getElementById('btnSubmitEmail');
  const biometricRegisterSection = document.getElementById('biometricRegisterSection');
  const btnRegisterBiometric = document.getElementById('btnRegisterBiometric');
  const biometricPrompt = document.getElementById('biometricPrompt');
  const crudContent = document.getElementById('crudContent');

  if (btnStartVerify) {
    btnStartVerify.addEventListener('click', () => {
      if (biometricInitial) biometricInitial.style.display = 'none';
      if (emailVerificationSection) emailVerificationSection.style.display = 'flex';
    });
  }

  if (btnSubmitEmail) {
    btnSubmitEmail.addEventListener('click', () => {
      const email = verifyEmailInput ? verifyEmailInput.value.trim() : '';
      if (email === 'alvinlovina06@gmail.com') {
        if (emailVerificationSection) emailVerificationSection.style.display = 'none';
        if (biometricRegisterSection) biometricRegisterSection.style.display = 'flex';
        showToast('Email terverifikasi. Silakan daftar biometrik.', 'success');
      } else {
        showToast('Email tidak sesuai!', 'error');
      }
    });
  }

  if (btnRegisterBiometric) {
    btnRegisterBiometric.addEventListener('click', async () => {
      if (typeof registerBiometric === 'function') {
        const success = await registerBiometric();
        if (success) {
          setTimeout(() => {
            if (biometricPrompt) biometricPrompt.style.display = 'none';
            if (crudContent) crudContent.style.display = 'block';
          }, 1500);
        }
      }
    });
  }
});
