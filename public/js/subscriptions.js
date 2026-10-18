(function () {
  'use strict';

  /** Canonical demo rows — encode platform rules for judges / merchants. */
  var DEMO_ROWS = [
    {
      id: 'demo-david',
      name: 'David Kim',
      email: 'david.kim@yahoo.com',
      project: 'StreamNetFly',
      plan: '$20/mo',
      status: 'Active',
      statusKey: 'active',
      action: '—',
      ltv: '$240',
    },
    {
      id: 'demo-sarah',
      name: 'Sarah J.',
      email: 'sarah@streamnet.com',
      project: 'StreamNetFly',
      plan: '$20/mo',
      status: 'Grace Period (48h)',
      statusKey: 'grace',
      action: 'AI Offer: 20% Refund if cancelled within 48h',
      ltv: '$200',
    },
    {
      id: 'demo-mario',
      name: 'Mario C.',
      email: 'mario@gmail.com',
      project: 'StreamNetFly',
      plan: '$20/mo',
      status: 'Pending Resale',
      statusKey: 'resale',
      action: 'Merchant enabled Resale. User listed 15 days on Marketplace.',
      ltv: '$180',
    },
    {
      id: 'demo-emma',
      name: 'Emma W.',
      email: 'emma.w@outlook.com',
      project: 'StreamNetFly',
      plan: '$20/mo',
      status: 'AI-Retained ❤️',
      statusKey: 'retained',
      action: 'Merchant enabled Charity. User donated $10 to Red Cross.',
      ltv: '$210',
    },
    {
      id: 'demo-lisa',
      name: 'Lisa A.',
      email: 'lisa.a@proton.me',
      project: 'StreamNetFly',
      plan: '$20/mo',
      status: 'Churned',
      statusKey: 'churned',
      action: '48h expired. No resale/donate enabled. Subscription ended.',
      ltv: '$200',
    },
  ];

  function money(minor, currency) {
    var c = currency || 'USD';
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: c }).format((minor || 0) / 100);
    } catch (e) {
      return ((minor || 0) / 100).toFixed(2) + ' ' + c;
    }
  }

  function statusKeyFromLife(life) {
    var s = String(life || '').toUpperCase();
    if (s === 'ACTIVE' || s === 'TRIALING') return 'active';
    if (s.indexOf('GRACE') !== -1 || s === 'AT RISK' || s === 'PAST_DUE') return 'grace';
    if (s.indexOf('RESALE') !== -1) return 'resale';
    if (s.indexOf('RETAIN') !== -1 || s.indexOf('AI') !== -1) return 'retained';
    if (s.indexOf('CHURN') !== -1 || s === 'CANCELLED' || s === 'EXPIRED') return 'churned';
    return 'active';
  }

  window.subscriptionsApp = function () {
    return {
      loading: false,
      filter: 'all',
      rows: DEMO_ROWS.slice(),
      filters: [
        { id: 'all', label: 'All' },
        { id: 'active', label: 'Active' },
        { id: 'grace', label: 'Grace / At Risk' },
        { id: 'resale', label: 'Pending Resale' },
        { id: 'retained', label: 'AI-Retained' },
        { id: 'churned', label: 'Churned' },
      ],
      get filtered() {
        var f = this.filter;
        if (f === 'all') return this.rows;
        return this.rows.filter(function (r) {
          return r.statusKey === f;
        });
      },
      badgeClass: function (key) {
        var map = {
          active: 'bg-emerald-100 text-emerald-700',
          grace: 'bg-amber-100 text-amber-700',
          resale: 'bg-purple-100 text-purple-700',
          retained: 'bg-pink-100 text-pink-700',
          churned: 'bg-slate-100 text-slate-500',
        };
        return map[key] || 'bg-slate-100 text-slate-500';
      },
      init: function () {
        this.load();
      },
      load: async function () {
        this.loading = true;
        try {
          var res = await fetch('/api/v1/dashboard/transactions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var json = res.ok ? await res.json() : { data: [] };
          var data = Array.isArray(json.data) ? json.data : [];
          if (!data.length) {
            this.rows = DEMO_ROWS.slice();
            return;
          }
          var byEmail = {};
          data.forEach(function (r) {
            var email = r.customer_email || r.email || '';
            var name = r.customer_name || r.name || email || 'Subscriber';
            var meta = r.metadata;
            if (typeof meta === 'string') {
              try { meta = JSON.parse(meta); } catch (e) { meta = {}; }
            }
            meta = meta || {};
            var life = (meta.lifecycle || r.lifecycle || r.status || 'ACTIVE').toString();
            var key = email || name + (r.id || '');
            var amount = Number(r.amount_minor != null ? r.amount_minor : 0);
            if (!byEmail[key]) {
              byEmail[key] = {
                id: key,
                name: name,
                email: email,
                project: meta.product || r.product || (window.PROJECT && PROJECT.name) || '—',
                plan: meta.plan || r.plan || '$20/mo',
                status: life,
                statusKey: statusKeyFromLife(life),
                action: meta.ai_nudge || meta.decision || '—',
                ltv_minor: 0,
                currency: r.currency || 'USD',
              };
            }
            byEmail[key].ltv_minor += Math.abs(amount);
            byEmail[key].status = life;
            byEmail[key].statusKey = statusKeyFromLife(life);
          });
          var live = Object.keys(byEmail).map(function (k) {
            var row = byEmail[k];
            row.ltv = money(row.ltv_minor, row.currency);
            return row;
          });
          // Prefer live rows; keep demo rows if live is sparse so the story stays visible
          this.rows = live.length >= 3 ? live : DEMO_ROWS.concat(live);
        } catch (e) {
          this.rows = DEMO_ROWS.slice();
        } finally {
          this.loading = false;
        }
      },
    };
  };
})();
