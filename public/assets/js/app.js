/**
 * ============================================================
 * UASKTE App — Main Application JavaScript
 * Handles PWA registration, toasts, loading overlay, sidebar,
 * and shared utilities.
 * ============================================================
 */

/* ── PWA Service Worker Registration ─────────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/uaskte/public/service-worker.js')
      .then((reg) => {
        console.log('[SW] Registered successfully. Scope:', reg.scope);
      })
      .catch((err) => {
        console.error('[SW] Registration failed:', err);
      });
  });
}


/* ── Toast Notification System ───────────────────────────── */

/**
 * Display an animated toast notification.
 * @param {string} message  — The message to display
 * @param {string} type     — 'success' | 'error' | 'warning' | 'info'
 */
function showToast(message, type = 'info') {
  // Ensure container exists
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  // Icon map
  const icons = {
    success: '✅',
    error:   '❌',
    warning: '⚠️',
    info:    'ℹ️',
  };

  // Title map
  const titles = {
    success: 'Success',
    error:   'Error',
    warning: 'Warning',
    info:    'Info',
  };

  // Create alert element
  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  alert.innerHTML = `
    <span class="alert__icon">${icons[type] || icons.info}</span>
    <div class="alert__content">
      <div class="alert__title">${titles[type] || titles.info}</div>
      <div class="alert__message">${message}</div>
    </div>
    <button class="alert__close" aria-label="Close">&times;</button>
    <div class="alert__progress"></div>
  `;

  // Close handler
  const closeBtn = alert.querySelector('.alert__close');
  closeBtn.addEventListener('click', () => dismissToast(alert));

  // Add to container
  container.appendChild(alert);

  // Auto-dismiss after 4 seconds
  setTimeout(() => dismissToast(alert), 4000);
}

/**
 * Dismiss a toast with slide-out animation.
 * @param {HTMLElement} alert — The alert element to remove
 */
function dismissToast(alert) {
  if (!alert || alert.classList.contains('removing')) return;
  alert.classList.add('removing');
  alert.addEventListener('animationend', () => {
    alert.remove();
  });
}


/* ── Loading Overlay ─────────────────────────────────────── */

/**
 * Show a full-screen loading overlay with a gradient spinner.
 */
function showLoading() {
  let overlay = document.querySelector('.loading-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
      <div class="loading-spinner"></div>
      <div class="loading-overlay__text">Loading…</div>
    `;
    document.body.appendChild(overlay);
  }
  // Force reflow before adding class for CSS transition
  void overlay.offsetWidth;
  overlay.classList.add('active');
}

/**
 * Hide the loading overlay.
 */
function hideLoading() {
  const overlay = document.querySelector('.loading-overlay');
  if (overlay) {
    overlay.classList.remove('active');
  }
}


/* ── Sidebar Toggle (Mobile) ─────────────────────────────── */

/**
 * Toggle sidebar open/closed state. Creates a backdrop overlay
 * on mobile to allow closing the sidebar by tapping outside.
 */
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  const isOpen = sidebar.classList.toggle('open');
  let backdrop = document.querySelector('.sidebar-backdrop');

  if (isOpen) {
    // Create backdrop if it doesn't exist
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'sidebar-backdrop';
      backdrop.addEventListener('click', toggleSidebar);
      document.body.appendChild(backdrop);
    }
    // Force reflow
    void backdrop.offsetWidth;
    backdrop.classList.add('active');
  } else if (backdrop) {
    backdrop.classList.remove('active');
  }
}


/* ── Fetch API Utility ───────────────────────────────────── */

/**
 * Wrapper around fetch with automatic JSON handling,
 * loading state, and error toasts.
 *
 * @param {string} url      — API endpoint
 * @param {object} options  — fetch options (method, body, etc.)
 * @returns {Promise<any>}  — Parsed JSON response data
 */
async function fetchAPI(url, options = {}) {
  // Merge default headers
  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  const config = {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  };

  // Convert body to JSON string if it's an object
  if (config.body && typeof config.body === 'object') {
    config.body = JSON.stringify(config.body);
  }

  showLoading();

  try {
    const response = await fetch(url, config);
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || `HTTP Error ${response.status}`);
    }

    return data;
  } catch (error) {
    console.error('[fetchAPI] Error:', error);
    showToast(error.message || 'An unexpected error occurred.', 'error');
    throw error;
  } finally {
    hideLoading();
  }
}


/* ── Date Formatting Utility ─────────────────────────────── */

/**
 * Format a date string to Indonesian locale.
 * @param {string} dateString — ISO date string or parseable date
 * @returns {string}          — Formatted date, e.g. "24 Juni 2026, 13:30:00"
 */
function formatDate(dateString) {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch (e) {
    return dateString;
  }
}


/* ── DOM Ready Initialization ────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
  // Hamburger toggle
  const hamburger = document.querySelector('.hamburger');
  if (hamburger) {
    hamburger.addEventListener('click', toggleSidebar);
  }

  // Sidebar nav-item active state
  const navLinks = document.querySelectorAll('.sidebar__nav-link');
  navLinks.forEach((link) => {
    link.addEventListener('click', () => {
      navLinks.forEach((l) => l.classList.remove('active'));
      link.classList.add('active');

      // Close sidebar on mobile after navigation
      if (window.innerWidth <= 1024) {
        toggleSidebar();
      }
    });
  });

  // Mark current page as active based on URL
  const currentPath = window.location.pathname;
  navLinks.forEach((link) => {
    if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
      link.classList.add('active');
    }
  });

  // Page enter animation
  document.body.classList.add('page-enter');
});
