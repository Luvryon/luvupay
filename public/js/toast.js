/**
 * LuvuToast: light notification stack.
 *   LuvuToast.success | error | warning | info (title, message?, extra?)
 *   LuvuToast.notify({ type, title, message, duration, action, id })
 * Titles and messages never end with a period.
 * Hard security: no innerHTML — SVG built with createElementNS only.
 */
(function (global) {
  'use strict';

  var ROOT_ID = 'luvu-toast-root';
  var DURATIONS = { success: 4200, info: 4200, warning: 6000, error: 7000 };
  var EXIT_MS = 220;
  var MAX_VISIBLE = 4;
  var SVG_NS = 'http://www.w3.org/2000/svg';

  // Path data only (never raw HTML)
  var ICONS = {
    success: [{ d: 'M5 12.5l4.5 4.5L19 7.5' }],
    error: [
      { d: 'M6 6l12 12' },
      { d: 'M18 6L6 18' },
    ],
    warning: [
      { d: 'M12 8v5M12 16.5v.01' },
      { d: 'M10.3 3.9L2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0z' },
    ],
    info: [
      { d: 'M12 11v5M12 7.5v.01' },
      { tag: 'circle', cx: '12', cy: '12', r: '9' },
    ],
  };

  function clean(text) {
    return String(text == null ? '' : text).trim().replace(/\.+$/, '');
  }

  function ensureRoot() {
    var root = document.getElementById(ROOT_ID);
    if (root) return root;
    root = document.createElement('div');
    root.id = ROOT_ID;
    root.className = 'lt-root';
    root.setAttribute('aria-live', 'polite');
    document.body.appendChild(root);
    return root;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function buildIcon(type) {
    var box = el('div', 'lt-icon');
    var svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    var paths = ICONS[type] || ICONS.info;
    paths.forEach(function (p) {
      var node;
      if (p.tag === 'circle') {
        node = document.createElementNS(SVG_NS, 'circle');
        if (p.cx) node.setAttribute('cx', p.cx);
        if (p.cy) node.setAttribute('cy', p.cy);
        if (p.r) node.setAttribute('r', p.r);
      } else {
        node = document.createElementNS(SVG_NS, 'path');
        node.setAttribute('d', p.d);
      }
      node.setAttribute('fill', 'none');
      node.setAttribute('stroke', 'currentColor');
      node.setAttribute('stroke-width', '2');
      node.setAttribute('stroke-linecap', 'round');
      node.setAttribute('stroke-linejoin', 'round');
      svg.appendChild(node);
    });
    box.appendChild(svg);
    return box;
  }

  function buildAction(action, toast) {
    var node = el(action.href ? 'a' : 'button', 'lt-action', clean(action.label));
    if (action.href) {
      // block javascript: URLs
      var href = String(action.href);
      if (!/^\s*javascript:/i.test(href)) node.href = href;
    } else {
      node.type = 'button';
      node.addEventListener('click', function (event) {
        event.preventDefault();
        if (typeof action.onClick === 'function') action.onClick();
        dismiss(toast);
      });
    }
    return node;
  }

  function dismiss(toast) {
    if (!toast || toast.classList.contains('lt-out')) return;
    toast.classList.add('lt-out');
    setTimeout(function () { toast.remove(); }, EXIT_MS);
  }

  function notify(opts) {
    opts = opts || {};
    var type = ICONS[opts.type] ? opts.type : 'info';
    var duration = opts.duration != null ? opts.duration : DURATIONS[type];
    var root = ensureRoot();

    if (opts.id) {
      var previous = root.querySelector('[data-toast-id="' + opts.id + '"]');
      if (previous) previous.remove();
    }
    while (root.children.length >= MAX_VISIBLE) root.firstElementChild.remove();

    var toast = el('div', 'lt-toast lt-toast--' + type);
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    if (opts.id) toast.setAttribute('data-toast-id', opts.id);

    var body = el('div', 'lt-body');
    body.appendChild(el('div', 'lt-title', clean(opts.title || opts.message)));
    if (opts.title && opts.message) body.appendChild(el('div', 'lt-msg', clean(opts.message)));
    if (opts.action && opts.action.label) body.appendChild(buildAction(opts.action, toast));

    toast.appendChild(buildIcon(type));
    toast.appendChild(body);

    if (opts.dismissible !== false) {
      var close = el('button', 'lt-close', '\u00d7');
      close.type = 'button';
      close.setAttribute('aria-label', 'Dismiss');
      close.addEventListener('click', function () { dismiss(toast); });
      toast.appendChild(close);
    }

    if (duration > 0) {
      var bar = el('div', 'lt-bar');
      bar.style.setProperty('--lt-duration', duration + 'ms');
      toast.appendChild(bar);
      scheduleDismiss(toast, bar, duration);
    }

    root.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('lt-in'); });
    return toast;
  }

  /** The countdown bar drives the lifetime, so hovering (which pauses the bar) also pauses dismissal. */
  function scheduleDismiss(toast, bar, duration) {
    var remaining = duration;
    var startedAt = Date.now();
    var timer = setTimeout(function () { dismiss(toast); }, remaining);

    toast.addEventListener('mouseenter', function () {
      clearTimeout(timer);
      remaining -= Date.now() - startedAt;
    });
    toast.addEventListener('mouseleave', function () {
      startedAt = Date.now();
      timer = setTimeout(function () { dismiss(toast); }, Math.max(remaining, 1200));
    });
  }

  function shortcut(type) {
    return function (title, message, extra) {
      return notify(Object.assign({ type: type, title: title, message: message }, extra || {}));
    };
  }

  global.LuvuToast = {
    notify: notify,
    dismiss: dismiss,
    success: shortcut('success'),
    error: shortcut('error'),
    warning: shortcut('warning'),
    info: shortcut('info'),
  };
})(window);
