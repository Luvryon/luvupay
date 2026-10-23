/**
 * Safe DOM helpers — no insertAdjacentHTML / document.write / eval / Function / string timers.
 * Use textContent and createElement only.
 */
(function (global) {
  'use strict';

  function el(tag, props, children) {
    var node = document.createElement(tag);
    if (props) {
      Object.keys(props).forEach(function (k) {
        var v = props[k];
        if (v == null) return;
        if (k === 'className') node.className = String(v);
        else if (k === 'text') node.textContent = String(v);
        else if (k === 'attrs' && typeof v === 'object') {
          Object.keys(v).forEach(function (a) {
            if (a === 'href' || a === 'src') {
              var url = String(v[a]);
              if (/^\s*javascript:/i.test(url)) return;
              node.setAttribute(a, url);
            } else if (a.indexOf('on') === 0) {
              // never set inline handlers from data
              return;
            } else {
              node.setAttribute(a, String(v[a]));
            }
          });
        } else if (k === 'on' && typeof v === 'object') {
          Object.keys(v).forEach(function (ev) {
            if (typeof v[ev] === 'function') node.addEventListener(ev, v[ev]);
          });
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

  global.DomSafe = { el: el, clear: clear };
})(window);
