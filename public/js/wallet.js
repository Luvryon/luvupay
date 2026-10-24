(function () {
  'use strict';
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }
  function money(minor, cur) {
    return (cur === 'USD' || !cur ? '$' : '') + (Number(minor || 0) / 100).toFixed(2) + (cur && cur !== 'USD' ? ' ' + cur : '');
  }
  window.walletPage = function () {
    return {
      loading: false,
      withdrawing: false,
      balanceMinor: 0,
      currency: 'USD',
      rows: [],
      q: '',
      fromDate: '',
      toDate: '',
      page: 1,
      perPage: 15,
      get balanceLabel() { return money(this.balanceMinor, this.currency); },
      get filteredRows() {
        var q = (this.q || '').toLowerCase().trim();
        var from = this.fromDate ? new Date(this.fromDate + 'T00:00:00') : null;
        var to = this.toDate ? new Date(this.toDate + 'T23:59:59') : null;
        return this.rows.filter(function (r) {
          if (from && r.ts && r.ts < from) return false;
          if (to && r.ts && r.ts > to) return false;
          if (!q) return true;
          return (r.id + ' ' + r.type + ' ' + r.ref + ' ' + (r.email || '')).toLowerCase().indexOf(q) !== -1;
        });
      },
      get totalPages() {
        return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
      },
      get pageRows() {
        var start = (this.page - 1) * this.perPage;
        return this.filteredRows.slice(start, start + this.perPage);
      },
      async load() {
        this.loading = true;
        try {
          var [sumRes, txRes] = await Promise.all([
            fetch('/api/v1/dashboard/summary', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
            fetch('/api/v1/dashboard/transactions', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
          ]);
          var sum = sumRes.ok ? await sumRes.json() : {};
          var json = txRes.ok ? await txRes.json() : { data: [] };
          var data = Array.isArray(json.data) ? json.data : [];
          this.currency = sum.currency || 'USD';
          this.balanceMinor = Number(sum.wallet_balance_minor != null ? sum.wallet_balance_minor : sum.available_balance_minor) || 0;
          this.rows = data.map(function (r) {
            var ts = r.created_at ? new Date(r.created_at) : null;
            return {
              id: r.id,
              email: r.email || '',
              when: ts ? ts.toLocaleString() : '—',
              ts: ts,
              type: r.type || '—',
              status: r.status || '—',
              ref: r.provider_reference || r.id || '—',
              amount: money(r.amount_minor, r.currency || 'USD'),
              amount_minor: Number(r.amount_minor || 0),
            };
          });
          this.page = 1;
        } catch (e) {
          this.rows = [];
          if (window.LuvuToast) window.LuvuToast.error('Wallet load failed', String(e.message || e));
        } finally {
          this.loading = false;
        }
      },
      exportCsv() {
        var lines = ['id,when,type,status,reference,amount_minor'];
        this.filteredRows.forEach(function (r) {
          lines.push([r.id, r.when, r.type, r.status, r.ref, r.amount_minor].map(function (c) {
            return '"' + String(c).replace(/"/g, '""') + '"';
          }).join(','));
        });
        var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'luvupay-ledger.csv';
        a.click();
        if (window.LuvuToast) window.LuvuToast.success('CSV exported', this.filteredRows.length + ' rows');
      },
      async withdraw() {
        if (this.withdrawing || this.balanceMinor <= 0) return;
        var ob = document.getElementById('luvu-onboarding');
        if (ob && ob.getAttribute('data-payment-driver') === 'paypal' && ob.getAttribute('data-has-paypal') !== '1') {
          if (window.LuvuToast) {
            window.LuvuToast.info('Connect PayPal first', 'Link Sandbox merchant in Settings before withdrawing.', {
              action: { label: 'Open Settings', href: '/settings#paypal' },
            });
          }
          return;
        }
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
            body: JSON.stringify({ payout_type: 'merchant_withdrawal', amount_minor: this.balanceMinor }),
          });
          var j = await res.json().catch(function () { return {}; });
          if (res.ok && j.ok) {
            if (window.LuvuToast) window.LuvuToast.success('Withdrawal submitted', j.message || ('Batch ' + (j.payout_batch_id || '')));
            await this.load();
          } else {
            if (window.LuvuToast) window.LuvuToast.error('Withdrawal failed', j.error || j.message || 'Request failed');
          }
        } catch (e) {
          if (window.LuvuToast) window.LuvuToast.error('Network error', String(e.message || e));
        } finally {
          this.withdrawing = false;
        }
      },
    };
  };
})();
