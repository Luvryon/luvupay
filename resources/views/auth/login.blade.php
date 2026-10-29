@extends('layouts.auth')
@section('title', 'Sign in · LuvuPay')
@section('auth')
<div class="lp-card p-7 sm:p-8 shadow-[0_8px_30px_-12px_rgb(20_21_26/0.18)]">
    <h1 class="text-xl font-semibold tracking-tight">Sign in to LuvuPay</h1>
    <p class="mt-1.5 text-sm text-muted">Welcome back. Use your verified work email</p>

    @if($errors->any())<div class="mt-5 lp-alert lp-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('status'))<div class="mt-5 lp-alert lp-alert-ok">{{ session('status') }}</div>@endif

    <form method="post" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="lp-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus class="lp-input" placeholder="you@company.com" @error('email') aria-invalid="true" @enderror>
        </div>
        <div>
            <label for="password" class="lp-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="your password" class="lp-input">
        </div>
        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="remember" value="1" class="size-4 rounded border-line accent-brand-600"> Keep me signed in</label>
        <button class="lp-btn lp-btn-primary w-full lp-btn-lg">Continue</button>
    </form>
</div>
<p class="mt-6 text-center text-sm text-muted">New to LuvuPay? <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:text-brand-800">Create an account</a></p>
@endsection
