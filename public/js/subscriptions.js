(function () {
  'use strict';
  function money(minor) {
    var n = Number(minor || 0) / 100;
    return '$' + n.toFixed(2);
  }
  window.subsPage = function () {
    return {
      loading: false,
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
          if (window.LuvuToast) window.LuvuToast.error('Could not load subscriptions', String(e.message || e));
        } finally {
          this.loading = false;
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
        if (typeof agGrid !== 'undefined' && agGrid.createGrid) {
          if (this.grid) { try { this.grid.destroy(); } catch (e) {} this.grid = null; }
          this.grid = agGrid.createGrid(el, {
            rowData: data,
            defaultColDef: { sortable: true, filter: true, resizable: true },
            columnDefs: [
              { field: 'display_name', headerName: 'Name', flex: 1.2, minWidth: 140 },
              { field: 'email', headerName: 'Email', flex: 1.4, minWidth: 160 },
              { field: 'project', headerName: 'Project', flex: 1, minWidth: 120 },
              { field: 'plan', headerName: 'Plan', width: 110 },
              {
                field: 'status', headerName: 'Status', width: 130,
                cellRenderer: function (p) {
                  var s = p.value || '';
                  var color = s === 'Active' ? '#0f8f5f' : s === 'At Risk' ? '#b45309' : s === 'AI-Retained' ? '#5b3df5' : '#b4232f';
                  var span = document.createElement('span');
                  span.style.cssText = 'font-weight:600;color:' + color;
                  span.textContent = s;
                  return span;
                },
              },
              { field: 'ltv_minor', headerName: 'LTV', width: 100, valueFormatter: function (p) { return money(p.value); } },
            ],
            animateRows: true,
            suppressCellFocus: true,
          });
        } else {
          // Safe DOM fallback when AG Grid assets missing (no innerHTML)
          while (el.firstChild) el.removeChild(el.firstChild);
          var table = document.createElement('table');
          table.className = 'w-full text-sm text-left';
          var thead = document.createElement('thead');
          var hr = document.createElement('tr');
          hr.className = 'text-xs text-slate-500 uppercase';
          ['Name', 'Email', 'Status', 'LTV'].forEach(function (label) {
            var th = document.createElement('th');
            th.className = 'p-3';
            th.textContent = label;
            hr.appendChild(th);
          });
          thead.appendChild(hr);
          table.appendChild(thead);
          var tbody = document.createElement('tbody');
          data.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.className = 'border-t border-slate-100';
            [r.display_name || '—', r.email || '', r.status || '', money(r.ltv_minor)].forEach(function (val, idx) {
              var td = document.createElement('td');
              td.className = idx === 0 ? 'p-3 font-medium' : 'p-3';
              td.textContent = val;
              tr.appendChild(td);
            });
            tbody.appendChild(tr);
          });
          table.appendChild(tbody);
          el.appendChild(table);
          el.style.height = 'auto';
        }
      },
    };
  };
})();
