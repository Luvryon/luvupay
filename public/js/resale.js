(function () {
  'use strict';
  window.resalePage = function () {
    return {
      d: { listings: [], counts: {}, enabled: true, store_url: '#' }, loaded: false, failed: false,
      money: function (minor, cur) {
        var n = ((Number(minor) || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return (cur || 'USD') === 'USD' ? '$' + n : n + ' ' + cur;
      },
      grid: null,
      init: function () { this.load(); },
      columns: function () {
        var self = this, G = window.LuvuGrid;
        return [
          { field: 'seller', headerName: 'Seller', flex: 1, minWidth: 150 },
          { field: 'days', headerName: 'Access', width: 120, valueFormatter: function (p) { return p.value ? p.value + ' days' : '—'; }, filter: 'agNumberColumnFilter' },
          { field: 'price_minor', headerName: 'Price', width: 130, filter: 'agNumberColumnFilter', cellStyle: { fontWeight: '600' },
            valueFormatter: function (p) { return self.money(p.value, p.data && p.data.currency); } },
          { field: 'status', headerName: 'Status', width: 130, cellRenderer: G.badge({ active: 'ok', sold: 'info' }), filter: G.enterprise ? 'agSetColumnFilter' : true },
          { field: 'buyer', headerName: 'Buyer', flex: 1, minWidth: 150, valueFormatter: function (p) { return p.value || '—'; } },
          { headerName: 'Link', colId: 'link', width: 150, sortable: false, filter: false,
            cellRenderer: function (p) {
              var l = p.data || {};
              var wrap = G.el('span');
              if (!(l.link && l.status === 'active')) { wrap.appendChild(G.el('span', 'lg-dim', '—')); return wrap; }
              var open = G.el('a', 'lg-link', 'Open');
              open.href = l.link; open.target = '_blank'; open.rel = 'noopener';
              var copy = G.el('button', 'lg-link', 'Copy');
              copy.type = 'button';
              copy.addEventListener('click', function () { self.copy(l.link); });
              wrap.appendChild(open);
              wrap.appendChild(document.createTextNode(' · '));
              wrap.appendChild(copy);
              return wrap;
            } },
        ];
      },
      syncGrid: function () {
        var host = document.getElementById('grid-resale');
        var rows = (this.d && this.d.listings) || [];
        if (!host || !window.LuvuGrid || !rows.length) return;
        if (this.grid && host.__lg === this.grid) { window.LuvuGrid.setRows(this.grid, rows); return; }
        this.grid = window.LuvuGrid.create(host, { columns: this.columns(), rows: rows, rowId: 'id', pageSize: 15, height: 560, floatingFilter: true });
      },
      load: async function () {
        this.failed = false;
        try {
          var r = await fetch('/api/v1/dashboard/resale', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          this.d = await r.json();
        } catch (e) {
          this.failed = true;
        }
        this.loaded = true;
        var self = this;
        this.$nextTick(function () { self.syncGrid(); });
      },
      copy: function (text) {
        var done = function () { if (window.LuvuToast) window.LuvuToast.success('Link copied', text); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); } else { done(); }
      }
    };
  };
})();
