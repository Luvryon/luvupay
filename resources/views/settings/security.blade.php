@extends('layouts.console')
@section('title', 'Security')
@section('heading', 'Security')
@section('content')
<div class="narrow stack">
    <div>
        <h1 class="page-title">Security</h1>
        <p class="page-sub">Protect your account and review what your role can change.</p>
    </div>

    @if($recovery)
        <div class="c-alert" style="display:block;padding:20px 24px">
            <b>Save your recovery codes</b>
            <p style="margin:4px 0 0">Each code works once if you lose your authenticator. They are shown only now.</p>
            <div class="codes">@foreach($recovery as $c)<code>{{ $c }}</code>@endforeach</div>
        </div>
    @endif

    <section class="c-card">
        <div class="c-head">
            <div><h2 class="c-title">Two-factor authentication</h2><p class="c-sub">Time-based codes from Google Authenticator, 1Password, or Authy.</p></div>
            <span class="tag dot {{ $user->hasMfa() ? 'ok' : 'warn' }}">{{ $user->hasMfa() ? 'Enabled' : 'Off' }}</span>
        </div>
        <div class="c-pad">
            @if($user->hasMfa())
                <form method="post" action="{{ route('security.mfa.disable') }}" class="form-row fr-2" autocomplete="off">
                    @csrf
                    <div class="field"><label class="label" for="mfa-pw">Confirm your password to turn it off</label><input id="mfa-pw" class="input" type="password" name="password" required autocomplete="current-password"></div>
                    <button type="submit" class="c-btn c-btn-bad">Turn off</button>
                </form>
            @elseif($setup)
                <div class="step">
                    <p style="margin:0;font-weight:600"><span class="step-n">1</span>Add this key in your authenticator app</p>
                    <code class="secret">{{ $setup['secret'] }}</code>
                    <p class="help">Or open the <a href="{{ $setup['uri'] }}" style="font-weight:600;text-decoration:underline">otpauth link</a> on your phone.</p>
                </div>
                <div class="step">
                    <p style="margin:0;font-weight:600"><span class="step-n">2</span>Enter the 6-digit code it shows</p>
                    <form method="post" action="{{ route('security.mfa.confirm') }}" style="display:flex;flex-wrap:wrap;gap:12px">
                        @csrf
                        <input name="code" class="input code-in" inputmode="numeric" maxlength="6" required placeholder="000000" autocomplete="one-time-code" aria-label="6-digit code">
                        <button type="submit" class="c-btn c-btn-brand">Turn on</button>
                    </form>
                </div>
            @else
                <p class="help" style="margin-bottom:16px">Add a second step at sign-in so a stolen password isn't enough to get into your account.</p>
                <form method="post" action="{{ route('security.mfa.start') }}">@csrf<button type="submit" class="c-btn c-btn-brand">Set up two-factor</button></form>
            @endif
        </div>
    </section>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">Your access</h2><p class="c-sub">Roles limit what you can change in the console. Ask an owner to change yours.</p></div></div>
        <div class="rows">
            <div><span style="color:var(--muted)">Signed in as</span><b>{{ $user->email }}</b></div>
            <div><span style="color:var(--muted)">Role</span><span class="tag dark">{{ $user->role }}</span></div>
        </div>
    </section>
</div>
@endsection
