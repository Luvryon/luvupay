@extends('layouts.auth')
@section('title', 'Join team · LuvuPay')
@section('auth')
<div class="lp-card p-7 sm:p-8 shadow-[0_8px_30px_-12px_rgb(20_21_26/0.18)]" x-data="acceptForm()">
    <h1 class="text-xl font-semibold tracking-tight">Join {{ $invitation->merchant->name }}</h1>
    <p class="mt-1.5 text-sm text-muted">You were invited as <strong class="text-ink">{{ $invitation->role }}</strong> using {{ $invitation->email }}.</p>
    @if($errors->any())<div class="mt-5 lp-alert lp-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('team.accept.store', $token) }}" class="mt-6 space-y-4" novalidate
          x-on:submit="if (!canSubmit) { $event.preventDefault(); return }">
        @csrf
        <div>
            <label for="name" class="lp-label">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required class="lp-input" autocomplete="name">
        </div>
        <div>
            <label for="password" class="lp-label">Password</label>
            <input id="password" type="password" name="password" x-model="pw" required autocomplete="new-password" class="lp-input" placeholder="At least 10 characters">
            <div class="mt-2 flex gap-1.5" aria-hidden="true">
                <span x-bind:style="'height:6px;flex:1;border-radius:999px;background:' + (pw.length > 3 ? (pw.length > 20 ? '#12b76a' : (pw.length > 10 ? '#fdb022' : '#f04438')) : '#e7e8ee')"></span>
                <span x-bind:style="'height:6px;flex:1;border-radius:999px;background:' + (pw.length > 10 ? (pw.length > 20 ? '#12b76a' : '#fdb022') : '#e7e8ee')"></span>
                <span x-bind:style="'height:6px;flex:1;border-radius:999px;background:' + (pw.length > 20 ? '#12b76a' : '#e7e8ee')"></span>
            </div>
            <p class="lp-hint mt-1.5" x-show="pw.length > 0" x-cloak
               x-bind:style="pw.length > 20 ? 'color:#067647' : (pw.length > 10 ? 'color:#b54708' : 'color:#d92d20')"
               x-text="pw.length > 20 ? 'Strong password' : (pw.length > 10 ? 'Decent — add more characters' : 'Too short — need at least 10 characters')"></p>
            <p class="lp-hint" x-show="pw.length === 0">Min. 10 characters, upper & lower case and a number</p>
        </div>
        <div>
            <label for="pc" class="lp-label">Confirm password</label>
            <input id="pc" type="password" name="password_confirmation" x-model="pw2" required autocomplete="new-password" class="lp-input"
                   x-bind:aria-invalid="pw2.length > 0 && !passwordsMatch ? 'true' : 'false'">
            <p class="lp-hint mt-1.5" x-show="pw2.length > 0" x-cloak
               x-bind:style="passwordsMatch ? 'color:#067647;font-weight:600' : 'color:#d92d20;font-weight:600'"
               x-text="passwordsMatch ? '✓ Passwords match' : '✗ Passwords do not match'"></p>
        </div>
        <button type="submit" class="lp-btn lp-btn-primary w-full lp-btn-lg" x-bind:disabled="!canSubmit">Accept invitation</button>
    </form>
</div>
<script>
function acceptForm() {
  return {
    pw: '',
    pw2: '',
    get passwordsMatch() { return this.pw.length > 0 && this.pw === this.pw2; },
    get complexityOk() {
      var s = 0;
      if (this.pw.length >= 10) s++;
      if (/[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw)) s++;
      if (/\d/.test(this.pw)) s++;
      if (/[^A-Za-z0-9]/.test(this.pw) || this.pw.length >= 14) s++;
      return s >= 3;
    },
    get canSubmit() { return this.passwordsMatch && this.complexityOk; }
  };
}
</script>
@endsection
