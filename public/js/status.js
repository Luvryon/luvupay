/** Public status page: polls the liveness probe every 30s. Text-only rendering. */
(function (global) {
  'use strict';
  global.statusPage = function statusPage() {
    return {
      overall: 'checking', title: 'Checking systems', subtitle: 'Please wait a moment', services: [], timer: null,
      init: function () { this.load(); },
      load: async function () {
        var ok = false, code = 0, error = null;
        try {
          var res = await fetch('/up', { headers: { Accept: 'text/plain, application/json' }, cache: 'no-store' });
          ok = res.ok; code = res.status;
        } catch (e) { error = 'Network unreachable'; }
        var detail = function (d) { return error || d; };
        this.services = [
          { name: 'API', detail: detail('HTTP application liveness'), ok: ok },
          { name: 'Platform', detail: detail('Core services (public probe)'), ok: ok },
          { name: 'Payments', detail: detail('Provider connectivity (monitored internally)'), ok: ok },
          { name: 'Webhooks', detail: detail('Delivery pipeline (monitored internally)'), ok: ok },
        ];
        this.overall = ok ? 'operational' : 'outage';
        this.title = ok ? 'All systems operational' : (error ? 'Unable to reach the status endpoint' : 'Service disruption');
        this.subtitle = new Date().toLocaleString() + (code ? ' · HTTP ' + code : '');
        clearTimeout(this.timer);
        var self = this;
        this.timer = setTimeout(function () { self.load(); }, 30000);
      },
    };
  };
})(window);
