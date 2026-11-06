/**
 * LuvuGrid: one thin wrapper around AG Grid so every table in LuvuPay looks and behaves the same.
 *
 *  - Reads the licence key from <meta name="ag-grid-license"> (rendered from AG_GRID_LICENSE_KEY in .env).
 *  - LuvuGrid.create(el, {columns, rows, ...})  -> grid api (live data tables)
 *  - LuvuGrid.fromTable(table, opts)            -> turns a server-rendered <table> into a grid
 *  - Any <table data-ag-table> (or any table inside [data-ag-tables]) is upgraded automatically on load.
 *  Cells are built with DOM APIs (textContent), never innerHTML.
 */
(function (global) {
  'use strict';
  if (global.LuvuGrid) return;

  var booted = false;
  var enterprise = false;

  function boot() {
    if (booted) return !!global.agGrid;
    var g = global.agGrid;
    if (!g) return false;
    booted = true;
    enterprise = !!g.LicenseManager;
    var m = document.querySelector('meta[name="ag-grid-license"]');
    var key = m ? String(m.getAttribute('content') || '').trim() : '';
    if (enterprise && key) {
      try { g.LicenseManager.setLicenseKey(key); } catch (e) { /* invalid key: AG Grid shows its own watermark */ }
    }
    return true;
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function resolve(target) {
    return typeof target === 'string' ? document.getElementById(target) : target;
  }

  function money(minor, cur) {
    var n = ((Number(minor) || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (!cur || cur === 'USD') ? '$' + n : n + ' ' + cur;
  }

  function tone(v) {
    var s = String(v == null ? '' : v).toLowerCase();
    if (/settled|complet|active|captur|sent|paid|success|\bok\b|retained|on$/.test(s)) return 'ok';
    if (/pending|await|grace|warn|processing|at risk|proposed/.test(s)) return 'warn';
    if (/fail|cancel|expire|reject|error|churn|removed|bad|off$/.test(s)) return 'bad';
    if (/resale|resold|sold|info|owner/.test(s)) return 'info';
    return 'neutral';
  }

  /** cellRenderer: coloured pill. Optional explicit map {value: tone}. */
  function badge(map) {
    return function (p) {
      var v = p.value == null ? '' : String(p.value);
      if (!v) return el('span', 'lg-dim', '—');
      var t = (map && map[v.toLowerCase()]) || tone(v);
      return el('span', 'lg-badge lg-' + t, v);
    };
  }

  function defaults(o) {
    var d = {
      defaultColDef: { sortable: true, filter: o.filter !== false, resizable: true, minWidth: 90 },
      animateRows: true,
      suppressCellFocus: true,
      enableCellTextSelection: true,
      rowHeight: o.rowHeight || 44,
      headerHeight: o.headerHeight == null ? 42 : o.headerHeight,
      overlayNoRowsTemplate: '<span class="lg-empty">' + String(o.noRowsText || 'No rows yet').replace(/[&<>"]/g, '') + '</span>',
    };
    if (o.floatingFilter) d.defaultColDef.floatingFilter = true;
    if (o.autoHeight) d.domLayout = o.print ? 'print' : 'autoHeight';
    if (o.pageSize) {
      d.pagination = true;
      d.paginationPageSize = o.pageSize;
      d.paginationPageSizeSelector = [10, 15, 25, 50, 100];
    }
    // Enterprise-only extras (only when the enterprise bundle is loaded)
    if (enterprise && !o.autoHeight && o.statusBar !== false) {
      d.statusBar = { statusPanels: [{ statusPanel: 'agTotalAndFilteredRowCountComponent', align: 'left' }] };
    }
    return d;
  }

  function fallback(host, msg) {
    host.textContent = '';
    host.appendChild(el('p', 'lg-empty', msg || 'Table library failed to load. Refresh the page.'));
  }

  function create(target, o) {
    var host = resolve(target);
    o = o || {};
    if (!host) return null;
    if (!boot()) { fallback(host); return null; }
    if (host.__lg) { try { host.__lg.destroy(); } catch (e) { /* already gone */ } host.__lg = null; }
    host.classList.add('ag-theme-quartz');
    if (o.height && !o.autoHeight) host.style.height = o.height + 'px';
    var opts = defaults(o);
    if (o.defaultColDef) opts.defaultColDef = Object.assign({}, opts.defaultColDef, o.defaultColDef);
    if (o.grid) Object.keys(o.grid).forEach(function (k) { opts[k] = o.grid[k]; });
    opts.columnDefs = o.columns || [];
    opts.rowData = o.rows || [];
    if (o.rowId) opts.getRowId = function (p) { return String(p.data && p.data[o.rowId]); };
    var api = global.agGrid.createGrid(host, opts);
    host.__lg = api;
    return api;
  }

  /** Replace rows on an existing grid (keeps sort/filter/page). */
  function setRows(api, rows) {
    if (!api) return;
    try { api.setGridOption('rowData', rows || []); } catch (e) { /* grid destroyed */ }
  }

  function exportCsv(api, name) { if (api) api.exportDataAsCsv({ fileName: name || 'export.csv' }); }
  function exportExcel(api, name) {
    if (!api) return;
    if (enterprise && api.exportDataAsExcel) api.exportDataAsExcel({ fileName: name || 'export.xlsx' });
    else exportCsv(api, String(name || 'export').replace(/\.xlsx$/, '') + '.csv');
  }

  /** <table> -> AG Grid. Cell markup is cloned (trusted, server-rendered), never re-parsed from strings. */
  function fromTable(table, o) {
    o = o || {};
    if (!table || table.__lgDone || !boot()) return null;
    var trs = Array.prototype.slice.call(table.rows);
    if (!trs.length) return null;

    var headRow = null;
    if (table.tHead && table.tHead.rows.length) headRow = table.tHead.rows[table.tHead.rows.length - 1];
    else if (Array.prototype.every.call(trs[0].cells, function (c) { return c.tagName === 'TH'; })) headRow = trs[0];
    var body = trs.filter(function (r) { return r !== headRow && !(table.tHead && table.tHead.contains(r)); });
    var first = headRow || body[0];
    var n = first.cells.length;

    var cols = [];
    var rows = body.map(function () { return {}; });
    for (var i = 0; i < n; i++) {
      (function (i) {
        var h = headRow ? headRow.cells[i] : null;
        var align = (h && getComputedStyle(h).textAlign) || 'left';
        var cellAlign = body[0] && body[0].cells[i] ? getComputedStyle(body[0].cells[i]).textAlign : align;
        var rich = false, maxLen = 4;
        body.forEach(function (r, ri) {
          var c = r.cells[i];
          var text = c ? c.textContent.trim() : '';
          if (text.length > maxLen) maxLen = text.length;
          rows[ri]['c' + i] = text;
          if (c && c.children.length) {
            rich = true;
            rows[ri]['n' + i] = Array.prototype.map.call(c.childNodes, function (x) { return x.cloneNode(true); });
          }
        });
        var def = {
          field: 'c' + i,
          headerName: h ? h.textContent.trim() : '',
          flex: Math.min(5, Math.max(1, Math.ceil(maxLen / 22))),
          minWidth: o.minWidth || 110,
          sortable: o.sortable !== false && !!h,
          filter: !!o.filter,
          wrapText: !!o.wrap,
          autoHeight: !!o.wrap,
        };
        if (cellAlign === 'right' || cellAlign === 'end') {
          def.cellStyle = { textAlign: 'right' };
          def.headerClass = 'ag-right-aligned-header';
        }
        if (rich) {
          def.cellRenderer = function (p) {
            var wrap = el('span');
            var nodes = p.data && p.data['n' + i];
            // rows whose cell has no child elements keep their plain text (otherwise they render blank)
            if (!nodes) { wrap.textContent = p.value == null ? '' : String(p.value); return wrap; }
            nodes.forEach(function (x) { wrap.appendChild(x.cloneNode(true)); });
            return wrap;
          };
        }
        cols.push(def);
      })(i);
    }

    var host = el('div', 'lg-table');
    table.parentNode.insertBefore(host, table);
    var api = create(host, {
      columns: cols, rows: rows, autoHeight: true, print: o.print,
      rowHeight: o.rowHeight || (o.wrap ? 40 : 48),
      headerHeight: headRow ? 42 : 0,
      filter: !!o.filter, statusBar: false,
      grid: { suppressMovableColumns: true, suppressNoRowsOverlay: true },
    });
    if (!api) return null;
    table.__lgDone = true;
    table.style.display = 'none';
    table.setAttribute('aria-hidden', 'true');
    host.__lgSource = table;
    return api;
  }

  function upgradeTables(root) {
    root = root || document;
    var list = root.querySelectorAll('table[data-ag-table], [data-ag-tables] table');
    Array.prototype.forEach.call(list, function (t) {
      if (t.closest('.ag-root-wrapper, .lg-table') || t.getAttribute('data-ag-table') === 'off') return;
      var mode = t.getAttribute('data-ag-table');
      var inDocs = !!t.closest('[data-ag-tables]');
      fromTable(t, { print: mode === 'print', wrap: inDocs || mode === 'wrap' });
    });
  }

  function ready() { upgradeTables(document); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();

  global.LuvuGrid = {
    create: create, setRows: setRows, fromTable: fromTable, upgradeTables: upgradeTables,
    badge: badge, tone: tone, money: money, el: el,
    exportCsv: exportCsv, exportExcel: exportExcel,
    get enterprise() { boot(); return enterprise; },
  };
})(window);
