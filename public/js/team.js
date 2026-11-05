/** Team page: members and invitations load over AJAX, every action is a fetch call with a loading state */
(function () {
  'use strict';

  var page = document.getElementById('team-page');
  if (!page) return;

  var memberHost = document.getElementById('grid-members');
  var inviteHost = document.getElementById('grid-invites');
  var memberApi = null;
  var inviteApi = null;
  var inviteCard = document.getElementById('invite-card');
  var memberCount = document.getElementById('member-count');
  var inviteCount = document.getElementById('invite-count');
  var form = document.getElementById('invite-form');
  var base = page.getAttribute('data-base');
  var toast = window.LuvuToast;

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function request(method, url, body) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw new Error(data.message || 'Request failed (HTTP ' + res.status + ')');
        return data;
      });
    });
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function tag(text, modifier) {
    return el('span', 'tag' + (modifier ? ' ' + modifier : ''), text);
  }

  function cap(text) { return text.charAt(0).toUpperCase() + text.slice(1); }

  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  /** Runs an action, shows the result as a toast and reloads both grids */
  function act(method, url, body, button) {
    var task = request(method, url, body).then(function (data) {
      if (toast) toast.success(data.message || 'Saved');
      return load();
    }).catch(function (err) {
      if (toast) toast.error('Action failed', err.message);
      return load();
    });
    return button ? window.LuvuLoader.busy(button, task) : task;
  }

  function memberColumns(roles) {
    return [
      { headerName: 'Member', field: 'name', flex: 2, minWidth: 220,
        cellRenderer: function (p) {
          var m = p.data;
          var info = el('div', 'member');
          info.appendChild(el('span', 'avatar', m.initial));
          var text = el('div');
          text.appendChild(el('b', null, m.name));
          text.appendChild(el('span', null, m.email));
          info.appendChild(text);
          return info;
        },
        filterValueGetter: function (p) { return p.data.name + ' ' + p.data.email; } },
      { headerName: 'Role', field: 'role', flex: 1, minWidth: 140,
        cellRenderer: function (p) {
          var m = p.data;
          if (!m.editable) return m.is_owner ? tag('Owner', 'dark') : tag(cap(m.role));
          var select = el('select', 'select-sm');
          select.setAttribute('aria-label', 'Role for ' + m.name);
          roles.forEach(function (r) {
            var opt = el('option', null, cap(r));
            opt.value = r;
            opt.selected = r === m.role;
            select.appendChild(opt);
          });
          select.addEventListener('change', function () {
            select.disabled = true;
            act('PATCH', base + '/members/' + m.id, { role: select.value });
          });
          return select;
        } },
      { headerName: 'Two-factor', field: 'mfa', width: 130,
        valueGetter: function (p) { return p.data.mfa ? 'On' : 'Off'; },
        cellRenderer: function (p) { return tag(p.value, p.data.mfa ? 'ok' : ''); } },
      { headerName: 'Status', field: 'is_active', width: 130,
        valueGetter: function (p) { return p.data.is_active ? 'Active' : 'Removed'; },
        cellRenderer: function (p) { return tag(p.value, 'dot ' + (p.data.is_active ? 'ok' : 'bad')); } },
      { headerName: '', colId: 'actions', width: 110, sortable: false, filter: false, resizable: false,
        cellStyle: { justifyContent: 'flex-end' },
        cellRenderer: function (p) {
          var m = p.data;
          var wrap = el('span');
          if (m.editable && m.is_active) {
            var remove = el('button', 'link-bad', 'Remove');
            remove.type = 'button';
            remove.addEventListener('click', function () {
              if (window.confirm('Remove ' + m.name + ' from the team?')) act('DELETE', base + '/members/' + m.id, null, remove);
            });
            wrap.appendChild(remove);
          }
          return wrap;
        } },
    ];
  }

  function inviteColumns() {
    return [
      { headerName: 'Email', field: 'email', flex: 2, minWidth: 220, cellStyle: { fontWeight: '600' } },
      { headerName: 'Role', field: 'role', width: 140, cellRenderer: function (p) { return tag(cap(p.value || '')); } },
      { headerName: 'Expires', field: 'expires', flex: 1, minWidth: 150,
        valueFormatter: function (p) { return 'Expires ' + p.value; }, cellStyle: { color: 'var(--muted)', fontSize: '.82rem' } },
      { headerName: '', colId: 'actions', width: 110, sortable: false, filter: false, resizable: false,
        cellStyle: { justifyContent: 'flex-end' },
        cellRenderer: function (p) {
          var i = p.data;
          var revoke = el('button', 'link-bad', 'Revoke');
          revoke.type = 'button';
          revoke.addEventListener('click', function () {
            if (window.confirm('Revoke this invitation?')) act('DELETE', base + '/invitations/' + i.id, null, revoke);
          });
          return revoke;
        } },
    ];
  }

  function render(data) {
    memberApi = window.LuvuGrid.create(memberHost, {
      columns: memberColumns(data.roles), rows: data.members, rowId: 'id', autoHeight: true, rowHeight: 60,
      noRowsText: 'No members yet', statusBar: false,
    });
    memberCount.textContent = plural(data.members.length, 'person', 'people') + ' on this merchant';

    inviteCard.hidden = data.invitations.length === 0; // visible before the grid is measured
    if (data.invitations.length) {
      inviteApi = window.LuvuGrid.create(inviteHost, {
        columns: inviteColumns(), rows: data.invitations, rowId: 'id', autoHeight: true, rowHeight: 52, headerHeight: 0, filter: false, statusBar: false,
      });
    }
    inviteCount.textContent = data.invitations.length + ' waiting to be accepted';
  }

  function load() {
    if (memberApi) { try { memberApi.setGridOption('loading', true); } catch (e) { /* destroyed */ } }
    return request('GET', page.getAttribute('data-url'))
      .then(render)
      .catch(function () {
        memberCount.textContent = 'Unavailable';
        if (toast) toast.error('Could not load the team', 'Check your connection and try again');
        if (memberApi) { try { memberApi.setGridOption('loading', false); } catch (e) { /* destroyed */ } }
      });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    var payload = { email: form.elements.email.value.trim(), role: form.elements.role.value };
    var task = request('POST', form.getAttribute('action'), payload).then(function (data) {
      if (toast) toast.success(data.message || 'Invitation sent');
      form.reset();
      return load();
    }).catch(function (err) {
      if (toast) toast.error('Invitation failed', err.message);
    });
    window.LuvuLoader.busy(button, task);
  });

  load();
})();
