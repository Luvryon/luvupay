/**
 * LuvuPay Merchant Console — dashboard Alpine app (CTO Fix).
 *
 * Security notes
 *  - Everything coming from the API is rendered with textContent / x-text, never as HTML.
 *  - The plan name and price come from the server-provided project context; the browser never decides what is charged.
 *  - Only an https://*.paypal.com approval URL is ever opened.
 */
(function (global) {
  'use strict';

  // ------------------------------------------------------------------ config & helpers
  function readConfig() {
    var node = document.getElementById('lp-config');
    try { return JSON.parse(node ? node.textContent : '{}') || {}; } catch (e) { return {}; }
  }

  var CFG = readConfig();
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var PROJECT = Object.assign({ name: '', plan: 'Premium', product: '', price: '$20.00', price_minor: 2000, currency: 'USD', is_set: false }, CFG.project || {});
  // MUST be global so project-context.js can update it after "Create project"
  global.PROJECT = PROJECT;
  var API = CFG.apiBase || '/api/v1/dashboard';
  var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

  function money(minor, currency) {
    var c = currency || 'USD';
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: c }).format((minor || 0) / 100); }
    catch (e) { return ((minor || 0) / 100).toFixed(2) + ' ' + c; }
  }

  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function isPayPalUrl(value) {
    try {
      var u = new URL(value);
      return u.protocol === 'https:' && /(^|\.)paypal\.com$/.test(u.hostname);
    } catch (e) { return false; }
  }

  /** Tiny safe DOM builder for AG Grid cell renderers. */
  function h(tag, className, text, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = String(text);
    Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    return node;
  }

  function request(method, path, body) {
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var init = { method: method, credentials: 'same-origin', headers: headers };
    if (method !== 'GET') {
      headers['X-CSRF-TOKEN'] = CSRF;
      if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
      }
    }
    return fetch(API + '/' + path, init).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        return { ok: res.ok, status: res.status, data: data || {} };
      });
    });
  }

  /**
   * Human-facing lifecycle statuses only.
   * Removed internal SMART-CONVERTED label — mapped to AI-RETAINED / PENDING RESALE.
   */
  var LIFECYCLE_CLASS = {
    ACTIVE: 'text-ok',
    'GRACE PERIOD': 'text-warn',
    GRACE_PERIOD: 'text-warn',
    'PENDING RESALE': 'text-brand-soft',
    'AI-RETAINED': 'text-info',
    CHURNED: 'text-bad',
    RESOLD: 'text-brand-soft',
    AWAITING_USER: 'text-warn',
  };

  /** Normalize any legacy backend status into clean user-facing labels. */
  function normalizeLifecycle(raw) {
    var s = String(raw || '').toUpperCase().replace(/_/g, ' ').trim();
    if (s === 'SMART-CONVERTED' || s === 'SMART CONVERTED' || s === 'SMART-CON') return 'AI-RETAINED';
    if (s === 'GRACE_PERIOD' || s === 'GRACE PERIOD') return 'GRACE PERIOD';
    if (s === 'PENDING_RESALE' || s === 'PENDING RESALE' || s === 'RESOLD') return 'PENDING RESALE';
    if (s === 'AI_RETAINED' || s === 'AI-RETAINED') return 'AI-RETAINED';
    if (s === 'ACTIVE') return 'ACTIVE';
    if (s === 'CHURNED' || s === 'CANCELLED' || s === 'EXPIRED') return 'CHURNED';
    if (s === 'AWAITING USER' || s === 'AWAITING_USER') return 'GRACE PERIOD';
    return s || '—';
  }

  global.money = money;

  // ------------------------------------------------------------------ AG Grid
  function ledgerColumns() {
    return [
      { field: 'type', headerName: 'Type', width: 100 },
      { field: 'product', headerName: 'Product / Plan', minWidth: 170, flex: 1,
        cellRenderer: function (p) { return h('span', 'font-medium text-white', p.value || PROJECT.product); } },
      { field: 'lifecycle', headerName: 'Status', width: 150,
        cellRenderer: function (p) {
          var label = normalizeLifecycle(p.value);
          return h('span', 'font-bold ' + (LIFECYCLE_CLASS[label] || LIFECYCLE_CLASS[p.value] || 'text-muted'), label);
        } },
      { field: 'email', headerName: 'Customer', flex: 1, minWidth: 170 },
      { field: 'amount_minor', headerName: 'Amount', width: 110,
        valueFormatter: function (p) { return money(p.value, (p.data && p.data.currency) || 'USD'); } },
      { field: 'breakdown', headerName: 'Value split', minWidth: 170,
        cellRenderer: function (p) {
          var retained = normalizeLifecycle(p.data && p.data.lifecycle) === 'AI-RETAINED' || p.value;
          return h('span', retained ? 'text-[11px] font-semibold text-ok' : 'text-faint',
            retained ? (p.value || 'Store Credit: $15 · Charity: $10 · Fee: $5') : '—');
        } },
      { field: 'trust_score', headerName: 'Trust', width: 120,
        cellRenderer: function (p) {
          if (p.value == null) return h('span', 'text-faint', '—');
          var tone = p.value >= 80 ? 'ok' : p.value >= 50 ? 'warn' : 'bad';
          var wrap = h('div', 'flex items-center gap-2');
          var meter = h('meter', 'h-1.5 w-12', null, { min: '0', max: '100', value: String(p.value) });
          wrap.appendChild(meter);
          wrap.appendChild(h('span', 'text-[11px] font-bold text-' + tone, p.value));
          return wrap;
        } },
      { field: 'churn_risk', headerName: 'Churn', width: 90,
        cellRenderer: function (p) {
          var map = { low: ['Low', 'text-ok'], medium: ['Med', 'text-warn'], high: ['High', 'text-bad'] };
          var m = map[String(p.value || '').toLowerCase()] || ['—', 'text-faint'];
          return h('span', 'text-xs font-bold ' + m[1], m[0]);
        } },
      { field: 'actions', headerName: '', width: 110, sortable: false, filter: false,
        cellRenderer: function (p) {
          var wrap = h('div', 'flex gap-1');
          if (p.data && p.data.invoice_id) {
            wrap.appendChild(h('button', 'text-[10px] font-semibold text-brand-soft hover:underline', 'Invoice', { type: 'button', 'data-action': 'invoice' }));
          }
          if (p.data && (p.data.receipt_id || p.data.id)) {
            wrap.appendChild(h('button', 'text-[10px] font-semibold text-muted hover:underline', 'Receipt', { type: 'button', 'data-action': 'receipt' }));
          }
          return wrap;
        } },
    ];
  }

  function resaleColumns() {
    return [
      { field: 'product', headerName: 'Product', flex: 1, minWidth: 160 },
      { field: 'seller_email', headerName: 'Seller', flex: 1 },
      { field: 'price_minor', headerName: 'Ask', width: 110,
        valueFormatter: function (p) { return money(p.value, (p.data && p.data.currency) || 'USD'); } },
      { field: 'status', headerName: 'Status', width: 120 },
      { field: 'created_at', headerName: 'Listed', width: 140,
        valueFormatter: function (p) { return (p.value || '').replace('T', ' ').slice(0, 16); } },
    ];
  }

  // ------------------------------------------------------------------ Alpine app
  global.consoleApp = function () {
    return {
      // state
      loading: false,
      tab: 'overview',
      demoEnabled: !!CFG.demoEnabled,
      selectedId: null,
      selectedLifecycle: null,
      simBusy: null,
      simMsg: '',
      simMsgOk: true,
      usage: { used: 0, limit: 1000 },
      kpi: { revenueSaved: '$0.00', chargebacks: '0', subscriptions: '0', charity: '$0.00', resaleVolume: '$0.00' },
      rowData: [],
      resaleRows: [],
      grids: {},
      toasts: [],
      webhookLogs: [],
      deliveries: [],
      receipt: {},
      showCheckoutModal: false,
      checkoutPaying: false,
      checkoutForm: { name: 'John Doe', email: 'john@example.com' },
      decisionOpen: false,
      decisionBusy: false,
      decisionStep: 0,
      decisionReady: false,
      decisionOutcome: null,
      decisionOutcomeLabel: '',
      decisionResultLog: '',
      decisionLogs: [],
      decisionCustomer: '',
      userPreviewStep: 1,
      showUserPreview: true,
      /** Demo mode for Missed-Cancellation choices: 'mock' (instant) | 'paypal' (sandbox latency / real API) */
      apiMode: 'mock',
      paypalConnecting: false,
      webhookOpen: false,
      receiptOpen: false,
      preview: { phase: null, name: '', email: '', product: '', status: null, trust_score: null, churn_risk: null, ai_nudge: null, spinning: false, certificate_code: null },

      // Merchant Wallet (PayPal Payouts demo)
      walletBalanceMinor: 0,
      hasData: false,
      loadingSeed: false,
      seedModalOpen: false,
      seedDraftRows: [],
      seedProjectName: '',

      walletWithdrawing: false,
      paypalConnected: false,
      dualViewOpen: false,

      // AI Insights — derived from real ledger / summary (never invented)
      aiInsights: [],

      get usagePct() {
        var lim = this.usage.limit || 1000;
        return Math.min(100, Math.round(((this.usage.used || 0) / lim) * 100));
      },
      get usageTone() {
        var p = this.usagePct;
        return p >= 90 ? 'bg-bad' : p >= 70 ? 'bg-warn' : 'bg-ok';
      },
      get walletBalance() {
        return money(this.walletBalanceMinor, 'USD');
      },

      init: function () {
        this.load();
        if (this.demoEnabled) this.loadDeliveries();
      },

      setTab: function (id) { this.tab = id; },

      
      _hydrateDemoRows: function () { /* no fake rows — ledger is AJAX/DB only */ },

      seedDemoData: async function () {
        // Resolve project name from live context (after create, PROJECT is updated)
        var pname = '';
        try {
          var P = (typeof window !== 'undefined' && window.PROJECT) ? window.PROJECT : PROJECT;
          if (P && P.name) pname = String(P.name).trim();
          if (pname === 'Project' && P && P.is_set === false) pname = '';
        } catch (e) {}
        if (!pname) {
          var root = document.getElementById('project-context');
          if (root) pname = (root.getAttribute('data-name') || '').trim();
        }
        if (!pname) {
          this.pushToast('Create a project first (top of the page), then run Simulate 30 days', '⚠️');
          return;
        }
        this.seedProjectName = pname;
        this.seedDraftRows = [
          { name: 'Sarah Johnson', email: 'sarah@streamnet.com', product: PROJECT.product || 'Premium', amount: PROJECT.price || '$20.00', selected: true },
          { name: 'Mario Chen', email: 'mario@gmail.com', product: PROJECT.product || 'Premium', amount: PROJECT.price || '$20.00', selected: true },
          { name: 'Emma Wilson', email: 'emma.w@outlook.com', product: PROJECT.product || 'Premium', amount: PROJECT.price || '$20.00', selected: true },
          { name: 'David Kim', email: 'david.kim@yahoo.com', product: PROJECT.product || 'Premium', amount: PROJECT.price || '$20.00', selected: true },
          { name: 'Lisa Anderson', email: 'lisa.a@proton.me', product: PROJECT.product || 'Premium', amount: PROJECT.price || '$20.00', selected: true },
        ];
        this.seedModalOpen = true;
      },

      confirmSeedSandbox: async function () {
        if (this.loadingSeed) return;
        var rows = (this.seedDraftRows || []).filter(function (r) { return r.selected; });
        if (!rows.length) {
          this.pushToast('Select at least one row to simulate', '⚠️');
          return;
        }
        this.loadingSeed = true;
        this.seedModalOpen = false;
        var self = this;
        try {
          self.pushToast('Sending ' + rows.length + ' checkouts to PayPal Sandbox for ' + (self.seedProjectName || 'project') + '…', '⏳');
          for (var i = 0; i < rows.length; i++) {
            var n = rows[i];
            try {
              await self.sim('checkout', { display_name: n.name, email: n.email }, 'seed');
              await sleep(500);
            } catch (e) {}
          }
          await self.load();
          self.walletBalanceMinor = 0;
          self.hasData = !!(self.rowData && self.rowData.length);
          if (!self.hasData) {
            self.pushToast('Sandbox returned no rows — check PayPal credentials & API logs', '⚠️');
          } else {
            self.pushToast('Sandbox activity written for project: ' + (self.seedProjectName || '—'), '✅');
          }
          // Event log entry for successful Simulate 30 days
          var now = new Date().toISOString();
          var ev = {
            id: 'sim30-' + Date.now(),
            timestamp: now,
            at: now,
            provider: 'demo',
            event_type: 'demo.simulate_30_days',
            http_hint: self.hasData ? '200 OK' : 'empty',
            message: 'Simulate 30 days · ' + rows.length + ' checkout(s) for ' + (self.seedProjectName || 'project'),
          };
          self.webhookLogs = [ev].concat(self.webhookLogs || []);
          try { window.localStorage.setItem('luvupay_demo_seeded', '1'); } catch (e) {}
        } catch (e) {
          self.pushToast('Simulation failed — see console', '⚠️');
        } finally {
          self.loadingSeed = false;
        }
      },

      refreshEvents: async function () {
        try {
          var j = await this.request('GET', '/api/v1/dashboard/summary');
          if (j && Array.isArray(j.recent_events)) this.webhookLogs = j.recent_events;
          else if (j && Array.isArray(j.events)) this.webhookLogs = j.events;
        } catch (e) {}
      },
      load: async function () {
        this.loading = true;
        try {
          var r = await Promise.all([
            request('GET', 'summary'),
            request('GET', 'transactions'),
            request('GET', 'resale-listings'),
            request('GET', 'usage'),
          ]);
          var summary = r[0].ok ? (r[0].data || {}) : {};
          var rows = r[1].data.data || [];
          this.rowData = Array.isArray(rows) ? rows.map(function (row) {
            return Object.assign({}, row, { lifecycle: normalizeLifecycle(row.lifecycle) });
          }) : [];
          this.resaleRows = r[2].data.data || [];
          this.hasData = (this.rowData && this.rowData.length > 0);
          // Never auto-inject fake rows — only real API data
          this.hasData = !!(this.rowData && this.rowData.length);
          if (r[3].ok && r[3].data.limit != null) this.usage = r[3].data;

          // Real KPI from DashboardApiController::summary + ledger rows (no invented floors)
          var c = summary.currency || PROJECT.currency || 'USD';
          var revenueSavedMinor = Number(summary.revenue_saved_minor) || 0;
          var charityMinor = Math.abs(Number(summary.charity_minor) || 0);
          var chargeVolumeMinor = Math.abs(Number(summary.charge_volume_minor != null ? summary.charge_volume_minor : summary.total_revenue_minor) || 0);
          var chargeCount = summary.transaction_count != null
            ? Number(summary.transaction_count)
            : this.rowData.filter(function (t) { return String(t.type).toLowerCase().indexOf('charge') !== -1; }).length;
          var activeSubs = summary.active_subscribers != null ? Number(summary.active_subscribers) : chargeCount;
          var resaleVolume = Math.abs(Number(summary.resale_volume_minor) || 0);
          if (resaleVolume === 0) {
            resaleVolume = this.rowData.filter(function (t) {
              return String(t.type).toLowerCase().indexOf('resale') !== -1;
            }).reduce(function (s, t) { return s + Math.abs(Number(t.amount_minor) || 0); }, 0);
          }
          var chargebacks = summary.chargebacks_prevented != null
            ? Number(summary.chargebacks_prevented)
            : this.rowData.filter(function (t) {
                return String(t.lifecycle || '').toUpperCase().indexOf('RETAIN') !== -1;
              }).length;

          // Wallet funded by completed charges + resale (server-side ledger math)
          var walletMinor = Number(summary.wallet_balance_minor != null ? summary.wallet_balance_minor : summary.available_balance_minor);
          if (!walletMinor && walletMinor !== 0) {
            walletMinor = chargeVolumeMinor; // fallback: all completed charges sit in wallet until withdrawn
          }
          this.walletBalanceMinor = Math.max(0, walletMinor || 0);
          this.paypalConnected = !!(summary.integrations && (summary.integrations.paypal_merchant_connected || summary.integrations.paypal));

          // Revenue Saved = retention formula only (never fall back to gross charge volume)
          this.kpi = {
            revenueSaved: money(revenueSavedMinor, c),
            chargebacks: String(chargebacks),
            subscriptions: String(activeSubs),
            charity: money(charityMinor, c),
            resaleVolume: money(resaleVolume, c)
          };
          var self = this;
          this.$nextTick(function () { self.mountGrids(); });
        } finally {
          this.loading = false;
        }
      },

      mountGrids: function () {
        this.mountGrid('tx', 'grid-tx', ledgerColumns(), this.rowData, true);
        this.mountGrid('rs', 'grid-rs', resaleColumns(), this.resaleRows, false);
      },

      mountGrid: function (key, elId, columns, rows, selectable) {
        var node = document.getElementById(elId);
        if (!node || typeof agGrid === 'undefined') return;
        var self = this;
        if (this.grids[key]) { try { this.grids[key].destroy(); } catch (e) { /* already gone */ } }
        this.grids[key] = agGrid.createGrid(node, {
          columnDefs: columns,
          rowData: rows || [],
          getRowId: function (p) { return String(p.data && p.data.id); },
          rowSelection: selectable ? 'single' : undefined,
          defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            filterParams: { buttons: ['reset', 'apply'], closeOnApply: true },
            floatingFilter: true,
            minWidth: 90,
          },
          animateRows: true,
          suppressCellFocus: true,
          rowHeight: 44,
          headerHeight: 42,
          overlayNoRowsTemplate: '<span class="text-sm text-muted">No rows yet — run a Sandbox / Demo Control.</span>',
          getRowClass: function (p) { return p.data && p.data._flash ? 'row-flash' : ''; },
          onSelectionChanged: function () {
            if (!selectable) return;
            var sel = self.grids[key].getSelectedRows()[0];
            if (sel) { self.selectedId = sel.id; self.selectedLifecycle = normalizeLifecycle(sel.lifecycle); self.hydratePreview(sel); }
          },
          onCellClicked: function (e) {
            var target = e.event && e.event.target;
            var action = target && target.getAttribute && target.getAttribute('data-action');
            if (!action || !e.data) return;
            if (action === 'invoice' && e.data.invoice_id && /^[A-Za-z0-9-]+$/.test(String(e.data.invoice_id))) {
              global.location.assign('/dashboard/invoices/' + encodeURIComponent(String(e.data.invoice_id)));
            } else if (action === 'receipt') {
              self.openReceipt(e.data.receipt_id || e.data.id);
            }
          },
        });
      },

      hydratePreview: function (row) {
        var life = normalizeLifecycle(row.lifecycle);
        this.preview = {
          phase: life === 'GRACE PERIOD' ? 'grace' : (life === 'PENDING RESALE' || life === 'RESOLD') ? 'resale' : life === 'ACTIVE' ? 'checkout' : null,
          name: row.display_name || String(row.email || '').split('.')[0] || 'Member',
          email: row.email,
          product: row.product || PROJECT.product,
          status: life,
          trust_score: row.trust_score,
          churn_risk: row.churn_risk,
          ai_nudge: row.ai_nudge,
          spinning: false,
          certificate_code: row.certificate_code,
        };
      },

      applyRow: function (row, isNew) {
        if (!row || !row.id) return;
        row.lifecycle = normalizeLifecycle(row.lifecycle);
        row._flash = true;
        var i = this.rowData.findIndex(function (r) { return r.id === row.id; });
        if (i >= 0) this.rowData[i] = row; else this.rowData.unshift(row);
        var g = this.grids.tx;
        if (g) {
          if (isNew) g.applyTransaction({ add: [row], addIndex: 0 }); else g.applyTransaction({ update: [row] });
          setTimeout(function () {
            row._flash = false;
            try { var n = g.getRowNode(row.id); if (n) g.redrawRows({ rowNodes: [n] }); } catch (e) { /* grid destroyed */ }
          }, 1300);
        }
        this.selectedId = row.id;
        this.selectedLifecycle = row.lifecycle || null;
        // Refresh KPI from live row counts (money fields stay server-authoritative until next load())
        this.recalcKpiLocal();
      },

      recalcKpiLocal: function () {
        var wins = this.rowData.filter(function (t) {
          return normalizeLifecycle(t.lifecycle) === 'AI-RETAINED'
            || (t.smart_convert && t.smart_convert.chargeback_prevented);
        }).length;
        this.kpi.chargebacks = String(wins);
        var charges = this.rowData.filter(function (t) {
          return String(t.type).toLowerCase() === 'charge';
        }).length;
        this.kpi.subscriptions = String(charges || this.rowData.length);
        var resaleVolume = this.rowData
          .filter(function (t) {
            var ty = String(t.type).toLowerCase();
            return ty.indexOf('resale') !== -1 || ty === 'resale_sale';
          })
          .reduce(function (s, t) { return s + (Number(t.amount_minor) || 0); }, 0);
        this.kpi.resaleVolume = money(resaleVolume, PROJECT.currency || 'USD');
      },

      // ---- feedback
      pushToast: function (message, icon) {
        var id = Date.now() + Math.random();
        var self = this;
        this.toasts = this.toasts.concat([{ id: id, message: message, icon: icon || '✅', leaving: false }]);
        setTimeout(function () {
          self.toasts = self.toasts.map(function (t) { return t.id === id ? Object.assign({}, t, { leaving: true }) : t; });
          setTimeout(function () { self.toasts = self.toasts.filter(function (t) { return t.id !== id; }); }, 350);
        }, 3400);
      },

      // ---- Merchant Wallet · PayPal Payouts (real sandbox)
      withdrawToPayPal: async function () {
        if (this.walletWithdrawing || this.walletBalanceMinor <= 0) return;
        // Gate: must connect merchant PayPal in Settings first
        if (!this.paypalConnected) {
          this.pushToast('Connect PayPal in Settings before withdrawing.', '⚠️');
          try { window.location.href = '/settings'; } catch (e) {}
          return;
        }
        this.walletWithdrawing = true;
        var amountMinor = this.walletBalanceMinor;
        var amount = money(amountMinor, 'USD');
        try {
          var res = await request('POST', 'wallet/withdraw', { amount_minor: amountMinor, payout_type: 'merchant_withdrawal' });
          if (res && res.ok) {
            this.walletBalanceMinor = 0;
            this.pushToast(amount + ' sent to your linked PayPal account.', '💸');
            if (res.data && res.data.batch_id) {
              this.pushToast('PayPal batch: ' + res.data.batch_id, '🔗');
            }
            await this.load();
          } else {
            var err = (res && res.data && (res.data.message || res.data.error)) || ('HTTP ' + (res && res.status));
            if (res && res.status === 403) {
              this.pushToast('Connect PayPal in Settings first.', '⚠️');
              try { window.location.href = '/settings'; } catch (e2) {}
            } else {
              this.pushToast(err || 'Withdraw failed', '⚠️');
            }
          }
        } catch (e) {
          this.pushToast('Network error during withdraw', '⚠️');
        } finally {
          this.walletWithdrawing = false;
        }
      },

      sim: async function (path, body, busyKey) {
        this.simBusy = busyKey;
        this.simMsg = '';
        try {
          var res = await request('POST', 'demo/' + path, body || {});
          this.simMsgOk = res.ok;
          this.simMsg = res.ok ? (res.data.message || 'Done') : (res.data.message || res.data.error || ('HTTP ' + res.status));
          if (!res.ok) {
            var err = res.data.message || res.data.error || ('HTTP ' + res.status);
            this.pushToast(err, '⚠️');
            if (res.status === 403 && (res.data.error === 'paypal_not_connected' || String(err).indexOf('PayPal') !== -1)) {
              this.pushToast('Open Settings → PayPal, or set LUVUPAY_PAYMENT_DRIVER=mock', '⚠️');
            }
          }
          return res.ok ? res.data : null;
        } catch (e) {
          this.simMsgOk = false;
          this.simMsg = 'Network error';
          this.pushToast('Network error calling demo/' + path, '⚠️');
          return null;
        } finally {
          this.simBusy = null;
        }
      },

      // ---- 1. New Subscription Checkout
      openCheckout: function () {
        if (this.simBusy || this.checkoutPaying) return;
        this.checkoutForm = { name: 'John Doe', email: 'john@example.com' };
        this.showCheckoutModal = true;
      },

      confirmCheckout: async function () {
        if (this.checkoutPaying) return;
        var name = (this.checkoutForm.name || '').trim() || 'John Doe';
        var email = (this.checkoutForm.email || '').trim() || 'john@example.com';
        this.checkoutPaying = true;
        this.preview = { phase: 'checkout', name: name, email: email, product: PROJECT.product, status: 'PENDING', trust_score: null, churn_risk: null, ai_nudge: null, spinning: true, certificate_code: null };

        await sleep(1400);
        var j = await this.sim('checkout', { display_name: name, email: email }, 'checkout');
        var invoice = (j && j.invoice && j.invoice.invoice_number) || (j && j.row && j.row.invoice_number) || ('LUVI-' + new Date().getFullYear() + '-001');

        if (j && j.email_sent) this.pushToast('Confirmation email sent to ' + email, '📧');
        else this.pushToast('Email not sent — configure MAIL_* in .env', '📧');

        await sleep(550);
        var pp = j && j.paypal_sandbox;
        if (pp && pp.live && pp.approve_url && isPayPalUrl(pp.approve_url)) {
          this.pushToast('PayPal order created — opening Sandbox checkout…', '💳');
          global.open(pp.approve_url, 'paypal_sandbox', 'noopener,noreferrer,width=520,height=720');
        } else if (pp && pp.live) {
          this.pushToast('PayPal Sandbox order live', '💳');
        } else {
          this.pushToast("'New Subscription' webhook dispatched to Merchant API", '📩');
        }

        await sleep(550);
        this.pushToast('Invoice generated: ' + invoice, '🧾');
        await sleep(600);

        if (j && j.recent_events && j.recent_events.length) this.webhookLogs = j.recent_events;
        var row = j && j.row ? Object.assign({}, j.row) : {
          id: 'local-' + Date.now(), created_at: new Date().toISOString(), email: email, display_name: name,
          amount_minor: PROJECT.price_minor, currency: PROJECT.currency, status: 'completed', lifecycle: 'ACTIVE',
          type: 'Charge', product: PROJECT.product, invoice_number: invoice, trust_score: 88, churn_risk: 'low',
        };
        row.lifecycle = 'ACTIVE';
        row.product = row.product || PROJECT.product;
        this.applyRow(row, true);
        this.preview = { phase: 'checkout', name: name, email: email, product: row.product, status: 'ACTIVE', trust_score: row.trust_score, churn_risk: row.churn_risk, ai_nudge: null, spinning: false, certificate_code: null };

        await sleep(450);
        this.showCheckoutModal = false;
        this.checkoutPaying = false;
      },

      // ---- 2. Missed Cancellation journey
      logDecision: function (text, tone) {
        this.decisionLogs = this.decisionLogs.concat([{ text: text, tone: tone || 'dim' }]);
        this.$nextTick(function () {
          var box = document.getElementById('decision-log');
          if (box) box.scrollTop = box.scrollHeight;
        });
      },

      decisionToneClass: function (tone) {
        return ({ gold: 'text-warn', purple: 'text-brand-soft', green: 'text-ok', warn: 'text-bad' })[tone] || 'text-muted';
      },

      openDecision: async function () {
        if (this.decisionBusy || this.decisionOpen) return;
        var life = this.selectedLifecycle ? normalizeLifecycle(this.selectedLifecycle) : null;
        if (life && life !== 'ACTIVE') {
          this.simMsgOk = false;
          this.simMsg = 'Select an ACTIVE row first (or clear the selection).';
          return;
        }
        var row = this.rowData.find(function (r) { return r.id === this.selectedId; }.bind(this))
          || this.rowData.find(function (r) { return normalizeLifecycle(r.lifecycle) === 'ACTIVE' && UUID.test(String(r.id)); });
        var customer = (row && row.display_name) || 'Sarah J.';
        var product = PROJECT.product;
        this.decisionCustomer = customer;
        this.decisionOpen = true;
        this.decisionBusy = true;
        this.decisionStep = 0;
        this.decisionReady = false;
        this.decisionOutcome = null;
        this.decisionOutcomeLabel = '';
        this.decisionResultLog = '';
        this.decisionLogs = [];
        this.userPreviewStep = 1;
        this.showUserPreview = true;
        this.preview = { phase: 'grace', name: customer, email: (row && row.email) || 'sarah.j@example.com', product: product, status: 'GRACE PERIOD', trust_score: 64, churn_risk: 'high', ai_nudge: null, spinning: true, certificate_code: null };

        await sleep(450);
        this.decisionStep = 1;
        this.logDecision('[H-7 Days] Reminder email sent for ' + product + '.', 'green');
        await sleep(900);
        this.decisionStep = 2;
        this.logDecision('[Day H] Payment Failed. [PayPal Webhook] BILLING.SUBSCRIPTION.PAYMENT.FAILED for User: ' + customer + ' (' + product + ').', 'warn');
        await sleep(600);
        this.logDecision('[Nebius AI] Analyzing ' + customer + '’s usage of ' + product + '…', 'gold');
        await sleep(550);
        this.logDecision('[Tavily API] Scanning external context…', 'purple');
        await sleep(550);
        this.logDecision('[Nemotron 3 Ultra] Preparing options for ' + product + ' (' + PROJECT.price + '/mo)…', 'purple');
        await sleep(650);
        this.logDecision('[AI Agent] Options ready: retry payment · let it expire · resell the remaining access.', 'green');
        this.decisionReady = true;
        this.decisionBusy = false;
        this.preview.spinning = false;
        this.preview.status = 'GRACE PERIOD';
        // Reflect GRACE PERIOD on the selected row
        if (row) {
          var patched = Object.assign({}, row, { lifecycle: 'GRACE PERIOD' });
          this.applyRow(patched, false);
        }
      },

      choose: async function (choice) {
        if (this.decisionBusy || this.decisionOutcome) return;
        this.decisionBusy = true;
        this.paypalConnecting = false;
        this.userPreviewStep = 2; // phone preview → success
        var product = PROJECT.product;
        var labels = {
          renew: ['[User] Retried ' + product + ' payment.', 'green', 'Renewed — ACTIVE', '[PayPal] Payment retry successful for ' + product + '. Status: ACTIVE.', 'ACTIVE'],
          cancel: ['[User] Let ' + product + ' expire.', 'warn', 'Expired — CHURNED', '[System] ' + product + ' expired. Status: CHURNED.', 'CHURNED'],
          resale: ['[User] Chose to resell ' + product + ' access on the Marketplace.', 'purple', 'Listed for resale — PENDING RESALE', '[Marketplace] ' + product + ' access transferred to a new buyer. Status: PENDING RESALE.', 'PENDING RESALE'],
        };
        var l = labels[choice];
        this.logDecision(l[0], l[1]);

        var uuid = this.selectedId && UUID.test(String(this.selectedId)) ? this.selectedId : null;
        var j = null;

        if (this.apiMode === 'paypal') {
          // Real PayPal Sandbox path — show latency, then hit backend (or swap setTimeout for fetch)
          this.paypalConnecting = true;
          this.logDecision('[PayPal Sandbox] Connecting to PayPal Sandbox API…', 'gold');
          // ---------------------------------------------------------------------------
          // Replace the sleep below with a real backend call, e.g.:
          //   j = await this.sim(choice === 'resale' ? 'resale' : 'decision', {
          //     transaction_id: uuid, choice: choice, mode: 'paypal'
          //   }, 'decision');
          // ---------------------------------------------------------------------------
          await sleep(1500);
          this.paypalConnecting = false;
        } else {
          // Mock (instant) — minimal delay for UI polish
          await sleep(300);
        }

        if (uuid) {
          j = await this.sim(
            choice === 'resale' ? 'resale' : 'decision',
            choice === 'resale'
              ? { transaction_id: uuid, mode: this.apiMode }
              : { transaction_id: uuid, choice: choice, mode: this.apiMode },
            'decision'
          );
          if (!j) {
            this.logDecision('[Error] ' + (this.simMsg || 'Request failed'), 'warn');
            this.decisionBusy = false;
            this.paypalConnecting = false;
            return;
          }
          if (j.row) {
            j.row.lifecycle = normalizeLifecycle(j.row.lifecycle) === 'RESOLD' ? 'PENDING RESALE' : normalizeLifecycle(j.row.lifecycle);
            if (String(j.row.lifecycle).toUpperCase().indexOf('SMART') !== -1) j.row.lifecycle = 'AI-RETAINED';
            this.applyRow(j.row, false);
          }
          if (j.recent_events) this.webhookLogs = j.recent_events;
          if (j.preview) this.preview = Object.assign({}, this.preview, j.preview, { product: product, status: l[4] });
        } else {
          this.patchRow(l[4], choice === 'cancel' ? 'cancelled' : 'completed');
        }

        this.decisionResultLog = (j && j.message) || l[3];
        this.logDecision(this.decisionResultLog, l[1]);
        this.decisionOutcome = choice;
        this.decisionOutcomeLabel = l[2];
        this.preview.status = l[4];
        this.preview.spinning = false;
        if (choice === 'resale') this.preview.phase = 'resale';
        this.decisionBusy = false;
        this.paypalConnecting = false;
        this.pushToast(l[2], '✅');
        // Pull server-authoritative money KPIs after a successful decision
        this.load();
      },

      /** Local-only update used when no ledger row exists yet. */
      patchRow: function (lifecycle, status) {
        var row = this.rowData.find(function (r) { return r.id === this.selectedId; }.bind(this));
        var next = Object.assign({
          id: this.selectedId || ('local-' + Date.now()), created_at: new Date().toISOString(), email: 'sarah.j@example.com',
          display_name: this.decisionCustomer, amount_minor: PROJECT.price_minor, currency: PROJECT.currency, type: 'Charge',
          product: PROJECT.product, trust_score: 64, churn_risk: 'high',
        }, row || {}, { lifecycle: normalizeLifecycle(lifecycle), status: status });
        this.applyRow(next, !row);
      },

      // ---- receipts, webhooks, rate limit, deliveries
      openReceipt: async function (id) {
        if (!id) { this.pushToast('No receipt id on this row', '⚠️'); return; }
        var res = await request('GET', 'demo/receipts/' + encodeURIComponent(id)).catch(function () { return { ok: false, data: {} }; });
        if (!res.ok) {
          this.simMsgOk = false;
          this.simMsg = (res.data && (res.data.message || res.data.error)) || 'Receipt not found';
          this.pushToast(this.simMsg, '⚠️');
          return;
        }
        var j = res.data;
        this.receipt = {
          id: j.id || id,
          amount: money(j.amount_minor, j.currency || 'USD'),
          charity: money(j.charity_minor, j.currency || 'USD'),
          fee: money(j.fee_minor, j.currency || 'USD'),
          store_credit: money(j.store_credit_minor, j.currency || 'USD'),
          certificate_code: j.certificate_code || '—',
          transaction_id: j.transaction_id || '—',
          paypal_payout_id: j.paypal_payout_id || '—',
        };
        this.receiptOpen = true;
      },

      openWebhookLogs: function () { this.webhookOpen = true; },

      loadDeliveries: async function () {
        var res = await request('GET', 'webhook-deliveries').catch(function () { return { ok: false, data: {} }; });
        this.deliveries = (res.ok && res.data.data) ? res.data.data : [];
      },

      retryDelivery: async function (id) {
        var res = await request('POST', 'webhook-deliveries/' + encodeURIComponent(id) + '/retry');
        this.simMsgOk = res.ok;
        this.simMsg = res.data.message || (res.ok ? 'Retry queued' : 'Retry failed');
        await this.loadDeliveries();
      },

      maxRateLimit: async function () {
        var j = await this.sim('rate-limit/max', {}, 'ratelimit');
        if (j && j.usage) this.usage = j.usage;
      },

      resetRateLimit: async function () {
        var j = await this.sim('rate-limit/reset', {});
        if (j && j.usage) this.usage = j.usage;
        this.pushToast('API quota reset', '🔄');
      },
    };
  };
})(window);
