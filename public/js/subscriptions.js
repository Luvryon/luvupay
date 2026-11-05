(function () {
  'use strict';
  function money(minor) {
    var n = Number(minor || 0) / 100;
    return '$' + n.toFixed(2);
  }
  window.subsPage = function () {
    return {
      loading: true,
      loaded: false,
      failed: false,
      rows: [],
      filter: 'all',
      filters: [
        { id: 'all', label: 'All' },
        { id: 'Active', label: 'Active' },
        { id: 'At Risk', label: 'At Risk' },
        { id: 'AI-Retained', label: 'AI-Retained' },
        { id: 'Churned', label: 'Churned' },
      ],
      grid: null,
      async load() {
        this.loading = true;
        this.failed = false;
        try {
          var res = await fetch('/api/v1/dashboard/subscriptions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });
          var j = res.ok ? await res.json() : { data: [] };
          this.rows = Array.isArray(j.data) ? j.data : [];
          this.apply();
        } catch (e) {
          this.rows = [];
          this.failed = true;
          if (window.LuvuToast) window.LuvuToast.error('Could not load subscriptions', String(e.message || e));
        } finally {
          this.loading = false;
          this.loaded = true;
        }
      },
      filtered() {
        if (this.filter === 'all') return this.rows;
        return this.rows.filter(function (r) { return r.status === this.filter; }.bind(this));
      },
      apply() {
        var data = this.filtered();
        var el = document.getElementById('grid-subs');
        if (!el) return;
        var G = window.LuvuGrid;
        if (!G) return;
        this.grid = G.create(el, {
          rows: data, rowId: 'id', pageSize: 15, height: 560, floatingFilter: true,
          noRowsText: 'No subscribers match this filter',
          columns: [
            { field: 'display_name', headerName: 'Name', flex: 1.2, minWidth: 140 },
            { field: 'email', headerName: 'Email', flex: 1.4, minWidth: 160 },
            { field: 'project', headerName: 'Project', flex: 1, minWidth: 120 },
            { field: 'plan', headerName: 'Plan', width: 120 },
            { field: 'status', headerName: 'Status', width: 140, cellRenderer: G.badge({ 'active': 'ok', 'at risk': 'warn', 'ai-retained': 'info', 'churned': 'bad' }),
              filter: G.enterprise ? 'agSetColumnFilter' : true },
            { field: 'ltv_minor', headerName: 'LTV', width: 120, type: 'rightAligned', filter: 'agNumberColumnFilter',
              valueFormatter: function (p) { return money(p.value); } },
          ],
        });
      },
    };
  };
})();
