@extends('layouts.auth')
@section('title', 'Verify email · LuvuPay')
@section('auth')
@php $from = config('mail.from.address') ?: 'contact@domainku.com'; @endphp
<div class="lp-card p-7 sm:p-8 shadow-[0_8px_30px_-12px_rgb(20_21_26/0.18)]">
    <h1 class="text-xl font-semibold tracking-tight">Check your email</h1>
    <p class="mt-1.5 text-sm text-muted leading-relaxed">We sent a 6-digit code from <span class="font-mono text-ink">{{ $from }}</span>. Your API key is shown once you verify.</p>
    @if($errors->any())<div class="mt-5 lp-alert lp-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('status'))<div class="mt-5 lp-alert lp-alert-ok">{{ session('status') }}</div>@endif
    <form method="post" action="{{ route('verification.verify') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="code" class="lp-label">Verification code</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" autofocus placeholder="000000" class="lp-input text-center font-mono text-2xl tracking-[0.5em] py-3">
        </div>
        <button class="lp-btn lp-btn-primary w-full lp-btn-lg">Verify email</button>
    </form>
    <form method="post" action="{{ route('verification.resend') }}" class="mt-4 text-center">@csrf
        <button class="text-sm font-semibold text-brand-700 hover:text-brand-800">Send a new code</button>
    </form>
</div>
@endsection
