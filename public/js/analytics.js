(function () {
  'use strict';

  function money(minor, currency) {
    var c = currency || 'USD';
    var n = Number(minor);
    if (!isFinite(n)) n = 0;
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: c }).format(n / 100);
    } catch (e) {
      return (n / 100).toFixed(2) + ' ' + c;
    }
  }

  function zeroKpi() {
    return {
      totalRevenueSaved: '$0.00',
      churnRate: '0%',
      marketplaceVolume: '$0.00',
      charityImpact: '$0.00',
      revenueSaved: '$0.00',
      chargebacks: '0',
      subscriptions: '0',
      charity: '$0.00',
      resaleVolume: '$0.00',
    };
  }

  window.analyticsApp = function () {
    return {
      loading: false,
      range: '30d',
      rangeLabel: 'Last 30 Days',
      rangeOpen: false,
      rows: [],
      kpi: zeroKpi(),
      ranges: [
        { id: '7d', label: 'Last 7 Days' },
        { id: '30d', label: 'Last 30 Days' },
        { id: '90d', label: 'Last 90 Days' },
      ],
      init: function () {
        this.load();
      },
      pickRange: function (r) {
        this.range = r.id;
        this.rangeLabel = r.label;
        this.rangeOpen = false;
        this.load();
      },
      fmtDate: function (iso) {
        if (!iso) return '—';
        return String(iso).replace('T', ' ').slice(0, 16);
      },
      fmtMoney: function (minor, currency) {
        return money(minor, currency);
      },
      statusClass: function (s) {
        s = String(s || '').toLowerCase();
        if (s === 'completed' || s === 'success' || s === 'active') return 'ok';
        if (s === 'pending' || s === 'processing' || s === 'past_due') return 'warn';
        if (s === 'failed' || s === 'cancelled' || s === 'churned') return 'bad';
        return '';
      },
      lifeClass: function (s) {
        s = String(s || '').toUpperCase();
        if (s === 'ACTIVE' || s === 'AI-RETAINED') return 'ok';
        if (s === 'GRACE PERIOD' || s === 'PENDING RESALE' || s === 'AT RISK') return 'warn';
        if (s === 'CHURNED' || s === 'CANCELLED') return 'bad';
        return 'brand';
      },
      load: async function () {
        this.loading = true;
        try {
          var sRes = await fetch('/api/v1/dashboard/summary', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var tRes = await fetch('/api/v1/dashboard/transactions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var summary = sRes.ok ? await sRes.json() : {};
          var txJson = tRes.ok ? await tRes.json() : { data: [] };
          var data = Array.isArray(txJson.data) ? txJson.data : [];
          this.rows = data.map(function (r, i) {
            var meta = r.metadata;
            if (typeof meta === 'string') {
              try { meta = JSON.parse(meta); } catch (e) { meta = {}; }
            }
            meta = meta || {};
            return {
              id: r.id || ('row-' + i),
              created_at: r.created_at || '',
              customer_name: r.customer_name || r.name || '',
              email: r.customer_email || r.email || '',
              type: r.type || r.transaction_type || 'charge',
              lifecycle: meta.lifecycle || r.lifecycle || '',
              amount_minor: r.amount_minor != null ? r.amount_minor : 0,
              currency: r.currency || summary.currency || 'USD',
              status: r.status || 'completed',
            };
          });

          var c = summary.currency || 'USD';
          this.kpi = {
            totalRevenueSaved: money(summary.total_revenue_saved_minor ?? summary.revenue_saved_minor ?? 0, c),
            churnRate: (summary.churn_rate != null ? Number(summary.churn_rate) : 0) + '%',
            marketplaceVolume: money(summary.marketplace_volume_minor ?? summary.resale_volume_minor ?? 0, c),
            charityImpact: money(summary.charity_impact_minor ?? summary.charity_minor ?? 0, c),
            revenueSaved: money(summary.revenue_saved_minor ?? 0, c),
            chargebacks: String(summary.chargebacks_prevented ?? 0),
            subscriptions: String(summary.active_subscribers ?? summary.transaction_count ?? 0),
            charity: money(summary.charity_minor ?? 0, c),
            resaleVolume: money(summary.resale_volume_minor ?? 0, c),
          };
        } catch (e) {
          this.kpi = zeroKpi();
          this.rows = [];
        } finally {
          this.loading = false;
        }
      },
      downloadCsv: async function () {
        try {
          var res = await fetch('/api/v1/dashboard/transactions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var json = await res.json();
          var data = (json && json.data) ? json.data : [];
          var rows = [['date', 'email', 'amount_minor', 'currency', 'status', 'lifecycle', 'type']];
          data.forEach(function (r) {
            var meta = r.metadata;
            if (typeof meta === 'string') {
              try { meta = JSON.parse(meta); } catch (e) { meta = {}; }
            }
            meta = meta || {};
            rows.push([
              (r.created_at || '').slice(0, 10),
              r.customer_email || r.email || '',
              r.amount_minor != null ? r.amount_minor : '',
              r.currency || 'USD',
              r.status || '',
              meta.lifecycle || r.lifecycle || '',
              r.type || '',
            ]);
          });
          var csv = rows.map(function (r) { return r.join(','); }).join('\n');
          var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url;
          a.download = 'luvupay-transactions-' + this.range + '.csv';
          document.body.appendChild(a);
          a.click();
          a.remove();
          URL.revokeObjectURL(url);
        } catch (e) { /* ignore */ }
      },
    };
  };
})();
