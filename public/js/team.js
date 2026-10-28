/** Team page: members and invitations load over AJAX, every action is a fetch call with a loading state */
(function () {
  'use strict';

  var page = document.getElementById('team-page');
  if (!page) return;

  var memberRows = document.getElementById('member-rows');
  var inviteRows = document.getElementById('invite-rows');
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

  function cell(child) {
    var td = el('td');
    if (child) td.appendChild(child);
    return td;
  }

  function tag(text, modifier) {
    return el('span', 'tag' + (modifier ? ' ' + modifier : ''), text);
  }

  function cap(text) { return text.charAt(0).toUpperCase() + text.slice(1); }

  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  /** Runs an action, shows the result as a toast and reloads both tables */
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

  function memberRow(m, roles) {
    var tr = el('tr');

    var info = el('div', 'member');
    info.appendChild(el('span', 'avatar', m.initial));
    var text = el('div');
    text.appendChild(el('b', null, m.name));
    text.appendChild(el('span', null, m.email));
    info.appendChild(text);
    tr.appendChild(cell(info));

    if (!m.editable) {
      tr.appendChild(cell(m.is_owner ? tag('Owner', 'dark') : tag(cap(m.role))));
    } else {
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
      tr.appendChild(cell(select));
    }

    tr.appendChild(cell(tag(m.mfa ? 'On' : 'Off', m.mfa ? 'ok' : '')));
    tr.appendChild(cell(tag(m.is_active ? 'Active' : 'Removed', 'dot ' + (m.is_active ? 'ok' : 'bad'))));

    var actions = cell();
    actions.style.textAlign = 'right';
    if (m.editable && m.is_active) {
      var remove = el('button', 'link-bad', 'Remove');
      remove.type = 'button';
      remove.addEventListener('click', function () {
        if (window.confirm('Remove ' + m.name + ' from the team?')) act('DELETE', base + '/members/' + m.id, null, remove);
      });
      actions.appendChild(remove);
    }
    tr.appendChild(actions);
    return tr;
  }

  function inviteRow(i) {
    var tr = el('tr');
    var email = el('b', null, i.email);
    email.style.fontWeight = '600';
    tr.appendChild(cell(email));
    tr.appendChild(cell(tag(cap(i.role))));
    var expires = cell();
    expires.textContent = 'Expires ' + i.expires;
    expires.style.cssText = 'color:var(--muted);font-size:.82rem';
    tr.appendChild(expires);

    var actions = cell();
    actions.style.textAlign = 'right';
    var revoke = el('button', 'link-bad', 'Revoke');
    revoke.type = 'button';
    revoke.addEventListener('click', function () {
      if (window.confirm('Revoke this invitation?')) act('DELETE', base + '/invitations/' + i.id, null, revoke);
    });
    actions.appendChild(revoke);
    tr.appendChild(actions);
    return tr;
  }

  function render(data) {
    memberRows.textContent = '';
    data.members.forEach(function (m) { memberRows.appendChild(memberRow(m, data.roles)); });
    memberCount.textContent = plural(data.members.length, 'person', 'people') + ' on this merchant';

    inviteRows.textContent = '';
    data.invitations.forEach(function (i) { inviteRows.appendChild(inviteRow(i)); });
    inviteCard.hidden = data.invitations.length === 0;
    inviteCount.textContent = data.invitations.length + ' waiting to be accepted';
  }

  function load() {
    var first = !memberRows.children.length;
    if (first) window.LuvuLoader.table.loading(memberRows, 5, 'Loading members');
    return request('GET', page.getAttribute('data-url'))
      .then(render)
      .catch(function () {
        window.LuvuLoader.table.error(memberRows, 5, 'Could not load the team', load);
        memberCount.textContent = 'Unavailable';
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
