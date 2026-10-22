(function (global) {
  'use strict';
  function money(m, c) {
    c = c || 'USD';
    var n = (Number(m) || 0) / 100;
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: c }).format(n); }
    catch (e) { return (c === 'USD' ? '$' : '') + n.toFixed(2); }
  }
  function request(path) {
    return fetch('/api/v1/dashboard/' + path, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
  }
  global.subsPage = function () {
    return {
      loading: false,
      rows: [],
      all: [],
      filter: 'all',
      filters: [
        { id: 'all', label: 'All' },
        { id: 'Active', label: 'Active' },
        { id: 'At Risk', label: 'At Risk' },
        { id: 'AI-Retained', label: 'AI-Retained' },
        { id: 'Churned', label: 'Churned' },
      ],
      grid: null,
      load: async function () {
        this.loading = true;
        try {
          var res = await request('subscriptions');
          this.all = (res.ok && res.data && res.data.data) ? res.data.data : [];
          this.apply();
        } catch (e) {
          this.all = [];
          this.rows = [];
        } finally {
          this.loading = false;
          var self = this;
          this.$nextTick(function () { self.mount(); });
        }
      },
      apply: function () {
        var f = this.filter;
        this.rows = f === 'all' ? this.all.slice() : this.all.filter(function (r) { return r.status === f; });
        if (this.grid) {
          this.grid.setGridOption('rowData', this.rows);
        }
      },
      mount: function () {
        var el = document.getElementById('grid-subs');
        if (!el || typeof agGrid === 'undefined') return;
        if (this.grid) {
          this.grid.setGridOption('rowData', this.rows);
          return;
        }
        this.grid = agGrid.createGrid(el, {
          rowData: this.rows,
          defaultColDef: { sortable: true, filter: true, resizable: true },
          columnDefs: [
            { field: 'display_name', headerName: 'Customer', flex: 1.2, minWidth: 140 },
            { field: 'email', headerName: 'Email', flex: 1.4, minWidth: 160 },
            { field: 'project', headerName: 'Project', flex: 1, minWidth: 120 },
            { field: 'plan', headerName: 'Plan', width: 110 },
            {
              field: 'status', headerName: 'Status', width: 120,
              cellRenderer: function (p) {
                var s = p.value || '';
                var color = s === 'Active' ? '#0f8f5f' : s === 'At Risk' ? '#b45309' : s === 'AI-Retained' ? '#5b3df5' : '#b4232f';
                var span = document.createElement('span');
                span.style.cssText = 'font-weight:600;color:' + color;
                span.textContent = s;
                return span;
              },
            },
            {
              field: 'ltv_minor', headerName: 'LTV', width: 100,
              valueFormatter: function (p) { return money(p.value); },
            },
            { field: 'created_at', headerName: 'Since', width: 120, valueFormatter: function (p) {
              return p.value ? String(p.value).slice(0, 10) : '—';
            }},
          ],
          animateRows: true,
          rowSelection: { mode: 'singleRow' },
        });
      },
    };
  };
})(window);
