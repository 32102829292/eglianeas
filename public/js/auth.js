/* Egliane — auth flows (login tabs, PIN keypad, biometric, verification, register) */
(function () {
  'use strict';

  var E = (window.egliane = window.egliane || {});

  var b64urlEncode = function (buffer) {
    var bytes = new Uint8Array(buffer);
    var str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  };

  var toBase64Url = function (string) {
    var bytes = new TextEncoder().encode(string);
    return b64urlEncode(bytes);
  };

  E.webauthn = {
    supported: function () {
      return typeof window.PublicKeyCredential !== 'undefined' &&
        typeof window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function';
    },

    create: function (options) {
      options.challenge = E.webauthn.fromB64url(options.challenge);
      if (options.user && options.user.id) options.user.id = E.webauthn.fromB64url(options.user.id);
      if (options.excludeCredentials) {
        options.excludeCredentials = options.excludeCredentials.map(function (c) {
          c.id = E.webauthn.fromB64url(c.id);
          return c;
        });
      }
      return navigator.credentials.create({ publicKey: options }).then(function (cred) {
        return {
          id: cred.id,
          rawId: b64urlEncode(cred.rawId),
          type: cred.type,
          transports: cred.response && cred.response.getTransports ? cred.response.getTransports() : [],
          response: {
            clientDataJSON: toBase64Url(new TextDecoder().decode(cred.response.clientDataJSON)),
            attestationObject: b64urlEncode(cred.response.attestationObject)
          }
        };
      });
    },

    get: function (options) {
      options.challenge = E.webauthn.fromB64url(options.challenge);
      if (options.allowCredentials) {
        options.allowCredentials = options.allowCredentials.map(function (c) {
          c.id = E.webauthn.fromB64url(c.id);
          return c;
        });
      }
      return navigator.credentials.get({ publicKey: options }).then(function (cred) {
        var json = {
          id: cred.id,
          rawId: b64urlEncode(cred.rawId),
          type: cred.type,
          response: {
            clientDataJSON: toBase64Url(new TextDecoder().decode(cred.response.clientDataJSON)),
            authenticatorData: b64urlEncode(cred.response.authenticatorData),
            signature: b64urlEncode(cred.response.signature),
            userHandle: cred.response.userHandle ? b64urlEncode(cred.response.userHandle) : null
          }
        };
        return json;
      });
    },

    fromB64url: function (value) {
      var base64 = value.replace(/-/g, '+').replace(/_/g, '/');
      while (base64.length % 4) base64 += '=';
      var binary = atob(base64);
      var bytes = new Uint8Array(binary.length);
      for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
      return bytes;
    }
  };

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().then(function (data) { return { status: res.status, data: data }; });
    });
  }

  function handleAuthError(err, boxId) {
    var box = document.getElementById(boxId);
    if (!box) return;
    var message = (err && err.data && err.data.error) || (err && err.data && err.data.message) || 'Something went wrong. Please try again.';
    if (err.status === 422 && err.data && err.data.errors) {
      var firstKey = Object.keys(err.data.errors)[0];
      message = err.data.errors[firstKey][0];
    }
    box.textContent = message;
    box.classList.add('show');
  }

  function clearAuthError(boxId) {
    var box = document.getElementById(boxId);
    if (box) box.classList.remove('show');
  }

  /* ================= Login tabs ================= */
  function syncAuthLink() {
    var link = document.getElementById('useFaceLink');
    if (!link) return;
    var facePanel = document.querySelector('.auth-panel[data-panel="face"]');
    var faceActive = facePanel && facePanel.classList.contains('active');
    link.textContent = faceActive ? 'Use PIN instead' : 'Use Face ID instead';
  }

  var tabs = document.querySelectorAll('.auth-tab');
  for (var i = 0; i < tabs.length; i++) {
    (function (tab) {
      tab.addEventListener('click', function () {
        var panelId = tab.getAttribute('data-tab');
        for (var j = 0; j < tabs.length; j++) tabs[j].classList.remove('active');
        tab.classList.add('active');
        var panels = document.querySelectorAll('.auth-panel');
        for (var k = 0; k < panels.length; k++) {
          panels[k].classList.toggle('active', panels[k].getAttribute('data-panel') === panelId);
        }
        syncAuthLink();
      });
    })(tabs[i]);
  }

  /* ================= PIN keypads (login + register) ================= */
  function wireKeypad(keypadEl, opts) {
    if (!keypadEl) return null;
    var dots = keypadEl.parentElement.querySelectorAll('.pin-dot');
    var okKey = keypadEl.querySelector('[data-key="ok"]');
    var length = 0;
    var value = '';

    function render() {
      for (var i = 0; i < dots.length; i++) dots[i].classList.toggle('filled', i < length);
      if (opts.valueEl) opts.valueEl.value = value;
      if (okKey) okKey.disabled = length < 4;
    }

    keypadEl.addEventListener('click', function (e) {
      var key = e.target.closest('.key');
      if (!key) return;
      var data = key.getAttribute('data-key');
      if (data === 'back') {
        if (length > 0) { value = value.slice(0, -1); length--; }
        render();
      } else if (data === 'ok') {
        if (length >= 4 && opts.onOk) opts.onOk(value);
      } else if (length < 4) {
        value += data;
        length++;
        render();
        if (length >= 4 && opts.autoSubmit) opts.onAutoSubmit(value);
      }
    });

    return {
      get: function () { return value; },
      set: function (v) { value = v; length = v.length; render(); },
      clear: function () { value = ''; length = 0; render(); }
    };
  }

  var loginKeypad = document.getElementById('keypad');
  var pinForm = document.getElementById('pinForm');
  var authEmail = document.getElementById('authEmail');
  var pinError = document.getElementById('pinError');

  if (loginKeypad && pinForm) {
    var loginPad = wireKeypad(loginKeypad, {
      valueEl: document.getElementById('pinValue'),
      autoSubmit: true,
      onAutoSubmit: function () {
        var email = authEmail ? authEmail.value.trim() : '';
        if (!email) {
          if (pinError) { pinError.textContent = 'Enter your email address first.'; pinError.hidden = false; }
          loginPad.clear();
          return;
        }
        if (pinError) pinError.hidden = true;
        document.getElementById('pinEmail').value = email;
        pinForm.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });

    /* "Log In" button — submits whatever PIN is currently on the keypad. */
    var pinSubmitBtn = document.getElementById('pinSubmitBtn');
    if (pinSubmitBtn) {
      pinSubmitBtn.addEventListener('click', function (e) {
        var email = authEmail ? authEmail.value.trim() : '';
        if (!email) {
          e.preventDefault();
          if (pinError) { pinError.textContent = 'Enter your email address first.'; pinError.hidden = false; }
          authEmail.focus();
          return;
        }
        if (loginPad.get().length < 4) {
          e.preventDefault();
          if (pinError) { pinError.textContent = 'Enter your 4-digit PIN.'; pinError.hidden = false; }
          return;
        }
        if (pinError) pinError.hidden = true;
        document.getElementById('pinEmail').value = email;
      });
    }
  }

  /* "Use Face ID instead" — switches between the PIN and Face panels. */
  var useFaceLink = document.getElementById('useFaceLink');
  if (useFaceLink) {
    useFaceLink.addEventListener('click', function (e) {
      e.preventDefault();
      var facePanel = document.querySelector('.auth-panel[data-panel="face"]');
      var faceActive = facePanel && facePanel.classList.contains('active');
      var tab = document.querySelector('.auth-tab[data-tab="' + (faceActive ? 'pin' : 'face') + '"]');
      if (tab) tab.click();
      syncAuthLink();
    });
  }

  /* ================= Saved accounts (device-local) =================
     Stores basic account info (name, email) so returning clients on the
     same device skip re-typing their email. PIN / biometric checks are
     never stored or bypassed. */
  var ACCOUNTS_KEY = 'egliane:saved_accounts';

  function savedAccountsGet() {
    try { return JSON.parse(localStorage.getItem(ACCOUNTS_KEY)) || []; } catch (e) { return []; }
  }
  function savedAccountsSave(name, email) {
    try {
      var accounts = savedAccountsGet();
      accounts = accounts.filter(function (a) { return a.email !== email; });
      accounts.unshift({ name: name || email, email: email });
      if (accounts.length > 5) accounts = accounts.slice(0, 5);
      localStorage.setItem(ACCOUNTS_KEY, JSON.stringify(accounts));
    } catch (e) {}
  }
  function savedAccountsRemove(email) {
    try {
      var accounts = savedAccountsGet().filter(function (a) { return a.email !== email; });
      localStorage.setItem(ACCOUNTS_KEY, JSON.stringify(accounts));
    } catch (e) {}
  }
  function savedAccountsMask(email) {
    var parts = email.split('@');
    if (parts.length !== 2) return email;
    return parts[0].charAt(0) + '***@' + parts[1];
  }
  function escapeHtml(str) {
    var d = document.createElement('div'); d.textContent = str; return d.innerHTML;
  }

  var rememberCheck = document.getElementById('rememberEmail');
  var notYouLink = document.getElementById('notYouLink');
  var savedContainer = document.getElementById('savedAccounts');
  var savedAltRow = document.getElementById('savedAccountsAlt');
  var emailGroup = document.getElementById('emailGroup');
  var rememberRow = document.getElementById('rememberRow');

  function showEmailMode() {
    if (emailGroup) emailGroup.hidden = false;
    if (rememberRow) rememberRow.hidden = false;
    if (savedContainer) savedContainer.hidden = true;
    if (savedAltRow) savedAltRow.hidden = true;
    if (notYouLink) notYouLink.hidden = true;
  }

  function savedAccountsRender() {
    var accounts = savedAccountsGet();
    if (!savedContainer) return;

    if (accounts.length === 0) {
      showEmailMode();
      return;
    }

    savedContainer.innerHTML = '';
    for (var i = 0; i < accounts.length; i++) {
      (function (account) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'saved-account-btn';
        btn.setAttribute('data-email', account.email);
        var initial = (account.name || account.email).charAt(0).toUpperCase();
        btn.innerHTML =
          '<span class="saved-account-avatar">' + initial + '</span>' +
          '<span class="saved-account-info">' +
            '<span class="saved-account-name">' + escapeHtml(account.name) + '</span>' +
            '<span class="saved-account-email">' + escapeHtml(savedAccountsMask(account.email)) + '</span>' +
          '</span>' +
          '<span class="saved-account-remove" title="Remove account" data-remove="' + escapeHtml(account.email) + '">&times;</span>';
        savedContainer.appendChild(btn);
      })(accounts[i]);
    }

    savedContainer.hidden = false;
    if (savedAltRow) savedAltRow.hidden = false;
    if (emailGroup) emailGroup.hidden = true;
    if (rememberRow) rememberRow.hidden = true;
    if (notYouLink) notYouLink.hidden = true;
  }

  savedAccountsRender();

  if (savedContainer) {
    savedContainer.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('[data-remove]');
      if (removeBtn) {
        e.stopPropagation();
        savedAccountsRemove(removeBtn.getAttribute('data-remove'));
        savedAccountsRender();
        return;
      }
      var btn = e.target.closest('.saved-account-btn');
      if (!btn) return;
      var email = btn.getAttribute('data-email');
      if (authEmail) authEmail.value = email;
      if (rememberCheck) rememberCheck.checked = true;
      showEmailMode();
      if (notYouLink) notYouLink.hidden = false;
      var pinTab = document.querySelector('.auth-tab[data-tab="pin"]');
      if (pinTab && !pinTab.classList.contains('active')) pinTab.click();
    });
  }

  if (savedAltRow) {
    savedAltRow.addEventListener('click', function () {
      showEmailMode();
      if (authEmail) { authEmail.value = ''; authEmail.focus(); }
    });
  }

  if (notYouLink) {
    notYouLink.addEventListener('click', function (e) {
      e.preventDefault();
      if (authEmail) authEmail.value = '';
      if (rememberCheck) rememberCheck.checked = false;
      notYouLink.hidden = true;
      savedAccountsRender();
    });
  }

  if (rememberCheck && authEmail && pinForm) {
    pinForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var email = authEmail.value.trim();
      var remember = rememberCheck.checked;

      fetch(pinForm.action, {
        method: 'POST',
        body: new FormData(pinForm),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (res) {
          return res.json().then(function (data) { return { status: res.status, data: data }; });
        })
        .then(function (result) {
          if (result.status === 200 && result.data && result.data.redirect) {
            if (remember) savedAccountsSave(result.data.name || email, email);
            window.location.assign(result.data.redirect);
            return;
          }
          var message = 'Invalid PIN for this account.';
          if (result.data && result.data.errors) {
            var firstKey = Object.keys(result.data.errors)[0];
            message = result.data.errors[firstKey][0];
          } else if (result.data && result.data.error) {
            message = result.data.error;
          }
          if (pinError) { pinError.textContent = message; pinError.hidden = false; }
          if (loginPad) loginPad.clear();
        })
        .catch(function () {
          pinForm.submit();
        });
    });
  }

  /* ================= Biometric (face) login ================= */
  var faceBtn = document.getElementById('faceLoginBtn');
  if (faceBtn) {
    faceBtn.addEventListener('click', function () {
      clearAuthError('faceError');
      var email = document.getElementById('authEmail').value.trim();
      if (!email) {
        handleAuthError({ data: { error: 'Enter your email address first.' } }, 'faceError');
        return;
      }
      if (!E.webauthn.supported()) {
        handleAuthError({ data: { error: 'Face / biometric login is not supported on this device or browser. Please use your PIN instead.' } }, 'faceError');
        document.querySelector('.auth-tab[data-tab="pin"]')?.click();
        return;
      }
      var remember = rememberCheck ? rememberCheck.checked : false;
      faceBtn.disabled = true;
      faceBtn.textContent = 'Contacting your device\u2026';

      postJson('/login/webauthn/options', { email: email })
        .then(function (res) {
          if (res.status !== 200) {
            throw { status: res.status, data: res.data };
          }
          return E.webauthn.get(res.data);
        })
        .then(function (credential) {
          return postJson('/login/webauthn/verify', { credential: credential });
        })
        .then(function (res) {
          if (res.status === 200 && res.data.redirect) {
            if (remember) savedAccountsSave(res.data.name || email, email);
            window.location.href = res.data.redirect;
          } else {
            throw { status: res.status, data: res.data };
          }
        })
        .catch(function (err) {
          faceBtn.disabled = false;
          faceBtn.textContent = 'Continue with face';
          if (err && err.name === 'NotAllowedError') {
            handleAuthError({ data: { error: 'Biometric prompt was dismissed. Try again or use your PIN.' } }, 'faceError');
          } else {
            handleAuthError(err, 'faceError');
          }
        });
    });
  }

  /* ================= Verification code entry ================= */
  var codeInputs = document.querySelectorAll('.code-input');
  if (codeInputs.length) {
    var codeForm = document.getElementById('verifyCodeForm');
    var codeValue = document.getElementById('codeValue');

    var fillCode = function (value) {
      var digits = value.replace(/\D/g, '').slice(0, 6);
      for (var i = 0; i < codeInputs.length; i++) {
        codeInputs[i].value = digits[i] || '';
        codeInputs[i].classList.toggle('filled', !!digits[i]);
      }
      codeValue.value = digits;
      if (digits.length === 6 && codeForm) codeForm.submit();
    };

    for (var c = 0; c < codeInputs.length; c++) {
      (function (idx, input) {
        input.addEventListener('input', function () {
          var digits = input.value.replace(/\D/g, '').slice(0, 1);
          input.value = digits;
          input.classList.toggle('filled', !!digits);
          if (digits && idx < codeInputs.length - 1) {
            codeInputs[idx + 1].focus();
          }
          fillCode(Array.from(codeInputs).map(function (el) { return el.value; }).join(''));
        });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !input.value && idx > 0) {
            codeInputs[idx - 1].focus();
            codeInputs[idx - 1].value = '';
          }
        });
        input.addEventListener('paste', function (e) {
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData('text');
          fillCode(text);
        });
      })(c, codeInputs[c]);
    }

    var devCodeBtn = document.getElementById('devCodeBtn');
    if (devCodeBtn) {
      devCodeBtn.addEventListener('click', function () {
        fillCode(devCodeBtn.getAttribute('data-code') || '');
      });
    }

    /* Resend cooldown */
    var resendBtn = document.getElementById('resendBtn');
    var countdownEl = document.getElementById('resendCountdown');
    var cooldownUntil = parseInt(document.body.getAttribute('data-cooldown-until') || '0', 10);
    var lastSent = cooldownUntil * 1000;

    function tickResend() {
      if (!resendBtn) return;
      var remaining = Math.max(0, Math.ceil((lastSent + 60000 - Date.now()) / 1000));
      if (remaining > 0) {
        resendBtn.disabled = true;
        if (countdownEl) countdownEl.textContent = remaining + 's';
      } else {
        resendBtn.disabled = false;
        if (countdownEl) countdownEl.textContent = '';
      }
    }
    if (resendBtn) {
      resendBtn.addEventListener('click', function () {
        resendBtn.disabled = true;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = resendBtn.getAttribute('data-url');
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = token;
        form.appendChild(t);
        document.body.appendChild(form);
        form.submit();
      });
      setInterval(tickResend, 1000);
      tickResend();
    }
  }

  /* ================= Register steps ================= */
  var regSteps = document.querySelectorAll('.register-step');
  var regForms = document.querySelectorAll('.reg-step-panel');
  var regNext = document.querySelectorAll('[data-next]');
  var regBack = document.querySelectorAll('[data-back]');

  var regKeypad = document.getElementById('regKeypad');
  var regPhaseLabel = document.getElementById('regPhaseLabel');
  var regPin = document.getElementById('pin');
  var regPinConfirm = document.getElementById('pin_confirmation');
  var regTerms = document.getElementById('terms');
  var regPinError = document.getElementById('regPinError');
  var termsError = document.getElementById('termsError');
  var regPad = regKeypad ? wireKeypad(regKeypad, { onOk: function (pin) { regOnOk(pin); } }) : null;
  if (regPad) regPad.phase = 'set';

  function regOnOk(pin) {
    if (!regPad) return;
    if (regPad.phase === 'set') {
      regPad.firstPin = pin;
      regPad.phase = 'confirm';
      regPad.clear();
      if (regPhaseLabel) regPhaseLabel.textContent = 'Confirm your PIN';
      if (regPinError) regPinError.hidden = true;
      return;
    }
    if (pin !== regPad.firstPin) {
      if (regPinError) { regPinError.textContent = 'PINs do not match. Try again.'; regPinError.hidden = false; }
      regPad.phase = 'set';
      regPad.clear();
      if (regPhaseLabel) regPhaseLabel.textContent = 'Set your PIN';
      return;
    }
    if (regPinError) regPinError.hidden = true;
    if (regTerms && !regTerms.checked) {
      if (termsError) { termsError.textContent = 'Please accept the terms and conditions to continue.'; termsError.hidden = false; }
      return;
    }
    if (termsError) termsError.hidden = true;
    regPin.value = regPad.firstPin;
    regPinConfirm.value = pin;
    document.getElementById('registerForm').submit();
  }

  function showRegStep(step) {
    for (var i = 0; i < regSteps.length; i++) {
      regSteps[i].classList.toggle('active', i === step);
      regSteps[i].classList.toggle('done', i < step);
    }
    for (var j = 0; j < regForms.length; j++) {
      regForms[j].classList.toggle('active', j === step);
    }
    if (step === 2 && regPad) {
      regPad.phase = 'set';
      regPad.clear();
      if (regPhaseLabel) regPhaseLabel.textContent = 'Set your PIN';
    }
  }

  function validateRegStep(step) {
    var validators = {
      0: function () {
        var name = document.getElementById('name');
        var biz = document.getElementById('business_name');
        return { ok: name.value.trim().length >= 2 && biz.value.trim().length >= 2, fields: [name, biz] };
      },
      1: function () {
        var email = document.getElementById('email');
        var ok = /^[^\s@]+@gmail\.com$/i.test(email.value.trim());
        return { ok: ok, fields: [email] };
      }
    };
    var result = validators[step]();
    for (var i = 0; i < result.fields.length; i++) {
      var errBox = result.fields[i].parentNode.querySelector('.form-error');
      if (errBox) errBox.classList.toggle('hidden', result.ok);
    }
    return result.ok;
  }

  if (regSteps.length) {
    for (var s = 0; s < regNext.length; s++) {
      (function (btn) {
        btn.addEventListener('click', function () {
          var step = parseInt(btn.getAttribute('data-next'), 10);
          if (validateRegStep(step - 1)) showRegStep(step);
        });
      })(regNext[s]);
    }
    for (var b = 0; b < regBack.length; b++) {
      (function (btn) {
        btn.addEventListener('click', function () {
          showRegStep(parseInt(btn.getAttribute('data-back'), 10));
        });
      })(regBack[b]);
    }

    var submitRegBtn = document.getElementById('submitReg');
    if (submitRegBtn) {
      submitRegBtn.addEventListener('click', function () {
        var pin = regPad ? regPad.get() : '';
        if (pin.length < 4) {
          if (regPinError) { regPinError.textContent = 'Enter a 4-digit PIN.'; regPinError.hidden = false; }
          return;
        }
        regOnOk(pin);
      });
    }
  }

  /* ================= Security settings: enroll biometric ================= */
  var enrollBtn = document.getElementById('enrollBiometric');
  if (enrollBtn) {
    enrollBtn.addEventListener('click', function () {
      var statusEl = document.getElementById('biometricStatus');
      enrollBtn.disabled = true;
      enrollBtn.textContent = 'Contacting your device…';

      function setStatus(msg, ok) {
        if (statusEl) {
          statusEl.textContent = msg;
          statusEl.className = ok ? 'alert alert-success' : 'alert alert-error';
        }
      }

      if (!E.webauthn.supported()) {
        setStatus('Face / biometric login is not supported on this device or browser. Your PIN login will still work.', false);
        enrollBtn.disabled = false;
        enrollBtn.textContent = 'Enable Face / Biometric login';
        return;
      }

      fetch('/webauthn/register/options', {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
      })
        .then(function (res) { return res.json(); })
        .then(function (options) {
          return E.webauthn.create(options);
        })
        .then(function (credential) {
          return postJson('/webauthn/register/verify', { credential: credential });
        })
        .then(function (res) {
          if (res.status === 200) {
            setStatus('Face / biometric login is now enabled.', true);
            setTimeout(function () { window.location.reload(); }, 900);
          } else {
            throw { data: res.data };
          }
        })
        .catch(function (err) {
          enrollBtn.disabled = false;
          enrollBtn.textContent = 'Enable Face / Biometric login';
          if (err && err.name === 'NotAllowedError') {
            setStatus('Registration was cancelled.', false);
          } else {
            setStatus((err && err.data && err.data.error) || 'Registration failed. Please try again.', false);
          }
        });
    });
  }

  /* ================= Simple toggle helpers ================= */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-toggle]');
    if (toggle) {
      var target = document.querySelector(toggle.getAttribute('data-toggle'));
      if (target) target.classList.toggle('hidden');
    }
    var modalClose = e.target.closest('[data-modal-close]');
    if (modalClose) {
      var modal = document.querySelector(modalClose.getAttribute('data-modal-close'));
      if (modal) modal.classList.add('hidden');
    }
  });
})();
