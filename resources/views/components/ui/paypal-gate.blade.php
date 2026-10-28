{{-- Hard gate UI — no query-string unlock --}}
<section class="mx-auto flex max-w-lg flex-col items-center px-5 py-20 text-center" aria-label="PayPal connection required">
    <div class="mb-6 grid size-16 place-items-center rounded-2xl bg-[#ffc439]/15 text-3xl">🔐</div>
    <h1 class="text-2xl font-bold tracking-tight text-white">Connect PayPal to continue</h1>
    <p class="mt-3 text-sm leading-relaxed text-muted">
        LuvuPay requires a <strong class="text-white">server-verified</strong> PayPal Business link before charges, payouts, or the console.
        Query parameters and localStorage cannot unlock this gate
    </p>
    <a href="{{ route('oauth.paypal.redirect') }}"
       class="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#ffc439] px-4 py-3.5 text-sm font-bold text-[#003087] shadow-lg transition hover:brightness-105">
        Connect PayPal Business (OAuth)
    </a>
    <form method="POST" action="{{ route('oauth.paypal.sandbox-connect') }}" class="mt-3 w-full">
        @csrf
        <input type="hidden" name="confirm" value="1" />
        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl border border-edge bg-white/5 px-4 py-3 text-sm font-semibold text-white hover:bg-white/10">
            Connect PayPal Sandbox (Demo · CSRF POST)
        </button>
    </form>
    <p class="mt-3 text-[11px] text-faint">Rate-limited · session bound to your user · not a live Business account in demo mode</p>
    <a href="{{ route('settings') }}" class="mt-6 text-sm text-brand-soft hover:underline">Open Settings to connect PayPal →</a>
</section>
