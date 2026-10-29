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
        ['name' => 'PayPal', 'kind' => 'Sandbox', 'svg' => '<path fill="#003087" d="M9.5 3h4.2c1.9 0 3.2.4 4 1.3.7.8.9 1.8.7 3.2-.7 3.7-3.2 5-6.4 5H9.7L8.5 20H5.8L8 3h1.5zm1.1 2.2l-.9 5.4h1.8c1.8 0 3.1-.5 3.6-2.6.2-.9.1-1.5-.3-1.9-.4-.4-1.1-.9-2.6-.9h-1.6z"/><path fill="#009cde" d="M15.8 8.2c-.4 2.2-1.7 3.7-3.7 4.4l-.7 4.4h2.6l.5-3.1h.4c2.2 0 3.9-.9 4.4-3.4.2-1.1.1-2-.4-2.6-.3-.4-.7-.7-1.3-.9.1-.4.1-.8 0-1.1-.3-1.4-1.4-2.1-3.3-2.1h-3.7l-1.7 10.5h2.6l.8-5.1h.3c1.4 0 2.4-.4 2.8-1.9.2-.6.1-1.1-.2-1.4-.2-.2-.5-.4-.9-.5.1-.2.1-.4.1-.6z"/>'],
        ['name' => 'Laravel', 'kind' => 'Package', 'svg' => '<path fill="#FF2D20" d="M12 2.2L4.5 6v12L12 21.8 19.5 18V6L12 2.2zM8.2 14.2l-2.4-1.3V9.6l2.4 1.3v3.3zm0-4.2L5.8 8.7l2.4-1.3 2.4 1.3-2.4 1.3zm3.8 6.2l-2.4-1.3v-3.3l2.4 1.3v3.3zm0-4.2l-2.4-1.3 2.4-1.3 2.4 1.3-2.4 1.3zm3.8 2.2l-2.4 1.3v-3.3l2.4-1.3v3.3zm0-4.2l-2.4 1.3-2.4-1.3 2.4-1.3 2.4 1.3z"/>'],
        ['name' => 'PostgreSQL', 'kind' => 'Driver', 'svg' => '<ellipse cx="12" cy="7" rx="7" ry="3.2" fill="#336791"/><path fill="#336791" d="M5 7v7.5c0 1.8 3.1 3.2 7 3.2s7-1.4 7-3.2V7"/><ellipse cx="12" cy="14.5" rx="7" ry="3.2" fill="#336791" opacity=".85"/><circle cx="10" cy="6.5" r="1" fill="#fff"/><path fill="#fff" d="M14.5 5.5c.8 0 1.5.5 1.5 1.2S15.3 8 14.5 8 13 7.4 13 6.7s.7-1.2 1.5-1.2z"/>'],
        ['name' => 'Redis', 'kind' => 'Queue', 'svg' => '<path fill="#DC382D" d="M4 7.5c0-1.5 3.6-2.7 8-2.7s8 1.2 8 2.7-3.6 2.7-8 2.7-8-1.2-8-2.7z"/><path fill="#A41E11" d="M4 7.5v3c0 1.5 3.6 2.7 8 2.7s8-1.2 8-2.7v-3c0 1.5-3.6 2.7-8 2.7S4 9 4 7.5z"/><path fill="#DC382D" d="M4 10.5v3c0 1.5 3.6 2.7 8 2.7s8-1.2 8-2.7v-3c0 1.5-3.6 2.7-8 2.7s-8-1.2-8-2.7z"/><path fill="#A41E11" d="M4 13.5v3c0 1.5 3.6 2.7 8 2.7s8-1.2 8-2.7v-3c0 1.5-3.6 2.7-8 2.7s-8-1.2-8-2.7z"/>'],
        ['name' => 'FrankenPHP', 'kind' => 'Runtime', 'svg' => '<path fill="#7C3AED" d="M12 2L3 6.5v11L12 22l9-4.5v-11L12 2zm0 2.4l6.8 3.4v2.1L12 6.5 5.2 9.9V7.8L12 4.4zM5.2 12.2l5.5 2.7v3.7l-5.5-2.8v-3.6zm13.6 0v3.6l-5.5 2.8v-3.7l5.5-2.7z"/>'],
        ['name' => 'Nebius AI', 'kind' => 'AI', 'svg' => '<circle cx="12" cy="12" r="9" fill="none" stroke="#6366F1" stroke-width="1.6"/><circle cx="12" cy="8" r="1.6" fill="#6366F1"/><circle cx="8" cy="13" r="1.6" fill="#6366F1"/><circle cx="16" cy="13" r="1.6" fill="#6366F1"/><circle cx="12" cy="16.5" r="1.3" fill="#6366F1"/><path d="M12 9.6v2.2M9.2 12.4l1.8 1.2M14.8 12.4l-1.8 1.2" stroke="#6366F1" stroke-width="1.2" stroke-linecap="round"/>'],
        ['name' => 'React', 'kind' => 'Frontend', 'svg' => '<circle cx="12" cy="12" r="2" fill="#61DAFB"/><g fill="none" stroke="#61DAFB" stroke-width="1.3"><ellipse cx="12" cy="12" rx="9.5" ry="3.6"/><ellipse cx="12" cy="12" rx="9.5" ry="3.6" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="9.5" ry="3.6" transform="rotate(120 12 12)"/></g>'],
        ['name' => 'Next.js', 'kind' => 'Frontend', 'svg' => '<circle cx="12" cy="12" r="10" fill="#000"/><path fill="#fff" d="M9.2 7.5h1.6l4.2 6.2V7.5h1.5v9h-1.6l-4.2-6.2v6.2H9.2v-9z"/>'],
        ['name' => 'Angular', 'kind' => 'Frontend', 'svg' => '<path fill="#DD0031" d="M12 2.5L3.5 5.5l1.4 12.2L12 21.5l7.1-3.8 1.4-12.2L12 2.5z"/><path fill="#fff" d="M12 6.2l-3.2 7.6h1.9l.65-1.7h3.3l.65 1.7h1.9L12 6.2zm0 2.5l1.1 2.8h-2.2L12 8.7z"/>'],
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
<section class="border-t border-line py-20 overflow-hidden">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-center">
            {{-- Left copy --}}
            <div>
                <p class="lp-eyebrow">Ecosystem</p>
                <h2 class="lp-h2 max-w-xl">Integrated with the tools you already know and trust</h2>
                <p class="mt-4 max-w-md text-muted">Use LuvuPay with the tools and platforms you already run. Our integrations keep growing to support your stack — from backend to frontend frameworks.</p>
            </div>

            {{-- Right floating logos --}}
            <div class="relative hidden min-h-[280px] lg:block" aria-hidden="true">
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 400 280" fill="none">
                    <path d="M80 140 C140 80, 200 60, 280 90" stroke="#e0e4ff" stroke-width="1.5" stroke-dasharray="4 6"/>
                    <path d="M100 180 C160 200, 220 160, 300 170" stroke="#e0e4ff" stroke-width="1.5" stroke-dasharray="4 6"/>
                    <path d="M60 100 C120 140, 180 200, 260 220" stroke="#e0e4ff" stroke-width="1.5" stroke-dasharray="4 6"/>
                </svg>
                <div class="absolute left-[8%] top-[12%] grid size-14 place-items-center rounded-2xl border border-line bg-white shadow-sm">
                    <svg viewBox="0 0 24 24" class="size-7" aria-hidden="true">{!! $ecosystem[1]['svg'] !!}</svg>
                </div>
                <div class="absolute left-[38%] top-[28%] grid size-14 place-items-center rounded-2xl border border-line bg-white shadow-sm">
                    <svg viewBox="0 0 24 24" class="size-7" aria-hidden="true">{!! $ecosystem[0]['svg'] !!}</svg>
                </div>
                <div class="absolute left-[58%] top-[8%] grid size-14 place-items-center rounded-2xl border border-line bg-white shadow-sm">
                    <svg viewBox="0 0 24 24" class="size-7" aria-hidden="true">{!! $ecosystem[3]['svg'] !!}</svg>
                </div>
                <div class="absolute left-[78%] top-[32%] grid size-12 place-items-center rounded-2xl border border-line bg-white shadow-sm text-muted text-lg font-semibold">···</div>
                <div class="absolute left-[48%] top-[62%] grid size-14 place-items-center rounded-2xl border border-line bg-white shadow-sm">
                    <svg viewBox="0 0 24 24" class="size-7" aria-hidden="true">{!! $ecosystem[2]['svg'] !!}</svg>
                </div>
                <div class="absolute left-[22%] top-[58%] grid size-14 place-items-center rounded-2xl border border-line bg-white shadow-sm">
                    <svg viewBox="0 0 24 24" class="size-7" aria-hidden="true">{!! $ecosystem[6]['svg'] !!}</svg>
                </div>
            </div>
        </div>

        {{-- Logo strip --}}
        <div class="mt-12 flex flex-wrap gap-3">
            @foreach($ecosystem as $item)
                <div class="lp-card flex min-w-[9.5rem] flex-1 items-center gap-3 p-4 sm:min-w-0 sm:flex-none">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-line bg-white">
                        <svg viewBox="0 0 24 24" class="size-6" aria-hidden="true">{!! $item['svg'] !!}</svg>
                    </span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold">{{ $item['name'] }}</div>
                        <div class="text-xs text-muted">{{ $item['kind'] }}</div>
                    </div>
                </div>
            @endforeach
            <a href="/docs" class="lp-card flex min-w-[9.5rem] flex-1 items-center gap-2 p-4 text-brand-600 transition hover:border-brand-200 sm:min-w-0 sm:flex-none">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 text-lg font-bold">↗</span>
                <div class="text-sm font-semibold">See all integrations <span aria-hidden="true">→</span></div>
            </a>
        </div>
    </div>
</section>

{{-- Infrastructure --}}
<section class="lp-dark py-24 overflow-hidden">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2">
        {{-- Left: world map + status --}}
        <div class="relative order-2 lg:order-1 min-h-[320px] sm:min-h-[380px]">
            {{-- Dotted world map (simplified SVG) --}}
            <svg class="absolute inset-0 h-full w-full opacity-90" viewBox="0 0 560 320" fill="none" aria-hidden="true">
                {{-- Soft grid dots for oceans --}}
                <defs>
                    <pattern id="infra-dots" x="0" y="0" width="10" height="10" patternUnits="userSpaceOnUse">
                        <circle cx="1" cy="1" r="0.9" fill="#3b3f5c"/>
                    </pattern>
                </defs>
                <rect width="560" height="320" fill="url(#infra-dots)"/>
                {{-- Connection arcs --}}
                <path d="M145 145 C220 90, 300 85, 400 130" stroke="#6d5dfc" stroke-width="1.5" fill="none" opacity="0.7"/>
                <path d="M145 145 C200 180, 280 210, 380 195" stroke="#6d5dfc" stroke-width="1.5" fill="none" opacity="0.55"/>
                <path d="M400 130 C430 160, 450 185, 470 210" stroke="#6d5dfc" stroke-width="1.5" fill="none" opacity="0.5"/>
                {{-- Region pins --}}
                {{-- US East --}}
                <circle cx="145" cy="145" r="5" fill="#6d5dfc"/>
                <circle cx="145" cy="145" r="10" fill="#6d5dfc" opacity="0.25"/>
                {{-- Europe --}}
                <circle cx="280" cy="115" r="5" fill="#6d5dfc"/>
                <circle cx="280" cy="115" r="10" fill="#6d5dfc" opacity="0.25"/>
                {{-- Singapore --}}
                <circle cx="400" cy="195" r="5" fill="#22c55e"/>
                <circle cx="400" cy="195" r="10" fill="#22c55e" opacity="0.3"/>
                {{-- US West --}}
                <circle cx="95" cy="155" r="4" fill="#6d5dfc" opacity="0.8"/>
                {{-- Japan --}}
                <circle cx="470" cy="140" r="4" fill="#6d5dfc" opacity="0.8"/>
            </svg>

            {{-- Region cards --}}
            <div class="absolute left-[8%] top-[28%] rounded-xl border border-white/10 bg-[#16182a]/95 px-3 py-2 shadow-lg backdrop-blur">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-brand-400"></span>
                    <div>
                        <div class="text-xs font-semibold text-white">United States</div>
                        <div class="text-[10px] text-white/50">Latency 28ms</div>
                    </div>
                </div>
            </div>
            <div class="absolute left-[42%] top-[12%] rounded-xl border border-white/10 bg-[#16182a]/95 px-3 py-2 shadow-lg backdrop-blur">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-brand-400"></span>
                    <div>
                        <div class="text-xs font-semibold text-white">Europe</div>
                        <div class="text-[10px] text-white/50">Latency 24ms</div>
                    </div>
                </div>
            </div>
            <div class="absolute right-[10%] top-[52%] rounded-xl border border-white/10 bg-[#16182a]/95 px-3 py-2 shadow-lg backdrop-blur">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-400"></span>
                    <div>
                        <div class="text-xs font-semibold text-white">Singapore</div>
                        <div class="text-[10px] text-white/50">Latency 32ms</div>
                    </div>
                </div>
            </div>

            {{-- Status bar --}}
            <div class="absolute bottom-2 left-0 right-0 mx-auto flex max-w-sm items-center justify-between rounded-xl border border-white/10 bg-[#16182a]/95 px-4 py-3 shadow-lg backdrop-blur">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-400"></span>
                    <div>
                        <div class="text-xs font-semibold text-white">Infrastructure status</div>
                        <div class="text-[10px] text-white/50">All systems operational</div>
                    </div>
                </div>
                <a href="/docs" class="text-[11px] font-semibold text-brand-300 hover:text-white">View status →</a>
            </div>
        </div>

        {{-- Right: text + features --}}
        <div class="order-1 lg:order-2">
            <p class="lp-eyebrow text-brand-300">Infrastructure</p>
            <h2 class="lp-h2 text-white">Built for subscription apps at scale</h2>
            <p class="mt-4 max-w-md leading-relaxed text-white/65">LuvuPay runs on stable cloud infrastructure, a microservice architecture, and layered security — so you can focus on shipping product.</p>

            <ul class="mt-8 space-y-5">
                <li class="flex gap-3">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">High availability</div>
                        <div class="text-xs text-white/50">99.9% uptime, always online.</div>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">Auto scaling</div>
                        <div class="text-xs text-white/50">Scales with demand automatically.</div>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-white/5 text-brand-300">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                    </span>
                    <div>
                        <div class="text-sm font-semibold text-white">Global CDN</div>
                        <div class="text-xs text-white/50">Fast access worldwide — US, Europe, APAC.</div>
                    </div>
                </li>
            </ul>
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
        <div class="lp-cta-visual" aria-hidden="true">
            <img src="{{ asset('images/ready-start.webp') }}" alt="" width="640" height="640" class="w-full max-w-md mx-auto lg:max-w-none" loading="lazy">
        </div>
    </div>
</section>
@endsection
