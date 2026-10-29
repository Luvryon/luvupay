/**
 * LuvuLoader: one way to show loading, empty and error states for any AJAX region.
 *
 *   var done = LuvuLoader.start(el, 'Loading transactions');   // spinner + message
 *   ...fetch...
 *   done();                                                      // removes it
 *
 *   LuvuLoader.skeletonRows(tbody, 5, 4);        // shimmer rows inside a table body
 *   LuvuLoader.message(el, 'No data yet', 'hint'); // empty state
 *   LuvuLoader.error(el, 'Could not load', retryFn);
 *   LuvuLoader.busy(button, promise);            // spinner on a button while a promise runs
 */
(function (global) {
  'use strict';

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
    target.textContent = '';
    target.appendChild(node);
  }

  /** Spinner state inside the target. Returns a function that clears nothing, so the caller renders its own result. */
  function start(target, title) {
    if (!target) return function () {};
    replace(target, state({ spinner: true, title: title || 'Loading' }));
    return function () {};
  }

  function message(target, title, hint) {
    if (target) replace(target, state({ title: title, hint: hint }));
  }

  function error(target, title, retry) {
    if (target) replace(target, state({ title: title || 'Could not load data', hint: 'Check your connection and try again', retry: retry }));
  }

  /** Table helpers work on a <tbody> and span all columns. */
  function tableRow(tbody, colspan, node) {
    var tr = make('tr');
    var td = make('td');
    td.colSpan = colspan;
    td.style.padding = '0';
    td.appendChild(node);
    tr.appendChild(td);
    tbody.textContent = '';
    tbody.appendChild(tr);
  }

  function tableLoading(tbody, colspan, title) { tableRow(tbody, colspan, state({ spinner: true, title: title || 'Loading' })); }
  function tableEmpty(tbody, colspan, title, hint) { tableRow(tbody, colspan, state({ title: title, hint: hint })); }
  function tableError(tbody, colspan, title, retry) { tableRow(tbody, colspan, state({ title: title || 'Could not load data', hint: 'Check your connection and try again', retry: retry })); }

  function skeletonRows(tbody, rows, cols) {
    tbody.textContent = '';
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

  /** Dim a region and show a spinner until the returned function is called (keeps existing content visible). */
  function overlay(target) {
    if (!target) return function () {};
    target.classList.add('lp-loading');
    return function () { target.classList.remove('lp-loading'); };
  }

  function busy(button, promise) {
    if (!button) return promise;
    button.classList.add('is-busy');
    button.disabled = true;
    var release = function () { button.classList.remove('is-busy'); button.disabled = false; };
    return Promise.resolve(promise).then(function (v) { release(); return v; }, function (e) { release(); throw e; });
  }

  global.LuvuLoader = {
    start: start, message: message, error: error, overlay: overlay, busy: busy,
    skeletonRows: skeletonRows,
    table: { loading: tableLoading, empty: tableEmpty, error: tableError },
  };
})(window);
