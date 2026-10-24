(function () {
  'use strict';
  var C = { rev: '#6366f1', saved: '#10b981', charity: '#10b981', resale: '#f59e0b' };

  window.analyticsApp = function () {
    return {
      days: 30, ranges: [7, 30, 90], loading: false, loaded: false, error: '',
      d: { project: {}, series: [], recent: [], insights: [], subscribers: {}, outcomes: {}, saved_breakdown: {} },

      get hasData() { return this.loaded && (Number(this.d.transaction_count) > 0 || (this.d.recent || []).length > 0); },
      init: function () { this.load(); },
      pick: function (r) { this.days = r; this.load(); },
      money: function (minor, cur) {
        cur = cur || this.d.currency || 'USD';
        var n = (Math.abs(Number(minor) || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return cur === 'USD' ? '$' + n : n + ' ' + cur;
      },
      load: async function () {
        this.loading = true; this.error = '';
        try {
          var res = await fetch('/api/v1/dashboard/analytics?days=' + this.days, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          if (!res.ok) throw new Error('Analytics request failed (HTTP ' + res.status + ')');
          this.d = await res.json();
          this.loaded = true;
        } catch (e) { this.error = String(e.message || e); this.loaded = true; }
        finally { this.loading = false; }
      },

      get kpis() {
        var d = this.d, m = this.money.bind(this);
        return [
          { label: 'Revenue', value: m(d.charge_volume_minor), hint: (d.transaction_count || 0) + ' charges · last ' + this.days + 'd', color: '#0f172a' },
          { label: 'Revenue saved', value: m(d.revenue_saved_minor), hint: (d.chargebacks_prevented || 0) + ' chargebacks prevented', color: '#047857' },
          { label: 'Charity donated', value: m(d.charity_minor), hint: 'Second Chance donations', color: '#047857' },
          { label: 'Resale volume', value: m(d.resale_volume_minor), hint: 'Your fees: ' + m(d.resale_fees_minor), color: '#b45309' },
          { label: 'Partial refunds', value: m(d.refunded_minor), hint: 'Given to keep subscribers', color: '#0f172a' },
          { label: 'Active subscribers', value: String(d.active_subscribers || 0), hint: ((d.subscribers || {}).at_risk || 0) + ' in grace', color: '#0f172a' },
          { label: 'Churn rate', value: (d.churn_rate || 0) + '%', hint: (d.churned_count || 0) + ' churned', color: (d.churn_rate || 0) >= 25 ? '#b4232f' : '#0f172a' },
          { label: 'Wallet balance', value: m(d.wallet_balance_minor), hint: 'Withdrawable now (all-time)', color: '#4338ca' },
        ];
      },
      get outcomes() {
        var o = this.d.outcomes || {}, total = 0, k;
        for (k in o) total += Number(o[k]) || 0;
        var defs = [['active', 'Active, no issue', '#94a3b8'], ['renewed', 'User renewed', '#6366f1'], ['grace', 'In grace period', '#f59e0b'],
          ['partial_refund', 'Partial refund accepted', '#0ea5e9'], ['charity', 'Donated to charity', '#10b981'],
          ['resale', 'Resold on marketplace', '#8b5cf6'], ['resale_pending', 'Listed for resale', '#a78bfa'],
          ['smart_convert', 'Smart-converted', '#14b8a6'], ['churned', 'Churned', '#ef4444']];
        return defs.map(function (x) {
          var c = Number(o[x[0]]) || 0;
          return { label: x[1], count: c, color: x[2], pct: total ? Math.round((c / total) * 100) : 0 };
        }).filter(function (x) { return x.count > 0; });
      },
      get savedParts() {
        var b = this.d.saved_breakdown || {}, m = this.money.bind(this);
        return [
          { label: 'Partial refund — cash kept', value: m(b.partial_refund_minor), color: '#0ea5e9' },
          { label: 'Charity — chargeback fees avoided', value: m(b.charity_minor), color: '#10b981' },
          { label: 'Marketplace resale fees', value: m(b.resale_fees_minor), color: '#8b5cf6' },
          { label: 'Smart-convert retained', value: m(b.smart_convert_minor), color: '#14b8a6' },
        ];
      },
      get subs() {
        var s = this.d.subscribers || {};
        return [{ label: 'Active', value: s.active || 0 }, { label: 'In grace period', value: s.at_risk || 0 },
          { label: 'Resold to a buyer', value: s.resold || 0 }, { label: 'Churned', value: s.churned || 0 }, { label: 'Total', value: s.total || 0 }];
      },
      get projectRows() {
        var p = this.d.project || {}, m = this.money.bind(this);
        var on = function (v) { return v ? 'On' : 'Off'; };
        return [
          { label: 'Plan', value: (p.plan || '—') + ' · ' + m(p.price_minor || 0, p.currency) + '/' + (p.interval || 'month') },
          { label: 'Environment', value: p.environment || 'sandbox' },
          { label: 'Grace period', value: on(p.grace_period) },
          { label: 'Marketplace resale', value: on(p.marketplace_resale) },
          { label: 'Charity split', value: on(p.charity_split) },
          { label: 'Partial refund offer', value: Math.round((Number(p.partial_refund_bps) || 0) / 100) + '%' },
        ];
      },

      svg: function (series, keys, kinds) {
        var W = 760, H = 220, L = 46, R = 10, T = 10, B = 28, n = series.length;
        if (!n) return '';
        var max = 1, i, k;
        for (i = 0; i < n; i++) for (k = 0; k < keys.length; k++) max = Math.max(max, Number(series[i][keys[k]]) || 0);
        var iw = W - L - R, ih = H - T - B, step = iw / n, bw = Math.max(2, Math.min(18, step * 0.6));
        var x = function (idx) { return L + step * idx + step / 2; };
        var y = function (v) { return T + ih - (v / max) * ih; };
        var out = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="100%" style="min-width:520px;display:block" role="img">';
        for (i = 0; i <= 4; i++) {
          var gy = T + (ih / 4) * i, val = max - (max / 4) * i;
          out += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + gy + '" y2="' + gy + '" stroke="#e2e8f0"/>' +
            '<text x="' + (L - 6) + '" y="' + (gy + 3) + '" text-anchor="end" font-size="10" fill="#94a3b8">' + this.short(val) + '</text>';
        }
        var every = Math.ceil(n / 8);
        for (i = 0; i < n; i += every) {
          out += '<text x="' + x(i) + '" y="' + (H - 8) + '" text-anchor="middle" font-size="10" fill="#94a3b8">' + series[i].date.slice(5) + '</text>';
        }
        for (k = 0; k < keys.length; k++) {
          var key = keys[k], color = C[key] || C.rev;
          if (kinds[k] === 'bar') {
            var off = kinds.filter(function (q) { return q === 'bar'; }).length > 1 ? (k === 0 ? -bw / 2 : bw / 2) : 0;
            for (i = 0; i < n; i++) {
              var v = Number(series[i][key]) || 0;
              if (v > 0) out += '<rect x="' + (x(i) - bw / 2 + off / 2) + '" y="' + y(v) + '" width="' + (bw / (off ? 2 : 1)) + '" height="' + (T + ih - y(v)) + '" rx="2" fill="' + color + '" opacity=".85"><title>' + series[i].date + ': ' + this.money(v) + '</title></rect>';
            }
          } else {
            var pts = [];
            for (i = 0; i < n; i++) pts.push(x(i) + ',' + y(Number(series[i][key]) || 0));
            out += '<polyline fill="none" stroke="' + color + '" stroke-width="2.5" stroke-linejoin="round" points="' + pts.join(' ') + '"/>';
            for (i = 0; i < n; i++) if ((Number(series[i][key]) || 0) > 0) out += '<circle cx="' + x(i) + '" cy="' + y(Number(series[i][key])) + '" r="3" fill="' + color + '"><title>' + series[i].date + ': ' + this.money(series[i][key]) + '</title></circle>';
          }
        }
        return out + '</svg>';
      },
      short: function (minor) { var v = minor / 100; return v >= 1000 ? (v / 1000).toFixed(1) + 'k' : String(Math.round(v)); },
      chartMain: function () { return this.svg(this.d.series || [], ['revenue', 'saved'], ['bar', 'line']); },
      chartSecondary: function () { return this.svg(this.d.series || [], ['charity', 'resale'], ['bar', 'bar']); },

      downloadCsv: function () {
        var rows = ['date,revenue_minor,saved_minor,charity_minor,resale_minor,refunded_minor'];
        (this.d.series || []).forEach(function (r) { rows.push([r.date, r.revenue, r.saved, r.charity, r.resale, r.refunded].join(',')); });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'luvupay-analytics-' + this.days + 'd.csv';
        a.click();
      },
    };
  };
})();
