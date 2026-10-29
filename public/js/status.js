/** Public status page — live probe against GET /up (real latency + HTTP code). */
(function (global) {
  'use strict';

  global.statusPage = function statusPage() {
    return {
      overall: 'checking',
      title: 'Checking systems…',
      subtitle: 'Pinging the live probe',
      overallLabel: 'Checking',
      services: [],
      latencyMs: null,
      httpCode: null,
      probeName: '/up',
      lastCheckedLabel: '—',
      timer: null,

      init: function () {
        this.load();
      },

      load: async function () {
        this.overall = 'checking';
        this.overallLabel = 'Checking';
        this.title = 'Checking systems…';
        this.subtitle = 'Pinging the live probe';

        var ok = false;
        var code = 0;
        var error = null;
        var latency = null;
        var t0 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();

        try {
          var res = await fetch('/up', {
            headers: { Accept: 'text/plain, application/json' },
            cache: 'no-store',
          });
          var t1 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
          latency = Math.max(1, Math.round(t1 - t0));
          ok = res.ok;
          code = res.status;
        } catch (e) {
          var t2 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
          latency = Math.max(1, Math.round(t2 - t0));
          error = 'Network unreachable';
          ok = false;
          code = 0;
        }

        this.latencyMs = latency;
        this.httpCode = code || null;
        this.lastCheckedLabel = new Date().toLocaleTimeString(undefined, {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        });

        var latencyLabel = latency !== null ? latency + ' ms' : '—';
        var sourceLive = error ? 'Probe failed' : 'Live probe';
        var detailBase = function (text) {
          return error ? error : text;
        };

        // Components are derived from the same public probe (only real signal available).
        // Payments / webhooks are labeled as monitored via the same app liveness gate.
        this.services = [
          {
            name: 'API',
            detail: detailBase('Application HTTP liveness'),
            ok: ok,
            latency: latencyLabel,
            source: sourceLive,
          },
          {
            name: 'Platform',
            detail: detailBase('Core app process & routing'),
            ok: ok,
            latency: latencyLabel,
            source: sourceLive,
          },
          {
            name: 'Payments',
            detail: detailBase('Payment stack reaches this host'),
            ok: ok,
            latency: ok ? latencyLabel : '—',
            source: ok ? 'Inferred from probe' : sourceLive,
          },
          {
            name: 'Webhooks',
            detail: detailBase('Webhook delivery pipeline host'),
            ok: ok,
            latency: ok ? latencyLabel : '—',
            source: ok ? 'Inferred from probe' : sourceLive,
          },
        ];

        this.overall = ok ? 'operational' : 'outage';
        this.overallLabel = ok ? 'All systems up' : 'Disruption';
        this.title = ok ? 'All systems operational' : (error ? 'Unable to reach the status endpoint' : 'Service disruption');
        this.subtitle = 'Checked ' + this.lastCheckedLabel + (code ? ' · HTTP ' + code : '') + (latency !== null ? ' · ' + latency + ' ms' : '');

        clearTimeout(this.timer);
        var self = this;
        this.timer = setTimeout(function () {
          self.load();
        }, 30000);
      },
    };
  };
})(window);
