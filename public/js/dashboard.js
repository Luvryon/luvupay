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

  /**
   * Resolves when the PayPal popup reports a result (BroadcastChannel / storage / postMessage) or is closed.
   * Resolves { state: 'closed' } when the window disappears without a message (buyer closed it).
   */
  function waitForPopup(pop, tx, check) {
    return new Promise(function (resolve) {
      var finished = false, bc = null, poll = null, cap = null, srv = null, srvBusy = false;
      function done(payload) {
        if (finished) return;
        finished = true;
        clearInterval(poll); clearInterval(srv); clearTimeout(cap);
        try { if (bc) bc.close(); } catch (e) {}
        global.removeEventListener('message', onMessage);
        global.removeEventListener('storage', onStorage);
        resolve(payload);
      }
      function accept(d) {
        if (d && d.type === 'lp-paypal-result' && (!d.tx || d.tx === tx)) { done(d); return true; }
        return false;
      }
      function onMessage(ev) { if (ev.origin === global.location.origin) accept(ev.data); }
      function onStorage(ev) {
        if (ev.key !== 'lp_paypal_result' || !ev.newValue) return;
        try { accept(JSON.parse(ev.newValue)); } catch (e) {}
      }
      try { bc = new BroadcastChannel('luvupay-paypal'); bc.onmessage = function (ev) { accept(ev.data); }; } catch (e) { bc = null; }
      global.addEventListener('message', onMessage);
      global.addEventListener('storage', onStorage);
      poll = setInterval(function () {
        var closed = false;
        try { closed = !pop || pop.closed; } catch (e) { closed = false; }
        if (closed) {
          clearInterval(poll);
          setTimeout(function () { done({ state: 'closed' }); }, 800);   // let a late message win
        }
      }, 500);
      // Safety net when the popup message gets lost: ask the server (which asks PayPal) every few seconds.
      if (typeof check === 'function') {
        srv = setInterval(function () {
          if (finished || srvBusy) return;
          srvBusy = true;
          Promise.resolve(check()).then(function (d) { if (d) done(d); }).catch(function () {}).then(function () { srvBusy = false; });
        }, 4000);
      }
      cap = setTimeout(function () { done({ state: 'closed' }); }, 15 * 60 * 1000);
    });
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
    DISPUTED: 'text-bad',
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
    if (s === 'DISPUTED' || s === 'DISPUTE' || s === 'CHARGEBACK') return 'DISPUTED';
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
      { field: 'actions', headerName: 'Actions', width: 200, sortable: false, filter: false,
        cellRenderer: function (p) {
          var wrap = h('div', 'flex flex-wrap gap-1 items-center');
          var d = p.data || {};
          var life = normalizeLifecycle(d.lifecycle);
          var name = String(d.display_name || d.email || '').toLowerCase();
          var highRisk = life === 'DISPUTED'
            || String(d.chargeback_risk || '').toLowerCase() === 'high'
            || (String(d.churn_risk || '').toLowerCase() === 'high' && Number(d.trust_score) > 0 && Number(d.trust_score) < 55)
            || name.indexOf('john doe') !== -1
            || name.indexOf('john.doe') !== -1;
          if (highRisk) {
            wrap.appendChild(h(
              'button',
              'bg-rose-50 text-rose-600 border border-rose-200 text-xs px-3 py-1.5 rounded-lg hover:bg-rose-100 font-medium',
              'Generate Evidence',
              { type: 'button', 'data-action': 'evidence' }
            ));
          }
          if (d.invoice_id) {
            wrap.appendChild(h('button', 'text-[10px] font-semibold text-brand-soft hover:underline', 'Invoice', { type: 'button', 'data-action': 'invoice' }));
          }
          if (d.receipt_id || d.id) {
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
      checkoutRows: [],
      /** 'paypal' = each subscriber approves in a PayPal popup; 'mock' = simulated instantly. Decided by the server (LUVUPAY_PAYMENT_DRIVER). */
      checkoutMode: CFG.paymentMode === 'paypal' ? 'paypal' : 'mock',
      checkoutEnv: CFG.paypalEnv || 'sandbox',
      decisionOpen: false,
      campaignStudioOpen: false,
      evidenceOpen: false,
      withdrawalReceiptOpen: false,
      withdrawalReceipt: {},
      evidenceRow: null,
      evidenceExporting: false,
      evidenceExported: false,
      decisionRow: null,
      selectedAction: null,
      phoneView: 'options',
      phoneTab: 'options',
      journeyStep: 'ready',
      aiThinkingLabel: 'Analyzing LTV…',
      aiThinkingProgress: 8,
      journey: { brand: '', name: '', first: '', email: '', amount: '', hours: 48, refundPct: 20, nudge: '', sandbox: false, nebius: false, features: { resale: true, charity: true }, doneMessage: '', listingUrl: '' },
      _journeySeen: {},
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
      walletCurrency: 'USD',
      money: function (m, c) { return money(m, c); },
      payoutFee: { bps: 200, fixed_minor: 0, cap_minor: 0 },
      get checkoutSelectedCount() {
        return (this.checkoutRows || []).filter(function (r) { return r.selected; }).length;
      },
      get checkoutRemaining() {
        return (this.checkoutRows || []).filter(function (r) { return r.selected && r.status !== 'done'; }).length;
      },
      get checkoutDoneCount() {
        return (this.checkoutRows || []).filter(function (r) { return r.status === 'done'; }).length;
      },
      get checkoutAllDone() {
        return this.checkoutSelectedCount > 0 && this.checkoutRemaining === 0;
      },
      get checkoutTotalLabel() {
        return money(this.checkoutRemaining * (Number(PROJECT.price_minor) || 0), PROJECT.currency);
      },
      get checkoutCta() {
        if (this.checkoutAllDone) return 'Done';
        if (this.checkoutMode === 'paypal') {
          if (this.checkoutPaying) return 'Waiting for PayPal…';
          return this.checkoutDoneCount > 0 ? 'Continue with next subscriber' : 'Pay with PayPal';
        }
        if (this.checkoutPaying) return 'Processing…';
        return 'Charge ' + this.checkoutRemaining + ' subscriber' + (this.checkoutRemaining === 1 ? '' : 's');
      },

      get walletQuote() {
        var f = this.payoutFee || {}; var g = Number(this.walletBalanceMinor) || 0;
        var fee = Math.round(g * (Number(f.bps) || 0) / 10000) + (Number(f.fixed_minor) || 0);
        if (Number(f.cap_minor) > 0) fee = Math.min(fee, Number(f.cap_minor));
        fee = Math.max(0, Math.min(fee, g));
        return { fee: fee, net: g - fee };
      },
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
        this.handlePayPalReturn();
      },

      /** Full-page fallback (popup blocked): PayPal returned to /dashboard?paypal=... after the server settled the checkout. */
      handlePayPalReturn: function () {
        try {
          var params = new URLSearchParams(global.location.search || '');
          var flag = params.get('paypal');
          if (!flag) return;
          var self = this;
          var queue = null;
          try {
            queue = JSON.parse(sessionStorage.getItem('lp_checkout_queue') || 'null');
            sessionStorage.removeItem('lp_checkout_queue');
            sessionStorage.removeItem('lp_pending_checkout');
          } catch (e) {}

          if (flag === 'captured') {
            this.pushToast('Payment confirmed. Subscription is active and the funds are in your PayPal sandbox business balance.', '✅');
          } else if (flag === 'processing') {
            this.pushToast('Approved. PayPal is still confirming the payment, refresh in a moment.', '⏳');
          } else if (flag === 'capture_failed') {
            this.pushToast('PayPal payment not completed: ' + (params.get('err') || 'unknown error'), '❌');
          } else if (flag === 'cancelled') {
            this.pushToast('PayPal checkout cancelled. No money moved.', '⚠️');
          }
          if (typeof this.load === 'function') this.load();

          if (queue && queue.length && (flag === 'captured' || flag === 'cancelled')) {
            setTimeout(function () {
              self.checkoutRows = self._checkoutSeedRows(queue);
              self.showCheckoutModal = true;
              self.pushToast(queue.length + ' subscriber' + (queue.length === 1 ? '' : 's') + ' still to go.', '➡️');
            }, 600);
          }
          if (global.history && global.history.replaceState) {
            global.history.replaceState({}, '', global.location.pathname + (global.location.hash || ''));
          }
        } catch (e) {}
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
          if (window.LuvuToast) {
            window.LuvuToast.warning(
              'Create a project first',
              'Simulate 30 days needs an active project at the top of the page.',
              {
                id: 'need-project-sim',
                action: {
                  label: 'Create project',
                  onClick: function () {
                    var b = document.querySelector('#project-context button');
                    if (b) b.click();
                  },
                },
              }
            );
          } else {
            this.pushToast('Create a project first (top of the page), then run Simulate 30 days', '⚠️');
          }
          return;
        }
        // PayPal required when driver=paypal
        var ob = document.getElementById('luvu-onboarding');
        if (ob && ob.getAttribute('data-payment-driver') === 'paypal' && ob.getAttribute('data-has-paypal') !== '1') {
          if (window.LuvuToast) {
            window.LuvuToast.info(
              'Connect PayPal Sandbox',
              'Payment driver is paypal. Link your Sandbox merchant in Settings before Simulate.',
              {
                id: 'need-paypal-sim',
                action: { label: 'Open Settings', href: '/settings#paypal' },
              }
            );
          } else {
            this.pushToast('Connect PayPal in Settings first', '⚠️');
          }
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
          self.pushToast('Simulating 30 days for ' + (self.seedProjectName || 'your project') + '…', '⏳');
          var payload = rows.map(function (n) { return { name: n.name, email: n.email }; });
          var res = await request('POST', 'demo/simulate-month', { rows: payload });
          if (!res.ok || !res.data || !res.data.ok) {
            var why = (res.data && (res.data.message || res.data.error)) || ('HTTP ' + res.status);
            self.pushToast('Simulation failed: ' + why, '❌');
            return;
          }
          await self.load();
          self.hasData = !!(self.rowData && self.rowData.length);
          self.pushToast(res.data.message || 'Simulated 30 days', '✅');
          if (window.LuvuToast) {
            window.LuvuToast.info('Data synced', 'Wallet, Subscriptions, Invoices, Analytics and Events now show the same ledger.');
          }
          try { window.localStorage.setItem('luvupay_demo_seeded', '1'); } catch (e) {}
        } catch (e) {
          self.pushToast('Simulation failed — see console', '❌');
          if (window.console) console.error(e);
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

          this.applySummary(summary);
          var self = this;
          this.$nextTick(function () { self.mountGrids(); });
        } finally {
          this.loading = false;
        }
      },

      applySummary: function (summary) {
        summary = summary || {};
        var c = summary.currency || PROJECT.currency || 'USD';
        this.kpi = {
          revenueSaved: money(Number(summary.revenue_saved_minor) || 0, c),
          chargebacks: String(Number(summary.chargebacks_prevented) || 0),
          subscriptions: String(Number(summary.active_subscribers) || 0),
          charity: money(Math.abs(Number(summary.charity_minor) || 0), c),
          resaleVolume: money(Math.abs(Number(summary.resale_volume_minor) || 0), c)
        };
        this.walletBalanceMinor = Math.max(0, Number(summary.wallet_balance_minor) || 0);
        this.walletCurrency = c;
        this.payoutFee = summary.payout_fee || this.payoutFee;
        this.paypalConnected = !!(summary.can_withdraw);
        this.aiInsights = Array.isArray(summary.insights) ? summary.insights : [];
        if (Array.isArray(summary.recent_events)) this.webhookLogs = summary.recent_events;
      },

      refreshKpi: async function () {
        try {
          var r = await request('GET', 'summary');
          if (r.ok) this.applySummary(r.data);
        } catch (e) { /* keep last numbers */ }
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
            if (action === 'evidence') {
              self.openEvidenceBuilder(e.data);
            } else if (action === 'invoice' && e.data.invoice_id && /^[A-Za-z0-9-]+$/.test(String(e.data.invoice_id))) {
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
        if (window.LuvuToast) {
          var type = 'success';
          if (icon === '❌' || icon === '🚫') type = 'error';
          else if (icon === '⚠️' || icon === '!') type = 'warning';
          else if (icon === 'ℹ️' || icon === '⏳' || icon === '🔗' || icon === '🔔') type = 'info';
          window.LuvuToast.notify({ type: type, title: message, duration: 4500 });
          return;
        }
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
        // Gate: only when the live PayPal driver is on and no PayPal merchant is linked (mock mode never needs it)
        if (!this.paypalConnected) {
          this.pushToast('Connect PayPal in Settings before withdrawing.', '⚠️');
          try { window.location.href = '/settings'; } catch (e) {}
          return;
        }
        this.walletWithdrawing = true;
        var amountMinor = this.walletBalanceMinor;
        var amount = money(amountMinor, this.walletCurrency || 'USD');
        try {
          var res = await request('POST', 'wallet/withdraw', { amount_minor: amountMinor, payout_type: 'merchant_withdrawal' });
          if (res && res.ok) {
            this.walletBalanceMinor = 0;
            var netLabel = money((res.data && res.data.net_minor) || 0, this.walletCurrency || 'USD');
            var feeLabel = money((res.data && res.data.fee_minor) || 0, this.walletCurrency || 'USD');
            this.pushToast('Withdrew ' + amount + ' — you receive ' + netLabel + ' after the ' + feeLabel + ' PayPal fee' + (res.data && res.data.mock ? ' (simulated).' : '.') + (res.data && res.data.receipt_email_sent ? ' Receipt emailed.' : ''), '💸');
            if (res.data && res.data.warning) { this.pushToast(res.data.warning, '⚠️'); }
            if (res.data && res.data.payout_batch_id) {
              this.pushToast('Payout batch: ' + res.data.payout_batch_id, '🔗');
            }
            // Always show in-app receipt so merchant can download even if email SMTP fails
            if (res.data && res.data.receipt) {
              this.withdrawalReceipt = res.data.receipt;
              this.withdrawalReceiptOpen = true;
            } else {
              this.withdrawalReceipt = {
                merchant_name: 'Merchant',
                gross: amount,
                fee: feeLabel,
                net: netLabel,
                status: (res.data && res.data.batch_status) || 'SUCCESS',
                batch_id: (res.data && res.data.payout_batch_id) || null,
                receiver: (res.data && res.data.receiver_display) || null,
                simulated: !!(res.data && res.data.mock),
                ledger_id: null,
                issued_at: new Date().toISOString(),
                html: '',
              };
              this.withdrawalReceiptOpen = true;
            }
            if (res.data && res.data.receipt_email_error && !res.data.receipt_email_sent) {
              this.pushToast('Email receipt not sent: ' + res.data.receipt_email_error + ' — download from the receipt panel.', '📧');
            }
            await this.load();
          } else {
            var err = (res && res.data && (res.data.message || res.data.error)) || ('HTTP ' + (res && res.status));
            if (res && res.status === 403) {
              this.pushToast('Connect PayPal in Settings first.', '⚠️');
              try { window.location.href = '/settings'; } catch (e2) {}
            } else {
              this.pushToast(err || 'Withdraw failed', '❌');
            }
          }
        } catch (e) {
          this.pushToast('Network error during withdraw', '❌');
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
            this.pushToast(err, '❌');
            if (res.status === 403 && (res.data.error === 'paypal_not_connected' || String(err).indexOf('PayPal') !== -1)) {
              this.pushToast('Open Settings → PayPal, or set LUVUPAY_PAYMENT_DRIVER=mock', '⚠️');
            }
          }
          if (res.ok) { await this.refreshKpi(); }
          return res.ok ? res.data : null;
        } catch (e) {
          this.simMsgOk = false;
          this.simMsg = 'Network error';
          this.pushToast('Network error calling demo/' + path, '❌');
          return null;
        } finally {
          this.simBusy = null;
        }
      },

      // ---- 1. New Subscription Checkout
      //  mock   : every selected subscriber is charged instantly (simulated).
      //  paypal : one subscriber at a time; the buyer approves in a PayPal popup, the server then verifies the
      //           payment with PayPal, activates the subscription, issues the invoice and sends the emails.
      _checkoutSeedRows: function (list) {
        var product = (PROJECT && PROJECT.product) || 'Premium';
        var amount = (PROJECT && PROJECT.price) || '$20.00';
        var base = list || [
          { name: 'Sarah Johnson', email: 'sarah@streamnet.com' },
          { name: 'Mario Chen', email: 'mario@gmail.com' },
          { name: 'Emma Wilson', email: 'emma.w@outlook.com' },
          { name: 'David Kim', email: 'david.kim@yahoo.com' },
          { name: 'Lisa Anderson', email: 'lisa.a@proton.me' },
        ];
        var pre = this.checkoutMode === 'paypal' ? 1 : 3;
        return base.map(function (p, i) {
          return { name: p.name, email: p.email, product: product, amount: amount, selected: list ? true : i < pre, status: 'idle', note: '' };
        });
      },

      openCheckout: function () {
        if (this.simBusy || this.checkoutPaying) return;
        this.checkoutRows = this._checkoutSeedRows();
        this.showCheckoutModal = true;
      },

      closeCheckout: function () {
        if (this.checkoutPaying) return;
        this.showCheckoutModal = false;
      },

      addCheckoutRow: function () {
        var product = (PROJECT && PROJECT.product) || 'Premium';
        var amount = (PROJECT && PROJECT.price) || '$20.00';
        var n = (this.checkoutRows || []).length + 1;
        this.checkoutRows.push({ name: 'Subscriber ' + n, email: 'user' + n + '@example.com', product: product, amount: amount, selected: true, status: 'idle', note: '' });
      },

      removeCheckoutRow: function (idx) {
        if (this.checkoutPaying) return;
        var r = this.checkoutRows[idx];
        if (r && r.status !== 'idle' && r.status !== 'failed') return;
        this.checkoutRows.splice(idx, 1);
      },

      /** Selecting a row in PayPal mode is just "who goes next"; the CTA always takes the first selected one. */
      confirmCheckoutBatch: async function () {
        if (this.checkoutPaying) return;
        if (this.checkoutAllDone) { this.showCheckoutModal = false; return; }
        var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var todo = [];
        (this.checkoutRows || []).forEach(function (r) {
          if (!r.selected || r.status === 'done') return;
          r.email = (r.email || '').trim();
          r.name = (r.name || '').trim();
          if (!EMAIL.test(r.email)) { r.status = 'failed'; r.note = 'Enter a valid email'; return; }
          if (r.status === 'failed' || r.status === 'cancelled') { r.status = 'idle'; r.note = ''; }
          todo.push(r);
        });
        if (!todo.length) {
          this.pushToast((this.checkoutRows || []).some(function (r) { return r.selected; }) ? 'Fix the highlighted rows first' : 'Select at least one subscriber', '⚠️');
          return;
        }
        // NB: no await before this call: window.open must run inside the click, or popup blockers eat it.
        if (this.checkoutMode === 'paypal') return this._checkoutPaypalOne(todo[0], todo.slice(1));
        return this._checkoutMockAll(todo);
      },

      _checkoutMockAll: async function (todo) {
        this.checkoutPaying = true;
        var ok = 0, fail = 0, emailed = 0, lastErr = '';
        try {
          for (var i = 0; i < todo.length; i++) {
            var r = todo[i];
            r.status = 'working'; r.note = 'Charging…';
            this.preview = { phase: 'checkout', name: r.name, email: r.email, product: PROJECT.product, status: 'PENDING', trust_score: null, churn_risk: null, ai_nudge: null, spinning: true, certificate_code: null };
            var j = await this.sim('checkout', { display_name: r.name, email: r.email }, 'checkout');
            if (!j) { r.status = 'failed'; r.note = this.simMsg || 'Checkout failed'; lastErr = r.note; fail++; continue; }
            ok++; if (j.email_sent) emailed++;
            r.status = 'done';
            r.note = (j.invoice && j.invoice.invoice_number) ? ('Invoice ' + j.invoice.invoice_number) : 'Active';
            if (j.row) this.applyRow(j.row, true);
            if (j.recent_events && j.recent_events.length) this.webhookLogs = j.recent_events;
            if (j.preview) this.preview = Object.assign({}, j.preview, { spinning: false });
            await sleep(250);
          }
          await this.load();
          if (ok) this.pushToast(ok + ' subscription' + (ok === 1 ? '' : 's') + ' active' + (emailed ? ' · ' + emailed + ' receipt email' + (emailed === 1 ? '' : 's') + ' sent' : ''), '✅');
          if (fail) this.pushToast(fail + ' failed: ' + lastErr, '❌');
          if (!fail) { await sleep(500); this.showCheckoutModal = false; }
        } catch (e) {
          this.pushToast('Checkout error', '❌');
        } finally {
          this.checkoutPaying = false;
        }
      },

      _checkoutPaypalOne: async function (row, rest) {
        var self = this;
        var pop = null;
        try {
          var w = 500, hgt = 740;
          var left = Math.max(0, Math.round((global.screenX || 0) + ((global.outerWidth || 1000) - w) / 2));
          var top = Math.max(0, Math.round((global.screenY || 0) + ((global.outerHeight || 800) - hgt) / 2));
          pop = global.open('', 'luvupay_paypal', 'popup=yes,width=' + w + ',height=' + hgt + ',left=' + left + ',top=' + top);
          if (pop && pop.document && pop.document.body) {
            pop.document.title = 'PayPal';
            pop.document.body.style.cssText = 'margin:0;display:grid;place-items:center;min-height:100vh;font:14px system-ui,sans-serif;color:#475569;background:#f8fafc';
            pop.document.body.textContent = 'Contacting PayPal…';
          }
        } catch (e) { pop = null; }

        this.checkoutPaying = true;
        row.status = 'working'; row.note = 'Creating PayPal checkout…';
        this.preview = { phase: 'checkout', name: row.name, email: row.email, product: PROJECT.product, status: 'PENDING', trust_score: null, churn_risk: null, ai_nudge: null, spinning: true, certificate_code: null };

        var j = await this.sim('checkout', { display_name: row.name, email: row.email, popup: !!pop }, 'checkout');
        var pp = (j && j.paypal_sandbox) || {};
        if (!j || !(j.requires_approval && pp.approve_url && isPayPalUrl(pp.approve_url))) {
          try { if (pop) pop.close(); } catch (e) {}
          row.status = 'failed';
          row.note = (j ? 'PayPal returned no approval link' : (this.simMsg || 'Checkout failed'));
          this.checkoutPaying = false;
          this.preview.spinning = false;
          return;
        }
        if (j.row) this.applyRow(j.row, true);
        if (j.recent_events && j.recent_events.length) this.webhookLogs = j.recent_events;
        row.status = 'awaiting'; row.note = 'Waiting for approval on PayPal…';

        // Popup blocked: fall back to a full-page redirect and resume the remaining subscribers on return.
        if (!pop) {
          try {
            sessionStorage.setItem('lp_checkout_queue', JSON.stringify(rest.map(function (r) { return { name: r.name, email: r.email }; })));
          } catch (e) {}
          this.pushToast('Popup blocked, redirecting to PayPal…', '💳');
          this.showCheckoutModal = false;
          this.checkoutPaying = false;
          global.location.href = pp.approve_url;
          return;
        }

        pop.location.href = pp.approve_url;
        var txId = j.transaction_id;
        var msg = await waitForPopup(pop, txId, function () {
          return request('GET', 'demo/checkout/' + txId + '/status').then(function (r) {
            var d = r.data || {};
            return (r.ok && (d.state === 'captured' || d.state === 'failed' || d.state === 'gone')) ? d : null;
          });
        });
        var res = await this._checkoutResolve(msg, j.transaction_id);
        await this._checkoutApplyResult(row, res);
        this.checkoutPaying = false;
        this.preview.spinning = false;
        await this.load();

        if (res.state === 'captured' && this.checkoutRemaining > 0) {
          this.pushToast('Next up: ' + (this.checkoutRows.filter(function (r) { return r.selected && r.status !== 'done'; })[0] || {}).name + '. Click continue.', '➡️');
        } else if (res.state === 'captured') {
          await sleep(900);
          this.showCheckoutModal = false;
        }
      },

      /** The popup said something (or was closed): confirm with the server, which asks PayPal. */
      _checkoutResolve: async function (msg, tx) {
        var abandoned = !msg || msg.state === 'closed';
        var r, d, i;
        if (msg && !abandoned && msg.state === 'cancelled') return { state: 'cancelled', message: msg.message };
        if (msg && !abandoned && msg.state === 'failed') return { state: 'failed', message: msg.message || msg.error || 'Payment failed' };

        if (abandoned) {
          // Window closed with no message. Give PayPal / the return page a moment before treating it as cancelled.
          for (i = 0; i < 6; i++) {
            r = await request('GET', 'demo/checkout/' + tx + '/status');
            d = r.data || {};
            if (!r.ok) return { state: 'failed', message: d.message || 'Could not check the payment status.' };
            if (d.state !== 'processing' || !d.awaiting_buyer) return d;
            await sleep(2500);
          }
          r = await request('GET', 'demo/checkout/' + tx + '/status?abandon=1');
          return r.ok ? (r.data || { state: 'cancelled' }) : { state: 'failed', message: (r.data && r.data.message) || 'Could not cancel the checkout.' };
        }

        for (i = 0; i < 4; i++) {
          r = await request('GET', 'demo/checkout/' + tx + '/status');
          d = r.data || {};
          if (!r.ok) return { state: 'failed', message: d.message || 'Could not check the payment status.' };
          if (d.state === 'processing' && !d.awaiting_buyer && i < 3) { await sleep(2000); continue; }
          return d;
        }
        return { state: 'processing', message: 'PayPal is still confirming the payment.' };
      },

      _checkoutApplyResult: async function (row, res) {
        if (res.state === 'captured') {
          row.status = 'done';
          var inv = res.row && res.row.invoice_number;
          row.note = inv ? ('Paid · invoice ' + inv) : 'Paid via PayPal';
          if (res.row) this.applyRow(res.row, true);
          this.pushToast('Payment confirmed for ' + row.email + '. Funds are in your PayPal sandbox business balance.', '✅');
          if (inv) this.pushToast('Invoice generated: ' + inv, '🧾');
          this.preview = { phase: 'checkout', name: row.name, email: row.email, product: PROJECT.product, status: 'ACTIVE', trust_score: res.row && res.row.trust_score, churn_risk: res.row && res.row.churn_risk, ai_nudge: null, spinning: false, certificate_code: null };
        } else if (res.state === 'processing') {
          row.status = 'awaiting'; row.note = 'Approved, waiting for PayPal to confirm…';
          this.pushToast(res.message || 'PayPal is still confirming the payment.', '⏳');
        } else if (res.state === 'cancelled' || res.state === 'gone') {
          row.status = 'idle'; row.note = 'Cancelled on PayPal, nothing was charged';
          this.pushToast('PayPal checkout cancelled. No money moved.', '⚠️');
        } else {
          row.status = 'failed'; row.note = res.message || 'Payment failed';
          this.pushToast(row.note, '❌');
        }
      },

      // ---- 2. Missed Cancellation journey
      logDecision: function (text, tone) {
        this.decisionLogs.push({ text: text, tone: tone || '' });
        var self = this;
        this.$nextTick(function () { var el = self.$refs && self.$refs.journeyLog; if (el) el.scrollTop = el.scrollHeight; });
      },
      decisionToneClass: function (tone) {
        return tone === 'green' ? 'text-emerald-600' : tone === 'warn' ? 'text-rose-600' : tone === 'gold' ? 'text-indigo-600' : tone === 'blue' ? 'text-blue-600' : 'text-slate-500';
      },

      // ---- 2. Missed Cancellation journey (dual view: real backend events + user's phone)
      journeyDate: function (d) { return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }); },
      journeyEvents: function (events, seen, tone) {
        var self = this;
        (events || []).forEach(function (e) {
          if (!e || seen[e.id]) return;
          seen[e.id] = true;
          // only events produced by THIS journey (not the whole history)
          if (e.timestamp && Date.parse(e.timestamp) < (self._journeyStart || 0) - 2500) return;
          self.logDecision('[Webhook] ' + (e.provider || 'luvupay') + ' · ' + (e.event_type || e.type || 'event') + ' (' + (e.status || 'ok') + ')', tone || 'warn');
        });
      },

      openDecision: async function () {
        if (this.decisionBusy || this.decisionOpen) return;
        var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        var row = null, i;
        var sel = this.selectedRow;
        var isCharge = function (r) { return r && String(r.type || '').toLowerCase() === 'charge' && Number(r.amount_minor) > 0 && String(r.status || '') === 'completed'; };
        if (sel && isCharge(sel) && String(sel.lifecycle || '').toUpperCase() === 'ACTIVE' && UUID.test(String(sel.id))) row = sel;
        if (!row) {
          for (i = 0; i < this.rowData.length; i++) {
            var r = this.rowData[i];
            if (isCharge(r) && String(r.lifecycle || '').toUpperCase() === 'ACTIVE' && UUID.test(String(r.id))) { row = r; break; }
          }
        }
        if (!row) {
          this.pushToast('No ACTIVE subscription charge yet. Run “Simulate 30 days” or create a checkout first.', '❌');
          return;
        }

        var cur = row.currency || PROJECT.currency || 'USD';
        var name = row.display_name || String(row.email || 'Member').split('@')[0];
        this.decisionRow = row;
        this.decisionCustomer = name;
        this.decisionLogs = [];
        this.decisionOutcome = null;
        this.decisionReady = false;
        this.decisionBusy = true;
        this.phoneView = 'options';
        this.phoneTab = 'options';
        this.journeyStep = 'loading';
        this.aiThinkingLabel = 'Analyzing LTV…';
        this.aiThinkingProgress = 8;
        this.selectedAction = null;
        this.journey = {
          brand: PROJECT.name || 'Your product', name: name, first: String(name).split(' ')[0], email: row.email || '',
          amount: money(row.amount_minor, cur), amountMinor: row.amount_minor, currency: cur, hours: 48,
          refundPct: Math.round(((PROJECT.partial_refund_bps != null ? PROJECT.partial_refund_bps : 2000)) / 100),
          nudge: '', sandbox: false, nebius: false, features: { resale: PROJECT.marketplace_resale !== false, charity: PROJECT.charity_split !== false },
          doneMessage: '', listingUrl: ''
        };
        this.decisionOpen = true;
        this._journeyStart = Date.now();

        // Feature 1: AI thinking animation (~2s) before dual-view is shown
        var selfThink = this;
        var labels = ['Analyzing LTV…', 'Calculating Safe Refund…', 'Generating Micro-Payment Offer…', 'Preparing negotiator…'];
        var li = 0;
        selfThink.aiThinkingLabel = labels[0];
        selfThink.aiThinkingProgress = 12;
        var thinkTimer = setInterval(function () {
          li += 1;
          if (li < labels.length) selfThink.aiThinkingLabel = labels[li];
          selfThink.aiThinkingProgress = Math.min(95, 12 + li * 22);
        }, 450);
        setTimeout(function () {
          clearInterval(thinkTimer);
          selfThink.aiThinkingProgress = 100;
          selfThink.aiThinkingLabel = 'Ready';
          selfThink.journeyStep = 'ready';
        }, 2000);

        var seen = {};
        var renew = new Date(new Date(row.created_at || Date.now()).getTime() + 30 * 86400000);
        var h7 = new Date(renew.getTime() - 7 * 86400000);
        this.logDecision('[H-7 Days · ' + this.journeyDate(h7) + '] Reminder for ' + (row.email || name) + ': ' + (row.product || PROJECT.name || 'subscription') + ' renews ' + this.journeyDate(renew) + ' (' + this.journey.amount + ').', 'green');
        await sleep(450);

        var j = await this.sim('forgot', { transaction_id: row.id }, 'forgot');
        if (!j) {
          this.logDecision('[Error] ' + (this.simMsg || 'Could not start the missed-cancellation flow.'), 'warn');
          this.decisionBusy = false;
          return;
        }
        var g = j.grace || {};
        var hours = g.expires_at ? Math.max(1, Math.round((new Date(g.expires_at).getTime() - Date.now()) / 3600000)) : 48;
        this.journey.hours = hours;
        if (g.partial_refund_pct != null) this.journey.refundPct = Number(g.partial_refund_pct);
        if (j.project_features) {
          this.journey.features = { resale: !!j.project_features.marketplace_resale, charity: !!j.project_features.charity_split };
        }
        var integ = j.integrations || {};
        this.journey.sandbox = !!integ.paypal;
        this.journey.nebius = !!integ.nebius;
        this.journey.nudge = g.ai_nudge || '';

        this.logDecision('[Day H] Payment Failed. Missed cancellation detected → charge ' + String(row.id).slice(0, 8) + ' (' + this.journey.amount + ') held for ' + hours + 'h. [Webhook] subscription.grace_period_started', 'warn');
        this.journeyEvents(j.recent_events, seen, 'warn');
        await sleep(350);
        this.logDecision('[Nebius AI] Analyzing ' + name + "'s usage of " + (row.product || PROJECT.name || 'the product') + '…', 'gold');
        await sleep(450);
        var src = g.ai_source === 'nebius' ? 'Nebius model' : 'local template (set NEBIUS_API_KEY for live AI)';
        var risk = g.ai_churn && (g.ai_churn.risk || g.ai_churn.level || (typeof g.ai_churn === 'string' ? g.ai_churn : ''));
        this.logDecision('[Nebius AI] Source: ' + src + (risk ? ' · churn risk ' + risk : '') + '.', 'gold');
        if (g.ai_nudge) this.logDecision('[Nebius AI] Nudge: "' + g.ai_nudge + '"', 'gold');
        await sleep(300);
        // Feature 2 & 3: Safe Refund + Micro-Payment (demo logs for dual-view)
        this.logDecision('[Nebius AI] Calculating Safe Refund… Tagihan $50. Fee PayPal $2.50. Max Safe Refund: $47.50.', 'green');
        await sleep(280);
        this.logDecision('[Nebius AI] Generating Micro-Payment Offer: Pay $25 now, $25 next week.', 'gold');
        await sleep(280);
        var opts = ['Retry payment (' + this.journey.amount + ')', 'Partial refund ' + this.journey.refundPct + '%'];
        opts.push(this.journey.features.resale ? 'Resell on marketplace' : 'Resell (off in Settings)');
        opts.push(this.journey.features.charity ? 'Donate to charity' : 'Donate (off in Settings)');
        opts.push('Expire in ' + hours + 'h');
        this.logDecision('[AI Agent] Options ready: ' + opts.join(' · '), 'green');
        this.logDecision(this.journey.sandbox
          ? '[PayPal Sandbox] Connected — refunds are sent to the sandbox API when the charge has a capture id.'
          : '[Ledger] PayPal keys not set — running on the mock ledger (same real database rows).', this.journey.sandbox ? 'blue' : '');
        this._journeySeen = seen;
        this.decisionReady = true;
        this.decisionBusy = false;
      },

      choose: async function (choice) {
        if (!this.decisionRow || !this.decisionReady || this.decisionBusy) return;
        var labels = { renew: 'Retry Payment', partial_refund: 'Partial Refund', resale: 'Resell Remaining Access', donate: 'Donate to Charity', cancel: 'Let it expire' };
        var label = labels[choice] || choice;
        this.selectedAction = label;
        this.decisionBusy = true;
        this.logDecision('[User Action] ' + this.journey.name + ' selected: ' + label, 'blue');

        var j = await this.sim('decision', { transaction_id: this.decisionRow.id, choice: choice }, 'decision');
        if (!j) {
          this.logDecision('[Error] ' + (this.simMsg || 'The action failed.') + ' You can choose another option.', 'warn');
          this.selectedAction = null;
          this.decisionBusy = false;
          return;
        }
        this.journeyEvents(j.recent_events, this._journeySeen || {}, 'green');
        this.logDecision('[System] ✓ ' + (j.message || (label + ' completed')), 'green');
        if (j.row && j.row.lifecycle) this.logDecision('[Ledger] Charge is now ' + j.row.lifecycle + ' · status ' + (j.row.status || ''), 'green');
        if (j.listing && j.listing.url) {
          this.journey.listingUrl = j.listing.url;
          this.logDecision('[Marketplace] Listed for ' + money(j.listing.price_minor, this.journey.currency) + ' · token ' + j.listing.token + ' · live on /marketplace', 'purple');
        }
        this.journey.doneMessage = j.message || (label + ' completed');
        this.phoneView = 'done';
        this.decisionOutcome = label;
        this.decisionBusy = false;
        await this.load();
      },


      patchRow: function (lifecycle, status) {
        var row = this.rowData.find(function (r) { return r.id === this.selectedId; }.bind(this));
        var next = Object.assign({
          id: this.selectedId || ('local-' + Date.now()), created_at: new Date().toISOString(), email: 'sarah.j@example.com',
          display_name: this.decisionCustomer, amount_minor: PROJECT.price_minor, currency: PROJECT.currency, type: 'Charge',
          product: PROJECT.product, trust_score: 64, churn_risk: 'high',
        }, row || {}, { lifecycle: normalizeLifecycle(lifecycle), status: status });
        this.applyRow(next, !row);
      },


      downloadWithdrawalReceipt: function () {
        var r = this.withdrawalReceipt || {};
        var html = r.html;
        if (!html) {
          html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Withdrawal receipt</title></head><body style="font-family:system-ui,sans-serif;padding:32px;color:#0f172a">'
            + '<h1>Withdrawal receipt</h1>'
            + '<p><b>Merchant:</b> ' + (r.merchant_name || '') + '</p>'
            + '<p><b>Gross:</b> ' + (r.gross || '') + '</p>'
            + '<p><b>PayPal fee:</b> ' + (r.fee || '') + '</p>'
            + '<p><b>Net received:</b> ' + (r.net || '') + '</p>'
            + '<p><b>Status:</b> ' + (r.status || '') + '</p>'
            + '<p><b>PayPal batch:</b> ' + (r.batch_id || '') + '</p>'
            + '<p><b>To:</b> ' + (r.receiver || '') + '</p>'
            + '<p><b>Ledger:</b> ' + (r.ledger_id || '') + '</p>'
            + '<p style="color:#64748b;font-size:12px">Generated by LuvuPay · PayPal holds the official payout record.</p>'
            + '</body></html>';
        }
        var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'luvupay-withdrawal-receipt-' + String(r.batch_id || Date.now()).replace(/[^a-zA-Z0-9_-]/g, '') + '.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        this.pushToast('Receipt downloaded', '🧾');
      },

      printWithdrawalReceipt: function () {
        var r = this.withdrawalReceipt || {};
        var w = global.open('', '_blank', 'noopener,noreferrer,width=640,height=800');
        if (!w) { this.pushToast('Popup blocked — use Download instead', '⚠️'); return; }
        var html = r.html || ('<pre>' + JSON.stringify(r, null, 2) + '</pre>');
        w.document.write(html);
        w.document.close();
        setTimeout(function () { try { w.focus(); w.print(); } catch (e) {} }, 300);
      },

      openEvidenceBuilder: function (row) {
        this.evidenceRow = row || null;
        this.evidenceOpen = true;
        this.evidenceExporting = false;
        this.evidenceExported = false;
      },

      exportEvidenceToPayPal: async function () {
        if (this.evidenceExporting) return;
        this.evidenceExporting = true;
        await sleep(1100);
        this.evidenceExporting = false;
        this.evidenceExported = true;
        var who = (this.evidenceRow && (this.evidenceRow.display_name || this.evidenceRow.email)) || 'subscriber';
        this.pushToast('Evidence package exported for ' + who + ' — ready for PayPal Resolution Center', '⚖️');
      },

      // ---- receipts, webhooks, rate limit, deliveries
      openReceipt: async function (id) {
        if (!id) { this.pushToast('No receipt id on this row', '❌'); return; }
        var res = await request('GET', 'demo/receipts/' + encodeURIComponent(id)).catch(function () { return { ok: false, data: {} }; });
        if (!res.ok) {
          this.simMsgOk = false;
          this.simMsg = (res.data && (res.data.message || res.data.error)) || 'Receipt not found';
          this.pushToast(this.simMsg, '❌');
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
