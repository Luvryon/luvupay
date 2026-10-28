/** Public storefront: loads resale listings over AJAX and renders them with safe DOM calls */
(function () {
  'use strict';

  var section = document.getElementById('resale');
  var list = document.getElementById('listing-list');
  var count = document.getElementById('listing-count');
  if (!section || !list) return;

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function card(item) {
    var article = el('article', 'flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm');
    var info = el('div', 'min-w-0');
    info.appendChild(el('span', 'rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700', 'Verified token'));
    info.appendChild(el('p', 'mt-2 font-bold', item.days + ' days of ' + item.product));
    info.appendChild(el('p', 'text-xs text-slate-500', 'Transferred from ' + item.seller));

    var side = el('div', 'flex-none text-right');
    side.appendChild(el('p', 'text-lg font-bold', item.price));
    var buy = el('a', 'mt-2 inline-block rounded-xl bg-[#0070ba] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#005ea6]', 'Buy');
    buy.href = item.url;
    side.appendChild(buy);

    article.appendChild(info);
    article.appendChild(side);
    return article;
  }

  function render(items) {
    list.textContent = '';
    count.textContent = items.length ? items.length + ' available' : '';
    if (!items.length) {
      var empty = el('div', 'rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500', 'No resale access is listed right now. Check back soon');
      list.appendChild(empty);
      return;
    }
    items.forEach(function (item) { list.appendChild(card(item)); });
  }

  function load() {
    window.LuvuLoader.start(list, 'Loading listings');
    fetch(section.getAttribute('data-listings-url'), { headers: { Accept: 'application/json' } })
      .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
      .then(function (data) { render(data.listings || []); })
      .catch(function () { window.LuvuLoader.error(list, 'Could not load listings', load); });
  }

  load();
})();
