/**
 * Register form: password strength (lower/upper/digit/symbol) + match hint.
 * External file for CSP script-src 'self'.
 */
(function () {
  'use strict';

  var free = [
    'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.id', 'hotmail.com',
    'outlook.com', 'live.com', 'icloud.com', 'mail.com', 'proton.me',
    'protonmail.com', 'aol.com', 'yandex.com', 'zoho.com'
  ];

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function checks(pw) {
    return {
      lower: /[a-z]/.test(pw),
      upper: /[A-Z]/.test(pw),
      digit: /\d/.test(pw),
      symbol: /[^A-Za-z0-9]/.test(pw),
      minLen: pw.length >= 10
    };
  }

  function score(pw) {
    var c = checks(pw);
    var n = 0;
    if (c.lower) n++;
    if (c.upper) n++;
    if (c.digit) n++;
    if (c.symbol) n++;
    return n; // 0..4 requirements met
  }

  /** tier for bar color: 0 gray, 1 red (1-2), 2 yellow (3), 3 green (4) */
  function tierFromScore(s, hasInput) {
    if (!hasInput) return 0;
    if (s >= 4) return 3; // green
    if (s >= 3) return 2; // yellow
    if (s >= 1) return 1; // red
    return 1; // has input but none of the classes → still red
  }

  ready(function () {
    var emailEl = document.getElementById('email');
    var pwEl = document.getElementById('password');
    var pw2El = document.getElementById('password_confirmation');
    var bars = document.getElementById('pw-bars');
    var strengthHint = document.getElementById('pw-strength-hint');
    var matchHint = document.getElementById('pw-match-hint');
    var emailHint = document.getElementById('email-hint');
    var reqList = document.getElementById('pw-req-list');
    var submitBtn = document.getElementById('register-submit');
    var form = document.getElementById('register-form');

    if (!pwEl || !pw2El || !bars || !form) return;

    function emailOk() {
      if (!emailEl || !emailHint) return true;
      var v = (emailEl.value || '').trim().toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
        emailHint.textContent = 'Enter a valid email address';
        emailHint.className = 'pw-hint is-red';
        emailEl.setAttribute('aria-invalid', 'true');
        return false;
      }
      var domain = v.split('@')[1] || '';
      if (free.indexOf(domain) !== -1) {
        emailHint.textContent = 'Work email required — free providers are not allowed';
        emailHint.className = 'pw-hint is-red';
        emailEl.setAttribute('aria-invalid', 'true');
        return false;
      }
      emailHint.textContent = 'Looks like a valid work email';
      emailHint.className = 'pw-hint is-green';
      emailEl.removeAttribute('aria-invalid');
      return true;
    }

    function emailOkSilent() {
      if (!emailEl) return true;
      var v = (emailEl.value || '').trim().toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return false;
      return free.indexOf(v.split('@')[1] || '') === -1;
    }

    function updateReqs(pw) {
      if (!reqList) return;
      var c = checks(pw);
      var map = {
        'req-lower': c.lower,
        'req-upper': c.upper,
        'req-digit': c.digit,
        'req-symbol': c.symbol,
        'req-len': c.minLen
      };
      Object.keys(map).forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.className = map[id] ? 'pw-req is-ok' : 'pw-req';
      });
    }

    function updatePassword() {
      var pw = pwEl.value || '';
      var s = score(pw);
      var t = tierFromScore(s, pw.length > 0);
      bars.setAttribute('data-tier', String(t));
      // fill first N segments based on score (1..4)
      bars.setAttribute('data-score', String(s));
      updateReqs(pw);

      if (!strengthHint) { updateMatch(); updateSubmit(); return; }
      if (pw.length === 0) {
        strengthHint.textContent = 'Use upper & lower case, a number, and a symbol (min 10 chars)';
        strengthHint.className = 'pw-hint is-muted';
      } else if (s < 4 || pw.length < 10) {
        var missing = [];
        var c = checks(pw);
        if (!c.lower) missing.push('lowercase');
        if (!c.upper) missing.push('uppercase');
        if (!c.digit) missing.push('number');
        if (!c.symbol) missing.push('symbol');
        if (!c.minLen) missing.push('10+ chars');
        strengthHint.textContent = 'Missing: ' + missing.join(', ');
        strengthHint.className = t === 2 ? 'pw-hint is-yellow' : 'pw-hint is-red';
      } else {
        strengthHint.textContent = 'Strong password';
        strengthHint.className = 'pw-hint is-green';
      }
      updateMatch();
      updateSubmit();
    }

    function updateMatch() {
      if (!matchHint) return;
      var pw = pwEl.value || '';
      var pw2 = pw2El.value || '';
      if (pw2.length === 0) {
        matchHint.textContent = '\u00a0';
        matchHint.className = 'pw-hint is-muted';
        pw2El.removeAttribute('aria-invalid');
        updateSubmit();
        return;
      }
      if (pw.length > 0 && pw === pw2) {
        matchHint.textContent = '\u2713 Passwords match';
        matchHint.className = 'pw-hint is-green';
        pw2El.removeAttribute('aria-invalid');
      } else {
        matchHint.textContent = '\u2717 Passwords do not match';
        matchHint.className = 'pw-hint is-red';
        pw2El.setAttribute('aria-invalid', 'true');
      }
      updateSubmit();
    }

    function passwordOk(pw) {
      var c = checks(pw);
      return c.lower && c.upper && c.digit && c.symbol && c.minLen;
    }

    function updateSubmit() {
      if (!submitBtn) return;
      var pw = pwEl.value || '';
      var pw2 = pw2El.value || '';
      var ok = emailOkSilent() && passwordOk(pw) && pw === pw2;
      submitBtn.disabled = !ok;
    }

    if (emailEl) {
      emailEl.addEventListener('blur', emailOk);
      emailEl.addEventListener('input', function () {
        if (emailHint && (emailHint.classList.contains('is-red') || emailHint.classList.contains('is-green'))) emailOk();
        updateSubmit();
      });
    }
    pwEl.addEventListener('input', updatePassword);
    pwEl.addEventListener('keyup', updatePassword);
    pw2El.addEventListener('input', updateMatch);
    pw2El.addEventListener('keyup', updateMatch);

    form.addEventListener('submit', function (e) {
      var okEmail = emailOk();
      updatePassword();
      updateMatch();
      var pw = pwEl.value || '';
      var pw2 = pw2El.value || '';
      if (!okEmail || !passwordOk(pw) || pw !== pw2) {
        e.preventDefault();
        return;
      }
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account\u2026';
      }
    });

    updatePassword();
    updateSubmit();
    if (emailEl && (emailEl.value || '').trim()) emailOk();
  });
})();
