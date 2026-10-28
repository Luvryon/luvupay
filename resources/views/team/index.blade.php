@extends('layouts.console')
@section('title', 'Team')
@section('heading', 'Team')
@section('content')
<div class="stack">
    <div>
        <h1 class="page-title">Team</h1>
        <p class="page-sub">Invite operators and control what each role can change on this merchant</p>
    </div>

    <div class="team-cols stack" id="team-page" data-url="{{ route('team.data') }}" data-base="{{ url('/team') }}">
        <div class="stack">
            <section class="c-card">
                <div class="c-head"><div><h2 class="c-title">Members</h2><p class="c-sub" id="member-count">Loading members</p></div></div>
                <div style="overflow-x:auto">
                    <table class="c-table">
                        <thead><tr><th>Member</th><th>Role</th><th>Two-factor</th><th>Status</th><th></th></tr></thead>
                        <tbody id="member-rows"></tbody>
                    </table>
                </div>
            </section>

            <section class="c-card" id="invite-card" hidden>
                <div class="c-head"><div><h2 class="c-title">Pending invitations</h2><p class="c-sub" id="invite-count"></p></div></div>
                <div style="overflow-x:auto">
                    <table class="c-table"><tbody id="invite-rows"></tbody></table>
                </div>
            </section>
        </div>

        <section class="c-card">
            <div class="c-head"><div><h2 class="c-title">Invite a teammate</h2><p class="c-sub">Roles are enforced on the server</p></div></div>
            <form method="post" action="{{ route('team.invite') }}" class="c-pad stack-sm" id="invite-form">
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
@push('scripts')
<script src="/js/team.js?v={{ @filemtime(public_path('js/team.js')) }}" defer></script>
@endpush
@endsection
