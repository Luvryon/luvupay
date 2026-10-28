/**
 * Tracks merchant readiness: project + PayPal.
 * Shows modern toasts once per session (sessionStorage).
 */
(function () {
  'use strict';

  function flag(key) {
    try { return sessionStorage.getItem(key) === '1'; } catch (e) { return false; }
  }
  function mark(key) {
    try { sessionStorage.setItem(key, '1'); } catch (e) {}
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.LuvuToast) return;

    var el = document.getElementById('luvu-onboarding');
    if (!el) return;

    var hasProject = el.getAttribute('data-has-project') === '1';
    var hasPaypal = el.getAttribute('data-has-paypal') === '1';
    var driver = el.getAttribute('data-payment-driver') || 'mock';
    var needsPaypal = driver === 'paypal';

    // Priority 1: no project
    if (!hasProject && !flag('luvu_toast_need_project')) {
      mark('luvu_toast_need_project');
      window.LuvuToast.warning(
        'Create a project first',
        'Simulate 30 days and the ledger need an active project',
        {
          id: 'need-project',
          duration: 9000,
          action: {
            label: 'Create project',
            onClick: function () {
              var btn = document.querySelector('[x-on\\:click=\"openNew()\"]') ||
                        document.querySelector('button[x-text*=\"Create\"]');
              if (btn) btn.click();
              else window.location.href = '/dashboard';
            },
          },
        }
      );
      return; // don't stack PayPal toast on top yet
    }

    // Priority 2: project ok, PayPal missing (only when driver=paypal)
    if (hasProject && needsPaypal && !hasPaypal && !flag('luvu_toast_need_paypal')) {
      mark('luvu_toast_need_paypal');
      window.LuvuToast.info(
        'Connect PayPal Sandbox',
        'Withdrawals and live Simulate need a linked Sandbox merchant',
        {
          id: 'need-paypal',
          duration: 9000,
          action: {
            label: 'Open Settings',
            href: '/settings#paypal',
          },
        }
      );
    }

    // Soft success when both ready (once)
    if (hasProject && (!needsPaypal || hasPaypal) && !flag('luvu_toast_ready')) {
      mark('luvu_toast_ready');
      window.LuvuToast.success(
        'You\'re set up',
        needsPaypal ? 'Project + PayPal connected. Simulate 30 days is ready' : 'Project ready. Simulate 30 days is available',
        { id: 'ready', duration: 4500 }
      );
    }
  });
})();
