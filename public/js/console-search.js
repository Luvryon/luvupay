(function () {
  'use strict';

  var STATIC = [
    { title: 'Dashboard', href: '/dashboard', group: 'Console', keywords: 'home overview ledger' },
    { title: 'Wallet & Transactions', href: '/wallet', group: 'Console', keywords: 'balance withdraw money' },
    { title: 'Subscriptions', href: '/subscriptions', group: 'Console', keywords: 'customers crm churn' },
    { title: 'Developers', href: '/developers', group: 'Console', keywords: 'api keys webhook' },
    { title: 'API keys and webhooks', href: '/settings', group: 'Console', keywords: 'api keys webhook developers' },
    { title: 'Marketplace', href: '/marketplace', group: 'Console', keywords: 'resale listings' },
    { title: 'Settings', href: '/settings', group: 'Console', keywords: 'paypal logo api webhook' },
    { title: 'Analytics', href: '/analytics', group: 'Console', keywords: 'charts revenue' },
    { title: 'Docs overview', href: '/docs', group: 'Docs', keywords: 'documentation' },
    { title: 'API reference', href: '/docs/api', group: 'Docs', keywords: 'rest endpoints' },
    { title: 'Postman', href: '/docs/postman', group: 'Docs', keywords: 'collection' },
    { title: 'Privacy', href: '/privacy', group: 'Legal', keywords: 'privacy' },
    { title: 'Terms', href: '/terms', group: 'Legal', keywords: 'terms' },
  ];

  function norm(s) { return String(s || '').toLowerCase(); }

  function filterStatic(q) {
    q = norm(q).trim();
    if (!q) return STATIC.slice();
    return STATIC.filter(function (item) {
      return norm(item.title + ' ' + item.group + ' ' + (item.keywords || '')).indexOf(q) !== -1;
    });
  }

  function render(list) {
    var box = document.getElementById('luvupay-search-list');
    if (!box) return;
    if (window.DomSafe) DomSafe.clear(box);
    else while (box.firstChild) box.removeChild(box.firstChild);

    if (!list.length) {
      var empty = document.createElement('li');
      empty.className = 'ns-empty';
      empty.textContent = 'No matches';
      box.appendChild(empty);
      return;
    }
    var last = '';
    list.forEach(function (item) {
      if (item.group !== last) {
        last = item.group;
        var h = document.createElement('li');
        h.className = 'ns-empty';
        h.style.cssText = 'text-align:left;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:8px 12px 4px';
        h.textContent = last;
        box.appendChild(h);
      }
      var li = document.createElement('li');
      var a = document.createElement('a');
      var href = String(item.href || '/');
      if (!/^\s*javascript:/i.test(href)) a.setAttribute('href', href);
      var title = document.createElement('span');
      title.textContent = item.title || '';
      var meta = document.createElement('span');
      meta.className = 'ns-meta';
      meta.textContent = item.group || '';
      a.appendChild(title);
      a.appendChild(meta);
      li.appendChild(a);
      box.appendChild(li);
    });
  }

  function searchSubscribers(q) {
    q = norm(q).trim();
    if (q.length < 2) return Promise.resolve([]);
    return fetch('/api/v1/dashboard/subscriptions', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.json(); }).then(function (j) {
      var rows = (j && j.data) ? j.data : [];
      return rows.filter(function (row) {
        return norm((row.display_name || '') + ' ' + (row.email || '') + ' ' + (row.status || '')).indexOf(q) !== -1;
      }).slice(0, 8).map(function (row) {
        return {
          title: (row.display_name || row.email || 'Subscriber') + ' · ' + (row.status || ''),
          href: '/subscriptions',
          group: 'Subscribers',
        };
      });
    }).catch(function () { return []; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('luvupay-search-panel');
    var btn = document.getElementById('luvupay-search-btn');
    var input = document.getElementById('luvupay-search-input');
    var esc = document.getElementById('luvupay-search-esc');
    if (!panel || !input) return;

    function open() {
      panel.hidden = false;
      if (btn) btn.style.visibility = 'hidden';
      render(filterStatic(''));
      setTimeout(function () { input.focus(); }, 10);
    }
    function close() {
      panel.hidden = true;
      if (btn) btn.style.visibility = '';
      input.value = '';
      render(filterStatic(''));
    }
    async function onInput() {
      var q = input.value;
      var base = filterStatic(q);
      var extra = await searchSubscribers(q);
      render(base.concat(extra));
    }

    if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); open(); });
    if (esc) esc.addEventListener('click', close);
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
    });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        if (panel.hidden) open(); else close();
      }
      if (e.key === 'Escape' && !panel.hidden) close();
    });
    document.addEventListener('click', function (e) {
      var root = document.getElementById('luvupay-search');
      if (!panel.hidden && root && !root.contains(e.target)) close();
    });
  });
})();
