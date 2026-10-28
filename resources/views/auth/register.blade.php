@extends('layouts.auth')
@section('title', 'Create account · LuvuPay')
@section('auth')
<style>
  .pw-bars { display: flex; gap: 6px; margin-top: 8px; }
  .pw-bars span {
    flex: 1; height: 6px; border-radius: 999px;
    background: #e7e8ee; transition: background .15s;
  }
  /* data-score = how many of 4 rules met → fill that many bars; data-tier = color */
  .pw-bars[data-tier="1"][data-score="1"] span:nth-child(1),
  .pw-bars[data-tier="1"][data-score="2"] span:nth-child(-n+2) { background: #f04438; }
  .pw-bars[data-tier="2"][data-score="3"] span:nth-child(-n+3) { background: #fdb022; }
  .pw-bars[data-tier="3"][data-score="4"] span { background: #12b76a; }
  .pw-hint { font-size: .75rem; margin-top: .35rem; min-height: 1.15em; }
  .pw-hint.is-red { color: #d92d20; font-weight: 600; }
  .pw-hint.is-yellow { color: #b54708; font-weight: 600; }
  .pw-hint.is-green { color: #067647; font-weight: 600; }
  .pw-hint.is-muted { color: #5f6472; font-weight: 400; }
  .pw-reqs { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; margin-top: 8px; font-size: .72rem; color: #5f6472; }
  .pw-req::before { content: "○ "; }
  .pw-req.is-ok { color: #067647; font-weight: 600; }
  .pw-req.is-ok::before { content: "✓ "; }
</style>

<div class="lp-card p-7 sm:p-8 shadow-[0_8px_30px_-12px_rgb(20_21_26/0.18)]">
    <h1 class="text-xl font-semibold tracking-tight">Create your merchant account</h1>
    <p class="mt-1.5 text-sm text-muted">Work email only. Your API key appears after you verify it</p>

    @if($errors->any())<div class="mt-5 lp-alert lp-alert-error" role="alert">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('register') }}" class="mt-6 space-y-4" novalidate id="register-form">
        @csrf
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="company_name" class="lp-label">Company</label>
                <input id="company_name" name="company_name" value="{{ old('company_name') }}" required class="lp-input" placeholder="Acme SaaS" autocomplete="organization">
                @error('company_name')<p class="mt-1 text-xs" style="color:#d92d20">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="name" class="lp-label">Your name</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="lp-input" placeholder="Alex Founder" autocomplete="name">
                @error('name')<p class="mt-1 text-xs" style="color:#d92d20">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="email" class="lp-label">Work email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="lp-input" placeholder="you@yourcompany.com">
            <p class="pw-hint is-muted" id="email-hint">Use a company domain (not free webmail)</p>
            @error('email')<p class="mt-1 text-xs" style="color:#d92d20">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="lp-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="lp-input" placeholder="At least 10 characters">

            <div class="pw-bars" id="pw-bars" data-tier="0" data-score="0" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="pw-reqs" id="pw-req-list">
                <span class="pw-req" id="req-lower">Lowercase letter</span>
                <span class="pw-req" id="req-upper">Uppercase letter</span>
                <span class="pw-req" id="req-digit">Number</span>
                <span class="pw-req" id="req-symbol">Symbol (!@#$…)</span>
                <span class="pw-req" id="req-len">At least 10 characters</span>
            </div>
            <p class="pw-hint is-muted" id="pw-strength-hint">Use upper &amp; lower case, a number, and a symbol (min 10 chars)</p>
            @error('password')<p class="mt-1 text-xs" style="color:#d92d20">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="lp-label">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="lp-input">
            <p class="pw-hint is-muted" id="pw-match-hint">&nbsp;</p>
        </div>

        <button type="submit" class="lp-btn lp-btn-primary w-full lp-btn-lg" id="register-submit" disabled>Create account</button>
        <p class="text-xs text-muted text-center">If anything goes wrong, no account is created — just submit again</p>
    </form>
</div>
<p class="mt-6 text-center text-sm text-muted">Already registered? <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">Sign in</a></p>

<script src="{{ asset('js/register.js') }}?v={{ @filemtime(public_path('js/register.js')) ?: time() }}" defer></script>
@endsection
