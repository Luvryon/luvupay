(function () {
  'use strict';
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }
  window.projectFeatures = function () {
    return {
      gracePeriod: true,
      marketplaceResale: false,
      charitySplit: false,
      refundPct: 20,
      saving: false,
      saved: false,
      error: '',
      init: function () {
        var root = document.getElementById('project-features-seed');
        if (root) {
          try {
            var j = JSON.parse(root.textContent || '{}');
            this.marketplaceResale = !!j.marketplace_resale;
            this.charitySplit = !!j.charity_split;
            this.refundPct = Math.round((Number(j.partial_refund_bps) || 2000) / 100);
          } catch (e) {}
        }
      },
      save: async function () {
        this.saving = true;
        this.saved = false;
        this.error = '';
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
              grace_period: this.gracePeriod,
          marketplace_resale: this.marketplaceResale,
              charity_split: this.charitySplit,
              partial_refund_bps: Math.round(Number(this.refundPct) || 0) * 100,
            }),
          });
          var j = await res.json().catch(function () { return {}; });
          if (!res.ok) {
            this.error = j.message || j.error || ('HTTP ' + res.status);
            return;
          }
          this.saved = true;
          var self = this;
          setTimeout(function () { self.saved = false; }, 2500);
        } catch (e) {
          this.error = 'Network error';
        } finally {
          this.saving = false;
        }
      },
    };
  };
})();
