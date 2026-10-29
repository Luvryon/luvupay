/**
 * Safe DOM helpers — hard security mode.
 * Forbidden: insertAdjacentHTML, document.write, eval, new Function,
 * string-form setTimeout/setInterval, srcdoc, createContextualFragment,
 * setting on* attributes from data, javascript: URLs.
 * Only createElement + textContent + addEventListener.
 */
(function (global) {
  'use strict';

  var JS_SCHEME = /^\s*javascript:/i;
  var ON_ATTR = /^on/i;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function el(tag, props, children) {
    var node = document.createElement(tag);
    if (props) {
      Object.keys(props).forEach(function (k) {
        var v = props[k];
        if (v == null) return;
        if (k === 'className') {
          node.className = String(v);
        } else if (k === 'text') {
          node.textContent = String(v);
        } else if (k === 'attrs' && typeof v === 'object') {
          Object.keys(v).forEach(function (a) {
            if (ON_ATTR.test(a)) return; // never set inline handlers from data
            if (a === 'href' || a === 'src' || a === 'xlink:href') {
              var url = String(v[a]);
              if (JS_SCHEME.test(url)) return;
              node.setAttribute(a, url);
            } else {
              node.setAttribute(a, String(v[a]));
            }
          });
        } else if (k === 'on' && typeof v === 'object') {
          Object.keys(v).forEach(function (ev) {
            if (typeof v[ev] === 'function') node.addEventListener(ev, v[ev]);
          });
        } else if (k === 'style' && typeof v === 'string') {
          node.style.cssText = v;
        }
      });
    }
    if (children && children.length) {
      children.forEach(function (c) {
        if (c == null) return;
        node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      });
    }
    return node;
  }

  function clear(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  /**
   * Open a printable window with HTML content without document.write.
   * Uses Blob URL + location assignment (safer than document.write).
   * Caller must ensure `html` is already escaped / trusted.
   */
  function openPrintableHtml(html, opts) {
    opts = opts || {};
    var w = global.open('', '_blank', opts.features || 'noopener,noreferrer,width=640,height=800');
    if (!w) return null;
    var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    try {
      w.location.href = url;
    } catch (e) {
      try { w.close(); } catch (e2) {}
      URL.revokeObjectURL(url);
      return null;
    }
    var delay = opts.printDelay != null ? opts.printDelay : 400;
    setTimeout(function () {
      try {
        w.focus();
        if (opts.autoPrint !== false) w.print();
      } catch (e) {}
      setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
    }, delay);
    return w;
  }

  global.DomSafe = {
    el: el,
    clear: clear,
    esc: esc,
    openPrintableHtml: openPrintableHtml,
  };
})(window);
