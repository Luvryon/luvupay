/**
 * LuvuPay public marketplace (Alpine). Talks to /api/marketplace/*.
 * Security: no third-party credentials are ever collected; all API text is rendered via x-text; only an
 * https://*.paypal.com approval URL is opened; every write carries the CSRF token.
 */
(function (global) {
  'use strict';

  var APPS = {
    StreamFlix: { emoji: '🍿', tone: 'bg-rose-500/20 text-rose-300', original: 20, key: 'streamflix',
      title: '🍿 Marathon Sci-Fi & Horror — 15 Days StreamFlix Premium', description: 'Selling my remaining 15 days of 4K Premium access. Perfect for weekend binge-watching. Instant transfer via LuvuPay.' },
    MusicPro: { emoji: '🎧', tone: 'bg-violet-500/20 text-violet-300', original: 12, key: 'musicpro',
      title: '🎧 Ad-free Hi-Fi for 22 Days — MusicPro Family Seat', description: 'Unused family seat, AI-priced to move fast. Instant entitlement handoff.' },
    DesignAI: { emoji: '🎨', tone: 'bg-sky-500/20 text-sky-300', original: 30, key: 'designai',
      title: '🎨 DesignAI Pro GPU Queue — 9 Days Left', description: 'Priority render seats for a focused sprint. Residual time must not waste.' },
    FitLife: { emoji: '💪', tone: 'bg-emerald-500/20 text-emerald-300', original: 16, key: 'fitlife',
      title: '💪 FitLife Live Classes — 18 Days Remaining', description: 'Full live + meal-plan access. Transfer remaining cycle today.' },
    CloudDrive: { emoji: '☁️', tone: 'bg-amber-500/20 text-amber-300', original: 10, key: 'clouddrive',
      title: '☁️ 2TB CloudDrive Vault — 27 Days Left', description: 'Encrypted storage cycle leftover via LuvuPay entitlement bridge.' },
    NewsDaily: { emoji: '📰', tone: 'bg-slate-500/20 text-slate-300', original: 8, key: 'newsdaily',
      title: '📰 NewsDaily Ad-Free — 12 Days of Deep Reads', description: 'AI demand is high mid-week. Instant transfer, no friction.' },
  };
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function isPayPalUrl(v) {
    try { var u = new URL(v); return u.protocol === 'https:' && /(^|\.)paypal\.com$/.test(u.hostname); } catch (e) { return false; }
  }
  function post(path, body) {
    return fetch(path, {
      method: 'POST', credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (data) { return { ok: res.ok, status: res.status, data: data }; }); });
  }
  function firstError(res, fallback) {
    var d = res.data || {};
    if (d.errors) { var k = Object.keys(d.errors)[0]; if (k) return d.errors[k][0]; }
    return d.message || fallback;
  }

  global.marketplaceApp = function marketplaceApp() {
    return {
      appNames: Object.keys(APPS),
      listings: [], loading: true, error: '',
      toast: '', toastTimer: null,

      sellOpen: false, sellBusy: false, step: 1, formError: '',
      verifyDone: false, verifyPct: 0, aiReady: false, aiPct: 0,
      sell: { app: 'StreamFlix', email: '' },
      listing: { title: '', description: '', price: 10, days: 15, original: 20 },

      buyOpen: false, buyBusy: false, buyItem: null, buyEmail: '', buyError: '',

      get stepLabel() { return ({ 1: 'Step 1 · Choose the app', 2: 'Step 2 · Verification', 3: 'Step 3 · AI auto-listing (Nebius)' })[this.step] || ''; },

      init: async function () {
        await this.loadListings();
        var q = new URLSearchParams(global.location.search);
        var msg = q.get('bought') ? 'Payment received. The listing is yours — check your email.'
          : q.get('verified') ? 'Email confirmed. Your listing is now live.'
          : q.get('cancelled') ? 'Checkout cancelled. The listing is available again.'
          : q.get('failed') ? 'We could not complete that purchase. Please try again.' : '';
        if (msg) { this.say(msg); global.history.replaceState({}, '', global.location.pathname); }
      },

      say: function (message) {
        var self = this;
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(function () { self.toast = ''; }, 5000);
      },

      tone: function (app) { return (APPS[app] || {}).tone || 'bg-slate-500/20 text-slate-300'; },

      loadListings: async function () {
        this.loading = true; this.error = '';
        try {
          var res = await fetch('/api/marketplace/listings', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          if (!res.ok) throw new Error('HTTP ' + res.status);
          var j = await res.json();
          this.listings = (j.data || []).map(function (i) {
            return { id: i.id, app: i.app, emoji: i.emoji || (APPS[i.app] || {}).emoji || '🍿', daysLeft: i.daysLeft, original: i.original, price: i.price, description: i.description || i.title, title: i.title };
          });
        } catch (e) {
          this.error = 'Could not load listings. Please refresh.';
        } finally { this.loading = false; }
      },

      // ---- sell
      openSell: function () {
        Object.assign(this, { sellOpen: true, step: 1, verifyDone: false, verifyPct: 0, aiReady: false, aiPct: 0, sellBusy: false, formError: '', sell: { app: 'StreamFlix', email: '' } });
      },

      startVerify: async function () {
        var email = (this.sell.email || '').trim();
        if (!EMAIL.test(email)) { this.formError = 'Enter a valid email address — we send the confirmation link there.'; return; }
        this.formError = ''; this.sell.email = email;
        this.step = 2; this.sellBusy = true; this.verifyDone = false; this.verifyPct = 12;
        await sleep(450); this.verifyPct = 48;
        await sleep(500); this.verifyPct = 82;
        await sleep(400); this.verifyPct = 100; this.verifyDone = true;
        await sleep(650);
        this.step = 3; this.aiReady = false; this.aiPct = 18;
        await sleep(500); this.aiPct = 55;
        await sleep(500); this.aiPct = 88;
        await sleep(450); this.aiPct = 100;
        var a = APPS[this.sell.app] || APPS.StreamFlix;
        this.listing = { title: a.title, description: a.description, price: Math.round(a.original * 50) / 100, days: 15, original: a.original };
        this.aiReady = true; this.sellBusy = false;
      },

      publish: async function () {
        if (this.sellBusy) return;
        this.sellBusy = true; this.formError = '';
        var a = APPS[this.sell.app] || APPS.StreamFlix;
        try {
          var res = await post('/api/marketplace/sell', {
            app_key: a.key, app_name: this.sell.app, title: this.listing.title, description: this.listing.description,
            seller_email: this.sell.email, days_remaining: this.listing.days,
            original_price_minor: Math.round(this.listing.original * 100), price_minor: Math.round(this.listing.price * 100),
            currency: 'USD', emoji: a.emoji,
          });
          if (!res.ok) { this.formError = firstError(res, 'Publish failed (HTTP ' + res.status + ').'); return; }
          this.sellOpen = false;
          await this.loadListings();
          this.say(res.data.needs_verification ? 'Almost there: check your email and click the link to publish your listing.' : 'Listing published to the marketplace.');
        } catch (e) { this.formError = 'Network error. Please try again.'; }
        finally { this.sellBusy = false; }
      },

      // ---- buy
      openBuy: function (item) { Object.assign(this, { buyOpen: true, buyItem: item, buyEmail: '', buyError: '', buyBusy: false }); },

      confirmBuy: async function () {
        if (this.buyBusy || !this.buyItem) return;
        var email = (this.buyEmail || '').trim();
        if (!EMAIL.test(email)) { this.buyError = 'Enter a valid email for your receipt.'; return; }
        this.buyBusy = true; this.buyError = '';
        try {
          var res = await post('/api/marketplace/listings/' + encodeURIComponent(this.buyItem.id) + '/buy', { buyer_email: email });
          if (!res.ok) { this.buyError = firstError(res, 'Could not start checkout (HTTP ' + res.status + ').'); await this.loadListings(); return; }
          if (res.data.approve_url && isPayPalUrl(res.data.approve_url)) {
            this.buyOpen = false;
            this.say('Opening PayPal Sandbox checkout — the listing is held for you for ' + (res.data.expires_in_minutes || 15) + ' minutes.');
            global.open(res.data.approve_url, 'paypal_mkt', 'noopener,noreferrer,width=520,height=720');
            await this.loadListings();
          } else { this.buyError = 'Checkout link was not valid. Please try again.'; }
        } catch (e) { this.buyError = 'Network error. Please try again.'; }
        finally { this.buyBusy = false; }
      },
    };
  };

  document.addEventListener('alpine:init', function () {
    if (global.Alpine && typeof global.marketplaceApp === 'function') {
      try { global.Alpine.data('marketplaceApp', global.marketplaceApp); } catch (e) {}
    }
  });
})(window);
