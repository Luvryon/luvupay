@extends('layouts.auth')
@section('title', 'Two-factor authentication · LuvuPay')
@section('auth')
<div class="lp-card p-7 sm:p-8 shadow-[0_8px_30px_-12px_rgb(20_21_26/0.18)]">
    <h1 class="text-xl font-semibold tracking-tight">Two-step verification</h1>
    <p class="mt-1.5 text-sm text-muted">Enter the 6-digit code from your authenticator app, or a recovery code</p>
    @if($errors->any())<div class="mt-5 lp-alert lp-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('mfa.verify') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="code" class="lp-label">Code</label>
            <input id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="text" maxlength="32" class="lp-input text-center font-mono text-xl tracking-widest py-3" placeholder="000000">
        </div>
        <button class="lp-btn lp-btn-primary w-full lp-btn-lg">Verify</button>
    </form>
    <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="text-muted hover:text-ink">Back to sign in</a></p>
</div>
@endsection
