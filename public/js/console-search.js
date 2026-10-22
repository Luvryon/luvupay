(function () {
  'use strict';
  var STATIC = [
    { title: 'Dashboard', href: '/dashboard', group: 'Console', keywords: 'home overview ledger kpi' },
    { title: 'Wallet & Transactions', href: '/wallet', group: 'Console', keywords: 'balance withdraw payout money' },
    { title: 'Subscriptions', href: '/subscriptions', group: 'Console', keywords: 'customers crm active churn' },
    { title: 'Marketplace', href: '/marketplace', group: 'Console', keywords: 'resale listings buy sell' },
    { title: 'Settings', href: '/settings', group: 'Console', keywords: 'paypal logo api webhook project features' },
    { title: 'Analytics', href: '/analytics', group: 'Console', keywords: 'charts revenue' },
    { title: 'Team', href: '/team', group: 'Console', keywords: 'members invite' },
    { title: 'Status', href: '/status', group: 'Console', keywords: 'health uptime' },
    { title: 'Docs — Overview', href: '/docs', group: 'Docs', keywords: 'documentation guide' },
    { title: 'Docs — API reference', href: '/docs/api', group: 'Docs', keywords: 'rest endpoints auth scopes' },
    { title: 'Docs — Postman', href: '/docs/postman', group: 'Docs', keywords: 'collection import' },
    { title: 'Privacy', href: '/privacy', group: 'Legal', keywords: 'privacy policy data' },
    { title: 'Terms', href: '/terms', group: 'Legal', keywords: 'terms of service' },
  ];

  function norm(s) { return String(s || '').toLowerCase(); }

  function filterStatic(q) {
    q = norm(q).trim();
    if (!q) return STATIC.slice(0, 12);
    return STATIC.filter(function (item) {
      var hay = norm(item.title + ' ' + item.group + ' ' + (item.keywords || ''));
      return hay.indexOf(q) !== -1;
    }).slice(0, 20);
  }

  function render(list, q) {
    var box = document.getElementById('luvupay-search-results');
    if (!box) return;
    if (!list.length) {
      box.innerHTML = '<p class="px-3 py-4 text-sm text-slate-500">No matches for “' + (q || '') + '”</p>';
      return;
    }
    var html = '';
    var group = '';
    list.forEach(function (item) {
      if (item.group !== group) {
        group = item.group;
        html += '<p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">' + group + '</p>';
      }
      html += '<a class="block px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" href="' + item.href + '">' +
        '<span class="font-medium">' + item.title + '</span></a>';
    });
    box.innerHTML = html;
  }

  function searchMerchant(q) {
    q = norm(q).trim();
    if (q.length < 2) return Promise.resolve([]);
    return fetch('/api/v1/dashboard/subscriptions', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.json(); }).then(function (j) {
      var rows = (j && j.data) ? j.data : [];
      return rows.filter(function (row) {
        var hay = norm((row.display_name || '') + ' ' + (row.email || '') + ' ' + (row.status || ''));
        return hay.indexOf(q) !== -1;
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
      render(filterStatic(''), '');
      setTimeout(function () { input.focus(); }, 10);
    }
    function close() {
      panel.hidden = true;
      if (btn) btn.style.visibility = '';
      input.value = '';
      render(filterStatic(''), '');
    }
    async function onInput() {
      var q = input.value;
      var base = filterStatic(q);
      var extra = await searchMerchant(q);
      render(base.concat(extra), q);
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
        open();
      }
      if (e.key === 'Escape' && !panel.hidden) close();
    });
    document.addEventListener('click', function (e) {
      var root = document.getElementById('luvupay-search');
      if (!panel.hidden && root && !root.contains(e.target)) close();
    });
  });
})();
