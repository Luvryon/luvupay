/**
 * Runs inside the PayPal approval popup after PayPal sends the buyer back.
 * Tells the dashboard tab the result (BroadcastChannel + localStorage + opener), then closes itself.
 * External file on purpose: the app CSP forbids inline scripts.
 */
(function () {
  'use strict';
  var box = document.getElementById('lp-popup-result');
  if (!box) return;
  var payload = {
    type: 'lp-paypal-result',
    state: box.getAttribute('data-state') || 'failed',
    tx: box.getAttribute('data-tx') || '',
    message: box.getAttribute('data-message') || '',
    error: box.getAttribute('data-error') || '',
    at: Date.now()
  };

  // PayPal may sever window.opener (COOP), so same-origin channels come first.
  try { var bc = new BroadcastChannel('luvupay-paypal'); bc.postMessage(payload); bc.close(); } catch (e) {}
  try { localStorage.setItem('lp_paypal_result', JSON.stringify(payload)); } catch (e) {}
  try { if (window.opener) window.opener.postMessage(payload, window.location.origin); } catch (e) {}

  var btn = document.getElementById('lp-popup-close');
  var hint = document.getElementById('lp-popup-hint');
  if (btn) btn.addEventListener('click', function () { window.close(); });

  setTimeout(function () {
    window.close();
    // Still here: the browser refused to close it (opened without a script). Let the person do it.
    setTimeout(function () {
      if (btn) btn.hidden = false;
      if (hint) hint.textContent = 'You can close this window and return to the dashboard.';
    }, 400);
  }, payload.state === 'captured' ? 1200 : 2200);
})();
