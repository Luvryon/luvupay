(function () {
  'use strict';
  var ITEMS = [
    { label: 'Overview', href: '/dashboard', group: 'Workspace' },
    { label: 'Wallet & Transactions', href: '/wallet', group: 'Workspace' },
    { label: 'Subscriptions', href: '/subscriptions', group: 'Workspace' },
    { label: 'Analytics', href: '/analytics', group: 'Workspace' },
    { label: 'Team', href: '/team', group: 'Account' },
    { label: 'Security', href: '/security', group: 'Account' },
    { label: 'Settings', href: '/settings', group: 'Account' },
    { label: 'Marketplace', href: '/marketplace', group: 'Resources' },
    { label: 'Documentation', href: '/docs', group: 'Resources' },
    { label: 'System status', href: '/status', group: 'Resources' }
  ];

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var btn = document.getElementById('luvupay-search-btn');
    var panel = document.getElementById('luvupay-search-panel');
    var input = document.getElementById('luvupay-search-input');
    var list = document.getElementById('luvupay-search-list');
    var esc = document.getElementById('luvupay-search-esc');
    if (!btn || !panel || !input || !list) return;

    function render(q) {
      q = (q || '').trim().toLowerCase();
      var rows = !q ? ITEMS : ITEMS.filter(function (i) {
        return i.label.toLowerCase().indexOf(q) !== -1 || i.group.toLowerCase().indexOf(q) !== -1;
      });
      if (!rows.length) {
        list.innerHTML = '<li class="ns-empty">No results</li>';
        return;
      }
      list.innerHTML = rows.map(function (i) {
        return '<li><a href="' + i.href + '"><span>' + i.label + '</span><span class="ns-meta">' + i.group + '</span></a></li>';
      }).join('');
    }

    function open() {
      panel.hidden = false;
      btn.style.visibility = 'hidden';
      render(input.value);
      setTimeout(function () { input.focus(); }, 10);
    }
    function close() {
      panel.hidden = true;
      btn.style.visibility = '';
      input.value = '';
      render('');
    }

    btn.addEventListener('click', function (e) { e.preventDefault(); open(); });
    if (esc) esc.addEventListener('click', close);
    input.addEventListener('input', function () { render(input.value); });
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
    render('');
  });
})();
