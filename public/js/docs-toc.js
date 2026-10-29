/**
 * Docs table of contents — hard security: no innerHTML.
 * Builds anchors with createElement + textContent only.
 */
(function () {
  'use strict';

  function boot() {
    var nav = document.getElementById('docs-toc-links');
    if (!nav) return;

    var scope = document.querySelector('.docs-content') ||
      document.querySelector('article') ||
      document.querySelector('main') ||
      document.body;

    var heads = scope.querySelectorAll('h2, h3');

    // Clear safely
    while (nav.firstChild) nav.removeChild(nav.firstChild);

    if (!heads.length) {
      var empty = document.createElement('span');
      empty.style.cssText = 'font-size:12px;color:#94a3b8';
      empty.textContent = 'No sections on this page';
      nav.appendChild(empty);
    } else {
      heads.forEach(function (h, idx) {
        if (!h.id) {
          h.id = (h.textContent || 'section')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '') || ('section-' + idx);
        }
        var a = document.createElement('a');
        a.href = '#' + h.id;
        a.setAttribute('data-toc-id', h.id);
        if (h.tagName === 'H3') a.className = 'toc-h3';
        a.textContent = (h.textContent || '').trim();
        nav.appendChild(a);
      });
    }

    var links = Array.prototype.slice.call(nav.querySelectorAll('a[data-toc-id]'));

    function setActive(id) {
      links.forEach(function (a) {
        a.classList.toggle('active', a.getAttribute('data-toc-id') === id);
      });
    }

    function onScroll() {
      var y = window.scrollY + 110;
      var cur = null;
      links.forEach(function (a) {
        var el = document.getElementById(a.getAttribute('data-toc-id'));
        if (el && el.offsetTop <= y) cur = a.getAttribute('data-toc-id');
      });
      if (cur) setActive(cur);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var back = document.getElementById('docs-back-top');
    if (back) {
      back.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
      window.addEventListener('scroll', function () {
        back.hidden = window.scrollY < 600;
      }, { passive: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
