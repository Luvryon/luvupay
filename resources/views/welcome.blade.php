@extends('layouts.site')
@section('title', 'LuvuPay · Ethical subscription infrastructure')
@section('content')

@php
    $features = [
        ['48-hour Second Chance', 'Every recurring charge is held for a short window. One click splits the money between the customer, a charity and the merchant, using the point basis you set', '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>'],
        ['Resale of unused access', 'Customers can sell the rest of a billing cycle to someone else. Access moves through the Entitlement Bridge and money moves through the ledger, with no fake refund', '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>'],
        ['Adaptive Nudge', 'A reminder 72 hours before renewal plus a trust score, so Second Chance is not abused again and again', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>'],
    ];
    $developerPoints = [
        ['Idempotency-Key on every request', 'Retry safely. A different body with the same key is rejected, never silently executed'],
        ['A webhook inbox that never loses events', 'Verified, stored, then processed. Failures retry with backoff, land in a dead letter queue and replay with one command'],
        ['Scoped API keys and per-merchant rate limits', 'Give each key only the access it needs'],
    ];
    $ecosystem = ['PayPal' => 'Sandbox', 'Laravel' => 'Package', 'PostgreSQL' => 'Driver', 'Redis' => 'Queue', 'FrankenPHP' => 'Runtime'];
    $security = [
        ['Double-entry ledger', 'Append-only. Every journal balances, cannot be edited and is reconciled hourly'],
        ['HMAC audit chain', 'Each change is signed into a chain, so tampering is caught by audit:verify'],
        ['MFA, roles and teams', 'TOTP with recovery codes. Owner, Admin, Finance, Developer and Viewer'],
        ['PayPal reconciliation', 'Payouts left hanging are checked against PayPal and settled automatically'],
    ];
@endphp

{{-- Hero --}}
<section class="lp-hero">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:pb-28 lg:pt-20">
        <div>
            <a href="/docs/sandbox" class="lp-pill"><span class="size-1.5 rounded-full bg-emerald-500"></span>Ready on PayPal Sandbox today</a>
            <h1 class="mt-6 text-[2.7rem] font-bold leading-[1.04] tracking-[-0.035em] text-ink sm:text-[3.5rem]">
                Build payment experiences that give a <span class="text-brand-600">second chance</span>
            </h1>
            <p class="mt-6 max-w-lg text-[1.05rem] leading-relaxed text-muted">One API for the 48-hour Second Chance, resale of unused access and money movement you can audit to the cent. Designed for developers and trusted by finance teams</p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark lp-btn-lg">Start building <span aria-hidden="true">→</span></a>
                <a href="/docs" class="lp-btn lp-btn-outline lp-btn-lg">Read the docs</a>
            </div>
        </div>

        {{-- Product preview --}}
        <div class="lp-stage" aria-hidden="true">
            <div class="lp-window">
                <aside class="lp-window-side">
                    <div class="flex items-center gap-2 font-semibold">@include('partials.logo', ['size' => 20]) LuvuPay</div>
                    <ul>
                        <li class="is-active">Dashboard</li><li>Transactions</li><li>Customers</li><li>Products</li><li>Webhooks</li><li>Settings</li>
                    </ul>
                </aside>
                <div class="lp-window-main">
                    <div class="flex items-center justify-between">
                        <strong class="text-[0.95rem]">Overview</strong>
                        <span class="lp-tabs"><i class="is-on">7 days</i><i>30 days</i><i>3 months</i></span>
                    </div>
                    <div class="mt-4 rounded-xl border border-line p-4">
                        <div class="text-[11px] text-muted">Payment volume</div>
                        <div class="mt-1 flex items-baseline gap-2"><span class="text-2xl font-bold tracking-tight">$124,530</span><span class="text-[11px] font-semibold text-emerald-600">▲ 12.5% vs last period</span></div>
                        <svg viewBox="0 0 400 90" class="mt-3 w-full" preserveAspectRatio="none">
                            <defs><linearGradient id="lpg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#7456fa" stop-opacity=".28"/><stop offset="1" stop-color="#7456fa" stop-opacity="0"/></linearGradient></defs>
                            <path d="M0 70 C40 62 60 72 100 56 S170 50 210 42 S290 38 330 22 S380 14 400 8 L400 90 L0 90Z" fill="url(#lpg)"/>
                            <path d="M0 70 C40 62 60 72 100 56 S170 50 210 42 S290 38 330 22 S380 14 400 8" fill="none" stroke="#5b3df5" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-3">
                        @foreach([['Successful', '4,832', '+12%'], ['Second Chance', '612', '+28%'], ['Resold access', '321', '+10%']] as [$k, $v, $d])
                            <div class="rounded-xl border border-line p-3"><div class="text-[10px] text-muted">{{ $k }}</div><div class="text-lg font-bold">{{ $v }}</div><div class="text-[10px] font-semibold text-emerald-600">{{ $d }}</div></div>
                        @endforeach
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-line text-[11px]">
                        @foreach([['tr_3f8a20c', 'Martin H.', 'Second Chance', 'Settled', 'ok'], ['tr_0b0e9ab', 'Salsabila A.', 'Resale', 'Settled', 'ok'], ['tr_1d1ac09', 'Dimas R.', 'Held', 'Pending', 'warn']] as [$id, $who, $kind, $status, $tone])
                            <div class="grid grid-cols-[1.2fr_1fr_1fr_auto] gap-2 border-b border-line px-3 py-2 last:border-0"><span class="font-mono text-muted">{{ $id }}</span><span>{{ $who }}</span><span class="text-muted">{{ $kind }}</span><span class="lp-badge {{ $tone === 'ok' ? 'lp-badge-ok' : 'lp-badge-warn' }}">{{ $status }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Product --}}
<section id="product" class="border-t border-line bg-wash py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.6fr)] lg:items-center">
        <div>
            <p class="lp-eyebrow">Built for growth</p>
            <h2 class="lp-h2">Three subscription problems, one infrastructure layer</h2>
            <p class="mt-4 leading-relaxed text-muted">Customers forget to cancel, paid access goes to waste and merchants are blind to behavior. LuvuPay solves all three without you building a financial engine</p>
            <a href="/docs" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">Learn how it works <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($features as [$title, $text, $icon])
                <article class="lp-card lp-card-pad">
                    <span class="lp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg></span>
                    <h3 class="mt-5 font-semibold tracking-tight">{{ $title }}</h3>
                    <p class="mt-2 text-[0.82rem] leading-relaxed text-muted">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Developers --}}
<section id="developers" class="py-24">
    <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2">
        <div class="lp-terminal" x-data="{ copied: false }">
            <div class="lp-terminal-bar">
                <span>curl</span>
                <button type="button" @click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1600)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
<pre x-ref="code"><span class="c"># Create a subscription, safe to retry</span>
<span class="k">curl</span> -X POST https://api.luvupay.test/api/v1/subscriptions \
  -H <span class="s">"Authorization: Bearer sk_live_xxx"</span> \
  -H <span class="s">"Idempotency-Key: 9f1c7a52-order-1042"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{"plan_id":"P-PRO","email":"ana@acme.com"}'</span>

<span class="c"># 201 Created</span>
{
  <span class="n">"id"</span>: <span class="s">"sub_01J8..."</span>,
  <span class="n">"status"</span>: <span class="s">"approval_pending"</span>,
  <span class="n">"approval_url"</span>: <span class="s">"https://www.sandbox.paypal.com/..."</span>
}</pre>
            <div class="lp-terminal-foot"><span class="size-2 rounded-full bg-emerald-400"></span> Response 200 OK</div>
        </div>
        <div>
            <p class="lp-eyebrow">Developer first</p>
            <h2 class="lp-h2">An API that is safe to retry, webhooks that never get lost</h2>
            <p class="mt-4 leading-relaxed text-muted">Idempotency keys on every money request, a durable webhook inbox and scoped API keys for each merchant</p>
            <ul class="mt-8 space-y-5">
                @foreach($developerPoints as [$title, $text])
                    <li class="flex gap-3">
                        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700">@include('partials.icon', ['name' => 'check', 'size' => 14])</span>
                        <div><div class="font-semibold">{{ $title }}</div><p class="mt-0.5 text-sm leading-relaxed text-muted">{{ $text }}</p></div>
                    </li>
                @endforeach
            </ul>
            <a href="/docs/api" class="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">View API reference <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>

{{-- Ecosystem --}}
<section class="border-t border-line py-20">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <p class="lp-eyebrow">Ecosystem</p>
        <h2 class="lp-h2 max-w-xl">Integrated with the tools you already know and trust</h2>
        <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach($ecosystem as $name => $kind)
                <div class="lp-card flex items-center gap-3 p-4">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700">{{ strtoupper(substr($name, 0, 1)) }}</span>
                    <div><div class="text-sm font-semibold">{{ $name }}</div><div class="text-xs text-muted">{{ $kind }}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Security --}}
<section id="security" class="lp-dark py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.6fr)] lg:items-center">
        <div>
            <p class="lp-eyebrow text-brand-300">Security and trust</p>
            <h2 class="lp-h2 text-white">Built to be audited, not just to run</h2>
            <p class="mt-4 leading-relaxed text-white/65">Controls that usually arrive after an incident are here from day one, even in the sandbox</p>
            <a href="/docs/security" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-white">Read about our security <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($security as [$title, $text])
                <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                    <h3 class="text-sm font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-white/60">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Call to action --}}
<section class="py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-10 overflow-hidden px-5 sm:px-8 lg:grid-cols-2">
        <div>
            <p class="lp-eyebrow">Ready to start</p>
            <h2 class="lp-h2">Start in the sandbox, go live when you are ready</h2>
            <p class="mt-4 max-w-md text-muted">No real money moves until you decide it should</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark lp-btn-lg">Create merchant account <span aria-hidden="true">→</span></a>
                <a href="/docs/sandbox" class="lp-btn lp-btn-outline lp-btn-lg">Sandbox guide</a>
            </div>
        </div>
        <div class="lp-cta-panel" aria-hidden="true">
            <div>
                <p class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Build. Test. Grow</p>
                <p class="mt-2 text-xs uppercase tracking-[0.2em] text-white/50">Subscriptions with a conscience</p>
            </div>
        </div>
    </div>
</section>
@endsection
