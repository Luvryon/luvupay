/* Run AI Smart-Convert Protocol: panel logic. Shows model, tokens, latency, rationale, split and the real PayPal batch id.
   All numbers come from the server response (which reads smart_convert_runs); nothing is computed in the browser. */
(function (w) {
  'use strict';

  function csrf() { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; }
  function money(minor) { return (Number(minor || 0) / 100).toFixed(2); }

  async function call(method, url, body) {
    var res = await fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: body ? JSON.stringify(body) : undefined
    });
    var data = await res.json().catch(function () { return {}; });
    return { status: res.status, ok: res.ok, data: data };
  }

  w.luvuSmartConvert = function () {
    return {
      busy: false,
      approvalMode: false,   // true = AI proposes, merchant approves before PayPal moves money
      result: null,          // last successful response
      error: null,           // { stage, error, evidence_url }
      charityEdit: null,
      checks: null,

      money: money,

      headlineClass: function () {
        if (!this.result) return '';
        var p = this.result.path, s = this.result.status;
        if (s === 'proposed') return 'sc-warn';
        if (p === 'fallback') return 'sc-bad';
        if (this.result.paypal && this.result.paypal.simulated) return 'sc-warn';
        return 'sc-ok';
      },

      // Called from the Missed Cancellation Journey with the renewal being recovered
      announce: function (r) {
        if (r && r.status === 'executed' && w.dispatchEvent) {
          w.dispatchEvent(new CustomEvent('smart-convert-done', { detail: { row: r.row || null, message: r.message || 'AI Smart-Convert completed' } }));
        }
      },

      run: async function (txId) {
        if (this.busy) return;
        if (!txId) { this.error = { stage: 'journey', error: 'Open the Missed Cancellation Journey and choose a subscriber first.', evidence_url: null }; return; }
        this.busy = true; this.error = null; this.result = null;
        if (w.LuvuLoader && w.LuvuLoader.begin) w.LuvuLoader.begin();
        try {
          var r = await call('POST', '/api/v1/dashboard/demo/smart-convert', { transaction_id: txId, mode: this.approvalMode ? 'propose' : 'auto' });
          if (r.ok && r.data.ok) {
            this.result = r.data;
            this.charityEdit = r.data.conversion ? r.data.conversion.charity_minor : null;
            this.toast(r.data.status === 'proposed' ? 'info' : 'success', r.data.message);
            this.announce(r.data);
          } else {
            this.error = { stage: r.data.stage || 'request', error: r.data.error || r.data.message || ('HTTP ' + r.status), evidence_url: r.data.evidence_url || null };
            this.toast('error', this.error.error);
          }
        } catch (e) {
          this.error = { stage: 'network', error: 'Network error. Please try again.', evidence_url: null };
        } finally {
          this.busy = false;
          if (w.LuvuLoader && w.LuvuLoader.end) w.LuvuLoader.end();
        }
      },

      decide: async function (action) {
        if (this.busy || !this.result) return;
        this.busy = true; this.error = null;
        try {
          var url = '/api/v1/dashboard/demo/smart-convert/' + encodeURIComponent(this.result.run_id) + '/' + action;
          var body = action === 'approve' && this.charityEdit !== null ? { charity_minor: Number(this.charityEdit) } : null;
          var r = await call('POST', url, body);
          if (r.ok && r.data.ok) { this.result = r.data; this.toast('success', r.data.message); this.announce(r.data); }
          else {
            this.error = { stage: r.data.stage || 'request', error: r.data.error || r.data.message || ('HTTP ' + r.status), evidence_url: r.data.evidence_url || null };
            this.toast('error', this.error.error);
          }
        } catch (e) {
          this.error = { stage: 'network', error: 'Network error. Please try again.', evidence_url: null };
        } finally { this.busy = false; }
      },

      preflight: async function () {
        this.busy = true;
        try {
          var res = await fetch('/api/v1/dashboard/demo/smart-convert/preflight?ping=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          this.checks = await res.json();
        } catch (e) { this.checks = { ready_for_real_run: false, checks: {}, live_ai_ping: { ok: false, error: 'Request failed' } }; }
        finally { this.busy = false; }
      },

      toast: function (kind, msg) {
        if (w.LuvuToast && w.LuvuToast[kind]) w.LuvuToast[kind](msg);
      }
    };
  };
})(window);
