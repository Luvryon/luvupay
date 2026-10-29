@extends('layouts.docs')
@section('title', 'Security · LuvuPay')
@section('meta_description', 'LuvuPay security model: payout allow-lists, webhook URL locking, least-privilege API scopes, MFA, audit chains, and defense in depth for merchant accounts')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')
<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">

  {{-- HERO: panel gelap + diagram lapisan pertahanan --}}
  <header class="overflow-hidden rounded-3xl bg-ink text-white">
    <div class="grid items-center gap-10 p-8 sm:p-12 lg:grid-cols-2 lg:p-16">
      <div>
        <h1 class="text-4xl font-extrabold leading-[1.05] tracking-tight text-white sm:text-6xl" style="color:#fff">
          Five layers between an attacker and your money
        </h1>
        <p class="mt-6 max-w-md text-lg leading-relaxed text-white/70">
          Even with a stolen login or API key, the damage is capped. New payout emails wait 24 hours, keys only
          do what they were scoped to, and every action is written to a log that can’t be quietly edited
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
          <a href="#scenarios" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-ink">See how each attack is stopped</a>
          <a href="#incident" class="rounded-lg border border-white/30 px-5 py-3 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white">I think I’ve been compromised</a>
        </div>
      </div>

      <svg viewBox="0 0 400 400" role="img" aria-label="Concentric layers protecting your funds: edge, console, API keys, payouts" class="mx-auto h-auto w-full max-w-md">
        <g fill="none" stroke="white" stroke-width="1.5">
          <circle cx="200" cy="200" r="190" fill="white" fill-opacity=".04" stroke-opacity=".25"/>
          <circle cx="200" cy="200" r="154" fill="white" fill-opacity=".07" stroke-opacity=".3"/>
          <circle cx="200" cy="200" r="118" fill="white" fill-opacity=".1" stroke-opacity=".4"/>
          <circle cx="200" cy="200" r="82" fill="white" fill-opacity=".16" stroke-opacity=".5"/>
          <circle cx="200" cy="200" r="46" class="fill-brand-600" stroke="none"/>
        </g>
        <g fill="white" font-size="12" text-anchor="middle" font-family="inherit">
          <text x="200" y="32" fill-opacity=".75">Edge · HTTPS, headers, request IDs</text>
          <text x="200" y="68" fill-opacity=".8">Console · MFA, roles</text>
          <text x="200" y="104" fill-opacity=".85">API · scoped keys</text>
          <text x="200" y="140" fill-opacity=".9">Payouts · allow-list</text>
          <text x="200" y="205" font-size="14" font-weight="700">Funds</text>
        </g>
      </svg>
    </div>
  </header>

  {{-- SKENARIO: serangan → respons --}}
  <section id="scenarios" class="mt-20 scroll-mt-24" aria-labelledby="sc-title">
    <h2 id="sc-title" class="max-w-2xl text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">What happens when someone tries…</h2>

    {{-- Skenario utama: payout --}}
    <article class="mt-10 overflow-hidden rounded-2xl border border-line">
      <div class="grid lg:grid-cols-5">
        <div class="bg-wash p-7 sm:p-9 lg:col-span-2">
          <p class="text-sm font-semibold text-red-600">The attacker</p>
          <h3 class="mt-2 text-2xl font-bold leading-snug text-ink">Adds their own email as a payout destination</h3>
          <p class="mt-4 text-sm leading-relaxed text-muted">They’re already inside your console, so they can add the address. They just can’t use it yet</p>
        </div>
        <div class="p-7 sm:p-9 lg:col-span-3">
          <p class="text-sm font-semibold text-brand-600">LuvuPay</p>
          <ol class="mt-4 space-y-5 border-l-2 border-line pl-6">
            <li class="relative">
              <span class="absolute -left-[31px] top-1.5 h-3 w-3 rounded-full bg-ink"></span>
              <p class="font-semibold text-ink">Saved as <code class="font-mono text-sm">pending_verification</code></p>
              <p class="text-sm text-muted">Any payout to it fails with <code class="font-mono text-xs">payout_not_allowlisted</code>.</p>
            </li>
            <li class="relative">
              <span class="absolute -left-[31px] top-1.5 h-3 w-3 rounded-full bg-brand-600"></span>
              <p class="font-semibold text-ink">24 hours to notice</p>
              <p class="text-sm text-muted">Revoke the email and rotate keys any time in this window. Nothing has moved</p>
            </li>
            <li class="relative">
              <span class="absolute -left-[31px] top-1.5 h-3 w-3 rounded-full border-2 border-line bg-white"></span>
              <p class="font-semibold text-ink">Only then does it become <code class="font-mono text-sm">active</code></p>
              <p class="text-sm text-muted">Handled by <code class="font-mono text-xs">ActivatePayoutRecipientJob</code> · table <code class="font-mono text-xs">paypal_payout_recipients</code></p>
            </li>
          </ol>
        </div>
      </div>
    </article>

    {{-- Skenario lain: grid 2 kolom --}}
    @php
      $scenarios = [
        [
          'attack' => 'Points your webhook at their own server',
          'defense' => 'A new entitlement URL needs email confirmation and a cooling-off period before it goes live. After a suspected compromise, a lock window blocks rapid repeat changes',
          'tags' => ['pending_url', 'pending_url_activates_at', 'url_locked_until'],
        ],
        [
          'attack' => 'Steals an API key',
          'defense' => 'The key can only do what its scopes allow, and anything else returns 403. Rotate it in Console → Settings and the old key stops working at once. PayPal platform tokens can never make unrestricted withdrawals',
          'tags' => ['subscriptions:write', 'resale:write', 'export:read', '403 insufficient_scope'],
        ],
        [
          'attack' => 'Signs in to the console as your staff',
          'defense' => 'Argon2id password hashing, an emailed verification code, and optional TOTP MFA you can require for everyone. Roles (owner, admin, finance, developer, viewer) limit who can touch settings, exports, and team',
          'tags' => ['LUVUPAY_REQUIRE_MFA', 'merchant_web'],
        ],
        [
          'attack' => 'Replays a payment request',
          'defense' => 'Every write that moves money must carry an Idempotency-Key, so a retry or replay can’t capture or pay out twice',
          'tags' => ['Idempotency-Key'],
        ],
      ];
    @endphp

    <div class="mt-6 grid gap-6 md:grid-cols-2">
      @foreach ($scenarios as $s)
        <article class="flex flex-col overflow-hidden rounded-2xl border border-line">
          <div class="bg-wash px-6 py-5">
            <p class="text-sm font-semibold text-red-600">The attacker</p>
            <h3 class="mt-1 text-lg font-bold leading-snug text-ink">{{ $s['attack'] }}</h3>
          </div>
          <div class="flex flex-1 flex-col p-6">
            <p class="text-sm font-semibold text-brand-600">LuvuPay</p>
            <p class="mt-2 flex-1 text-sm leading-relaxed text-muted">{{ $s['defense'] }}</p>
            <ul class="mt-5 flex flex-wrap gap-2">
              @foreach ($s['tags'] as $tag)
                <li><code class="rounded bg-wash px-2 py-1 font-mono text-xs text-ink">{{ $tag }}</code></li>
              @endforeach
            </ul>
          </div>
        </article>
      @endforeach
    </div>

    {{-- Skenario penutup: lebar penuh --}}
    <article class="mt-6 overflow-hidden rounded-2xl border border-line">
      <div class="grid md:grid-cols-5">
        <div class="bg-wash px-6 py-5 md:col-span-2">
          <p class="text-sm font-semibold text-red-600">The attacker</p>
          <h3 class="mt-1 text-lg font-bold leading-snug text-ink">Covers their tracks afterwards</h3>
        </div>
        <div class="p-6 md:col-span-3">
          <p class="text-sm font-semibold text-brand-600">LuvuPay</p>
          <p class="mt-2 text-sm leading-relaxed text-muted">Audit logs are hash-chained, so each entry depends on the one before it. Editing or deleting one breaks the chain and shows. Exports and deactivation flows let you offboard cleanly, and data is kept only for the policy retention window</p>
        </div>
      </div>
    </article>

    <p class="mt-6 text-sm text-muted max-w-3xl">
      Always on: security headers, a request ID on every response, trusted-proxy support behind load balancers, HTTPS-only webhook targets in production.
      Health and Prometheus metrics are token-gated and not public
    </p>
  </section>

  {{-- INCIDENT RESPONSE --}}
  <section id="incident" class="mt-20 scroll-mt-24 rounded-3xl bg-wash p-8 sm:p-12" aria-labelledby="inc-title">
    <h2 id="inc-title" class="text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">If you think you’ve been compromised</h2>
    <p class="mt-3 max-w-xl text-muted">Do these in order. The first two cut off the most damage</p>

    @php
      $steps = [
        ['Rotate your API keys', 'Console → Settings. Old keys stop working immediately'],
        ['Revoke unknown payout emails', 'Check both pending and active recipients'],
        ['Confirm your webhook URL', 'Lock it if you’re unsure'],
        ['Re-verify MFA for all users', 'Turn it on for anyone who hasn’t'],
        ['Contact support', 'Include your merchant ID, rough timestamps, and request IDs from the <code class="font-mono text-xs">X-Request-Id</code> header.'],
      ];
    @endphp
    <ul class="mt-8 grid list-none gap-4 sm:grid-cols-2 lg:grid-cols-5" style="list-style:none;padding:0">
      @foreach ($steps as $i => $step)
        <li class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-line" style="list-style:none">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $i + 1 }}</span>
          <p class="mt-4 font-semibold leading-snug text-ink">{{ $step[0] }}</p>
          <p class="mt-1 text-sm leading-relaxed text-muted">{!! $step[1] !!}</p>
        </li>
      @endforeach
    </ul>

    <a href="/support" class="mt-8 inline-flex rounded-lg bg-ink px-5 py-3 text-sm font-semibold text-white no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2" style="color:#fff;text-decoration:none">Contact support</a>
  </section>

  <nav class="mt-12 flex flex-wrap justify-between gap-4 text-sm" aria-label="Page navigation">
    <a href="/docs/webhooks" class="font-medium text-brand-600">← Webhooks</a>
    <span class="flex gap-6">
      <a href="/docs/api" class="font-medium text-brand-600">API reference</a>
      <a href="/privacy" class="font-medium text-brand-600">Privacy</a>
    </span>
  </nav>
</div>
@endsection