document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-print]');
  if (b) { e.preventDefault(); window.print(); }
});
