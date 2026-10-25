document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('toast-queue');
  var queue = [];
  try { queue = JSON.parse(el ? el.textContent : '[]') || []; } catch (e) {}
  if (!window.LuvuToast || !queue.length) return;
  queue.forEach(function (item, i) {
    setTimeout(function () { window.LuvuToast.notify({ type: item.type, title: item.title, duration: 5200 }); }, 200 + i * 350);
  });
});
