/*! LuvuPay badge: "Protected by LuvuPay". No cookies, no PII, no network calls. */
(function () {
  'use strict';
  var CFG = /*__LUVUPAY_CONFIG__*/{}/*__END__*/;
  if (window.__luvupayLoaded) return;
  window.__luvupayLoaded = true;

  // Origin allow-list (the server cannot see the embedding page for <script> loads, so we enforce it here).
  var o = CFG.origins || [];
  if (o.length && o.indexOf(location.origin) === -1 && o.indexOf(location.hostname) === -1) {
    if (window.console) console.warn('[LuvuPay] Badge not shown: this origin is not allowed for this key.');
    return;
  }

  var FALLBACKS = ['[data-luvupay]', '#luvupay', 'form[action*="checkout" i]', '.checkout', '#checkout'];
  var MARK = 'data-luvupay-mounted';

  function find() {
    var sels = CFG.target ? [CFG.target].concat(FALLBACKS) : FALLBACKS;
    for (var i = 0; i < sels.length; i++) {
      try { var el = document.querySelector(sels[i]); if (el) return el; } catch (e) { /* invalid selector: skip */ }
    }
    return null;
  }

  function build() {
    var host = document.createElement('span');
    host.setAttribute(MARK, '1');
    host.style.cssText = 'display:inline-block;position:relative;margin:8px 0;';
    var root = host.attachShadow({ mode: 'open' });
    var dark = CFG.theme === 'dark';
    root.innerHTML =
      '<style>' +
      ':host{all:initial}' +
      '*{box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}' +
      'button{display:inline-flex;align-items:center;gap:8px;cursor:pointer;border-radius:999px;padding:7px 14px;font-size:13px;font-weight:600;' +
      'border:1px solid ' + (dark ? '#334155' : '#c7d2fe') + ';background:' + (dark ? '#0f172a' : '#eef2ff') + ';color:' + (dark ? '#e2e8f0' : '#3730a3') + '}' +
      'button:focus-visible{outline:2px solid #6366f1;outline-offset:2px}' +
      '.dot{width:8px;height:8px;border-radius:50%;background:#10b981}' +
      '.pop{position:absolute;z-index:2147483000;left:0;top:calc(100% + 8px);width:290px;padding:14px 16px;border-radius:14px;font-size:13px;line-height:1.45;' +
      'background:' + (dark ? '#0f172a' : '#fff') + ';color:' + (dark ? '#e2e8f0' : '#0f172a') + ';border:1px solid ' + (dark ? '#334155' : '#e2e8f0') + ';box-shadow:0 12px 32px rgba(15,23,42,.18)}' +
      '.pop[hidden]{display:none}.pop b{display:block;margin-bottom:6px;font-size:14px}.pop p{margin:6px 0}.pop small{opacity:.6}' +
      '</style>' +
      '<button type="button" aria-haspopup="dialog" aria-expanded="false"><span class="dot"></span><span class="label"></span></button>' +
      '<div class="pop" role="dialog" aria-label="About LuvuPay" hidden><b></b><p class="a"></p><p class="b"></p><small></small></div>';

    var btn = root.querySelector('button'), pop = root.querySelector('.pop');
    root.querySelector('.label').textContent = 'Protected by LuvuPay';
    pop.querySelector('b').textContent = 'Forget to cancel? You are covered.';
    pop.querySelector('.a').textContent = 'If you are charged by mistake you get a ' + Number(CFG.window || 48) +
      '-hour Second Chance: ' + Number(CFG.user || 30) + '% back to you, ' + Number(CFG.charity || 20) + '% to charity, and the subscription is cancelled automatically.';
    pop.querySelector('.b').textContent = 'Not using it anymore? Unused days can be resold instead of wasted.';
    pop.querySelector('small').textContent = 'No account needed' + (CFG.merchant ? ' · ' + CFG.merchant : '');

    function setOpen(v) { pop.hidden = !v; btn.setAttribute('aria-expanded', String(v)); }
    btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(pop.hidden); });
    document.addEventListener('click', function (e) { if (e.composedPath().indexOf(host) === -1) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
    return host;
  }

  function mount() {
    if (document.querySelector('[' + MARK + ']')) return true;
    var t = find();
    if (!t) return false;
    t.appendChild(build());
    try { document.dispatchEvent(new CustomEvent('luvupay:ready')); } catch (e) {}
    return true;
  }

  function start() {
    if (mount()) return;
    // SPA checkouts render late: watch for the target for up to 10 seconds.
    var mo = new MutationObserver(function () { if (mount()) mo.disconnect(); });
    mo.observe(document.documentElement, { childList: true, subtree: true });
    setTimeout(function () { mo.disconnect(); }, 10000);
  }

  window.LuvuPay = { version: '1', mount: mount };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
