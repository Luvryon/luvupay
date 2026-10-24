(function () {
  'use strict';
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }
  window.projectFeatures = function () {
    return {
      isSet: false,
      name: '',
      plan: 'Premium',
      description: '',
      environment: 'sandbox',
      price: 20,
      currency: 'USD',
      gracePeriod: true,
      marketplaceResale: true,
      charitySplit: true,
      refundPct: 20,
      saving: false,
      saved: false,
      error: '',
      init: function () {
        var root = document.getElementById('project-features-seed');
        if (!root) return;
        try {
          var j = JSON.parse(root.textContent || '{}');
          this.isSet = !!j.is_set;
          this.name = j.name || '';
          this.plan = j.plan || 'Premium';
          this.description = j.description || '';
          this.environment = j.environment === 'production' ? 'production' : 'sandbox';
          this.price = (Number(j.price_minor) || 2000) / 100;
          this.currency = j.currency || 'USD';
          this.gracePeriod = j.grace_period !== false;
          this.marketplaceResale = j.marketplace_resale !== false;
          this.charitySplit = j.charity_split !== false;
          this.refundPct = Math.round((j.partial_refund_bps != null ? Number(j.partial_refund_bps) : 2000) / 100);
        } catch (e) {}
      },
      save: async function () {
        this.saving = true;
        this.saved = false;
        this.error = '';
        var pct = Number(this.refundPct);
        if (isNaN(pct)) pct = 20;
        try {
          var res = await fetch('/api/v1/dashboard/project', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-TOKEN': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              name: this.name.trim(),
              plan: (this.plan || 'Premium').trim(),
              description: this.description || '',
              environment: this.environment,
              price_minor: Math.max(100, Math.round((Number(this.price) || 20) * 100)),
              currency: (this.currency || 'USD').toUpperCase(),
              grace_period: !!this.gracePeriod,
              marketplace_resale: !!this.marketplaceResale,
              charity_split: !!this.charitySplit,
              partial_refund_bps: Math.round(Math.min(100, Math.max(0, pct)) * 100),
            }),
          });
          var j = await res.json().catch(function () { return {}; });
          if (!res.ok) {
            var errs = j.errors ? Object.values(j.errors).flat().join(' ') : '';
            this.error = errs || j.message || j.error || ('HTTP ' + res.status);
            return;
          }
          this.isSet = true;
          this.saved = true;
          var self = this;
          setTimeout(function () { self.saved = false; }, 3500);
        } catch (e) {
          this.error = 'Network error';
        } finally {
          this.saving = false;
        }
      },
    };
  };
})();
