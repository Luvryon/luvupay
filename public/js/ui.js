/**
 * LuvuPay UI helpers. Replaces inline onclick/onsubmit handlers so pages can run under a strict CSP.
 * Declarative hooks (all values come from escaped data-* attributes):
 *   <form data-confirm="Are you sure?">        confirm before submit
 *   <form data-once>                           disable submit buttons after the first submit (double-submit guard)
 *   <select data-autosubmit>                   submit the parent form on change
 *   <button data-copy="text">                  copy text to the clipboard
 *   <button data-print>                        window.print()
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      e.preventDefault();
      return;
    }
    if (form.hasAttribute('data-once') || form.method.toLowerCase() === 'post') {
      // Let the browser dispatch the submit first, then lock the buttons.
      setTimeout(function () {
        if (e.defaultPrevented) return;   // an Alpine @submit.prevent handler took over
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (b) {
          b.setAttribute('aria-busy', 'true');
          b.disabled = true;
        });
      }, 0);
    }
  }, true);

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el instanceof HTMLSelectElement && el.hasAttribute('data-autosubmit') && el.form) {
      el.form.requestSubmit();
    }
  });

  document.addEventListener('click', function (e) {
    var copy = e.target.closest && e.target.closest('[data-copy]');
    if (copy) {
      var text = copy.getAttribute('data-copy') || '';
      var done = function () {
        copy.setAttribute('data-copied', 'true');
        var label = copy.getAttribute('aria-label');
        copy.setAttribute('aria-label', 'Copied');
        setTimeout(function () {
          copy.removeAttribute('data-copied');
          if (label) copy.setAttribute('aria-label', label);
        }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () {});
      }
      return;
    }
    if (e.target.closest && e.target.closest('[data-print]')) window.print();
  });

  // Re-enable buttons when the page is restored from the back/forward cache.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.querySelectorAll('button[aria-busy="true"]').forEach(function (b) {
      b.removeAttribute('aria-busy');
      b.disabled = false;
    });
  });
})();
