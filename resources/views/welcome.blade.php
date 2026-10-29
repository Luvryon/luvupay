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
            // Official-style PayPal wordmark mark (P)
            'svg' => '<path fill="#003087" d="M7.2 3.2h4.6c2.55 0 4.3.55 5.25 1.7.9 1.05 1.15 2.5.9 4.35-.85 4.85-3.95 6.55-8.05 6.55H7.55L6 20.8H2.8L5.55 3.2H7.2zm1.35 2.75l-1.2 6.85h2.25c2.35 0 4.05-.65 4.7-3.35.3-1.2.15-2.05-.35-2.55-.55-.55-1.5-1-3.35-1H8.55z"/><path fill="#009cde" d="M16.1 9.6c-.5 2.75-2.1 4.65-4.55 5.5l-.9 5.5h3.25l.65-3.85h.5c2.75 0 4.85-1.15 5.5-4.25.3-1.4.1-2.5-.5-3.25-.4-.5-.95-.9-1.7-1.15.1-.5.15-1 .05-1.4C18.1 4.6 16.7 3.6 14.3 3.6H9.7L7.55 17h3.25l1-6.35h.4c1.75 0 3-.5 3.5-2.4.2-.75.15-1.35-.2-1.75-.25-.3-.65-.5-1.15-.6.1-.25.15-.5.15-.75z"/>',
        ],
        [
            'name' => 'Laravel',
            'kind' => 'Package',
            // Classic Laravel hexagon mark
            'svg' => '<path fill="#FF2D20" d="M17.3 7.55 12 4.5 6.7 7.55v6.9L12 17.5l5.3-3.05V7.55zM12 5.85l3.85 2.2-1.55.9-2.3-1.3-2.3 1.3-1.55-.9L12 5.85zm-4.15 3.35 1.55.9v2.65l-1.55-.9V9.2zm0 4.15 3.15 1.8v2.2L7.85 15.6v-2.25zm4.15 4 3.15-1.8v2.25L12 18.35v-1zm4.15-1.95-1.55.9v-2.65l1.55-.9v2.65zm0-4.15-3.15-1.8 1.55-.9 1.6.9v1.8zm-4.15-.25 2.3 1.3-2.3 1.35-2.3-1.35 2.3-1.3z"/>',
        ],
        [
            'name' => 'PostgreSQL',
            'kind' => 'Driver',
            // Elephant-style simplified mark in PG blue
            'svg' => '<path fill="#336791" d="M17.6 9.2c-.15-2.4-1.65-3.7-4.05-3.95.35-.45.55-1 .55-1.6C14.1 2.15 12.85 1 11.25 1S8.4 2.15 8.4 3.65c0 .55.2 1.05.5 1.5C6.7 5.5 5.3 6.85 5.15 9.4c-.05.7-.05 1.35.1 2.05.35 1.7 1.35 2.85 2.85 3.25-.15.45-.25.95-.25 1.45 0 1.85 1.15 3.15 2.95 3.55v1.9c0 .55.45 1 1 1s1-.45 1-1v-1.9c1.8-.4 2.95-1.7 2.95-3.55 0-.45-.08-.9-.22-1.3 1.4-.45 2.35-1.6 2.65-3.25.2-.75.25-1.45.22-2.2zm-6.35-5.9c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-1.85 12.05c-.85-.2-1.35-.9-1.35-1.85 0-.35.1-.7.25-1 .55.25 1.15.4 1.8.4.35 0 .7-.05 1.05-.1-.15 1.35-.7 2.2-1.75 2.55zm5.7 0c-1.05-.35-1.6-1.2-1.75-2.55.35.05.7.1 1.05.1.65 0 1.25-.15 1.8-.4.15.3.25.65.25 1 0 .95-.5 1.65-1.35 1.85z"/>',
        ],
        [
            'name' => 'Redis',
            'kind' => 'Queue',
            // Redis cube-ish red mark
            'svg' => '<path fill="#DC382D" d="M4 9.2 12 5.5l8 3.7-8 3.7L4 9.2zm0 2.3 8 3.7 8-3.7v2.5L12 17.7 4 14v-2.5zm0 4 8 3.7 8-3.7v2.5L12 21.7 4 18v-2.5z"/>',
        ],
        [
            'name' => 'FrankenPHP',
            'kind' => 'Runtime',
            // FrankenPHP-inspired purple hex
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
<section class="lp-hero">
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
            {{-- glassmorphic background boxes (subtle) --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden" style="z-index:0">
                <div style="position:absolute;right:-6%;top:2%;width:48%;height:42%;border-radius:1.75rem;background:rgba(255,255,255,0.35);border:1px solid rgba(255,255,255,0.45);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:0 8px 32px rgba(91,61,245,0.04);transform:rotate(4deg);opacity:0.55"></div>
                <div style="position:absolute;right:8%;top:28%;width:40%;height:36%;border-radius:1.5rem;background:rgba(245,243,255,0.4);border:1px solid rgba(255,255,255,0.5);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 8px 28px rgba(91,61,245,0.03);transform:rotate(-2deg);opacity:0.5"></div>
                <div style="position:absolute;left:-4%;bottom:6%;width:44%;height:34%;border-radius:1.5rem;background:rgba(255,255,255,0.28);border:1px solid rgba(255,255,255,0.4);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);transform:rotate(-5deg);opacity:0.45"></div>
                <div style="position:absolute;right:18%;bottom:12%;width:32%;height:28%;border-radius:1.25rem;background:rgba(237,233,254,0.35);border:1px solid rgba(255,255,255,0.4);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);transform:rotate(3deg);opacity:0.4"></div>
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
<section class="relative overflow-hidden border-t border-line py-20 sm:py-24" style="background:linear-gradient(180deg,#fbfcff 0%,#f4f2ff 100%)">
    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-6">
            <div class="max-w-md">
                <p class="lp-eyebrow">Ecosystem</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl" style="line-height:1.15">
                    Integrated with the tools you already know and trust
                </h2>
                <p class="mt-4 text-muted leading-relaxed">
                    Use LuvuPay with the tools and platforms you already run. Our integrations keep growing to support your business stack.
                </p>
            </div>

            {{-- Right: floating logos + solid wave lines --}}
            <div class="relative hidden lg:block" style="height:280px" aria-hidden="true">
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 480 280" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M40 150 C100 80, 160 60, 230 100 C300 140, 350 70, 430 50" stroke="#c4b5fd" stroke-width="1.75" fill="none" opacity="0.9"/>
                    <path d="M20 190 C90 170, 140 230, 210 190 C280 150, 340 210, 450 170" stroke="#ddd6fe" stroke-width="1.5" fill="none" opacity="0.85"/>
                    <path d="M60 100 C120 140, 180 170, 260 150 C340 130, 380 180, 460 200" stroke="#ede9fe" stroke-width="1.4" fill="none" opacity="0.8"/>
                </svg>
                <div style="position:absolute;left:12%;top:38%;width:56px;height:56px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 28px rgba(91,61,245,0.12);border:1px solid rgba(15,23,42,0.05)">
                    <svg viewBox="0 0 24 24" width="30" height="30">{!! $ecosystem[0]['svg'] !!}</svg>
                </div>
                <div style="position:absolute;left:48%;top:4%;width:56px;height:56px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 28px rgba(91,61,245,0.12);border:1px solid rgba(15,23,42,0.05)">
                    <svg viewBox="0 0 24 24" width="30" height="30">{!! $ecosystem[1]['svg'] !!}</svg>
                </div>
                <div style="position:absolute;left:52%;top:44%;width:56px;height:56px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 28px rgba(91,61,245,0.12);border:1px solid rgba(15,23,42,0.05)">
                    <svg viewBox="0 0 24 24" width="30" height="30">{!! $ecosystem[3]['svg'] !!}</svg>
                </div>
                <div style="position:absolute;left:76%;top:58%;width:56px;height:56px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 28px rgba(91,61,245,0.12);border:1px solid rgba(15,23,42,0.05)">
                    <svg viewBox="0 0 24 24" width="30" height="30">{!! $ecosystem[2]['svg'] !!}</svg>
                </div>
                <div style="position:absolute;left:86%;top:18%;width:48px;height:48px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 22px rgba(91,61,245,0.1);border:1px solid rgba(15,23,42,0.05);color:#94a3b8;font-weight:700;font-size:16px;letter-spacing:0.12em">···</div>
            </div>
        </div>

        <div class="mt-12 flex flex-wrap gap-3">
            @foreach(array_slice($ecosystem, 0, 5) as $item)
                <div class="rounded-2xl border border-line bg-white p-4 shadow-sm" style="min-width:8.25rem;flex:1 1 8.25rem;max-width:10.5rem">
                    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">{!! $item['svg'] !!}</svg>
                    <div class="mt-3 text-sm font-semibold text-ink">{{ $item['name'] }}</div>
                    <div class="text-xs text-muted">{{ $item['kind'] }}</div>
                </div>
            @endforeach
            <a href="/docs" class="rounded-2xl p-4" style="min-width:8.25rem;flex:1 1 8.25rem;max-width:10.5rem;border:1.5px dashed #c4b5fd;background:rgba(91,61,245,0.04);color:#5b3df5;text-decoration:none;display:flex;flex-direction:column;justify-content:center;gap:6px">
                <span style="font-size:1.1rem;font-weight:700">↗</span>
                <span class="text-sm font-semibold">See all integrations →</span>
            </a>
        </div>
    </div>
</section>

{{-- Infrastructure --}}
<section class="lp-dark py-24 overflow-hidden">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2">
        <div>
            <p class="lp-eyebrow text-brand-300">Infrastructure</p>
            <h2 class="lp-h2 text-white">Built for subscription apps at scale</h2>
            <p class="mt-4 max-w-md leading-relaxed text-white/65">LuvuPay runs on stable cloud infrastructure, a microservice architecture, and layered security — so you can focus on shipping product.</p>
            <ul class="mt-8 space-y-5">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">High availability</div>
                        <div class="text-xs text-white/50">99.9% uptime, always online.</div>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">Auto scaling</div>
                        <div class="text-xs text-white/50">Scales with demand automatically.</div>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">Global CDN</div>
                        <div class="text-xs text-white/50">Fast access worldwide — US, Europe, APAC.</div>
                    </div>
                </li>
            </ul>
        </div>

        {{-- Geographic world map (equirectangular pins) --}}
        <div class="relative w-full" style="min-height:300px">
            <svg viewBox="0 0 1000 500" class="w-full h-auto" style="max-height:380px" aria-hidden="true">
                <defs>
                    <pattern id="mapDots" width="8" height="8" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.4" fill="#6b7190"/>
                    </pattern>
                    <filter id="softGlow" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="3" result="b"/>
                        <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                </defs>
                <style>
                    .c { fill: url(#mapDots); opacity: 0.95; }
                </style>
                
                <!-- Americas -->
                <path class="c" d="M175 95c20-25 55-35 85-30 25 5 40 25 35 50-5 20-25 35-20 55 5 25 15 40 5 60-15 30-45 40-70 30-30-10-45-45-40-75 5-20 0-40 5-60z"/>
                <path class="c" d="M210 230c15-5 30 5 35 25 5 25-5 55-20 70-20 20-45 15-50-5-5-25 5-55 15-75 5-10 10-15 20-15z"/>
                <!-- Europe -->
                <path class="c" d="M490 95c25-15 55-10 70 10 10 15 5 35-10 45-20 15-45 10-60-5-10-15-10-35 0-50z"/>
                <!-- Africa -->
                <path class="c" d="M510 155c25-5 50 10 55 40 5 35-10 70-30 90-25 25-55 15-65-10-10-30 0-65 15-95 5-15 15-25 25-25z"/>
                <!-- Asia -->
                <path class="c" d="M560 80c40-20 100-15 140 10 35 20 50 50 40 80-10 30-45 45-80 40-30-5-50-25-70-20-25 5-40-15-45-40-5-25 5-50 15-70z"/>
                <!-- SE Asia / Indonesia band -->
                <path class="c" d="M740 200c30-5 55 10 65 30 5 15-5 30-25 35-25 5-50-5-60-20-10-15 0-35 20-45z"/>
                <!-- Australia -->
                <path class="c" d="M800 260c30-10 60 0 70 25 10 25-5 50-30 55-30 5-55-10-60-35-5-20 5-40 20-45z"/>

                {{-- arcs US → Europe → Singapore → Japan --}}
                <path d="M227 142 C380 40, 480 50, 528 108" stroke="#8b7cf8" stroke-width="2" fill="none" opacity="0.7"/>
                <path d="M227 142 C420 220, 620 260, 788 246" stroke="#8b7cf8" stroke-width="1.8" fill="none" opacity="0.55"/>
                <path d="M528 108 C650 140, 720 200, 788 246" stroke="#8b7cf8" stroke-width="1.6" fill="none" opacity="0.5"/>
                <path d="M788 246 C830 200, 860 170, 888 151" stroke="#8b7cf8" stroke-width="1.5" fill="none" opacity="0.45"/>

                {{-- pins: US, Europe, Singapore, Japan --}}
                <g filter="url(#softGlow)">
                    <circle cx="227" cy="142" r="7" fill="#a78bfa"/>
                    <circle cx="227" cy="142" r="14" stroke="#a78bfa" stroke-width="1.6" fill="none" opacity="0.45"/>
                    <circle cx="528" cy="108" r="6.5" fill="#a78bfa"/>
                    <circle cx="528" cy="108" r="13" stroke="#a78bfa" stroke-width="1.6" fill="none" opacity="0.4"/>
                    <circle cx="788" cy="246" r="8" fill="#22c55e"/>
                    <circle cx="788" cy="246" r="16" stroke="#22c55e" stroke-width="1.8" fill="none" opacity="0.5"/>
                    <circle cx="888" cy="151" r="6" fill="#a78bfa"/>
                    <circle cx="888" cy="151" r="12" stroke="#a78bfa" stroke-width="1.5" fill="none" opacity="0.4"/>
                </g>
            </svg>

            {{-- Labels positioned near geographic pins --}}
            <div style="position:absolute;left:14%;top:18%">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#a78bfa;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">United States</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 28ms</div>
                    </div>
                </div>
            </div>
            <div style="position:absolute;left:46%;top:6%">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#a78bfa;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">Europe</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 24ms</div>
                    </div>
                </div>
            </div>
            <div style="position:absolute;left:68%;top:52%">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#22c55e;box-shadow:0 0 8px #22c55e;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">Singapore</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 32ms</div>
                    </div>
                </div>
            </div>
            <div style="position:absolute;right:4%;top:22%" class="hidden sm:block">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#a78bfa;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">Japan</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 35ms</div>
                    </div>
                </div>
            </div>

            <div style="position:absolute;bottom:4px;left:50%;transform:translateX(-50%);width:min(100%,18rem);display:flex;align-items:center;justify-content:space-between;gap:12px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:10px 14px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                    <span style="width:9px;height:9px;border-radius:99px;background:#22c55e;box-shadow:0 0 10px #22c55e;flex-shrink:0"></span>
                    <div style="min-width:0">
                        <div style="font-size:12px;font-weight:600;color:#fff">Infrastructure status</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55)">All systems operational</div>
                    </div>
                </div>
                <a href="/status" style="font-size:11px;font-weight:600;color:#c4b5fd;text-decoration:none;flex-shrink:0">View status →</a>
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
            {{-- translucent aesthetic boxes --}}
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
