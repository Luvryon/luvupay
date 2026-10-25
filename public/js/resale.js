(function () {
  'use strict';
  window.resalePage = function () {
    return {
      d: { listings: [], counts: {}, enabled: true, store_url: '#' }, loaded: false,
      money: function (minor, cur) {
        var n = ((Number(minor) || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return (cur || 'USD') === 'USD' ? '$' + n : n + ' ' + cur;
      },
      load: async function () {
        try {
          var r = await fetch('/api/v1/dashboard/resale', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          if (r.ok) this.d = await r.json();
        } catch (e) {}
        this.loaded = true;
      },
      copy: function (text) {
        var done = function () { if (window.LuvuToast) window.LuvuToast.success('Link copied', text); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); } else { done(); }
      }
    };
  };
})();
