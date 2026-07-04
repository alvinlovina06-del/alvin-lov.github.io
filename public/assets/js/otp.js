/**
 * ============================================================
 * UASKTE App — OTP Verification Page
 * Handles 6-digit OTP input, auto-focus, paste, countdown
 * timer, resend, and AJAX verification.
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', () => {
  const otpInputs  = document.querySelectorAll('.otp-inputs input');
  const otpForm    = document.querySelector('.otp-inputs');
  const timerEl    = document.querySelector('.otp-timer');
  const resendBtn  = document.querySelector('.otp-resend button');

  if (!otpInputs.length) return;

  /* ── Auto-Focus & Input Handling ─────────────────────────── */

  otpInputs.forEach((input, index) => {
    // Only allow single digit
    input.addEventListener('input', (e) => {
      const value = e.target.value;

      // Keep only last digit entered
      if (value.length > 1) {
        e.target.value = value.slice(-1);
      }

      // Allow only digits
      if (!/^\d$/.test(e.target.value)) {
        e.target.value = '';
        return;
      }

      // Mark as filled
      e.target.classList.add('filled');

      // Auto-focus next input
      if (e.target.value && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }

      // Check if all inputs are filled → auto-submit
      checkAndSubmit();
    });

    // Backspace → move to previous input
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace') {
        if (!e.target.value && index > 0) {
          otpInputs[index - 1].focus();
          otpInputs[index - 1].value = '';
          otpInputs[index - 1].classList.remove('filled');
        } else {
          e.target.classList.remove('filled');
        }
      }

      // Arrow key navigation
      if (e.key === 'ArrowLeft' && index > 0) {
        e.preventDefault();
        otpInputs[index - 1].focus();
      }
      if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
        e.preventDefault();
        otpInputs[index + 1].focus();
      }
    });

    // Focus → select all text in input
    input.addEventListener('focus', () => {
      input.select();
    });
  });


  /* ── Paste Support ───────────────────────────────────────── */

  otpInputs[0].addEventListener('paste', (e) => {
    e.preventDefault();
    const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
    const digits = pasteData.replace(/\D/g, '').slice(0, 6);

    if (digits.length === 0) return;

    digits.split('').forEach((digit, i) => {
      if (otpInputs[i]) {
        otpInputs[i].value = digit;
        otpInputs[i].classList.add('filled');
      }
    });

    // Focus last filled or next empty
    const focusIdx = Math.min(digits.length, otpInputs.length - 1);
    otpInputs[focusIdx].focus();

    // Auto-submit if all 6 filled
    if (digits.length >= 6) {
      checkAndSubmit();
    }
  });


  /* ── Auto-Submit Check ───────────────────────────────────── */

  function checkAndSubmit() {
    const otp = getOtpValue();
    if (otp.length === 6) {
      verifyOtp(otp);
    }
  }

  /**
   * Collect the 6-digit OTP from all input fields.
   * @returns {string} — The concatenated OTP string
   */
  function getOtpValue() {
    return Array.from(otpInputs)
      .map((input) => input.value)
      .join('');
  }


  /* ── AJAX Verify OTP ─────────────────────────────────────── */

  let isSubmitting = false;

  async function verifyOtp(otp) {
    if (isSubmitting) return;
    isSubmitting = true;

    try {
      const data = await fetchAPI('/uaskte/public/api/otp-verify.php', {
        method: 'POST',
        body: { otp },
      });

      if (data.success) {
        showToast('OTP verified successfully!', 'success');
        // Short delay so user sees the success message
        setTimeout(() => {
          window.location.href = data.redirect ? '/uaskte/public/' + data.redirect : '/uaskte/public/dashboard.php';
        }, 800);
      } else {
        handleVerifyError(data.message || 'Invalid OTP code.');
      }
    } catch (error) {
      handleVerifyError('Verification failed. Please try again.');
    } finally {
      isSubmitting = false;
    }
  }

  /**
   * Handle a failed OTP verification attempt.
   * @param {string} message — Error message to display
   */
  function handleVerifyError(message) {
    showToast(message, 'error');

    // Shake animation on inputs
    if (otpForm) {
      otpForm.classList.add('shake');
      setTimeout(() => {
        otpForm.classList.remove('shake');
      }, 600);
    }

    // Clear all inputs
    otpInputs.forEach((input) => {
      input.value = '';
      input.classList.remove('filled');
    });

    // Re-focus first input
    otpInputs[0].focus();
  }


  /* ── Countdown Timer (5 minutes) ─────────────────────────── */

  let countdown = 300; // 5 minutes in seconds
  let timerInterval = null;

  function startTimer() {
    updateTimerDisplay();
    timerInterval = setInterval(() => {
      countdown--;
      updateTimerDisplay();

      if (countdown <= 0) {
        clearInterval(timerInterval);
        showToast('OTP has expired. Please request a new one.', 'warning');
        disableInputs(true);
      }
    }, 1000);
  }

  function updateTimerDisplay() {
    if (!timerEl) return;

    const minutes = Math.floor(countdown / 60);
    const seconds = countdown % 60;
    const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    timerEl.textContent = formatted;

    // Add expiring class when under 60 seconds
    if (countdown <= 60) {
      timerEl.classList.add('expiring');
    } else {
      timerEl.classList.remove('expiring');
    }
  }

  function disableInputs(disabled) {
    otpInputs.forEach((input) => {
      input.disabled = disabled;
    });
  }

  // Start the timer
  startTimer();


  /* ── Resend OTP ──────────────────────────────────────────── */

  let resendCooldown = 60; // seconds before resend is enabled
  let resendInterval = null;

  function startResendCooldown() {
    if (!resendBtn) return;

    resendBtn.disabled = true;
    resendCooldown = 60;
    updateResendDisplay();

    resendInterval = setInterval(() => {
      resendCooldown--;
      updateResendDisplay();

      if (resendCooldown <= 0) {
        clearInterval(resendInterval);
        resendBtn.disabled = false;
        resendBtn.textContent = 'Resend Code';
      }
    }, 1000);
  }

  function updateResendDisplay() {
    if (!resendBtn) return;
    if (resendCooldown > 0) {
      resendBtn.textContent = `Resend in ${resendCooldown}s`;
    }
  }

  // Initialize resend cooldown
  startResendCooldown();

  // Resend click handler
  if (resendBtn) {
    resendBtn.addEventListener('click', async () => {
      if (resendBtn.disabled) return;

      try {
        const data = await fetchAPI('/uaskte/public/api/otp-resend.php', {
          method: 'POST',
        });

        showToast(data.message || 'A new OTP has been sent.', 'success');

        // Reset timer
        clearInterval(timerInterval);
        countdown = 300;
        startTimer();

        // Reset inputs
        disableInputs(false);
        otpInputs.forEach((input) => {
          input.value = '';
          input.classList.remove('filled');
        });
        otpInputs[0].focus();

        // Restart resend cooldown
        startResendCooldown();
      } catch (error) {
        // fetchAPI already shows the error toast
      }
    });
  }

  // Auto-focus first input on load
  otpInputs[0].focus();
});
