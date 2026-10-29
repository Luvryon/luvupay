/**
 * LuvuLoader: soft / background loading for every AJAX region.
 *
 * Global top progress bar tracks in-flight fetch count (no full-page spinner).
 * Regions keep showing last data (stale-while-revalidate); only first paint uses skeleton.
 *
 *   LuvuLoader.track(promise)          // auto progress bar
 *   LuvuLoader.start(el, title)
 *   LuvuLoader.skeletonRows(tbody, 5, 4)
 *   LuvuLoader.overlay(el)             // dim region, keep content
 *   LuvuLoader.busy(button, promise)
 */
(function (global) {
  'use strict';

  var pending = 0;
  var barEl = null;
  var hideTimer = null;

  function ensureBar() {
    if (barEl) return barEl;
    barEl = document.createElement('div');
    barEl.id = 'lp-ajax-bar';
    barEl.setAttribute('aria-hidden', 'true');
    barEl.style.cssText =
      'position:fixed;top:0;left:0;height:2px;width:0;z-index:2147483646;' +
      'background:linear-gradient(90deg,#6366f1,#22d3ee);' +
      'box-shadow:0 0 8px rgba(99,102,241,.55);' +
      'transition:width .28s ease,opacity .35s ease;opacity:0;pointer-events:none;';
    (document.body || document.documentElement).appendChild(barEl);
    return barEl;
  }

  function paintBar() {
    var bar = ensureBar();
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    if (pending <= 0) {
      pending = 0;
      bar.style.width = '100%';
      hideTimer = setTimeout(function () {
        bar.style.opacity = '0';
        setTimeout(function () { if (pending === 0) bar.style.width = '0'; }, 280);
      }, 180);
      return;
    }
    bar.style.opacity = '1';
    // asymptote toward 90% while requests are open
    var pct = Math.min(90, 18 + pending * 12 + Math.random() * 8);
    bar.style.width = pct + '%';
  }

  function begin() {
    pending += 1;
    paintBar();
  }

  function end() {
    pending = Math.max(0, pending - 1);
    paintBar();
  }

  /** Wrap any promise so the global bar reflects it. */
  function track(promise) {
    begin();
    return Promise.resolve(promise).then(
      function (v) { end(); return v; },
      function (e) { end(); throw e; }
    );
  }

  function make(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function state(parts) {
    var box = make('div', 'lp-state');
    box.setAttribute('role', 'status');
    if (parts.spinner) box.appendChild(make('span', 'lp-spinner lp-spinner--lg'));
    var text = make('div');
    if (parts.title) text.appendChild(make('strong', null, parts.title));
    if (parts.hint) text.appendChild(document.createTextNode(parts.hint));
    box.appendChild(text);
    if (parts.retry) {
      var btn = make('button', 'c-btn c-btn-sm lp-retry', 'Try again');
      btn.type = 'button';
      btn.addEventListener('click', parts.retry);
      box.appendChild(btn);
    }
    return box;
  }

  function replace(target, node) {
    while (target.firstChild) target.removeChild(target.firstChild);
    target.appendChild(node);
  }

  function start(target, title) {
    if (!target) return function () {};
    replace(target, state({ spinner: true, title: title || 'Loading' }));
    return function () {};
  }

  function message(target, title, hint) {
    if (target) replace(target, state({ title: title, hint: hint }));
  }

  function error(target, title, retry) {
    if (target) {
      replace(target, state({
        title: title || 'Could not load data',
        hint: ' Check your connection and try again',
        retry: retry,
      }));
    }
  }

  function tableRow(tbody, colspan, node) {
    var tr = make('tr');
    var td = make('td');
    td.colSpan = colspan;
    td.style.padding = '0';
    td.appendChild(node);
    tr.appendChild(td);
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    tbody.appendChild(tr);
  }

  function tableLoading(tbody, colspan, title) {
    tableRow(tbody, colspan, state({ spinner: true, title: title || 'Loading' }));
  }
  function tableEmpty(tbody, colspan, title, hint) {
    tableRow(tbody, colspan, state({ title: title, hint: hint }));
  }
  function tableError(tbody, colspan, title, retry) {
    tableRow(tbody, colspan, state({
      title: title || 'Could not load data',
      hint: ' Check your connection and try again',
      retry: retry,
    }));
  }

  function skeletonRows(tbody, rows, cols) {
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    for (var r = 0; r < rows; r++) {
      var tr = make('tr');
      for (var c = 0; c < cols; c++) {
        var td = make('td');
        var bar = make('span', 'lp-skeleton');
        bar.style.width = (40 + ((r * 17 + c * 23) % 45)) + '%';
        td.appendChild(bar);
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
  }

  /** Dim region, keep existing content visible (true background load). */
  function overlay(target) {
    if (!target) return function () {};
    target.classList.add('lp-loading');
    return function () { target.classList.remove('lp-loading'); };
  }

  function busy(button, promise) {
    if (!button) return track(promise);
    button.classList.add('is-busy');
    button.disabled = true;
    var release = function () {
      button.classList.remove('is-busy');
      button.disabled = false;
    };
    return track(Promise.resolve(promise).then(
      function (v) { release(); return v; },
      function (e) { release(); throw e; }
    ));
  }

  // Boot bar early so first paint can use it
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { ensureBar(); });
  } else {
    ensureBar();
  }

  global.LuvuLoader = {
    start: start,
    message: message,
    error: error,
    overlay: overlay,
    busy: busy,
    track: track,
    begin: begin,
    end: end,
    skeletonRows: skeletonRows,
    table: { loading: tableLoading, empty: tableEmpty, error: tableError },
  };
})(window);
