(function () {
  'use strict';
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }
  window.hostedPage = function () {
    return {
      mode: 'checkout', payUrl: '', name: '', email: '', busy: false, done: false, error: '', invoiceUrl: '#',
      init: function () {
        try {
          var cfg = JSON.parse(this.$el.getAttribute('data-cfg') || '{}');
          this.mode = cfg.mode || 'checkout';
          this.payUrl = cfg.payUrl || '';
        } catch (e) {}
      },
      pay: async function () {
        this.error = '';
        var em = (this.email || '').trim();
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) { this.error = 'Enter a valid email for your receipt.'; return; }
        this.busy = true;
        try {
          var body = { email: em };
          if (this.mode === 'checkout') body.name = (this.name || '').trim();
          var res = await fetch(this.payUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
          });
          var j = await res.json().catch(function () { return {}; });
          if (!res.ok || !j.ok) {
            var errs = j.errors ? Object.keys(j.errors).map(function (k) { return j.errors[k].join(' '); }).join(' ') : '';
            this.error = errs || j.message || 'Payment could not be started. Please try again.';
            return;
          }
          if (j.approve_url) { window.location.href = j.approve_url; return; }
          this.invoiceUrl = j.invoice_url || '#';
          this.done = true;
        } catch (e) {
          this.error = 'Network error. Please try again.';
        } finally {
          this.busy = false;
        }
      }
    };
  };
})();
