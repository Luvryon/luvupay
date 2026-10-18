@extends('layouts.console')
@section('title', 'Team')
@section('heading', 'Team')
@section('content')
<div class="stack">
    <div>
        <h1 class="page-title">Team</h1>
        <p class="page-sub">Invite operators and control what each role can change on this merchant.</p>
    </div>

    <div class="team-cols stack">
        <div class="stack">
            <section class="c-card">
                <div class="c-head"><div><h2 class="c-title">Members</h2><p class="c-sub">{{ $members->count() }} on this merchant</p></div></div>
                <div style="overflow-x:auto">
                    <table class="c-table">
                        <thead><tr><th>Member</th><th>Role</th><th>Two-factor</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach($members as $m)
                            <tr>
                                <td><div class="member"><span class="avatar">{{ strtoupper(substr($m->name ?: $m->email, 0, 1)) }}</span><div><b>{{ $m->name }}</b><span>{{ $m->email }}</span></div></div></td>
                                <td>
                                    @if($m->is_owner)
                                        <span class="tag dark">Owner</span>
                                    @else
                                        <form method="post" action="{{ route('team.role', $m->id) }}">
                                            @csrf @method('PATCH')
                                            <select name="role" data-autosubmit aria-label="Role for {{ $m->name }}" class="select-sm">
                                                @foreach($roles as $r)<option value="{{ $r }}" @selected($m->role === $r)>{{ ucfirst($r) }}</option>@endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td><span class="tag {{ $m->hasMfa() ? 'ok' : '' }}">{{ $m->hasMfa() ? 'On' : 'Off' }}</span></td>
                                <td><span class="tag dot {{ $m->is_active ? 'ok' : 'bad' }}">{{ $m->is_active ? 'Active' : 'Removed' }}</span></td>
                                <td style="text-align:right">
                                    @if(! $m->is_owner && $m->is_active)
                                        <x-ui.confirm-form :action="route('team.remove', $m->id)" method="DELETE" :message="'Remove '.$m->name.' from the team?'" class="inline">
                                            <button type="submit" class="link-bad">Remove</button>
                                        </x-ui.confirm-form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if($invitations->isNotEmpty())
            <section class="c-card">
                <div class="c-head"><div><h2 class="c-title">Pending invitations</h2><p class="c-sub">{{ $invitations->count() }} waiting to be accepted</p></div></div>
                <div style="overflow-x:auto">
                    <table class="c-table">
                        <tbody>
                        @foreach($invitations as $i)
                            <tr>
                                <td><b style="font-weight:600">{{ $i->email }}</b></td>
                                <td><span class="tag">{{ ucfirst($i->role) }}</span></td>
                                <td style="color:var(--muted);font-size:.82rem">Expires {{ $i->expires_at->diffForHumans() }}</td>
                                <td style="text-align:right">
                                    <x-ui.confirm-form :action="route('team.revoke', $i->id)" method="DELETE" message="Revoke this invitation?" class="inline">
                                        <button type="submit" class="link-bad">Revoke</button>
                                    </x-ui.confirm-form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif
        </div>

        <section class="c-card">
            <div class="c-head"><div><h2 class="c-title">Invite a teammate</h2><p class="c-sub">Roles are enforced on the server.</p></div></div>
            <form method="post" action="{{ route('team.invite') }}" class="c-pad stack-sm">
                @csrf
                <div class="field"><label class="label" for="inv-email">Email</label><input id="inv-email" class="input" type="email" name="email" required placeholder="teammate@company.com"></div>
                <div class="field"><label class="label" for="inv-role">Role</label>
                    <select id="inv-role" name="role" class="input">@foreach($roles as $r)<option value="{{ $r }}">{{ ucfirst($r) }}</option>@endforeach</select>
                    <p class="help">{{ collect($roles)->map(fn ($r) => ucfirst($r))->join(', ') }}</p>
                </div>
                <button type="submit" class="c-btn c-btn-brand">Send invite</button>
            </form>
        </section>
    </div>
</div>
@endsection
