(function () {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  function defaults() {
    return { nameSelect: '', nameCustom: '', description: '', env: 'sandbox', plan: 'Premium', price: 20, currency: 'USD',
      grace_period: true, marketplace_resale: true, charity_split: true, partial_refund_pct: 20 };
  }

  window.projectContext = function () {
    var root = document.getElementById('project-context');
    var initialName = root ? (root.getAttribute('data-name') || '') : '';
    var initialEnv = root ? (root.getAttribute('data-env') || 'sandbox') : 'sandbox';
    var hasInitial = root ? root.getAttribute('data-has') === '1' : false;

    return {
      openModal: false,
      creating: false,
      toast: false,
      currentName: initialName,
      hasProject: hasInitial,
      currentEnv: initialEnv,
      form: defaults(),
      nameOpen: false,
      envOpen: false,
      nameOptions: [
        { value: 'StreamNetFly', label: 'StreamNetFly' },
        { value: 'LuvuFit', label: 'LuvuFit' },
        { value: 'CourseCloud', label: 'CourseCloud' },
        { value: 'MediaVault', label: 'MediaVault' },
        { value: 'PayFlow Pro', label: 'PayFlow Pro' },
        { value: '__custom', label: 'Custom name…' },
      ],
      envOptions: [
        { value: 'sandbox', label: 'Sandbox — PayPal Sandbox, no real money' },
        { value: 'production', label: 'Production — live charges' },
      ],
      get resolvedName() {
        if (this.form.nameSelect === '__custom') return (this.form.nameCustom || '').trim();
        return (this.form.nameSelect || '').trim();
      },
      get nameLabel() {
        var o = this.nameOptions.find(function (x) { return x.value === this.form.nameSelect; }.bind(this));
        return o ? o.label : 'Select a project…';
      },
      get envLabel() {
        var o = this.envOptions.find(function (x) { return x.value === this.form.env; }.bind(this));
        return o ? o.label : 'Select environment…';
      },
      openNew: function () {
        this.form = defaults();
        this.nameOpen = false;
        this.envOpen = false;
        this.openModal = true;
      },
      close: function () {
        this.openModal = false;
        this.nameOpen = false;
        this.envOpen = false;
      },
      createProject: async function () {
        var name = this.resolvedName;
        if (!name || this.creating) return;
        this.creating = true;
        var env = this.form.env === 'production' ? 'production' : 'sandbox';
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
              name: name,
              description: this.form.description || '',
              environment: env,
              plan: (this.form.plan || 'Premium').trim(),
              price_minor: Math.max(100, Math.round((Number(this.form.price) || 20) * 100)),
              currency: (this.form.currency || 'USD').toUpperCase(),
              grace_period: !!this.form.grace_period,
              marketplace_resale: !!this.form.marketplace_resale,
              charity_split: !!this.form.charity_split,
              partial_refund_bps: Math.round(Math.min(100, Math.max(0, isNaN(Number(this.form.partial_refund_pct)) ? 20 : Number(this.form.partial_refund_pct))) * 100),
            }),
          });
          if (!res.ok) {
            var ej = await res.json().catch(function () { return {}; });
            throw new Error(ej.message || 'Failed to save project');
          }
          var j = await res.json();
          this.currentName = (j.project && j.project.name) || name;
          this.currentEnv = env;
          this.hasProject = true;
          this.openModal = false;
          this.toast = true;
          var self = this;
          setTimeout(function () { self.toast = false; }, 3200);
          // Reload so Overview, Settings, Analytics and the simulator all read the same saved project
          setTimeout(function () { window.location.reload(); }, 700);
          try {
            // Prefer window.PROJECT (dashboard.js exports it); fall back to bare PROJECT
            var P = (typeof window !== 'undefined' && window.PROJECT) ? window.PROJECT : (typeof PROJECT !== 'undefined' ? PROJECT : null);
            if (P) {
              P.name = this.currentName;
              P.product = this.currentName + ' Premium';
              P.is_set = true;
              P.plan = P.plan || 'Premium';
            }
            var root = document.getElementById('project-context');
            if (root) {
              root.setAttribute('data-name', this.currentName);
              root.setAttribute('data-has', '1');
            }
          } catch (e) {}
        } catch (e) {
          alert('Could not create project: ' + (e && e.message ? e.message : 'please try again.'));
        } finally {
          this.creating = false;
        }
      },
    };
  };
})();
