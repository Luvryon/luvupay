/**
 * LuvuToast — modern notification stack (Toastify-inspired, pure JS)
 * window.LuvuToast.success|error|warning|info|notify
 */
(function (global) {
  'use strict';

  var ROOT_ID = 'luvu-toast-root';
  var defaults = { duration: 5200, dismissible: true };

  function ensureRoot() {
    var el = document.getElementById(ROOT_ID);
    if (el) return el;
    el = document.createElement('div');
    el.id = ROOT_ID;
    el.className = 'lt-root';
    el.setAttribute('aria-live', 'polite');
    document.body.appendChild(el);
    return el;
  }

  function iconFor(type) {
    if (type === 'success') return '✓';
    if (type === 'error') return '!';
    if (type === 'warning') return '⚠';
    return 'i';
  }

  /**
   * @param {object} opts
   * @param {string} opts.title
   * @param {string} [opts.message]
   * @param {string} [opts.type] success|error|warning|info
   * @param {number} [opts.duration]
   * @param {{label:string,href?:string,onClick?:Function}} [opts.action]
   * @param {string} [opts.id]  dedupe key — same id replaces previous
   */
  function notify(opts) {
    opts = Object.assign({}, defaults, opts || {});
    var type = opts.type || 'info';
    var root = ensureRoot();

    if (opts.id) {
      var prev = root.querySelector('[data-toast-id="' + opts.id + '"]');
      if (prev) prev.remove();
    }

    var node = document.createElement('div');
    node.className = 'lt-toast lt-toast--' + type;
    if (opts.id) node.setAttribute('data-toast-id', opts.id);
    node.setAttribute('role', 'status');

    var icon = document.createElement('div');
    icon.className = 'lt-icon';
    icon.textContent = iconFor(type);

    var body = document.createElement('div');
    body.className = 'lt-body';
    var title = document.createElement('div');
    title.className = 'lt-title';
    title.textContent = opts.title || opts.message || '';
    body.appendChild(title);
    if (opts.message && opts.title) {
      var msg = document.createElement('div');
      msg.className = 'lt-msg';
      msg.textContent = opts.message;
      body.appendChild(msg);
    }
    if (opts.action && opts.action.label) {
      var act = document.createElement(opts.action.href ? 'a' : 'button');
      act.className = 'lt-action';
      act.textContent = opts.action.label;
      if (opts.action.href) {
        act.href = opts.action.href;
      } else {
        act.type = 'button';
        act.addEventListener('click', function (e) {
          e.preventDefault();
          if (typeof opts.action.onClick === 'function') opts.action.onClick();
          dismiss(node);
        });
      }
      body.appendChild(act);
    }

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'lt-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () { dismiss(node); });

    node.appendChild(icon);
    node.appendChild(body);
    if (opts.dismissible !== false) node.appendChild(close);
    root.appendChild(node);

    // enter
    requestAnimationFrame(function () { node.classList.add('lt-in'); });

    var timer = null;
    if (opts.duration > 0) {
      timer = setTimeout(function () { dismiss(node); }, opts.duration);
    }
    node.addEventListener('mouseenter', function () {
      if (timer) { clearTimeout(timer); timer = null; }
    });
    node.addEventListener('mouseleave', function () {
      if (opts.duration > 0 && !timer) {
        timer = setTimeout(function () { dismiss(node); }, Math.max(2000, opts.duration / 2));
      }
    });

    return node;
  }

  function dismiss(node) {
    if (!node || node.classList.contains('lt-out')) return;
    node.classList.add('lt-out');
    setTimeout(function () { if (node.parentNode) node.parentNode.removeChild(node); }, 320);
  }

  var api = {
    notify: notify,
    success: function (title, message, extra) {
      return notify(Object.assign({ type: 'success', title: title, message: message }, extra || {}));
    },
    error: function (title, message, extra) {
      return notify(Object.assign({ type: 'error', title: title, message: message, duration: 7000 }, extra || {}));
    },
    warning: function (title, message, extra) {
      return notify(Object.assign({ type: 'warning', title: title, message: message, duration: 6500 }, extra || {}));
    },
    info: function (title, message, extra) {
      return notify(Object.assign({ type: 'info', title: title, message: message }, extra || {}));
    },
  };

  global.LuvuToast = api;
})(window);
