(function () {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function money(minor, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format((minor || 0) / 100);
    } catch (e) {
      return ((minor || 0) / 100).toFixed(2) + ' ' + (currency || 'USD');
    }
  }

  window.walletApp = function () {
    return {
      loading: false,
      withdrawing: false,
      donating: false,
      balanceMinor: 0,
      currency: 'USD',
      rows: [],
      lastPayoutId: '',
      get balanceDisplay() {
        return money(this.balanceMinor, this.currency);
      },
      init: function () {
        this.load();
      },
      load: async function () {
        this.loading = true;
        try {
          var res = await fetch('/api/v1/dashboard/transactions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var json = res.ok ? await res.json() : { data: [] };
          var data = Array.isArray(json.data) ? json.data : [];
          var bal = 0;
          var cur = 'USD';
          this.rows = data.map(function (r) {
            var amt = Number(r.amount_minor || 0);
            bal += amt;
            cur = r.currency || cur;
            return {
              id: r.id,
              when: r.created_at ? new Date(r.created_at).toLocaleString() : '—',
              type: r.type || '—',
              status: r.status || '—',
              ref: r.provider_reference || '—',
              amount: money(amt, r.currency || 'USD'),
            };
          });
          this.balanceMinor = Math.max(0, bal);
          this.currency = cur;
        } catch (e) {
          this.rows = [];
        } finally {
          this.loading = false;
        }
      },
      withdraw: async function () {
        if (this.withdrawing || this.balanceMinor <= 0) return;
        this.withdrawing = true;
        try {
          var res = await fetch('/api/v1/dashboard/payouts', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              payout_type: 'merchant_withdrawal',
              amount_minor: this.balanceMinor,
            }),
          });
          var j = await res.json().catch(function () { return {}; });
          if (res.ok && j.ok) {
            this.lastPayoutId = j.payout_batch_id || '';
            if (window.LuvuPayToast) window.LuvuPayToast(j.message || 'Withdrawal submitted', 'success');
            else alert(j.message || 'Withdrawal submitted');
            await this.load();
          } else {
            var err = j.error || j.message || 'Withdrawal failed';
            if (window.LuvuPayToast) window.LuvuPayToast(err, 'error');
            else alert(err);
          }
        } catch (e) {
          alert('Network error');
        } finally {
          this.withdrawing = false;
        }
      },
      donateCharity: async function () {
        if (this.donating) return;
        this.donating = true;
        try {
          // Find a charge row to attach residual donation
          var resList = await fetch('/api/v1/dashboard/transactions', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          var list = resList.ok ? await resList.json() : { data: [] };
          var rows = Array.isArray(list.data) ? list.data : [];
          var src = rows.find(function (r) {
            return r.type === 'charge' && Number(r.amount_minor) > 0;
          });
          if (!src) {
            alert('No charge transaction to donate residual from. Run a demo checkout first.');
            return;
          }
          var res = await fetch('/api/v1/dashboard/payouts', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              payout_type: 'charity_donation',
              transaction_id: src.id,
              amount_minor: Math.abs(Number(src.amount_minor)),
              charity_label: 'American Red Cross',
            }),
          });
          var j = await res.json().catch(function () { return {}; });
          if (res.ok && j.ok) {
            this.lastPayoutId = j.payout_batch_id || '';
            if (window.LuvuPayToast) window.LuvuPayToast(j.message || 'Charity payout submitted', 'success');
            else alert(j.message || 'Charity payout submitted');
            await this.load();
          } else {
            var err = j.error || j.message || 'Charity payout failed';
            if (window.LuvuPayToast) window.LuvuPayToast(err, 'error');
            else alert(err);
          }
        } catch (e) {
          alert('Network error');
        } finally {
          this.donating = false;
        }
      },
    };
  };
})();
