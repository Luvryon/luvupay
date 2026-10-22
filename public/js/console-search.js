/**
 * Console command palette — Ctrl+K / Cmd+K
 * Targets: #luvupay-search-btn, #luvupay-search-panel, #luvupay-search-input, #luvupay-search-list
 */
(function () {
  'use strict';

  var STATIC = [
    { title: 'Dashboard', href: '/dashboard', group: 'Console', keywords: 'home overview ledger' },
    { title: 'Wallet & Transactions', href: '/wallet', group: 'Console', keywords: 'balance withdraw money' },
    { title: 'Subscriptions', href: '/subscriptions', group: 'Console', keywords: 'customers crm churn' },
    { title: 'Marketplace', href: '/marketplace', group: 'Console', keywords: 'resale listings' },
    { title: 'Settings', href: '/settings', group: 'Console', keywords: 'paypal logo api webhook' },
    { title: 'Analytics', href: '/analytics', group: 'Console', keywords: 'charts revenue' },
    { title: 'Team', href: '/team', group: 'Console', keywords: 'invite members' },
    { title: 'Status', href: '/status', group: 'Console', keywords: 'health' },
    { title: 'Docs overview', href: '/docs', group: 'Docs', keywords: 'documentation' },
    { title: 'API reference', href: '/docs/api', group: 'Docs', keywords: 'rest endpoints auth' },
    { title: 'Postman collection', href: '/docs/postman', group: 'Docs', keywords: 'import collection' },
    { title: 'Sandbox guide', href: '/docs/sandbox', group: 'Docs', keywords: 'paypal demo' },
    { title: 'Privacy policy', href: '/privacy', group: 'Legal', keywords: 'privacy data' },
    { title: 'Terms of service', href: '/terms', group: 'Legal', keywords: 'terms legal' },
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
    if (!list.length) {
      box.innerHTML = '<li class="ns-empty">No matches</li>';
      return;
    }
    var html = '';
    var last = '';
    list.forEach(function (item) {
      if (item.group !== last) {
        last = item.group;
        html += '<li class="ns-empty" style="text-align:left;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:8px 12px 4px">' + last + '</li>';
      }
      html += '<li><a href="' + item.href + '"><span>' + item.title + '</span><span class="ns-meta">' + (item.group || '') + '</span></a></li>';
    });
    box.innerHTML = html;
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
