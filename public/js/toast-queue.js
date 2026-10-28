/** Shows the flash messages the server rendered into #toast-queue (see layouts/console.blade.php) */
document.addEventListener('DOMContentLoaded', function () {
  var source = document.getElementById('toast-queue');
  var queue = [];
  try { queue = JSON.parse(source ? source.textContent : '[]') || []; } catch (e) { queue = []; }
  if (!window.LuvuToast || !queue.length) return;

  queue.forEach(function (item, index) {
    setTimeout(function () { window.LuvuToast.notify({ type: item.type, title: item.title }); }, 200 + index * 300);
  });
});
