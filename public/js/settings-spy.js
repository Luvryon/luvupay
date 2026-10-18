(function () {
  'use strict';
  function init() {
    var nav = document.querySelector('.snav');
    if (!nav) return;
    var links = Array.prototype.slice.call(nav.querySelectorAll('a[href^="#"]'));
    if (!links.length) return;
    var map = {};
    links.forEach(function (a) {
      var id = (a.getAttribute('href') || '').slice(1);
      var el = document.getElementById(id);
      if (el) map[id] = { link: a, el: el };
    });
    function setActive(id) {
      links.forEach(function (a) {
        var on = a.getAttribute('href') === '#' + id;
        a.classList.toggle('is-active', on);
        if (on) a.setAttribute('aria-current', 'location');
        else a.removeAttribute('aria-current');
      });
    }
    function onScroll() {
      var y = window.scrollY + 120;
      var current = null;
      Object.keys(map).forEach(function (id) {
        if (map[id].el.offsetTop <= y) current = id;
      });
      if (current) setActive(current);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
