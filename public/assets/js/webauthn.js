/**
 * ============================================================
 * UASKTE App — WebAuthn Biometric Authentication
 * Handles browser biometric registration & verification
 * using the Web Authentication API (WebAuthn).
 * ============================================================
 */

/* ── Base64url ↔ ArrayBuffer Conversions ─────────────────── */

/**
 * Convert a base64url-encoded string to an ArrayBuffer.
 * @param {string} base64url — base64url string
 * @returns {ArrayBuffer}
 */
function base64urlToBuffer(base64url) {
  // Replace base64url characters with standard base64
  let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');

  // Add padding if necessary
  const pad = base64.length % 4;
  if (pad) {
    base64 += '='.repeat(4 - pad);
  }

  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

/**
 * Convert an ArrayBuffer to a base64url-encoded string.
 * @param {ArrayBuffer} buffer — The buffer to encode
 * @returns {string}           — base64url string
 */
function bufferToBase64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary)
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}


/* ── Browser Support Check ───────────────────────────────── */

/**
 * Check if the browser supports WebAuthn with a platform
 * authenticator (fingerprint, Face ID, Windows Hello, etc.).
 * @returns {Promise<boolean>}
 */
async function checkBiometricSupport() {
  if (!window.PublicKeyCredential) {
    return false;
  }
  return true;
}


/* ── Biometric Registration ──────────────────────────────── */

/**
 * Register a new biometric credential with the server.
 * Flow:
 * 1. GET /api/webauthn-register.php → credential creation options
 * 2. navigator.credentials.create() → create credential
 * 3. POST /api/webauthn-register.php → send credential to server
 */
async function registerBiometric() {
  const statusEl = document.querySelector('.biometric-card__status');

  try {
    // Check support first
    const supported = await checkBiometricSupport();
    if (!supported) {
      showBiometricFallback();
      return;
    }

    updateStatus(statusEl, 'Requesting registration options…', '');

    // Step 1: Fetch creation options from server
    const optionsResponse = await fetchAPI(window.API_BASE + 'webauthn-register.php', {
      method: 'GET',
    });

    const options = optionsResponse.options || optionsResponse;

    // Convert base64url fields to ArrayBuffers
    options.challenge = base64urlToBuffer(options.challenge);
    options.user.id   = base64urlToBuffer(options.user.id);

    // Convert excludeCredentials if present
    if (options.excludeCredentials) {
      options.excludeCredentials = options.excludeCredentials.map((cred) => ({
        ...cred,
        id: base64urlToBuffer(cred.id),
      }));
    }

    updateStatus(statusEl, 'Please verify your identity using biometrics…', '');

    // Step 2: Create credential via browser
    const credential = await navigator.credentials.create({
      publicKey: options,
    });

    updateStatus(statusEl, 'Processing registration…', '');

    // Step 3: Prepare and send result to server
    const registrationData = {
      id:    credential.id,
      rawId: bufferToBase64url(credential.rawId),
      type:  credential.type,
      response: {
        attestationObject: bufferToBase64url(credential.response.attestationObject),
        clientDataJSON:    bufferToBase64url(credential.response.clientDataJSON),
      },
    };

    const result = await fetchAPI(window.API_BASE + 'webauthn-register.php', {
      method: 'POST',
      body: registrationData,
    });

    if (result.success) {
      updateStatus(statusEl, '✅ Biometric registered successfully!', 'success');
      showToast('Biometric authentication registered!', 'success');
      return true;
    } else {
      throw new Error(result.message || 'Registration failed.');
    }
  } catch (error) {
    // User cancellation
    if (error.name === 'NotAllowedError') {
      updateStatus(statusEl, 'Registration cancelled by user.', 'error');
      showToast('Biometric registration was cancelled.', 'warning');
      return false;
    }

    console.error('[WebAuthn] Registration error:', error);
    updateStatus(statusEl, 'Registration failed. Please try again.', 'error');
    showToast(error.message || 'Biometric registration failed.', 'error');
    return false;
  }
}


/* ── Biometric Authentication ────────────────────────────── */

/**
 * Authenticate using a previously registered biometric credential.
 * Flow:
 * 1. GET /api/webauthn-verify.php → assertion options
 * 2. navigator.credentials.get() → get assertion
 * 3. POST /api/webauthn-verify.php → send assertion to server
 */
async function authenticateBiometric() {
  const statusEl = document.querySelector('.biometric-card__status');

  try {
    // Check support first
    const supported = await checkBiometricSupport();
    if (!supported) {
      showBiometricFallback();
      return;
    }

    updateStatus(statusEl, 'Requesting authentication options…', '');

    // Step 1: Fetch assertion options from server
    const optionsResponse = await fetchAPI(window.API_BASE + 'webauthn-verify.php', {
      method: 'GET',
    });

    const options = optionsResponse.options || optionsResponse;

    // Convert base64url fields to ArrayBuffers
    options.challenge = base64urlToBuffer(options.challenge);

    // Convert allowCredentials
    if (options.allowCredentials) {
      options.allowCredentials = options.allowCredentials.map((cred) => ({
        ...cred,
        id: base64urlToBuffer(cred.id),
      }));
    }

    updateStatus(statusEl, 'Please verify your identity using biometrics…', '');

    // Step 2: Get credential assertion via browser
    const assertion = await navigator.credentials.get({
      publicKey: options,
    });

    updateStatus(statusEl, 'Verifying identity…', '');

    // Step 3: Prepare and send result to server
    const authData = {
      id:    assertion.id,
      rawId: bufferToBase64url(assertion.rawId),
      type:  assertion.type,
      response: {
        authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
        clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
        signature:         bufferToBase64url(assertion.response.signature),
        userHandle:        assertion.response.userHandle
          ? bufferToBase64url(assertion.response.userHandle)
          : null,
      },
    };

    const result = await fetchAPI(window.API_BASE + 'webauthn-verify.php', {
      method: 'POST',
      body: authData,
    });

    if (result.success) {
      updateStatus(statusEl, '✅ Identity verified!', 'success');
      showToast('Biometric authentication successful!', 'success');

      // Redirect if the server provides a URL
      if (result.redirect) {
        setTimeout(() => {
          window.location.href = result.redirect;
        }, 800);
      }
    } else {
      throw new Error(result.message || 'Authentication failed.');
    }
  } catch (error) {
    // User cancellation
    if (error.name === 'NotAllowedError') {
      updateStatus(statusEl, 'Authentication cancelled by user.', 'error');
      showToast('Biometric authentication was cancelled.', 'warning');
      return;
    }

    console.error('[WebAuthn] Authentication error:', error);
    updateStatus(statusEl, 'Authentication failed. Please try again.', 'error');
    showToast(error.message || 'Biometric authentication failed.', 'error');
  }
}


/* ── UI Helpers ──────────────────────────────────────────── */

/**
 * Update the status text element with a message and style.
 * @param {HTMLElement|null} el  — The status element
 * @param {string} text         — Status message
 * @param {string} className    — 'success', 'error', or ''
 */
function updateStatus(el, text, className) {
  if (!el) return;
  el.textContent = text;
  el.className = 'biometric-card__status';
  if (className) {
    el.classList.add(className);
  }
}

/**
 * Show a fallback message when biometric auth is not supported.
 */
function showBiometricFallback() {
  const card = document.querySelector('.biometric-card');
  if (!card) return;

  const statusEl = card.querySelector('.biometric-card__status');
  if (statusEl) {
    statusEl.textContent = 'Biometric authentication is not supported on this device/browser.';
    statusEl.className = 'biometric-card__status error';
  }

  showToast(
    'Your browser or device does not support biometric authentication. Please use an alternative method.',
    'warning'
  );
}
