/**
 * LuvuAjaxNav: console pages open without a full page load.
 *
 * Sidebar links are fetched in the background, the <main> region, header title/actions, page stylesheets and page
 * scripts are swapped, and history is kept (back/forward work). The sidebar, top bar, toasts and Alpine stay alive,
 * so there is no white flash, no re-download of the shell and no lost state.
 *
 * Safety rules (any doubt = a normal full navigation, never a broken page):
 *  - same-origin GET only, HTML responses only, the response must contain #main and must not be a login redirect
 *  - only same-origin /js/ scripts are ever executed; inline scripts in fetched HTML are never run (CSP agrees)
 *  - pages that rely on one-time DOMContentLoaded wiring are listed in HARD and always load normally
 */
(function (global) {
  'use strict';
  if (global.LuvuAjaxNav || !global.fetch || !global.history || !global.DOMParser) return;

  var HARD = /(settings-spy|settings-logo|docs-toc|register)\.js/;          // one-time wiring: use a real load
  var LIBS = /(alpine|ag-grid|\/ui\.js|toast|loader|onboarding-track|dom-safe|console-search|ajax-nav)/; // shell scripts: never re-run
  var cache = {};                 // url -> { t, html }
  var ticket = 0, controller = null;

  function sameOriginUrl(href) {
    try {
      var u = new URL(href, global.location.href);
      return u.origin === global.location.origin ? u : null;
    } catch (e) { return null; }
  }

  function eligibleLink(a, ev) {
    if (!a || !a.href || ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return null;
    if (a.target && a.target !== '_self') return null;
    if (a.hasAttribute('download') || a.hasAttribute('data-no-ajax') || a.getAttribute('rel') === 'external') return null;
    var u = sameOriginUrl(a.href);
    if (!u || !/^https?:$/.test(u.protocol)) return null;
    if (u.pathname === global.location.pathname && u.search === global.location.search) return u.hash ? null : u; // same page: refresh in place
    if (/^\/(logout|login|register|api|internal|dashboard\/paypal)/.test(u.pathname)) return null;
    return u;
  }

  function fetchPage(url, signal) {
    var key = url.pathname + url.search;
    var hit = cache[key];
    if (hit && Date.now() - hit.t < 8000) return Promise.resolve(hit.html);
    return fetch(url.href, { credentials: 'same-origin', signal: signal, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } }).then(function (r) {
      var ru = sameOriginUrl(r.url);
      var ct = r.headers.get('content-type') || '';
      if (!r.ok || !ru || ct.indexOf('text/html') === -1 || /^\/(login|register)/.test(ru.pathname)) throw new Error('hard');
      return r.text().then(function (html) { cache[key] = { t: Date.now(), html: html }; return html; });
    });
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src + (src.indexOf('?') === -1 ? '?' : '&') + '_pj=' + Date.now();
      s.async = false;
      s.onload = function () { s.remove(); resolve(); };
      s.onerror = function () { s.remove(); reject(new Error('script')); };
      document.body.appendChild(s);
    });
  }

  function pageScripts(doc) {
    var out = [];
    doc.querySelectorAll('script[src]').forEach(function (s) {
      var u = sameOriginUrl(s.getAttribute('src'));
      if (u) out.push(u);
    });
    return out;
  }

  function syncStyles(doc) {
    var have = {};
    document.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) { have[new URL(l.href, global.location.href).pathname] = true; });
    var waits = [];
    doc.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) {
      var u = sameOriginUrl(l.getAttribute('href'));
      if (!u || have[u.pathname]) return;
      var n = document.createElement('link');
      n.rel = 'stylesheet'; n.href = u.href;
      waits.push(new Promise(function (res) { n.onload = n.onerror = res; }));
      document.head.appendChild(n);
    });
    return Promise.all(waits);
  }

  function swap(doc, url) {
    var newMain = doc.getElementById('main');
    var curMain = document.getElementById('main');
    if (!newMain || !curMain) throw new Error('hard');

    // 1) JSON config blocks the page scripts read while loading (kept inert in a holder, removed afterwards).
    var holder = document.createElement('div');
    holder.hidden = true;
    doc.querySelectorAll('script[type="application/json"]').forEach(function (j) {
      if (!j.id || j.id === 'toast-queue') return;
      var c = document.createElement('script');
      c.type = 'application/json'; c.id = j.id; c.textContent = j.textContent;
      holder.appendChild(c);
    });
    document.querySelectorAll('script[type="application/json"]').forEach(function (j) {
      if (j.id && j.id !== 'toast-queue' && holder.querySelector('#' + CSS.escape(j.id))) j.remove();
    });
    document.body.appendChild(holder);

    var scripts = pageScripts(doc).filter(function (u) { return !LIBS.test(u.pathname); });
    var libs = pageScripts(doc).filter(function (u) { return LIBS.test(u.pathname) && /ag-grid/.test(u.pathname); });

    return syncStyles(doc).then(function () {
      var haveLib = function (u) { return !!document.querySelector('script[src^="' + u.pathname + '"]'); };
      return libs.filter(function (u) { return !haveLib(u); }).reduce(function (p, u) { return p.then(function () { return loadScript(u.pathname); }); }, Promise.resolve());
    }).then(function () {
      // 2) Page scripts first (they define the Alpine components the new markup refers to), content second.
      return scripts.reduce(function (p, u) { return p.then(function () { return loadScript(u.pathname + u.search); }); }, Promise.resolve());
    }).then(function () {
      global.dispatchEvent(new CustomEvent('luvu:before-swap'));
      var clone = document.importNode(newMain, true);
      clone.querySelectorAll('script').forEach(function (s) { s.remove(); });
      curMain.replaceChildren.apply(curMain, Array.prototype.slice.call(clone.childNodes));
      holder.remove();

      // header: title crumb + actions
      var crumb = doc.querySelector('.crumb b'), curCrumb = document.querySelector('.crumb b');
      if (crumb && curCrumb) curCrumb.textContent = crumb.textContent;
      var hdr = doc.querySelector('header [style*="gap:8px"]:last-child'), curHdr = document.querySelector('header [style*="gap:8px"]:last-child');
      if (hdr && curHdr) curHdr.replaceChildren.apply(curHdr, Array.prototype.slice.call(document.importNode(hdr, true).childNodes));

      // sidebar active state, title, csrf
      var onHref = {};
      doc.querySelectorAll('.nav-a.on').forEach(function (a) { var u = sameOriginUrl(a.getAttribute('href')); if (u) onHref[u.pathname] = true; });
      document.querySelectorAll('.nav-a').forEach(function (a) {
        var u = sameOriginUrl(a.href), on = !!(u && onHref[u.pathname]);
        a.classList.toggle('on', on);
        if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
      });
      document.title = doc.title || document.title;
      var tok = doc.querySelector('meta[name="csrf-token"]'), curTok = document.querySelector('meta[name="csrf-token"]');
      if (tok && curTok) curTok.setAttribute('content', tok.getAttribute('content'));

      // flash messages the server queued for this page
      var q = doc.getElementById('toast-queue');
      if (q && global.LuvuToast && global.LuvuToast.notify) {
        try { (JSON.parse(q.textContent) || []).forEach(function (t) { global.LuvuToast.notify(t); }); } catch (e) { /* ignore */ }
      }
      var drawer = document.body.__x && document.body.__x.$data;
      if (drawer && 'drawer' in drawer) drawer.drawer = false;
      global.scrollTo(0, 0);
      curMain.setAttribute('tabindex', '-1');
      try { curMain.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
      global.dispatchEvent(new CustomEvent('luvu:page-load', { detail: { url: url.href } }));
    });
  }

  function go(url, push) {
    var my = ++ticket;
    if (controller) { try { controller.abort(); } catch (e) { /* ignore */ } }
    controller = typeof AbortController === 'function' ? new AbortController() : null;
    var hard = function () { global.location.href = url.href; };

    var p = fetchPage(url, controller && controller.signal).then(function (html) {
      if (my !== ticket) return;
      var doc = new DOMParser().parseFromString(html, 'text/html');
      if (pageScripts(doc).some(function (u) { return HARD.test(u.pathname); })) throw new Error('hard');
      return swap(doc, url).then(function () {
        if (my !== ticket) return;
        if (push) global.history.pushState({ lpNav: 1 }, '', url.pathname + url.search + url.hash);
      });
    }).catch(function (e) {
      if (e && e.name === 'AbortError') return;
      if (my === ticket) hard();
    });
    if (global.LuvuLoader && global.LuvuLoader.track) global.LuvuLoader.track(p);
    return p;
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a.nav-a, a[data-ajax-nav]') : null;
    var u = eligibleLink(a, ev);
    if (!u) return;
    if (a.classList.contains('nav-a') && a.closest('[data-external]')) return;
    ev.preventDefault();
    go(u, true);
  });

  // Warm the cache while the pointer is still travelling to the link: the click then feels instant.
  var warm = null;
  document.addEventListener('pointerover', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a.nav-a') : null;
    if (!a) return;
    var u = sameOriginUrl(a.href);
    if (!u || u.pathname === global.location.pathname) return;
    clearTimeout(warm);
    warm = setTimeout(function () { fetchPage(u).catch(function () { /* ignore */ }); }, 90);
  }, { passive: true });

  global.addEventListener('popstate', function () {
    var u = sameOriginUrl(global.location.href);
    if (u) go(u, false);
  });
  try { global.history.replaceState({ lpNav: 1 }, '', global.location.href); } catch (e) { /* ignore */ }

  global.LuvuAjaxNav = { go: function (href) { var u = sameOriginUrl(href); if (u) go(u, true); } };
})(window);
