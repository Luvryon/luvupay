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
    $ecosystem = [
        [
            'name' => 'PayPal',
            'kind' => 'Sandbox',
            'svg' => '<path fill="#003087" d="M7.2 3.2h4.6c2.55 0 4.3.55 5.25 1.7.9 1.05 1.15 2.5.9 4.35-.85 4.85-3.95 6.55-8.05 6.55H7.55L6 20.8H2.8L5.55 3.2H7.2zm1.35 2.75l-1.2 6.85h2.25c2.35 0 4.05-.65 4.7-3.35.3-1.2.15-2.05-.35-2.55-.55-.55-1.5-1-3.35-1H8.55z"/><path fill="#009cde" d="M16.1 9.6c-.5 2.75-2.1 4.65-4.55 5.5l-.9 5.5h3.25l.65-3.85h.5c2.75 0 4.85-1.15 5.5-4.25.3-1.4.1-2.5-.5-3.25-.4-.5-.95-.9-1.7-1.15.1-.5.15-1 .05-1.4C18.1 4.6 16.7 3.6 14.3 3.6H9.7L7.55 17h3.25l1-6.35h.4c1.75 0 3-.5 3.5-2.4.2-.75.15-1.35-.2-1.75-.25-.3-.65-.5-1.15-.6.1-.25.15-.5.15-.75z"/>',
        ],
        [
            'name' => 'Laravel',
            'kind' => 'Package',
            'svg' => '<path fill="#FF2D20" d="M17.3 7.55 12 4.5 6.7 7.55v6.9L12 17.5l5.3-3.05V7.55zM12 5.85l3.85 2.2-1.55.9-2.3-1.3-2.3 1.3-1.55-.9L12 5.85zm-4.15 3.35 1.55.9v2.65l-1.55-.9V9.2zm0 4.15 3.15 1.8v2.2L7.85 15.6v-2.25zm4.15 4 3.15-1.8v2.25L12 18.35v-1zm4.15-1.95-1.55.9v-2.65l1.55-.9v2.65zm0-4.15-3.15-1.8 1.55-.9 1.6.9v1.8zm-4.15-.25 2.3 1.3-2.3 1.35-2.3-1.35 2.3-1.3z"/>',
        ],
        [
            'name' => 'PostgreSQL',
            'kind' => 'Driver',
            'svg' => '<path fill="#336791" d="M17.6 9.2c-.15-2.4-1.65-3.7-4.05-3.95.35-.45.55-1 .55-1.6C14.1 2.15 12.85 1 11.25 1S8.4 2.15 8.4 3.65c0 .55.2 1.05.5 1.5C6.7 5.5 5.3 6.85 5.15 9.4c-.05.7-.05 1.35.1 2.05.35 1.7 1.35 2.85 2.85 3.25-.15.45-.25.95-.25 1.45 0 1.85 1.15 3.15 2.95 3.55v1.9c0 .55.45 1 1 1s1-.45 1-1v-1.9c1.8-.4 2.95-1.7 2.95-3.55 0-.45-.08-.9-.22-1.3 1.4-.45 2.35-1.6 2.65-3.25.2-.75.25-1.45.22-2.2zm-6.35-5.9c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-1.85 12.05c-.85-.2-1.35-.9-1.35-1.85 0-.35.1-.7.25-1 .55.25 1.15.4 1.8.4.35 0 .7-.05 1.05-.1-.15 1.35-.7 2.2-1.75 2.55zm5.7 0c-1.05-.35-1.6-1.2-1.75-2.55.35.05.7.1 1.05.1.65 0 1.25-.15 1.8-.4.15.3.25.65.25 1 0 .95-.5 1.65-1.35 1.85z"/>',
        ],
        [
            'name' => 'Redis',
            'kind' => 'Queue',
            'svg' => '<path fill="#DC382D" d="M4 9.2 12 5.5l8 3.7-8 3.7L4 9.2zm0 2.3 8 3.7 8-3.7v2.5L12 17.7 4 14v-2.5zm0 4 8 3.7 8-3.7v2.5L12 21.7 4 18v-2.5z"/>',
        ],
        [
            'name' => 'FrankenPHP',
            'kind' => 'Runtime',
            'svg' => '<path fill="#7C3AED" d="M12 2.2 4.8 6.2v8.1L12 18.3l7.2-4V6.2L12 2.2zm0 2.3 5 2.8v5.5l-5 2.8-5-2.8V7.3l5-2.8z"/><path fill="#A78BFA" d="M12 7.2 9 8.9v3.4l3 1.7 3-1.7V8.9L12 7.2z"/>',
        ],
        [
            'name' => 'React',
            'kind' => 'Frontend',
            'svg' => '<circle cx="12" cy="12" r="2.2" fill="#61DAFB"/><g fill="none" stroke="#61DAFB" stroke-width="1.2"><ellipse cx="12" cy="12" rx="10" ry="4.2"/><ellipse cx="12" cy="12" rx="10" ry="4.2" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4.2" transform="rotate(120 12 12)"/></g>',
        ],
        [
            'name' => 'Next.js',
            'kind' => 'Framework',
            'svg' => '<circle cx="12" cy="12" r="10" fill="#000"/><path fill="#fff" d="M14.5 16.8 9.2 8h2.1l5.3 8.8H14.5z"/><path fill="#fff" d="M15.2 8h1.9v8.8h-1.9z"/>',
        ],
        [
            'name' => 'Angular',
            'kind' => 'Framework',
            'svg' => '<path fill="#DD0031" d="M12 2.5 3.5 5.4l1.3 11.2L12 21.5l7.2-4.9 1.3-11.2L12 2.5z"/><path fill="#C3002F" d="M12 2.5v19l7.2-4.9 1.3-11.2L12 2.5z"/><path fill="#fff" d="m12 6.2 3.6 8.1h-1.7l-.7-1.7H10.8l-.7 1.7H8.4L12 6.2zm0 2.6-1.1 2.7h2.2L12 8.8z"/>',
        ],
        [
            'name' => 'Nebius AI',
            'kind' => 'Compute',
            'svg' => '<rect x="3" y="3" width="18" height="18" rx="4" fill="#1a1a2e"/><path fill="#6C5CE7" d="M7 16.5 12 7l5 9.5h-2.1l-1.1-2.2H10.2L9.1 16.5H7zm3.5-3.7h3L12 9.5l-1.5 3.3z"/>',
        ],
    ];

    $security = [
        ['Double-entry ledger', 'Append-only. Every journal balances, cannot be edited and is reconciled hourly'],
        ['HMAC audit chain', 'Each change is signed into a chain, so tampering is caught by audit:verify'],
        ['MFA, roles and teams', 'TOTP with recovery codes. Owner, Admin, Finance, Developer and Viewer'],
        ['PayPal reconciliation', 'Payouts left hanging are checked against PayPal and settled automatically'],
    ];
@endphp

{{-- Hero --}}
<section class="lp-hero relative overflow-hidden">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:pb-28 lg:pt-20">
        <div>
            <h1 class="text-[2.7rem] font-bold leading-[1.04] tracking-[-0.035em] text-ink sm:text-[3.5rem]">
                Build payment experiences that give a <span class="text-brand-600">second chance</span>
            </h1>
            <p class="mt-6 max-w-lg text-[1.05rem] leading-relaxed text-muted">One API for the 48-hour Second Chance, resale of unused access and money movement you can audit to the cent. Designed for developers and trusted by finance teams</p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark lp-btn-lg">Start building <span aria-hidden="true">→</span></a>
                <a href="/docs" class="lp-btn lp-btn-outline lp-btn-lg">Read the docs</a>
            </div>
        </div>

        {{-- Product preview --}}
        <div class="lp-stage relative" aria-hidden="true">
            {{-- background: kisi kotak-kotak, kotak miring, cahaya ungu --}}
            <div class="pointer-events-none absolute -inset-x-8 -inset-y-14 lg:-right-32" style="z-index:0">
                <svg class="h-full w-full" viewBox="0 0 700 600" preserveAspectRatio="xMidYMid slice" fill="none">
                    <defs>
                        <pattern id="hx-grid" width="36" height="36" patternUnits="userSpaceOnUse">
                            <path d="M36 0H0V36" stroke="#5b3df5" stroke-opacity=".11" stroke-width="1"/>
                        </pattern>
                        <radialGradient id="hx-fade" cx="62%" cy="48%" r="60%">
                            <stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
                        </radialGradient>
                        <mask id="hx-mask"><rect width="700" height="600" fill="url(#hx-fade)"/></mask>
                        <linearGradient id="hx-sq1" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#a78bfa" stop-opacity=".30"/><stop offset="1" stop-color="#5b3df5" stop-opacity=".06"/>
                        </linearGradient>
                        <linearGradient id="hx-sq2" x1="0" y1="1" x2="1" y2="0">
                            <stop offset="0" stop-color="#c4b5fd" stop-opacity=".35"/><stop offset="1" stop-color="#ede9fe" stop-opacity=".05"/>
                        </linearGradient>
                        <radialGradient id="hx-glow" cx="50%" cy="50%" r="50%">
                            <stop offset="0" stop-color="#7456fa" stop-opacity=".22"/><stop offset="1" stop-color="#7456fa" stop-opacity="0"/>
                        </radialGradient>
                    </defs>

                    <circle cx="430" cy="270" r="260" fill="url(#hx-glow)"/>
                    <rect width="700" height="600" fill="url(#hx-grid)" mask="url(#hx-mask)"/>

                    <rect x="470" y="40"  width="170" height="170" rx="28" fill="url(#hx-sq1)" stroke="#a78bfa" stroke-opacity=".35" transform="rotate(12 555 125)"/>
                    <rect x="520" y="150" width="120" height="120" rx="22" fill="url(#hx-sq2)" stroke="#c4b5fd" stroke-opacity=".45" transform="rotate(-8 580 210)"/>
                    <rect x="20"  y="420" width="150" height="150" rx="26" fill="url(#hx-sq2)" stroke="#c4b5fd" stroke-opacity=".40" transform="rotate(-10 95 495)"/>
                    <rect x="430" y="440" width="110" height="110" rx="20" fill="url(#hx-sq1)" stroke="#a78bfa" stroke-opacity=".30" transform="rotate(16 485 495)"/>

                    <rect x="610" y="300" width="22" height="22" rx="5" fill="#5b3df5" fill-opacity=".55" transform="rotate(14 621 311)"/>
                    <rect x="64"  y="78"  width="16" height="16" rx="4" fill="#a78bfa" fill-opacity=".6"  transform="rotate(-12 72 86)"/>
                    <rect x="590" y="500" width="14" height="14" rx="3" fill="#7456fa" fill-opacity=".45"/>
                    <rect x="360" y="20"  width="18" height="18" rx="4" stroke="#7456fa" stroke-opacity=".5" transform="rotate(20 369 29)"/>
                    <rect x="30"  y="300" width="26" height="26" rx="6" stroke="#a78bfa" stroke-opacity=".5" transform="rotate(-18 43 313)"/>

                    <g fill="#5b3df5" fill-opacity=".35">
                        <circle cx="288" cy="72"  r="2.5"/><circle cx="324" cy="72"  r="2.5"/><circle cx="360" cy="108" r="2.5"/>
                        <circle cx="648" cy="396" r="2.5"/><circle cx="612" cy="432" r="2.5"/><circle cx="648" cy="432" r="2.5"/>
                    </g>
                </svg>
            </div>

            <div class="lp-window relative" style="z-index:1">
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
<section class="eco relative overflow-hidden border-t border-line py-20 sm:py-24" style="background:linear-gradient(180deg,#fbfcff 0%,#f4f2ff 100%)">
    <style>
        .eco-node{position:absolute;display:grid;place-items:center;border-radius:18px;background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:0 14px 34px rgba(91,61,245,.14),0 1px 0 rgba(255,255,255,.9) inset;animation:ecoFloat 6s ease-in-out infinite}
        .eco-node.lg{width:64px;height:64px}
        .eco-node.sm{width:56px;height:56px;border-radius:16px}
        @keyframes ecoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
        .eco-tile{border-radius:16px;background:#fff;border:1px solid rgba(15,23,42,.07);padding:16px;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:box-shadow .2s,border-color .2s}
        .eco-tile:hover{border-color:#c4b5fd;box-shadow:0 10px 28px rgba(91,61,245,.12)}
        @media (prefers-reduced-motion:reduce){.eco-node{animation:none}}
    </style>

    {{-- background: kisi kotak-kotak + garis gelombang --}}
    <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true" preserveAspectRatio="xMidYMid slice" viewBox="0 0 1440 600" fill="none">
        <defs>
            <pattern id="eco-grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" stroke="#5b3df5" stroke-opacity=".07"/></pattern>
            <radialGradient id="eco-fade" cx="75%" cy="35%" r="55%"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
            <mask id="eco-mask"><rect width="1440" height="600" fill="url(#eco-fade)"/></mask>
            <linearGradient id="eco-wave" x1="0" x2="1"><stop offset="0" stop-color="#c4b5fd" stop-opacity="0"/><stop offset=".5" stop-color="#a78bfa" stop-opacity=".55"/><stop offset="1" stop-color="#c4b5fd" stop-opacity="0"/></linearGradient>
        </defs>
        <rect width="1440" height="600" fill="url(#eco-grid)" mask="url(#eco-mask)"/>
        <path d="M640 420 C 820 360, 900 120, 1100 190 S 1340 330, 1460 120" stroke="url(#eco-wave)" stroke-width="1.6"/>
        <path d="M700 520 C 880 470, 980 300, 1160 330 S 1380 450, 1460 300" stroke="url(#eco-wave)" stroke-width="1.2" opacity=".7"/>
        <rect x="1180" y="60"  width="90" height="90" rx="18" stroke="#c4b5fd" stroke-opacity=".5" transform="rotate(14 1225 105)"/>
        <rect x="1290" y="400" width="64" height="64" rx="14" fill="#a78bfa" fill-opacity=".10" stroke="#c4b5fd" stroke-opacity=".5" transform="rotate(-10 1322 432)"/>
    </svg>

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="max-w-md">
                <p class="lp-eyebrow">Ecosystem</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink sm:text-[2.5rem]" style="line-height:1.12">Integrated with the tools you already know and trust</h2>
                <p class="mt-4 leading-relaxed text-muted">Use LuvuPay with the tools and platforms you already run. Our integrations keep growing to support your business stack.</p>
            </div>

            {{-- logo mengambang --}}
            <div class="relative hidden lg:block" style="height:300px" aria-hidden="true">
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" fill="none">
                    <path d="M14 41 C 26 41, 28 63, 40 63 S 62 73, 73 73" stroke="#a78bfa" stroke-opacity=".6" stroke-width="1.4" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                    <path d="M14 41 C 34 41, 46 13, 62 13 S 84 43, 90 43" stroke="#a78bfa" stroke-opacity=".6" stroke-width="1.4" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                    <path d="M40 63 C 50 40, 55 30, 62 13" stroke="#c4b5fd" stroke-opacity=".55" stroke-width="1.2" vector-effect="non-scaling-stroke"/>
                </svg>
                <div class="eco-node lg" style="left:8%;top:30%;animation-delay:-1s"><svg viewBox="0 0 24 24" width="32" height="32">{!! $ecosystem[0]['svg'] !!}</svg></div>
                <div class="eco-node lg" style="left:56%;top:2%;animation-delay:-3s"><svg viewBox="0 0 24 24" width="32" height="32">{!! $ecosystem[1]['svg'] !!}</svg></div>
                <div class="eco-node lg" style="left:34%;top:52%;animation-delay:-2s"><svg viewBox="0 0 24 24" width="32" height="32">{!! $ecosystem[3]['svg'] !!}</svg></div>
                <div class="eco-node lg" style="left:67%;top:62%;animation-delay:-4s"><svg viewBox="0 0 24 24" width="32" height="32">{!! $ecosystem[2]['svg'] !!}</svg></div>
                <div class="eco-node sm" style="left:84%;top:31%;animation-delay:-5s;color:#8b8fb0;font-weight:700;letter-spacing:.14em">···</div>
            </div>
        </div>

        {{-- kartu bawah --}}
        <div class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach(array_slice($ecosystem, 0, 5) as $item)
                <div class="eco-tile">
                    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">{!! $item['svg'] !!}</svg>
                    <div class="mt-4 text-sm font-semibold text-ink">{{ $item['name'] }}</div>
                    <div class="text-xs text-muted">{{ $item['kind'] }}</div>
                </div>
            @endforeach
            <a href="/docs" class="flex flex-col justify-center gap-2 rounded-2xl p-4 no-underline transition hover:bg-brand-50/60" style="border:1.5px dashed #c4b5fd;background:rgba(91,61,245,.04);color:#5b3df5">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                <span class="text-sm font-semibold">See all integrations →</span>
            </a>
        </div>
    </div>
</section>

{{-- Infrastructure --}}
<section class="lp-dark infra relative overflow-hidden py-24" style="background:#090b15">
    <style>
        .infra-pin-ring{transform-box:fill-box;transform-origin:center;animation:infraPulse 2.8s ease-out infinite}
        @keyframes infraPulse{from{transform:scale(.5);opacity:.7}to{transform:scale(2.4);opacity:0}}
        .infra-arc{stroke-dasharray:5 7;animation:infraFlow 3s linear infinite}
        @keyframes infraFlow{to{stroke-dashoffset:-96}}
        .infra-card{background:rgba(16,19,34,.88);border:1px solid rgba(255,255,255,.10);border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.45);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
        .infra-chip{display:flex;align-items:center;gap:7px;padding:5px 10px;border-radius:999px;white-space:nowrap;font-size:11px;font-weight:600;color:#fff}
        .infra-chip small{font-weight:500;color:rgba(255,255,255,.5);font-size:10px}
        @media (prefers-reduced-motion:reduce){.infra-pin-ring,.infra-arc{animation:none}}
    </style>

    {{-- background: kisi kotak-kotak + cahaya ungu --}}
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute inset-0" style="background-image:linear-gradient(rgba(139,124,248,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(139,124,248,.07) 1px,transparent 1px);background-size:44px 44px;-webkit-mask-image:radial-gradient(ellipse 70% 80% at 72% 45%,#000 20%,transparent 75%);mask-image:radial-gradient(ellipse 70% 80% at 72% 45%,#000 20%,transparent 75%)"></div>
        <div class="absolute" style="right:-8%;top:-20%;width:640px;height:640px;border-radius:50%;background:radial-gradient(circle,rgba(91,61,245,.28),transparent 68%)"></div>
        <div class="absolute" style="left:-10%;bottom:-30%;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(91,61,245,.14),transparent 70%)"></div>
        <svg class="absolute right-6 top-8 hidden lg:block" width="120" height="120" viewBox="0 0 120 120" fill="none"><rect x="20" y="20" width="70" height="70" rx="14" stroke="#8b7cf8" stroke-opacity=".28" transform="rotate(12 55 55)"/><rect x="44" y="40" width="46" height="46" rx="10" stroke="#8b7cf8" stroke-opacity=".18" transform="rotate(-8 67 63)"/></svg>
    </div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.25fr)]">
        <div>
            <span class="inline-block rounded-md px-2.5 py-1 text-[11px] font-semibold tracking-[.14em] text-brand-300" style="background:rgba(139,124,248,.14);border:1px solid rgba(139,124,248,.25)">INFRASTRUCTURE</span>
            <h2 class="lp-h2 mt-5 text-white">Built for subscription apps at scale</h2>
            <p class="mt-4 max-w-md leading-relaxed text-white/65">LuvuPay runs on stable cloud infrastructure, a microservice architecture and layered security, so you can focus on shipping product.</p>

            <ul class="mt-9 space-y-6">
                @foreach([
                    ['High availability', '99.9% uptime, always online.', '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>'],
                    ['Auto scaling', 'Scales with demand automatically.', '<path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>'],
                    ['Global CDN', 'Fast access worldwide: US, Europe, APAC.', '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3M3.6 9h16.8M3.6 15h16.8"/>'],
                ] as [$t, $d, $icon])
                    <li class="flex items-center gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-brand-300" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);box-shadow:0 0 0 5px rgba(139,124,248,.06)">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">{!! $icon !!}</svg>
                        </span>
                        <div>
                            <div class="text-[0.95rem] font-semibold text-white">{{ $t }}</div>
                            <div class="text-sm text-white/55">{{ $d }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- peta dunia titik-titik (butuh public/images/world-dots.svg) --}}
        <div>
            <div class="relative w-full" style="aspect-ratio:2/1" aria-hidden="true">
                <img src="{{ asset('images/world-dots.svg') }}" alt="" class="absolute inset-0 h-full w-full" style="opacity:.55" loading="lazy">

                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 1000 500" fill="none">
                    <defs>
                        <linearGradient id="infra-arc" x1="0" x2="1000" y1="0" y2="0" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#6d5cf6"/><stop offset=".6" stop-color="#a78bfa"/><stop offset="1" stop-color="#c4b5fd"/>
                        </linearGradient>
                    </defs>
                    <path class="infra-arc" d="M228 157 Q 376 30 525 118" stroke="url(#infra-arc)" stroke-width="2" opacity=".9"/>
                    <path class="infra-arc" d="M525 118 Q 720 120 788 292" stroke="url(#infra-arc)" stroke-width="2" opacity=".85"/>
                    <path class="infra-arc" d="M228 157 Q 500 400 788 292" stroke="url(#infra-arc)" stroke-width="1.6" opacity=".5"/>
                    <path class="infra-arc" d="M788 292 Q 880 250 888 168" stroke="url(#infra-arc)" stroke-width="1.8" opacity=".75"/>

                    @foreach([[228,157,'#a78bfa'],[525,118,'#a78bfa'],[788,292,'#22c55e'],[888,168,'#a78bfa']] as [$x, $y, $c])
                        <circle class="infra-pin-ring" cx="{{ $x }}" cy="{{ $y }}" r="9" fill="{{ $c }}" fill-opacity=".35"/>
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="9" fill="{{ $c }}" fill-opacity=".25"/>
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="4.5" fill="{{ $c }}"/>
                    @endforeach
                </svg>

                <div class="infra-card infra-chip absolute hidden sm:flex" style="left:22.8%;top:31.4%;transform:translate(-50%,-190%)"><i class="h-2 w-2 rounded-full bg-violet-400"></i>United States <small>28ms</small></div>
                <div class="infra-card infra-chip absolute hidden sm:flex" style="left:52.5%;top:23.6%;transform:translate(-50%,-190%)"><i class="h-2 w-2 rounded-full bg-violet-400"></i>Europe <small>24ms</small></div>
                <div class="infra-card infra-chip absolute hidden sm:flex" style="left:88.8%;top:33.6%;transform:translate(-50%,-190%)"><i class="h-2 w-2 rounded-full bg-violet-400"></i>Japan <small>35ms</small></div>

                <div class="infra-card absolute flex items-center gap-3 px-4 py-3" style="left:78.8%;top:58.4%;transform:translate(-108%,26%)">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-400" style="box-shadow:0 0 10px #22c55e"></span>
                    <div>
                        <div class="text-[13px] font-semibold text-white">Singapore</div>
                        <div class="text-[11px] text-white/55">Latency 32ms</div>
                    </div>
                </div>
            </div>

            <div class="infra-card mt-5 flex items-center justify-between gap-4 px-5 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-400" style="box-shadow:0 0 12px #22c55e"></span>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-white">Infrastructure status</div>
                        <div class="text-xs text-white/55">All systems operational.</div>
                    </div>
                </div>
                <a href="/status" class="shrink-0 text-xs font-semibold text-brand-300 no-underline hover:text-white">View status →</a>
            </div>
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
        <div class="lp-cta-visual relative mx-auto w-full max-w-md lg:max-w-none" aria-hidden="true">
            <div class="pointer-events-none absolute inset-0" style="z-index:0">
                <div style="position:absolute;right:4%;top:8%;width:42%;height:58%;border-radius:1.5rem;background:linear-gradient(145deg,rgba(167,139,250,0.18),rgba(91,61,245,0.08));border:1px solid rgba(167,139,250,0.22);transform:rotate(6deg)"></div>
                <div style="position:absolute;right:14%;top:14%;width:38%;height:52%;border-radius:1.35rem;background:linear-gradient(145deg,rgba(196,181,253,0.22),rgba(139,92,246,0.06));border:1px solid rgba(196,181,253,0.28);transform:rotate(3deg)"></div>
                <div style="position:absolute;left:6%;bottom:12%;width:36%;height:40%;border-radius:1.25rem;background:linear-gradient(160deg,rgba(224,231,255,0.35),rgba(167,139,250,0.1));border:1px solid rgba(199,210,254,0.35);transform:rotate(-4deg);opacity:0.85"></div>
                <div style="position:absolute;left:18%;top:6%;width:28%;height:28%;border-radius:999px;background:radial-gradient(circle,rgba(91,61,245,0.12),transparent 70%);opacity:0.9"></div>
            </div>
            <img src="{{ asset('images/ready-start.webp') }}" alt="" width="640" height="640" class="relative w-full" style="z-index:1" loading="lazy">
        </div>
    </div>
</section>
@endsection
