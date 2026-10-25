(function () {
  'use strict';
  function toast(kind, title, msg) { if (window.LuvuToast && window.LuvuToast[kind]) window.LuvuToast[kind](title, msg); }

  // Confirm dialogs without inline handlers (the app CSP forbids inline JS)
  document.addEventListener('submit', function (e) {
    var q = e.target && e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (q && !window.confirm(q)) e.preventDefault();
  });

  var form = document.getElementById('logo-upload-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    var fileInput = document.getElementById('logo-file');
    var btn = document.getElementById('logo-upload-btn');
    if (!fileInput.files || !fileInput.files[0]) { e.preventDefault(); toast('warning', 'Choose a file', 'Select a PNG, JPEG, or WebP logo first.'); return; }
    if (fileInput.files[0].size > 2 * 1024 * 1024) { e.preventDefault(); toast('error', 'File too large', 'Max 2 MB.'); return; }
    e.preventDefault();
    if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
    var meta = document.querySelector('meta[name="csrf-token"]');
    fetch(form.action, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
      body: new FormData(form)
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
      .then(function (res) {
        if (res.ok && res.j && res.j.ok) {
          toast('success', 'Logo updated', res.j.message || 'Saved');
          // reload so the sidebar, invoices and emails all show the new logo
          setTimeout(function () { window.location.reload(); }, 600);
        } else {
          var errs = res.j && res.j.errors ? Object.keys(res.j.errors).map(function (k) { return res.j.errors[k].join(' '); }).join(' ') : '';
          toast('error', 'Upload failed', errs || (res.j && (res.j.message || res.j.error)) || ('HTTP ' + res.status));
        }
      })
      .catch(function (err) { toast('error', 'Upload failed', String(err && err.message || err)); })
      .finally(function () { if (btn) { btn.disabled = false; btn.textContent = 'Upload logo'; } });
  });
})();
