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

            {{-- Right decorative floating logos + waves --}}
            <div class="relative hidden lg:block" style="height:280px" aria-hidden="true">
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 480 280" fill="none" xmlns="http://www.w3.org/2000/svg">
                    {{-- soft wave paths --}}
                    <path d="M40 150 C100 80, 160 60, 230 100 C300 140, 350 70, 430 50" stroke="#c4b5fd" stroke-width="1.75" stroke-dasharray="5 8" opacity="0.85"/>
                    <path d="M20 190 C90 170, 140 230, 210 190 C280 150, 340 210, 450 170" stroke="#ddd6fe" stroke-width="1.5" stroke-dasharray="4 7" opacity="0.9"/>
                    <path d="M60 100 C120 140, 180 170, 260 150 C340 130, 380 180, 460 200" stroke="#ede9fe" stroke-width="1.4" stroke-dasharray="5 8" opacity="0.75"/>
                </svg>
                {{-- tiles --}}
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

        {{-- bottom logo strip --}}
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
        {{-- LEFT: copy (matches reference) --}}
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

        {{-- RIGHT: dotted world map --}}
        <div class="relative w-full" style="min-height:320px">
            <svg viewBox="0 0 640 360" class="w-full h-auto" style="max-height:380px" aria-hidden="true">
                <circle cx="20" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="104" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="118" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="244" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="356" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="384" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="412" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="440" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="510" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="524" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="538" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="552" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="594" cy="30" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="81" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="207" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="221" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="235" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="319" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="347" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="361" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="417" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="431" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="487" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="515" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="599" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="613" cy="44" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="76" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="146" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="258" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="328" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="342" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="454" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="510" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="524" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="580" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="594" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="608" cy="58" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="25" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="53" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="151" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="221" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="249" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="277" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="375" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="515" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="585" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="613" cy="72" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="48" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="202" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="230" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="272" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="370" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="538" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="566" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="608" cy="86" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="25" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="193" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="207" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="235" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="249" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="361" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="585" cy="100" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="34" cy="114" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="272" cy="114" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="608" cy="114" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="128" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="557" cy="128" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="230" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="566" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="580" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="594" cy="142" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="39" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="67" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="137" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="235" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="249" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="277" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="529" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="557" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="571" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="585" cy="156" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="48" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="90" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="118" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="174" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="230" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="258" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="272" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="384" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="580" cy="170" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="53" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="81" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="95" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="179" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="193" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="249" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="277" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="445" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="459" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="557" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="585" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="599" cy="184" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="174" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="188" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="230" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="258" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="412" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="426" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="566" cy="198" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="25" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="67" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="235" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="389" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="403" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="417" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="431" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="445" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="473" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="543" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="571" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="585" cy="212" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="34" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="188" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="398" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="468" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="496" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="552" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="566" cy="226" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="67" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="95" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="179" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="193" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="221" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="277" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="375" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="389" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="431" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="473" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="501" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="571" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="599" cy="240" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="48" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="90" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="188" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="216" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="258" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="286" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="300" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="384" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="454" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="468" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="594" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="608" cy="254" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="39" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="81" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="193" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="263" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="319" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="333" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="403" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="417" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="431" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="445" cy="268" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="20" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="76" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="90" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="146" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="244" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="356" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="370" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="384" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="398" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="412" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="510" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="608" cy="282" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="25" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="53" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="151" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="207" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="221" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="277" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="319" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="361" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="431" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="445" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="473" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="515" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="543" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="557" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="599" cy="296" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="76" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="90" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="132" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="160" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="188" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="230" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="328" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="370" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="384" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="440" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="496" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="524" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="552" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="580" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="594" cy="310" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="25" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="109" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="151" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="165" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="193" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="207" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="263" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="319" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="347" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="375" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="417" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="515" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="529" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="543" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
<circle cx="571" cy="324" r="1.2" fill="#3a3f55" opacity="0.55"/>
                <circle cx="86.3" cy="60.1" r="1.6" fill="#5c6280"/>
<circle cx="92.6" cy="60.4" r="1.6" fill="#5c6280"/>
<circle cx="100.5" cy="61.4" r="1.6" fill="#5c6280"/>
<circle cx="107.8" cy="60.2" r="1.6" fill="#5c6280"/>
<circle cx="113.8" cy="60.1" r="1.6" fill="#5c6280"/>
<circle cx="120.4" cy="61.0" r="1.6" fill="#5c6280"/>
<circle cx="127.1" cy="60.4" r="1.6" fill="#5c6280"/>
<circle cx="317.3" cy="61.1" r="1.6" fill="#5c6280"/>
<circle cx="323.4" cy="61.2" r="1.6" fill="#5c6280"/>
<circle cx="436.6" cy="60.0" r="1.6" fill="#5c6280"/>
<circle cx="443.6" cy="61.4" r="1.6" fill="#5c6280"/>
<circle cx="449.7" cy="60.3" r="1.6" fill="#5c6280"/>
<circle cx="457.9" cy="60.7" r="1.6" fill="#5c6280"/>
<circle cx="463.2" cy="60.2" r="1.6" fill="#5c6280"/>
<circle cx="75.7" cy="68.2" r="1.6" fill="#5c6280"/>
<circle cx="82.6" cy="68.5" r="1.6" fill="#5c6280"/>
<circle cx="89.1" cy="68.9" r="1.6" fill="#5c6280"/>
<circle cx="95.8" cy="68.1" r="1.6" fill="#5c6280"/>
<circle cx="103.7" cy="68.2" r="1.6" fill="#5c6280"/>
<circle cx="110.7" cy="68.2" r="1.6" fill="#5c6280"/>
<circle cx="117.4" cy="67.1" r="1.6" fill="#5c6280"/>
<circle cx="123.5" cy="67.6" r="1.6" fill="#5c6280"/>
<circle cx="130.2" cy="67.5" r="1.6" fill="#5c6280"/>
<circle cx="137.2" cy="67.6" r="1.6" fill="#5c6280"/>
<circle cx="299.3" cy="67.7" r="1.6" fill="#5c6280"/>
<circle cx="305.7" cy="67.4" r="1.6" fill="#5c6280"/>
<circle cx="312.5" cy="68.9" r="1.6" fill="#5c6280"/>
<circle cx="320.3" cy="68.2" r="1.6" fill="#5c6280"/>
<circle cx="326.3" cy="68.5" r="1.6" fill="#5c6280"/>
<circle cx="333.3" cy="67.8" r="1.6" fill="#5c6280"/>
<circle cx="342.0" cy="68.3" r="1.6" fill="#5c6280"/>
<circle cx="411.1" cy="68.4" r="1.6" fill="#5c6280"/>
<circle cx="418.7" cy="68.6" r="1.6" fill="#5c6280"/>
<circle cx="424.5" cy="67.1" r="1.6" fill="#5c6280"/>
<circle cx="431.6" cy="67.5" r="1.6" fill="#5c6280"/>
<circle cx="438.4" cy="68.9" r="1.6" fill="#5c6280"/>
<circle cx="446.8" cy="67.6" r="1.6" fill="#5c6280"/>
<circle cx="453.3" cy="67.8" r="1.6" fill="#5c6280"/>
<circle cx="460.8" cy="67.9" r="1.6" fill="#5c6280"/>
<circle cx="466.5" cy="67.5" r="1.6" fill="#5c6280"/>
<circle cx="474.1" cy="67.5" r="1.6" fill="#5c6280"/>
<circle cx="481.2" cy="68.8" r="1.6" fill="#5c6280"/>
<circle cx="487.8" cy="67.4" r="1.6" fill="#5c6280"/>
<circle cx="66.0" cy="75.0" r="1.6" fill="#5c6280"/>
<circle cx="71.2" cy="74.1" r="1.6" fill="#5c6280"/>
<circle cx="78.2" cy="75.3" r="1.6" fill="#5c6280"/>
<circle cx="86.6" cy="74.8" r="1.6" fill="#5c6280"/>
<circle cx="92.1" cy="74.8" r="1.6" fill="#5c6280"/>
<circle cx="101.0" cy="75.1" r="1.6" fill="#5c6280"/>
<circle cx="107.9" cy="75.7" r="1.6" fill="#5c6280"/>
<circle cx="113.0" cy="75.4" r="1.6" fill="#5c6280"/>
<circle cx="121.4" cy="75.1" r="1.6" fill="#5c6280"/>
<circle cx="127.5" cy="75.3" r="1.6" fill="#5c6280"/>
<circle cx="134.2" cy="74.9" r="1.6" fill="#5c6280"/>
<circle cx="141.9" cy="75.9" r="1.6" fill="#5c6280"/>
<circle cx="289.8" cy="74.5" r="1.6" fill="#5c6280"/>
<circle cx="296.0" cy="74.4" r="1.6" fill="#5c6280"/>
<circle cx="303.8" cy="75.7" r="1.6" fill="#5c6280"/>
<circle cx="309.6" cy="75.3" r="1.6" fill="#5c6280"/>
<circle cx="317.2" cy="74.3" r="1.6" fill="#5c6280"/>
<circle cx="324.5" cy="75.1" r="1.6" fill="#5c6280"/>
<circle cx="331.6" cy="75.1" r="1.6" fill="#5c6280"/>
<circle cx="337.0" cy="74.6" r="1.6" fill="#5c6280"/>
<circle cx="344.0" cy="75.9" r="1.6" fill="#5c6280"/>
<circle cx="352.8" cy="75.7" r="1.6" fill="#5c6280"/>
<circle cx="393.6" cy="74.1" r="1.6" fill="#5c6280"/>
<circle cx="401.8" cy="75.9" r="1.6" fill="#5c6280"/>
<circle cx="407.2" cy="75.0" r="1.6" fill="#5c6280"/>
<circle cx="414.1" cy="75.5" r="1.6" fill="#5c6280"/>
<circle cx="422.5" cy="74.3" r="1.6" fill="#5c6280"/>
<circle cx="429.0" cy="75.1" r="1.6" fill="#5c6280"/>
<circle cx="435.5" cy="75.7" r="1.6" fill="#5c6280"/>
<circle cx="442.8" cy="74.4" r="1.6" fill="#5c6280"/>
<circle cx="450.1" cy="75.5" r="1.6" fill="#5c6280"/>
<circle cx="456.4" cy="74.6" r="1.6" fill="#5c6280"/>
<circle cx="465.0" cy="75.3" r="1.6" fill="#5c6280"/>
<circle cx="470.9" cy="75.0" r="1.6" fill="#5c6280"/>
<circle cx="477.2" cy="74.4" r="1.6" fill="#5c6280"/>
<circle cx="484.7" cy="75.2" r="1.6" fill="#5c6280"/>
<circle cx="491.5" cy="74.4" r="1.6" fill="#5c6280"/>
<circle cx="498.1" cy="75.3" r="1.6" fill="#5c6280"/>
<circle cx="505.5" cy="75.8" r="1.6" fill="#5c6280"/>
<circle cx="61.7" cy="81.1" r="1.6" fill="#5c6280"/>
<circle cx="67.5" cy="82.3" r="1.6" fill="#5c6280"/>
<circle cx="74.4" cy="81.3" r="1.6" fill="#5c6280"/>
<circle cx="82.9" cy="82.1" r="1.6" fill="#5c6280"/>
<circle cx="88.9" cy="82.6" r="1.6" fill="#5c6280"/>
<circle cx="96.6" cy="81.4" r="1.6" fill="#5c6280"/>
<circle cx="102.2" cy="81.9" r="1.6" fill="#5c6280"/>
<circle cx="109.8" cy="81.9" r="1.6" fill="#5c6280"/>
<circle cx="117.5" cy="82.3" r="1.6" fill="#5c6280"/>
<circle cx="125.0" cy="81.2" r="1.6" fill="#5c6280"/>
<circle cx="130.8" cy="81.7" r="1.6" fill="#5c6280"/>
<circle cx="138.7" cy="81.5" r="1.6" fill="#5c6280"/>
<circle cx="144.4" cy="81.9" r="1.6" fill="#5c6280"/>
<circle cx="151.8" cy="81.6" r="1.6" fill="#5c6280"/>
<circle cx="284.5" cy="82.8" r="1.6" fill="#5c6280"/>
<circle cx="291.9" cy="82.7" r="1.6" fill="#5c6280"/>
<circle cx="299.1" cy="81.1" r="1.6" fill="#5c6280"/>
<circle cx="307.0" cy="82.7" r="1.6" fill="#5c6280"/>
<circle cx="313.9" cy="82.9" r="1.6" fill="#5c6280"/>
<circle cx="320.7" cy="81.3" r="1.6" fill="#5c6280"/>
<circle cx="327.0" cy="81.4" r="1.6" fill="#5c6280"/>
<circle cx="333.8" cy="81.1" r="1.6" fill="#5c6280"/>
<circle cx="340.8" cy="83.0" r="1.6" fill="#5c6280"/>
<circle cx="347.5" cy="82.6" r="1.6" fill="#5c6280"/>
<circle cx="354.9" cy="81.8" r="1.6" fill="#5c6280"/>
<circle cx="383.9" cy="83.0" r="1.6" fill="#5c6280"/>
<circle cx="390.1" cy="82.4" r="1.6" fill="#5c6280"/>
<circle cx="396.3" cy="81.6" r="1.6" fill="#5c6280"/>
<circle cx="404.9" cy="82.2" r="1.6" fill="#5c6280"/>
<circle cx="411.1" cy="82.5" r="1.6" fill="#5c6280"/>
<circle cx="417.1" cy="82.2" r="1.6" fill="#5c6280"/>
<circle cx="425.0" cy="82.7" r="1.6" fill="#5c6280"/>
<circle cx="431.3" cy="82.9" r="1.6" fill="#5c6280"/>
<circle cx="438.2" cy="81.4" r="1.6" fill="#5c6280"/>
<circle cx="446.2" cy="82.4" r="1.6" fill="#5c6280"/>
<circle cx="452.5" cy="81.2" r="1.6" fill="#5c6280"/>
<circle cx="460.8" cy="81.5" r="1.6" fill="#5c6280"/>
<circle cx="467.2" cy="82.2" r="1.6" fill="#5c6280"/>
<circle cx="473.8" cy="82.2" r="1.6" fill="#5c6280"/>
<circle cx="481.0" cy="82.9" r="1.6" fill="#5c6280"/>
<circle cx="487.4" cy="82.4" r="1.6" fill="#5c6280"/>
<circle cx="494.5" cy="81.8" r="1.6" fill="#5c6280"/>
<circle cx="502.3" cy="81.6" r="1.6" fill="#5c6280"/>
<circle cx="508.6" cy="82.5" r="1.6" fill="#5c6280"/>
<circle cx="515.1" cy="81.9" r="1.6" fill="#5c6280"/>
<circle cx="59.0" cy="90.0" r="1.6" fill="#5c6280"/>
<circle cx="64.1" cy="88.4" r="1.6" fill="#5c6280"/>
<circle cx="71.5" cy="89.9" r="1.6" fill="#5c6280"/>
<circle cx="79.8" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="85.7" cy="88.3" r="1.6" fill="#5c6280"/>
<circle cx="93.7" cy="89.4" r="1.6" fill="#5c6280"/>
<circle cx="100.2" cy="90.0" r="1.6" fill="#5c6280"/>
<circle cx="107.3" cy="88.0" r="1.6" fill="#5c6280"/>
<circle cx="114.6" cy="88.6" r="1.6" fill="#5c6280"/>
<circle cx="121.3" cy="89.9" r="1.6" fill="#5c6280"/>
<circle cx="127.3" cy="88.2" r="1.6" fill="#5c6280"/>
<circle cx="134.2" cy="89.1" r="1.6" fill="#5c6280"/>
<circle cx="141.5" cy="89.2" r="1.6" fill="#5c6280"/>
<circle cx="149.4" cy="88.4" r="1.6" fill="#5c6280"/>
<circle cx="282.3" cy="88.5" r="1.6" fill="#5c6280"/>
<circle cx="289.0" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="296.7" cy="88.2" r="1.6" fill="#5c6280"/>
<circle cx="302.8" cy="88.6" r="1.6" fill="#5c6280"/>
<circle cx="309.0" cy="89.5" r="1.6" fill="#5c6280"/>
<circle cx="317.3" cy="88.5" r="1.6" fill="#5c6280"/>
<circle cx="324.5" cy="89.1" r="1.6" fill="#5c6280"/>
<circle cx="330.9" cy="88.0" r="1.6" fill="#5c6280"/>
<circle cx="337.2" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="345.8" cy="89.1" r="1.6" fill="#5c6280"/>
<circle cx="352.7" cy="89.2" r="1.6" fill="#5c6280"/>
<circle cx="358.3" cy="88.3" r="1.6" fill="#5c6280"/>
<circle cx="372.6" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="380.6" cy="89.7" r="1.6" fill="#5c6280"/>
<circle cx="387.8" cy="88.4" r="1.6" fill="#5c6280"/>
<circle cx="393.5" cy="88.2" r="1.6" fill="#5c6280"/>
<circle cx="401.6" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="407.8" cy="89.2" r="1.6" fill="#5c6280"/>
<circle cx="414.3" cy="89.9" r="1.6" fill="#5c6280"/>
<circle cx="422.7" cy="90.0" r="1.6" fill="#5c6280"/>
<circle cx="429.6" cy="89.8" r="1.6" fill="#5c6280"/>
<circle cx="435.0" cy="89.5" r="1.6" fill="#5c6280"/>
<circle cx="442.7" cy="89.9" r="1.6" fill="#5c6280"/>
<circle cx="450.6" cy="89.7" r="1.6" fill="#5c6280"/>
<circle cx="457.6" cy="88.5" r="1.6" fill="#5c6280"/>
<circle cx="464.6" cy="88.2" r="1.6" fill="#5c6280"/>
<circle cx="471.7" cy="89.7" r="1.6" fill="#5c6280"/>
<circle cx="477.4" cy="89.6" r="1.6" fill="#5c6280"/>
<circle cx="484.9" cy="88.6" r="1.6" fill="#5c6280"/>
<circle cx="492.6" cy="88.5" r="1.6" fill="#5c6280"/>
<circle cx="498.0" cy="88.4" r="1.6" fill="#5c6280"/>
<circle cx="505.7" cy="89.7" r="1.6" fill="#5c6280"/>
<circle cx="513.9" cy="88.6" r="1.6" fill="#5c6280"/>
<circle cx="520.3" cy="88.8" r="1.6" fill="#5c6280"/>
<circle cx="528.0" cy="89.1" r="1.6" fill="#5c6280"/>
<circle cx="54.9" cy="95.2" r="1.6" fill="#5c6280"/>
<circle cx="61.9" cy="95.4" r="1.6" fill="#5c6280"/>
<circle cx="68.9" cy="95.5" r="1.6" fill="#5c6280"/>
<circle cx="74.2" cy="95.9" r="1.6" fill="#5c6280"/>
<circle cx="82.5" cy="95.6" r="1.6" fill="#5c6280"/>
<circle cx="89.2" cy="96.0" r="1.6" fill="#5c6280"/>
<circle cx="95.8" cy="96.2" r="1.6" fill="#5c6280"/>
<circle cx="102.5" cy="96.4" r="1.6" fill="#5c6280"/>
<circle cx="109.0" cy="96.9" r="1.6" fill="#5c6280"/>
<circle cx="117.1" cy="96.4" r="1.6" fill="#5c6280"/>
<circle cx="124.5" cy="96.3" r="1.6" fill="#5c6280"/>
<circle cx="130.7" cy="95.1" r="1.6" fill="#5c6280"/>
<circle cx="138.3" cy="95.7" r="1.6" fill="#5c6280"/>
<circle cx="144.6" cy="96.7" r="1.6" fill="#5c6280"/>
<circle cx="152.4" cy="95.6" r="1.6" fill="#5c6280"/>
<circle cx="284.6" cy="95.8" r="1.6" fill="#5c6280"/>
<circle cx="291.8" cy="95.6" r="1.6" fill="#5c6280"/>
<circle cx="298.3" cy="95.8" r="1.6" fill="#5c6280"/>
<circle cx="306.9" cy="96.4" r="1.6" fill="#5c6280"/>
<circle cx="313.8" cy="96.2" r="1.6" fill="#5c6280"/>
<circle cx="319.6" cy="96.1" r="1.6" fill="#5c6280"/>
<circle cx="326.0" cy="95.6" r="1.6" fill="#5c6280"/>
<circle cx="333.9" cy="96.2" r="1.6" fill="#5c6280"/>
<circle cx="341.3" cy="95.9" r="1.6" fill="#5c6280"/>
<circle cx="347.9" cy="95.4" r="1.6" fill="#5c6280"/>
<circle cx="354.9" cy="96.8" r="1.6" fill="#5c6280"/>
<circle cx="369.6" cy="95.3" r="1.6" fill="#5c6280"/>
<circle cx="375.2" cy="96.0" r="1.6" fill="#5c6280"/>
<circle cx="383.3" cy="95.7" r="1.6" fill="#5c6280"/>
<circle cx="390.6" cy="96.5" r="1.6" fill="#5c6280"/>
<circle cx="397.3" cy="95.4" r="1.6" fill="#5c6280"/>
<circle cx="403.4" cy="95.0" r="1.6" fill="#5c6280"/>
<circle cx="410.5" cy="96.0" r="1.6" fill="#5c6280"/>
<circle cx="418.7" cy="95.1" r="1.6" fill="#5c6280"/>
<circle cx="424.8" cy="96.3" r="1.6" fill="#5c6280"/>
<circle cx="431.4" cy="96.4" r="1.6" fill="#5c6280"/>
<circle cx="439.0" cy="95.5" r="1.6" fill="#5c6280"/>
<circle cx="446.3" cy="95.0" r="1.6" fill="#5c6280"/>
<circle cx="453.5" cy="96.5" r="1.6" fill="#5c6280"/>
<circle cx="459.2" cy="95.9" r="1.6" fill="#5c6280"/>
<circle cx="466.4" cy="96.9" r="1.6" fill="#5c6280"/>
<circle cx="474.0" cy="95.1" r="1.6" fill="#5c6280"/>
<circle cx="480.5" cy="96.7" r="1.6" fill="#5c6280"/>
<circle cx="487.9" cy="96.6" r="1.6" fill="#5c6280"/>
<circle cx="495.3" cy="97.0" r="1.6" fill="#5c6280"/>
<circle cx="502.2" cy="96.9" r="1.6" fill="#5c6280"/>
<circle cx="509.8" cy="96.2" r="1.6" fill="#5c6280"/>
<circle cx="516.4" cy="96.0" r="1.6" fill="#5c6280"/>
<circle cx="523.7" cy="96.1" r="1.6" fill="#5c6280"/>
<circle cx="530.8" cy="96.5" r="1.6" fill="#5c6280"/>
<circle cx="50.9" cy="102.5" r="1.6" fill="#5c6280"/>
<circle cx="57.5" cy="103.3" r="1.6" fill="#5c6280"/>
<circle cx="65.5" cy="103.0" r="1.6" fill="#5c6280"/>
<circle cx="72.3" cy="102.5" r="1.6" fill="#5c6280"/>
<circle cx="78.2" cy="102.6" r="1.6" fill="#5c6280"/>
<circle cx="85.5" cy="102.6" r="1.6" fill="#5c6280"/>
<circle cx="93.1" cy="102.3" r="1.6" fill="#5c6280"/>
<circle cx="99.5" cy="103.4" r="1.6" fill="#5c6280"/>
<circle cx="107.4" cy="102.1" r="1.6" fill="#5c6280"/>
<circle cx="113.8" cy="103.1" r="1.6" fill="#5c6280"/>
<circle cx="120.8" cy="102.4" r="1.6" fill="#5c6280"/>
<circle cx="127.8" cy="103.8" r="1.6" fill="#5c6280"/>
<circle cx="135.2" cy="103.4" r="1.6" fill="#5c6280"/>
<circle cx="142.7" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="148.8" cy="102.0" r="1.6" fill="#5c6280"/>
<circle cx="155.7" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="282.7" cy="103.9" r="1.6" fill="#5c6280"/>
<circle cx="288.8" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="296.1" cy="103.2" r="1.6" fill="#5c6280"/>
<circle cx="302.4" cy="102.4" r="1.6" fill="#5c6280"/>
<circle cx="309.9" cy="102.1" r="1.6" fill="#5c6280"/>
<circle cx="316.7" cy="103.4" r="1.6" fill="#5c6280"/>
<circle cx="323.8" cy="102.3" r="1.6" fill="#5c6280"/>
<circle cx="330.9" cy="102.3" r="1.6" fill="#5c6280"/>
<circle cx="338.2" cy="102.1" r="1.6" fill="#5c6280"/>
<circle cx="344.8" cy="103.1" r="1.6" fill="#5c6280"/>
<circle cx="351.1" cy="103.3" r="1.6" fill="#5c6280"/>
<circle cx="365.3" cy="102.9" r="1.6" fill="#5c6280"/>
<circle cx="372.1" cy="102.8" r="1.6" fill="#5c6280"/>
<circle cx="379.4" cy="102.7" r="1.6" fill="#5c6280"/>
<circle cx="387.5" cy="102.8" r="1.6" fill="#5c6280"/>
<circle cx="394.5" cy="103.7" r="1.6" fill="#5c6280"/>
<circle cx="400.5" cy="102.2" r="1.6" fill="#5c6280"/>
<circle cx="407.0" cy="103.1" r="1.6" fill="#5c6280"/>
<circle cx="416.0" cy="102.7" r="1.6" fill="#5c6280"/>
<circle cx="422.3" cy="103.6" r="1.6" fill="#5c6280"/>
<circle cx="429.3" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="436.9" cy="102.4" r="1.6" fill="#5c6280"/>
<circle cx="442.0" cy="102.3" r="1.6" fill="#5c6280"/>
<circle cx="449.3" cy="103.3" r="1.6" fill="#5c6280"/>
<circle cx="457.1" cy="102.4" r="1.6" fill="#5c6280"/>
<circle cx="464.4" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="470.3" cy="103.2" r="1.6" fill="#5c6280"/>
<circle cx="478.5" cy="102.2" r="1.6" fill="#5c6280"/>
<circle cx="485.6" cy="103.9" r="1.6" fill="#5c6280"/>
<circle cx="491.2" cy="102.1" r="1.6" fill="#5c6280"/>
<circle cx="498.6" cy="103.4" r="1.6" fill="#5c6280"/>
<circle cx="506.9" cy="102.8" r="1.6" fill="#5c6280"/>
<circle cx="513.4" cy="102.2" r="1.6" fill="#5c6280"/>
<circle cx="520.4" cy="103.3" r="1.6" fill="#5c6280"/>
<circle cx="526.2" cy="103.5" r="1.6" fill="#5c6280"/>
<circle cx="534.7" cy="103.2" r="1.6" fill="#5c6280"/>
<circle cx="53.2" cy="111.0" r="1.6" fill="#5c6280"/>
<circle cx="61.6" cy="109.7" r="1.6" fill="#5c6280"/>
<circle cx="67.9" cy="109.7" r="1.6" fill="#5c6280"/>
<circle cx="75.0" cy="109.7" r="1.6" fill="#5c6280"/>
<circle cx="82.7" cy="110.6" r="1.6" fill="#5c6280"/>
<circle cx="88.2" cy="110.9" r="1.6" fill="#5c6280"/>
<circle cx="96.3" cy="110.7" r="1.6" fill="#5c6280"/>
<circle cx="103.4" cy="109.9" r="1.6" fill="#5c6280"/>
<circle cx="110.5" cy="110.9" r="1.6" fill="#5c6280"/>
<circle cx="116.5" cy="110.6" r="1.6" fill="#5c6280"/>
<circle cx="124.1" cy="110.0" r="1.6" fill="#5c6280"/>
<circle cx="130.9" cy="110.5" r="1.6" fill="#5c6280"/>
<circle cx="137.5" cy="110.7" r="1.6" fill="#5c6280"/>
<circle cx="145.7" cy="109.2" r="1.6" fill="#5c6280"/>
<circle cx="152.8" cy="109.5" r="1.6" fill="#5c6280"/>
<circle cx="158.9" cy="110.2" r="1.6" fill="#5c6280"/>
<circle cx="284.8" cy="109.1" r="1.6" fill="#5c6280"/>
<circle cx="292.7" cy="109.4" r="1.6" fill="#5c6280"/>
<circle cx="298.4" cy="110.6" r="1.6" fill="#5c6280"/>
<circle cx="305.7" cy="110.8" r="1.6" fill="#5c6280"/>
<circle cx="313.4" cy="109.6" r="1.6" fill="#5c6280"/>
<circle cx="319.0" cy="110.9" r="1.6" fill="#5c6280"/>
<circle cx="326.2" cy="110.4" r="1.6" fill="#5c6280"/>
<circle cx="334.0" cy="110.5" r="1.6" fill="#5c6280"/>
<circle cx="341.4" cy="110.3" r="1.6" fill="#5c6280"/>
<circle cx="348.0" cy="110.6" r="1.6" fill="#5c6280"/>
<circle cx="354.2" cy="109.4" r="1.6" fill="#5c6280"/>
<circle cx="362.4" cy="109.6" r="1.6" fill="#5c6280"/>
<circle cx="369.2" cy="109.9" r="1.6" fill="#5c6280"/>
<circle cx="376.1" cy="109.9" r="1.6" fill="#5c6280"/>
<circle cx="383.5" cy="109.7" r="1.6" fill="#5c6280"/>
<circle cx="390.4" cy="109.5" r="1.6" fill="#5c6280"/>
<circle cx="396.5" cy="109.2" r="1.6" fill="#5c6280"/>
<circle cx="403.4" cy="109.2" r="1.6" fill="#5c6280"/>
<circle cx="411.1" cy="110.5" r="1.6" fill="#5c6280"/>
<circle cx="417.4" cy="109.4" r="1.6" fill="#5c6280"/>
<circle cx="425.0" cy="110.4" r="1.6" fill="#5c6280"/>
<circle cx="433.0" cy="110.0" r="1.6" fill="#5c6280"/>
<circle cx="438.6" cy="109.2" r="1.6" fill="#5c6280"/>
<circle cx="445.4" cy="109.5" r="1.6" fill="#5c6280"/>
<circle cx="452.4" cy="109.0" r="1.6" fill="#5c6280"/>
<circle cx="460.1" cy="109.5" r="1.6" fill="#5c6280"/>
<circle cx="467.9" cy="110.1" r="1.6" fill="#5c6280"/>
<circle cx="474.4" cy="109.3" r="1.6" fill="#5c6280"/>
<circle cx="481.7" cy="110.0" r="1.6" fill="#5c6280"/>
<circle cx="488.7" cy="110.1" r="1.6" fill="#5c6280"/>
<circle cx="494.9" cy="109.9" r="1.6" fill="#5c6280"/>
<circle cx="501.4" cy="109.1" r="1.6" fill="#5c6280"/>
<circle cx="509.9" cy="110.0" r="1.6" fill="#5c6280"/>
<circle cx="516.6" cy="109.8" r="1.6" fill="#5c6280"/>
<circle cx="522.1" cy="110.3" r="1.6" fill="#5c6280"/>
<circle cx="529.1" cy="109.3" r="1.6" fill="#5c6280"/>
<circle cx="537.1" cy="109.6" r="1.6" fill="#5c6280"/>
<circle cx="52.0" cy="116.2" r="1.6" fill="#5c6280"/>
<circle cx="58.5" cy="117.2" r="1.6" fill="#5c6280"/>
<circle cx="65.6" cy="116.5" r="1.6" fill="#5c6280"/>
<circle cx="72.0" cy="116.9" r="1.6" fill="#5c6280"/>
<circle cx="78.9" cy="117.7" r="1.6" fill="#5c6280"/>
<circle cx="87.0" cy="116.6" r="1.6" fill="#5c6280"/>
<circle cx="93.2" cy="117.2" r="1.6" fill="#5c6280"/>
<circle cx="100.5" cy="117.9" r="1.6" fill="#5c6280"/>
<circle cx="106.4" cy="116.4" r="1.6" fill="#5c6280"/>
<circle cx="114.3" cy="116.3" r="1.6" fill="#5c6280"/>
<circle cx="120.3" cy="116.2" r="1.6" fill="#5c6280"/>
<circle cx="127.0" cy="116.9" r="1.6" fill="#5c6280"/>
<circle cx="135.2" cy="116.6" r="1.6" fill="#5c6280"/>
<circle cx="141.5" cy="117.4" r="1.6" fill="#5c6280"/>
<circle cx="149.4" cy="116.9" r="1.6" fill="#5c6280"/>
<circle cx="156.4" cy="117.8" r="1.6" fill="#5c6280"/>
<circle cx="289.6" cy="117.3" r="1.6" fill="#5c6280"/>
<circle cx="296.3" cy="117.9" r="1.6" fill="#5c6280"/>
<circle cx="302.9" cy="117.1" r="1.6" fill="#5c6280"/>
<circle cx="310.3" cy="117.8" r="1.6" fill="#5c6280"/>
<circle cx="317.7" cy="116.1" r="1.6" fill="#5c6280"/>
<circle cx="323.3" cy="116.6" r="1.6" fill="#5c6280"/>
<circle cx="331.5" cy="117.1" r="1.6" fill="#5c6280"/>
<circle cx="337.6" cy="116.2" r="1.6" fill="#5c6280"/>
<circle cx="345.4" cy="117.4" r="1.6" fill="#5c6280"/>
<circle cx="366.9" cy="117.0" r="1.6" fill="#5c6280"/>
<circle cx="373.0" cy="116.2" r="1.6" fill="#5c6280"/>
<circle cx="379.1" cy="116.9" r="1.6" fill="#5c6280"/>
<circle cx="386.6" cy="116.5" r="1.6" fill="#5c6280"/>
<circle cx="393.2" cy="117.9" r="1.6" fill="#5c6280"/>
<circle cx="401.7" cy="117.2" r="1.6" fill="#5c6280"/>
<circle cx="408.9" cy="118.0" r="1.6" fill="#5c6280"/>
<circle cx="415.3" cy="116.5" r="1.6" fill="#5c6280"/>
<circle cx="421.1" cy="117.5" r="1.6" fill="#5c6280"/>
<circle cx="428.9" cy="117.3" r="1.6" fill="#5c6280"/>
<circle cx="436.8" cy="116.4" r="1.6" fill="#5c6280"/>
<circle cx="443.2" cy="117.3" r="1.6" fill="#5c6280"/>
<circle cx="450.0" cy="116.2" r="1.6" fill="#5c6280"/>
<circle cx="456.7" cy="116.7" r="1.6" fill="#5c6280"/>
<circle cx="464.3" cy="117.7" r="1.6" fill="#5c6280"/>
<circle cx="470.7" cy="117.4" r="1.6" fill="#5c6280"/>
<circle cx="477.6" cy="117.9" r="1.6" fill="#5c6280"/>
<circle cx="485.6" cy="117.1" r="1.6" fill="#5c6280"/>
<circle cx="491.9" cy="116.6" r="1.6" fill="#5c6280"/>
<circle cx="498.6" cy="117.9" r="1.6" fill="#5c6280"/>
<circle cx="505.8" cy="117.0" r="1.6" fill="#5c6280"/>
<circle cx="514.0" cy="117.3" r="1.6" fill="#5c6280"/>
<circle cx="520.1" cy="116.8" r="1.6" fill="#5c6280"/>
<circle cx="526.4" cy="116.7" r="1.6" fill="#5c6280"/>
<circle cx="534.5" cy="117.3" r="1.6" fill="#5c6280"/>
<circle cx="54.5" cy="123.4" r="1.6" fill="#5c6280"/>
<circle cx="61.1" cy="124.9" r="1.6" fill="#5c6280"/>
<circle cx="67.9" cy="124.4" r="1.6" fill="#5c6280"/>
<circle cx="74.2" cy="124.9" r="1.6" fill="#5c6280"/>
<circle cx="82.2" cy="123.5" r="1.6" fill="#5c6280"/>
<circle cx="88.3" cy="124.1" r="1.6" fill="#5c6280"/>
<circle cx="96.1" cy="123.2" r="1.6" fill="#5c6280"/>
<circle cx="104.0" cy="124.8" r="1.6" fill="#5c6280"/>
<circle cx="109.9" cy="123.2" r="1.6" fill="#5c6280"/>
<circle cx="117.7" cy="124.0" r="1.6" fill="#5c6280"/>
<circle cx="124.4" cy="124.0" r="1.6" fill="#5c6280"/>
<circle cx="130.5" cy="124.7" r="1.6" fill="#5c6280"/>
<circle cx="139.0" cy="123.5" r="1.6" fill="#5c6280"/>
<circle cx="145.1" cy="123.8" r="1.6" fill="#5c6280"/>
<circle cx="152.8" cy="124.0" r="1.6" fill="#5c6280"/>
<circle cx="299.8" cy="124.7" r="1.6" fill="#5c6280"/>
<circle cx="305.6" cy="124.6" r="1.6" fill="#5c6280"/>
<circle cx="312.8" cy="124.9" r="1.6" fill="#5c6280"/>
<circle cx="320.0" cy="124.6" r="1.6" fill="#5c6280"/>
<circle cx="326.6" cy="123.6" r="1.6" fill="#5c6280"/>
<circle cx="334.2" cy="125.0" r="1.6" fill="#5c6280"/>
<circle cx="341.0" cy="123.3" r="1.6" fill="#5c6280"/>
<circle cx="362.1" cy="123.7" r="1.6" fill="#5c6280"/>
<circle cx="369.1" cy="124.1" r="1.6" fill="#5c6280"/>
<circle cx="375.9" cy="123.6" r="1.6" fill="#5c6280"/>
<circle cx="382.4" cy="124.4" r="1.6" fill="#5c6280"/>
<circle cx="390.1" cy="123.5" r="1.6" fill="#5c6280"/>
<circle cx="397.6" cy="123.1" r="1.6" fill="#5c6280"/>
<circle cx="404.5" cy="124.4" r="1.6" fill="#5c6280"/>
<circle cx="411.6" cy="123.8" r="1.6" fill="#5c6280"/>
<circle cx="418.3" cy="124.6" r="1.6" fill="#5c6280"/>
<circle cx="426.0" cy="124.0" r="1.6" fill="#5c6280"/>
<circle cx="431.1" cy="124.0" r="1.6" fill="#5c6280"/>
<circle cx="439.2" cy="124.7" r="1.6" fill="#5c6280"/>
<circle cx="446.7" cy="123.9" r="1.6" fill="#5c6280"/>
<circle cx="453.1" cy="123.9" r="1.6" fill="#5c6280"/>
<circle cx="460.4" cy="123.8" r="1.6" fill="#5c6280"/>
<circle cx="467.3" cy="123.3" r="1.6" fill="#5c6280"/>
<circle cx="473.9" cy="124.9" r="1.6" fill="#5c6280"/>
<circle cx="480.7" cy="124.4" r="1.6" fill="#5c6280"/>
<circle cx="488.3" cy="124.7" r="1.6" fill="#5c6280"/>
<circle cx="495.7" cy="124.7" r="1.6" fill="#5c6280"/>
<circle cx="501.8" cy="123.6" r="1.6" fill="#5c6280"/>
<circle cx="509.4" cy="124.5" r="1.6" fill="#5c6280"/>
<circle cx="516.7" cy="123.1" r="1.6" fill="#5c6280"/>
<circle cx="522.1" cy="124.3" r="1.6" fill="#5c6280"/>
<circle cx="530.8" cy="125.0" r="1.6" fill="#5c6280"/>
<circle cx="537.5" cy="123.9" r="1.6" fill="#5c6280"/>
<circle cx="57.2" cy="131.3" r="1.6" fill="#5c6280"/>
<circle cx="65.7" cy="130.9" r="1.6" fill="#5c6280"/>
<circle cx="72.4" cy="131.8" r="1.6" fill="#5c6280"/>
<circle cx="78.1" cy="131.6" r="1.6" fill="#5c6280"/>
<circle cx="85.6" cy="130.7" r="1.6" fill="#5c6280"/>
<circle cx="92.3" cy="131.1" r="1.6" fill="#5c6280"/>
<circle cx="100.1" cy="131.6" r="1.6" fill="#5c6280"/>
<circle cx="106.3" cy="130.2" r="1.6" fill="#5c6280"/>
<circle cx="114.7" cy="131.2" r="1.6" fill="#5c6280"/>
<circle cx="120.5" cy="131.8" r="1.6" fill="#5c6280"/>
<circle cx="127.3" cy="130.9" r="1.6" fill="#5c6280"/>
<circle cx="134.5" cy="130.5" r="1.6" fill="#5c6280"/>
<circle cx="141.0" cy="131.6" r="1.6" fill="#5c6280"/>
<circle cx="149.8" cy="131.4" r="1.6" fill="#5c6280"/>
<circle cx="365.3" cy="130.9" r="1.6" fill="#5c6280"/>
<circle cx="372.7" cy="131.2" r="1.6" fill="#5c6280"/>
<circle cx="380.3" cy="130.8" r="1.6" fill="#5c6280"/>
<circle cx="386.5" cy="131.7" r="1.6" fill="#5c6280"/>
<circle cx="393.4" cy="130.8" r="1.6" fill="#5c6280"/>
<circle cx="401.0" cy="130.5" r="1.6" fill="#5c6280"/>
<circle cx="408.1" cy="131.1" r="1.6" fill="#5c6280"/>
<circle cx="416.0" cy="130.6" r="1.6" fill="#5c6280"/>
<circle cx="423.0" cy="131.3" r="1.6" fill="#5c6280"/>
<circle cx="428.5" cy="131.1" r="1.6" fill="#5c6280"/>
<circle cx="436.4" cy="131.5" r="1.6" fill="#5c6280"/>
<circle cx="442.1" cy="131.2" r="1.6" fill="#5c6280"/>
<circle cx="450.0" cy="131.8" r="1.6" fill="#5c6280"/>
<circle cx="456.6" cy="131.6" r="1.6" fill="#5c6280"/>
<circle cx="464.2" cy="130.7" r="1.6" fill="#5c6280"/>
<circle cx="471.3" cy="131.2" r="1.6" fill="#5c6280"/>
<circle cx="478.4" cy="131.4" r="1.6" fill="#5c6280"/>
<circle cx="485.3" cy="131.7" r="1.6" fill="#5c6280"/>
<circle cx="492.3" cy="131.8" r="1.6" fill="#5c6280"/>
<circle cx="499.3" cy="130.6" r="1.6" fill="#5c6280"/>
<circle cx="505.9" cy="131.2" r="1.6" fill="#5c6280"/>
<circle cx="513.5" cy="130.2" r="1.6" fill="#5c6280"/>
<circle cx="519.6" cy="131.5" r="1.6" fill="#5c6280"/>
<circle cx="526.4" cy="130.3" r="1.6" fill="#5c6280"/>
<circle cx="534.1" cy="131.9" r="1.6" fill="#5c6280"/>
<circle cx="61.1" cy="138.8" r="1.6" fill="#5c6280"/>
<circle cx="68.7" cy="137.5" r="1.6" fill="#5c6280"/>
<circle cx="75.6" cy="138.0" r="1.6" fill="#5c6280"/>
<circle cx="82.6" cy="138.5" r="1.6" fill="#5c6280"/>
<circle cx="88.7" cy="137.2" r="1.6" fill="#5c6280"/>
<circle cx="96.9" cy="137.3" r="1.6" fill="#5c6280"/>
<circle cx="103.9" cy="138.7" r="1.6" fill="#5c6280"/>
<circle cx="110.4" cy="139.0" r="1.6" fill="#5c6280"/>
<circle cx="117.9" cy="138.6" r="1.6" fill="#5c6280"/>
<circle cx="123.7" cy="138.6" r="1.6" fill="#5c6280"/>
<circle cx="130.0" cy="138.1" r="1.6" fill="#5c6280"/>
<circle cx="137.9" cy="138.3" r="1.6" fill="#5c6280"/>
<circle cx="145.3" cy="138.2" r="1.6" fill="#5c6280"/>
<circle cx="152.6" cy="138.9" r="1.6" fill="#5c6280"/>
<circle cx="326.2" cy="137.5" r="1.6" fill="#5c6280"/>
<circle cx="333.1" cy="138.8" r="1.6" fill="#5c6280"/>
<circle cx="341.1" cy="138.8" r="1.6" fill="#5c6280"/>
<circle cx="368.4" cy="137.1" r="1.6" fill="#5c6280"/>
<circle cx="376.6" cy="138.8" r="1.6" fill="#5c6280"/>
<circle cx="382.6" cy="137.8" r="1.6" fill="#5c6280"/>
<circle cx="389.3" cy="138.9" r="1.6" fill="#5c6280"/>
<circle cx="396.6" cy="138.0" r="1.6" fill="#5c6280"/>
<circle cx="403.2" cy="138.8" r="1.6" fill="#5c6280"/>
<circle cx="410.3" cy="137.9" r="1.6" fill="#5c6280"/>
<circle cx="418.3" cy="138.5" r="1.6" fill="#5c6280"/>
<circle cx="425.9" cy="137.8" r="1.6" fill="#5c6280"/>
<circle cx="432.5" cy="137.3" r="1.6" fill="#5c6280"/>
<circle cx="438.8" cy="137.2" r="1.6" fill="#5c6280"/>
<circle cx="446.0" cy="137.8" r="1.6" fill="#5c6280"/>
<circle cx="453.9" cy="137.1" r="1.6" fill="#5c6280"/>
<circle cx="459.7" cy="137.9" r="1.6" fill="#5c6280"/>
<circle cx="467.9" cy="138.7" r="1.6" fill="#5c6280"/>
<circle cx="473.2" cy="138.4" r="1.6" fill="#5c6280"/>
<circle cx="481.1" cy="139.0" r="1.6" fill="#5c6280"/>
<circle cx="487.7" cy="137.8" r="1.6" fill="#5c6280"/>
<circle cx="494.4" cy="137.2" r="1.6" fill="#5c6280"/>
<circle cx="502.7" cy="137.9" r="1.6" fill="#5c6280"/>
<circle cx="509.3" cy="138.3" r="1.6" fill="#5c6280"/>
<circle cx="516.2" cy="137.0" r="1.6" fill="#5c6280"/>
<circle cx="523.6" cy="137.5" r="1.6" fill="#5c6280"/>
<circle cx="529.3" cy="138.1" r="1.6" fill="#5c6280"/>
<circle cx="64.1" cy="145.5" r="1.6" fill="#5c6280"/>
<circle cx="71.4" cy="144.4" r="1.6" fill="#5c6280"/>
<circle cx="79.7" cy="144.7" r="1.6" fill="#5c6280"/>
<circle cx="85.3" cy="145.8" r="1.6" fill="#5c6280"/>
<circle cx="92.0" cy="145.7" r="1.6" fill="#5c6280"/>
<circle cx="99.3" cy="144.3" r="1.6" fill="#5c6280"/>
<circle cx="106.5" cy="144.3" r="1.6" fill="#5c6280"/>
<circle cx="114.3" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="120.0" cy="145.6" r="1.6" fill="#5c6280"/>
<circle cx="127.5" cy="144.6" r="1.6" fill="#5c6280"/>
<circle cx="134.3" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="142.5" cy="145.1" r="1.6" fill="#5c6280"/>
<circle cx="317.5" cy="145.0" r="1.6" fill="#5c6280"/>
<circle cx="324.6" cy="145.0" r="1.6" fill="#5c6280"/>
<circle cx="330.2" cy="145.0" r="1.6" fill="#5c6280"/>
<circle cx="338.9" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="345.6" cy="145.7" r="1.6" fill="#5c6280"/>
<circle cx="352.0" cy="144.9" r="1.6" fill="#5c6280"/>
<circle cx="373.9" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="380.0" cy="144.8" r="1.6" fill="#5c6280"/>
<circle cx="387.4" cy="145.0" r="1.6" fill="#5c6280"/>
<circle cx="394.8" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="400.2" cy="145.2" r="1.6" fill="#5c6280"/>
<circle cx="407.1" cy="144.6" r="1.6" fill="#5c6280"/>
<circle cx="415.3" cy="145.1" r="1.6" fill="#5c6280"/>
<circle cx="421.7" cy="146.0" r="1.6" fill="#5c6280"/>
<circle cx="429.1" cy="144.9" r="1.6" fill="#5c6280"/>
<circle cx="436.2" cy="144.2" r="1.6" fill="#5c6280"/>
<circle cx="443.4" cy="145.7" r="1.6" fill="#5c6280"/>
<circle cx="450.3" cy="145.5" r="1.6" fill="#5c6280"/>
<circle cx="457.4" cy="144.4" r="1.6" fill="#5c6280"/>
<circle cx="463.9" cy="144.5" r="1.6" fill="#5c6280"/>
<circle cx="470.7" cy="144.9" r="1.6" fill="#5c6280"/>
<circle cx="477.8" cy="144.2" r="1.6" fill="#5c6280"/>
<circle cx="484.9" cy="145.3" r="1.6" fill="#5c6280"/>
<circle cx="491.7" cy="144.3" r="1.6" fill="#5c6280"/>
<circle cx="499.8" cy="144.1" r="1.6" fill="#5c6280"/>
<circle cx="506.7" cy="144.2" r="1.6" fill="#5c6280"/>
<circle cx="512.2" cy="145.5" r="1.6" fill="#5c6280"/>
<circle cx="520.6" cy="145.1" r="1.6" fill="#5c6280"/>
<circle cx="527.2" cy="145.1" r="1.6" fill="#5c6280"/>
<circle cx="74.7" cy="151.2" r="1.6" fill="#5c6280"/>
<circle cx="81.7" cy="152.3" r="1.6" fill="#5c6280"/>
<circle cx="89.5" cy="152.7" r="1.6" fill="#5c6280"/>
<circle cx="96.4" cy="152.9" r="1.6" fill="#5c6280"/>
<circle cx="103.2" cy="151.7" r="1.6" fill="#5c6280"/>
<circle cx="110.2" cy="151.4" r="1.6" fill="#5c6280"/>
<circle cx="117.3" cy="151.4" r="1.6" fill="#5c6280"/>
<circle cx="123.2" cy="152.7" r="1.6" fill="#5c6280"/>
<circle cx="130.7" cy="152.5" r="1.6" fill="#5c6280"/>
<circle cx="138.1" cy="152.6" r="1.6" fill="#5c6280"/>
<circle cx="313.7" cy="152.9" r="1.6" fill="#5c6280"/>
<circle cx="320.6" cy="152.2" r="1.6" fill="#5c6280"/>
<circle cx="327.3" cy="151.1" r="1.6" fill="#5c6280"/>
<circle cx="334.9" cy="152.7" r="1.6" fill="#5c6280"/>
<circle cx="340.5" cy="151.4" r="1.6" fill="#5c6280"/>
<circle cx="348.4" cy="151.6" r="1.6" fill="#5c6280"/>
<circle cx="354.7" cy="151.0" r="1.6" fill="#5c6280"/>
<circle cx="376.7" cy="152.1" r="1.6" fill="#5c6280"/>
<circle cx="382.8" cy="151.3" r="1.6" fill="#5c6280"/>
<circle cx="390.3" cy="151.1" r="1.6" fill="#5c6280"/>
<circle cx="397.5" cy="151.4" r="1.6" fill="#5c6280"/>
<circle cx="403.8" cy="151.7" r="1.6" fill="#5c6280"/>
<circle cx="410.7" cy="152.4" r="1.6" fill="#5c6280"/>
<circle cx="418.6" cy="152.1" r="1.6" fill="#5c6280"/>
<circle cx="424.2" cy="151.1" r="1.6" fill="#5c6280"/>
<circle cx="431.3" cy="152.2" r="1.6" fill="#5c6280"/>
<circle cx="439.3" cy="151.5" r="1.6" fill="#5c6280"/>
<circle cx="446.3" cy="152.0" r="1.6" fill="#5c6280"/>
<circle cx="452.9" cy="151.5" r="1.6" fill="#5c6280"/>
<circle cx="460.5" cy="151.2" r="1.6" fill="#5c6280"/>
<circle cx="466.9" cy="151.6" r="1.6" fill="#5c6280"/>
<circle cx="474.4" cy="152.0" r="1.6" fill="#5c6280"/>
<circle cx="481.3" cy="151.1" r="1.6" fill="#5c6280"/>
<circle cx="487.8" cy="152.2" r="1.6" fill="#5c6280"/>
<circle cx="494.0" cy="151.6" r="1.6" fill="#5c6280"/>
<circle cx="501.4" cy="151.3" r="1.6" fill="#5c6280"/>
<circle cx="508.5" cy="151.7" r="1.6" fill="#5c6280"/>
<circle cx="515.0" cy="152.5" r="1.6" fill="#5c6280"/>
<circle cx="522.4" cy="151.8" r="1.6" fill="#5c6280"/>
<circle cx="86.4" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="93.7" cy="159.6" r="1.6" fill="#5c6280"/>
<circle cx="99.1" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="106.1" cy="158.0" r="1.6" fill="#5c6280"/>
<circle cx="114.8" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="121.2" cy="159.1" r="1.6" fill="#5c6280"/>
<circle cx="128.4" cy="158.8" r="1.6" fill="#5c6280"/>
<circle cx="309.2" cy="158.0" r="1.6" fill="#5c6280"/>
<circle cx="316.6" cy="159.6" r="1.6" fill="#5c6280"/>
<circle cx="324.2" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="331.8" cy="158.2" r="1.6" fill="#5c6280"/>
<circle cx="338.7" cy="158.5" r="1.6" fill="#5c6280"/>
<circle cx="345.2" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="351.8" cy="158.6" r="1.6" fill="#5c6280"/>
<circle cx="358.7" cy="158.7" r="1.6" fill="#5c6280"/>
<circle cx="365.3" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="386.2" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="394.8" cy="158.7" r="1.6" fill="#5c6280"/>
<circle cx="401.5" cy="159.6" r="1.6" fill="#5c6280"/>
<circle cx="408.6" cy="158.5" r="1.6" fill="#5c6280"/>
<circle cx="414.3" cy="158.4" r="1.6" fill="#5c6280"/>
<circle cx="422.2" cy="159.5" r="1.6" fill="#5c6280"/>
<circle cx="429.3" cy="158.4" r="1.6" fill="#5c6280"/>
<circle cx="436.5" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="443.5" cy="159.5" r="1.6" fill="#5c6280"/>
<circle cx="449.9" cy="159.8" r="1.6" fill="#5c6280"/>
<circle cx="457.1" cy="159.3" r="1.6" fill="#5c6280"/>
<circle cx="464.2" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="471.3" cy="158.3" r="1.6" fill="#5c6280"/>
<circle cx="477.1" cy="158.9" r="1.6" fill="#5c6280"/>
<circle cx="484.6" cy="158.5" r="1.6" fill="#5c6280"/>
<circle cx="491.1" cy="159.0" r="1.6" fill="#5c6280"/>
<circle cx="498.6" cy="158.9" r="1.6" fill="#5c6280"/>
<circle cx="505.1" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="512.2" cy="159.7" r="1.6" fill="#5c6280"/>
<circle cx="306.7" cy="166.2" r="1.6" fill="#5c6280"/>
<circle cx="313.0" cy="165.9" r="1.6" fill="#5c6280"/>
<circle cx="320.1" cy="166.6" r="1.6" fill="#5c6280"/>
<circle cx="327.8" cy="165.9" r="1.6" fill="#5c6280"/>
<circle cx="334.6" cy="166.3" r="1.6" fill="#5c6280"/>
<circle cx="340.6" cy="166.0" r="1.6" fill="#5c6280"/>
<circle cx="347.3" cy="165.1" r="1.6" fill="#5c6280"/>
<circle cx="354.2" cy="166.8" r="1.6" fill="#5c6280"/>
<circle cx="361.7" cy="166.4" r="1.6" fill="#5c6280"/>
<circle cx="369.0" cy="165.3" r="1.6" fill="#5c6280"/>
<circle cx="396.5" cy="165.9" r="1.6" fill="#5c6280"/>
<circle cx="403.9" cy="166.0" r="1.6" fill="#5c6280"/>
<circle cx="410.3" cy="165.7" r="1.6" fill="#5c6280"/>
<circle cx="417.6" cy="165.8" r="1.6" fill="#5c6280"/>
<circle cx="424.7" cy="166.2" r="1.6" fill="#5c6280"/>
<circle cx="432.6" cy="166.3" r="1.6" fill="#5c6280"/>
<circle cx="438.1" cy="165.2" r="1.6" fill="#5c6280"/>
<circle cx="446.4" cy="165.6" r="1.6" fill="#5c6280"/>
<circle cx="453.4" cy="166.3" r="1.6" fill="#5c6280"/>
<circle cx="460.8" cy="166.7" r="1.6" fill="#5c6280"/>
<circle cx="466.7" cy="166.2" r="1.6" fill="#5c6280"/>
<circle cx="473.3" cy="165.7" r="1.6" fill="#5c6280"/>
<circle cx="481.9" cy="166.4" r="1.6" fill="#5c6280"/>
<circle cx="487.8" cy="166.2" r="1.6" fill="#5c6280"/>
<circle cx="495.9" cy="165.6" r="1.6" fill="#5c6280"/>
<circle cx="501.8" cy="166.6" r="1.6" fill="#5c6280"/>
<circle cx="121.6" cy="173.3" r="1.6" fill="#5c6280"/>
<circle cx="128.7" cy="173.5" r="1.6" fill="#5c6280"/>
<circle cx="135.4" cy="173.1" r="1.6" fill="#5c6280"/>
<circle cx="303.3" cy="172.8" r="1.6" fill="#5c6280"/>
<circle cx="309.7" cy="172.7" r="1.6" fill="#5c6280"/>
<circle cx="316.4" cy="172.4" r="1.6" fill="#5c6280"/>
<circle cx="324.9" cy="173.0" r="1.6" fill="#5c6280"/>
<circle cx="330.5" cy="172.3" r="1.6" fill="#5c6280"/>
<circle cx="337.2" cy="173.7" r="1.6" fill="#5c6280"/>
<circle cx="344.2" cy="173.5" r="1.6" fill="#5c6280"/>
<circle cx="352.7" cy="173.8" r="1.6" fill="#5c6280"/>
<circle cx="358.1" cy="172.7" r="1.6" fill="#5c6280"/>
<circle cx="366.5" cy="172.3" r="1.6" fill="#5c6280"/>
<circle cx="407.8" cy="172.3" r="1.6" fill="#5c6280"/>
<circle cx="415.7" cy="173.5" r="1.6" fill="#5c6280"/>
<circle cx="422.6" cy="172.3" r="1.6" fill="#5c6280"/>
<circle cx="428.9" cy="172.8" r="1.6" fill="#5c6280"/>
<circle cx="436.4" cy="172.5" r="1.6" fill="#5c6280"/>
<circle cx="442.9" cy="172.6" r="1.6" fill="#5c6280"/>
<circle cx="450.5" cy="172.9" r="1.6" fill="#5c6280"/>
<circle cx="457.1" cy="172.6" r="1.6" fill="#5c6280"/>
<circle cx="464.6" cy="172.9" r="1.6" fill="#5c6280"/>
<circle cx="471.7" cy="172.7" r="1.6" fill="#5c6280"/>
<circle cx="478.9" cy="174.0" r="1.6" fill="#5c6280"/>
<circle cx="484.9" cy="172.6" r="1.6" fill="#5c6280"/>
<circle cx="491.8" cy="173.1" r="1.6" fill="#5c6280"/>
<circle cx="499.9" cy="173.6" r="1.6" fill="#5c6280"/>
<circle cx="506.6" cy="172.3" r="1.6" fill="#5c6280"/>
<circle cx="512.5" cy="173.3" r="1.6" fill="#5c6280"/>
<circle cx="117.7" cy="180.1" r="1.6" fill="#5c6280"/>
<circle cx="123.2" cy="180.7" r="1.6" fill="#5c6280"/>
<circle cx="131.7" cy="179.6" r="1.6" fill="#5c6280"/>
<circle cx="138.5" cy="179.5" r="1.6" fill="#5c6280"/>
<circle cx="145.8" cy="179.3" r="1.6" fill="#5c6280"/>
<circle cx="298.9" cy="180.9" r="1.6" fill="#5c6280"/>
<circle cx="305.4" cy="179.9" r="1.6" fill="#5c6280"/>
<circle cx="312.7" cy="179.1" r="1.6" fill="#5c6280"/>
<circle cx="319.1" cy="180.0" r="1.6" fill="#5c6280"/>
<circle cx="326.5" cy="181.0" r="1.6" fill="#5c6280"/>
<circle cx="333.7" cy="179.1" r="1.6" fill="#5c6280"/>
<circle cx="341.9" cy="180.7" r="1.6" fill="#5c6280"/>
<circle cx="348.3" cy="180.6" r="1.6" fill="#5c6280"/>
<circle cx="354.3" cy="179.6" r="1.6" fill="#5c6280"/>
<circle cx="362.7" cy="180.4" r="1.6" fill="#5c6280"/>
<circle cx="368.3" cy="180.4" r="1.6" fill="#5c6280"/>
<circle cx="473.9" cy="179.0" r="1.6" fill="#5c6280"/>
<circle cx="480.2" cy="179.5" r="1.6" fill="#5c6280"/>
<circle cx="488.7" cy="180.1" r="1.6" fill="#5c6280"/>
<circle cx="495.5" cy="180.1" r="1.6" fill="#5c6280"/>
<circle cx="501.2" cy="179.6" r="1.6" fill="#5c6280"/>
<circle cx="508.6" cy="179.1" r="1.6" fill="#5c6280"/>
<circle cx="515.8" cy="180.6" r="1.6" fill="#5c6280"/>
<circle cx="522.9" cy="179.2" r="1.6" fill="#5c6280"/>
<circle cx="107.8" cy="187.2" r="1.6" fill="#5c6280"/>
<circle cx="113.0" cy="187.0" r="1.6" fill="#5c6280"/>
<circle cx="120.5" cy="186.3" r="1.6" fill="#5c6280"/>
<circle cx="127.9" cy="187.2" r="1.6" fill="#5c6280"/>
<circle cx="134.5" cy="186.8" r="1.6" fill="#5c6280"/>
<circle cx="142.3" cy="186.2" r="1.6" fill="#5c6280"/>
<circle cx="149.9" cy="186.1" r="1.6" fill="#5c6280"/>
<circle cx="296.1" cy="187.0" r="1.6" fill="#5c6280"/>
<circle cx="304.0" cy="187.1" r="1.6" fill="#5c6280"/>
<circle cx="309.8" cy="186.9" r="1.6" fill="#5c6280"/>
<circle cx="317.3" cy="188.0" r="1.6" fill="#5c6280"/>
<circle cx="323.5" cy="186.0" r="1.6" fill="#5c6280"/>
<circle cx="331.6" cy="186.7" r="1.6" fill="#5c6280"/>
<circle cx="338.5" cy="187.3" r="1.6" fill="#5c6280"/>
<circle cx="345.5" cy="187.5" r="1.6" fill="#5c6280"/>
<circle cx="351.7" cy="186.1" r="1.6" fill="#5c6280"/>
<circle cx="359.1" cy="187.6" r="1.6" fill="#5c6280"/>
<circle cx="365.4" cy="187.6" r="1.6" fill="#5c6280"/>
<circle cx="372.9" cy="187.4" r="1.6" fill="#5c6280"/>
<circle cx="471.3" cy="187.6" r="1.6" fill="#5c6280"/>
<circle cx="477.1" cy="187.6" r="1.6" fill="#5c6280"/>
<circle cx="484.9" cy="186.6" r="1.6" fill="#5c6280"/>
<circle cx="491.1" cy="186.4" r="1.6" fill="#5c6280"/>
<circle cx="498.1" cy="187.9" r="1.6" fill="#5c6280"/>
<circle cx="506.0" cy="188.0" r="1.6" fill="#5c6280"/>
<circle cx="513.1" cy="186.5" r="1.6" fill="#5c6280"/>
<circle cx="520.5" cy="186.4" r="1.6" fill="#5c6280"/>
<circle cx="526.7" cy="187.6" r="1.6" fill="#5c6280"/>
<circle cx="103.7" cy="193.7" r="1.6" fill="#5c6280"/>
<circle cx="109.2" cy="193.7" r="1.6" fill="#5c6280"/>
<circle cx="117.8" cy="194.5" r="1.6" fill="#5c6280"/>
<circle cx="124.8" cy="193.8" r="1.6" fill="#5c6280"/>
<circle cx="131.9" cy="194.0" r="1.6" fill="#5c6280"/>
<circle cx="138.0" cy="194.8" r="1.6" fill="#5c6280"/>
<circle cx="145.0" cy="194.6" r="1.6" fill="#5c6280"/>
<circle cx="152.5" cy="193.2" r="1.6" fill="#5c6280"/>
<circle cx="299.2" cy="194.6" r="1.6" fill="#5c6280"/>
<circle cx="306.1" cy="193.6" r="1.6" fill="#5c6280"/>
<circle cx="312.2" cy="194.3" r="1.6" fill="#5c6280"/>
<circle cx="319.6" cy="194.2" r="1.6" fill="#5c6280"/>
<circle cx="326.9" cy="194.4" r="1.6" fill="#5c6280"/>
<circle cx="333.7" cy="193.1" r="1.6" fill="#5c6280"/>
<circle cx="341.7" cy="193.7" r="1.6" fill="#5c6280"/>
<circle cx="349.0" cy="193.5" r="1.6" fill="#5c6280"/>
<circle cx="356.0" cy="194.9" r="1.6" fill="#5c6280"/>
<circle cx="361.2" cy="194.3" r="1.6" fill="#5c6280"/>
<circle cx="368.7" cy="194.6" r="1.6" fill="#5c6280"/>
<circle cx="467.4" cy="194.9" r="1.6" fill="#5c6280"/>
<circle cx="473.3" cy="194.2" r="1.6" fill="#5c6280"/>
<circle cx="481.6" cy="193.1" r="1.6" fill="#5c6280"/>
<circle cx="487.1" cy="194.6" r="1.6" fill="#5c6280"/>
<circle cx="494.7" cy="193.8" r="1.6" fill="#5c6280"/>
<circle cx="502.1" cy="194.2" r="1.6" fill="#5c6280"/>
<circle cx="509.4" cy="194.9" r="1.6" fill="#5c6280"/>
<circle cx="515.7" cy="194.5" r="1.6" fill="#5c6280"/>
<circle cx="523.1" cy="194.1" r="1.6" fill="#5c6280"/>
<circle cx="529.8" cy="194.3" r="1.6" fill="#5c6280"/>
<circle cx="99.5" cy="200.2" r="1.6" fill="#5c6280"/>
<circle cx="107.5" cy="201.0" r="1.6" fill="#5c6280"/>
<circle cx="113.8" cy="201.1" r="1.6" fill="#5c6280"/>
<circle cx="120.5" cy="200.5" r="1.6" fill="#5c6280"/>
<circle cx="127.9" cy="202.0" r="1.6" fill="#5c6280"/>
<circle cx="134.6" cy="201.8" r="1.6" fill="#5c6280"/>
<circle cx="142.0" cy="200.2" r="1.6" fill="#5c6280"/>
<circle cx="149.7" cy="200.9" r="1.6" fill="#5c6280"/>
<circle cx="156.8" cy="200.9" r="1.6" fill="#5c6280"/>
<circle cx="295.2" cy="201.4" r="1.6" fill="#5c6280"/>
<circle cx="303.7" cy="200.6" r="1.6" fill="#5c6280"/>
<circle cx="309.7" cy="200.1" r="1.6" fill="#5c6280"/>
<circle cx="317.1" cy="201.8" r="1.6" fill="#5c6280"/>
<circle cx="324.7" cy="201.4" r="1.6" fill="#5c6280"/>
<circle cx="331.9" cy="201.3" r="1.6" fill="#5c6280"/>
<circle cx="338.6" cy="201.0" r="1.6" fill="#5c6280"/>
<circle cx="344.2" cy="200.4" r="1.6" fill="#5c6280"/>
<circle cx="351.3" cy="201.6" r="1.6" fill="#5c6280"/>
<circle cx="358.1" cy="201.1" r="1.6" fill="#5c6280"/>
<circle cx="365.7" cy="201.6" r="1.6" fill="#5c6280"/>
<circle cx="373.1" cy="201.2" r="1.6" fill="#5c6280"/>
<circle cx="470.2" cy="200.6" r="1.6" fill="#5c6280"/>
<circle cx="479.0" cy="201.4" r="1.6" fill="#5c6280"/>
<circle cx="485.1" cy="201.5" r="1.6" fill="#5c6280"/>
<circle cx="492.6" cy="200.1" r="1.6" fill="#5c6280"/>
<circle cx="499.9" cy="201.3" r="1.6" fill="#5c6280"/>
<circle cx="505.9" cy="201.4" r="1.6" fill="#5c6280"/>
<circle cx="512.7" cy="201.8" r="1.6" fill="#5c6280"/>
<circle cx="520.6" cy="201.3" r="1.6" fill="#5c6280"/>
<circle cx="526.4" cy="201.9" r="1.6" fill="#5c6280"/>
<circle cx="102.9" cy="208.8" r="1.6" fill="#5c6280"/>
<circle cx="109.1" cy="207.2" r="1.6" fill="#5c6280"/>
<circle cx="116.3" cy="207.3" r="1.6" fill="#5c6280"/>
<circle cx="123.6" cy="208.4" r="1.6" fill="#5c6280"/>
<circle cx="130.7" cy="208.9" r="1.6" fill="#5c6280"/>
<circle cx="138.8" cy="208.7" r="1.6" fill="#5c6280"/>
<circle cx="144.5" cy="208.3" r="1.6" fill="#5c6280"/>
<circle cx="152.1" cy="207.3" r="1.6" fill="#5c6280"/>
<circle cx="158.6" cy="208.1" r="1.6" fill="#5c6280"/>
<circle cx="299.0" cy="207.3" r="1.6" fill="#5c6280"/>
<circle cx="306.9" cy="207.3" r="1.6" fill="#5c6280"/>
<circle cx="313.3" cy="208.4" r="1.6" fill="#5c6280"/>
<circle cx="320.2" cy="208.7" r="1.6" fill="#5c6280"/>
<circle cx="327.1" cy="208.7" r="1.6" fill="#5c6280"/>
<circle cx="333.1" cy="207.1" r="1.6" fill="#5c6280"/>
<circle cx="341.3" cy="208.2" r="1.6" fill="#5c6280"/>
<circle cx="348.3" cy="208.5" r="1.6" fill="#5c6280"/>
<circle cx="354.8" cy="208.3" r="1.6" fill="#5c6280"/>
<circle cx="362.0" cy="208.3" r="1.6" fill="#5c6280"/>
<circle cx="368.6" cy="208.9" r="1.6" fill="#5c6280"/>
<circle cx="474.0" cy="208.6" r="1.6" fill="#5c6280"/>
<circle cx="481.4" cy="207.6" r="1.6" fill="#5c6280"/>
<circle cx="487.1" cy="207.1" r="1.6" fill="#5c6280"/>
<circle cx="494.9" cy="208.0" r="1.6" fill="#5c6280"/>
<circle cx="501.4" cy="208.2" r="1.6" fill="#5c6280"/>
<circle cx="508.6" cy="208.4" r="1.6" fill="#5c6280"/>
<circle cx="516.5" cy="208.7" r="1.6" fill="#5c6280"/>
<circle cx="524.0" cy="207.3" r="1.6" fill="#5c6280"/>
<circle cx="99.7" cy="215.1" r="1.6" fill="#5c6280"/>
<circle cx="106.6" cy="214.9" r="1.6" fill="#5c6280"/>
<circle cx="113.5" cy="214.5" r="1.6" fill="#5c6280"/>
<circle cx="120.2" cy="214.6" r="1.6" fill="#5c6280"/>
<circle cx="127.8" cy="215.2" r="1.6" fill="#5c6280"/>
<circle cx="134.5" cy="215.7" r="1.6" fill="#5c6280"/>
<circle cx="141.3" cy="214.7" r="1.6" fill="#5c6280"/>
<circle cx="149.2" cy="214.6" r="1.6" fill="#5c6280"/>
<circle cx="156.5" cy="215.0" r="1.6" fill="#5c6280"/>
<circle cx="163.0" cy="215.0" r="1.6" fill="#5c6280"/>
<circle cx="302.6" cy="214.0" r="1.6" fill="#5c6280"/>
<circle cx="310.9" cy="215.0" r="1.6" fill="#5c6280"/>
<circle cx="317.9" cy="214.4" r="1.6" fill="#5c6280"/>
<circle cx="323.7" cy="214.1" r="1.6" fill="#5c6280"/>
<circle cx="331.0" cy="215.8" r="1.6" fill="#5c6280"/>
<circle cx="338.3" cy="214.9" r="1.6" fill="#5c6280"/>
<circle cx="345.1" cy="215.7" r="1.6" fill="#5c6280"/>
<circle cx="351.9" cy="215.8" r="1.6" fill="#5c6280"/>
<circle cx="359.5" cy="215.5" r="1.6" fill="#5c6280"/>
<circle cx="365.7" cy="214.8" r="1.6" fill="#5c6280"/>
<circle cx="373.1" cy="214.4" r="1.6" fill="#5c6280"/>
<circle cx="485.1" cy="214.1" r="1.6" fill="#5c6280"/>
<circle cx="492.0" cy="215.5" r="1.6" fill="#5c6280"/>
<circle cx="498.6" cy="216.0" r="1.6" fill="#5c6280"/>
<circle cx="506.4" cy="214.2" r="1.6" fill="#5c6280"/>
<circle cx="514.0" cy="214.8" r="1.6" fill="#5c6280"/>
<circle cx="520.6" cy="214.7" r="1.6" fill="#5c6280"/>
<circle cx="96.9" cy="222.5" r="1.6" fill="#5c6280"/>
<circle cx="102.4" cy="222.0" r="1.6" fill="#5c6280"/>
<circle cx="110.0" cy="221.1" r="1.6" fill="#5c6280"/>
<circle cx="116.3" cy="221.7" r="1.6" fill="#5c6280"/>
<circle cx="123.9" cy="221.9" r="1.6" fill="#5c6280"/>
<circle cx="131.2" cy="222.0" r="1.6" fill="#5c6280"/>
<circle cx="137.7" cy="222.2" r="1.6" fill="#5c6280"/>
<circle cx="144.3" cy="223.0" r="1.6" fill="#5c6280"/>
<circle cx="152.5" cy="221.6" r="1.6" fill="#5c6280"/>
<circle cx="158.7" cy="222.7" r="1.6" fill="#5c6280"/>
<circle cx="299.1" cy="222.4" r="1.6" fill="#5c6280"/>
<circle cx="305.6" cy="222.6" r="1.6" fill="#5c6280"/>
<circle cx="312.7" cy="222.3" r="1.6" fill="#5c6280"/>
<circle cx="321.0" cy="222.2" r="1.6" fill="#5c6280"/>
<circle cx="327.6" cy="222.5" r="1.6" fill="#5c6280"/>
<circle cx="334.4" cy="221.1" r="1.6" fill="#5c6280"/>
<circle cx="340.9" cy="222.9" r="1.6" fill="#5c6280"/>
<circle cx="348.6" cy="222.6" r="1.6" fill="#5c6280"/>
<circle cx="355.2" cy="222.4" r="1.6" fill="#5c6280"/>
<circle cx="362.2" cy="221.3" r="1.6" fill="#5c6280"/>
<circle cx="369.3" cy="222.2" r="1.6" fill="#5c6280"/>
<circle cx="100.7" cy="228.3" r="1.6" fill="#5c6280"/>
<circle cx="107.4" cy="228.1" r="1.6" fill="#5c6280"/>
<circle cx="114.9" cy="228.2" r="1.6" fill="#5c6280"/>
<circle cx="120.0" cy="228.6" r="1.6" fill="#5c6280"/>
<circle cx="127.3" cy="229.4" r="1.6" fill="#5c6280"/>
<circle cx="134.8" cy="229.5" r="1.6" fill="#5c6280"/>
<circle cx="142.8" cy="229.7" r="1.6" fill="#5c6280"/>
<circle cx="149.5" cy="228.1" r="1.6" fill="#5c6280"/>
<circle cx="155.3" cy="228.4" r="1.6" fill="#5c6280"/>
<circle cx="162.7" cy="229.3" r="1.6" fill="#5c6280"/>
<circle cx="303.1" cy="228.6" r="1.6" fill="#5c6280"/>
<circle cx="309.3" cy="229.8" r="1.6" fill="#5c6280"/>
<circle cx="316.7" cy="228.7" r="1.6" fill="#5c6280"/>
<circle cx="324.5" cy="229.4" r="1.6" fill="#5c6280"/>
<circle cx="331.3" cy="229.4" r="1.6" fill="#5c6280"/>
<circle cx="338.2" cy="228.4" r="1.6" fill="#5c6280"/>
<circle cx="344.5" cy="229.1" r="1.6" fill="#5c6280"/>
<circle cx="351.4" cy="229.9" r="1.6" fill="#5c6280"/>
<circle cx="358.6" cy="228.6" r="1.6" fill="#5c6280"/>
<circle cx="365.4" cy="229.4" r="1.6" fill="#5c6280"/>
<circle cx="95.6" cy="235.7" r="1.6" fill="#5c6280"/>
<circle cx="103.9" cy="236.6" r="1.6" fill="#5c6280"/>
<circle cx="109.5" cy="235.2" r="1.6" fill="#5c6280"/>
<circle cx="117.4" cy="235.8" r="1.6" fill="#5c6280"/>
<circle cx="125.0" cy="236.6" r="1.6" fill="#5c6280"/>
<circle cx="131.9" cy="236.6" r="1.6" fill="#5c6280"/>
<circle cx="137.6" cy="235.6" r="1.6" fill="#5c6280"/>
<circle cx="145.4" cy="235.7" r="1.6" fill="#5c6280"/>
<circle cx="151.9" cy="235.5" r="1.6" fill="#5c6280"/>
<circle cx="159.0" cy="235.4" r="1.6" fill="#5c6280"/>
<circle cx="306.1" cy="236.9" r="1.6" fill="#5c6280"/>
<circle cx="313.4" cy="235.3" r="1.6" fill="#5c6280"/>
<circle cx="320.2" cy="236.2" r="1.6" fill="#5c6280"/>
<circle cx="326.5" cy="236.3" r="1.6" fill="#5c6280"/>
<circle cx="334.1" cy="236.3" r="1.6" fill="#5c6280"/>
<circle cx="340.1" cy="235.8" r="1.6" fill="#5c6280"/>
<circle cx="348.4" cy="235.2" r="1.6" fill="#5c6280"/>
<circle cx="355.5" cy="235.0" r="1.6" fill="#5c6280"/>
<circle cx="362.1" cy="236.9" r="1.6" fill="#5c6280"/>
<circle cx="99.8" cy="243.9" r="1.6" fill="#5c6280"/>
<circle cx="107.8" cy="243.0" r="1.6" fill="#5c6280"/>
<circle cx="113.4" cy="243.9" r="1.6" fill="#5c6280"/>
<circle cx="120.6" cy="243.3" r="1.6" fill="#5c6280"/>
<circle cx="128.8" cy="242.2" r="1.6" fill="#5c6280"/>
<circle cx="135.1" cy="243.1" r="1.6" fill="#5c6280"/>
<circle cx="142.4" cy="243.9" r="1.6" fill="#5c6280"/>
<circle cx="149.8" cy="242.4" r="1.6" fill="#5c6280"/>
<circle cx="156.8" cy="242.4" r="1.6" fill="#5c6280"/>
<circle cx="163.8" cy="244.0" r="1.6" fill="#5c6280"/>
<circle cx="309.8" cy="243.0" r="1.6" fill="#5c6280"/>
<circle cx="317.9" cy="243.9" r="1.6" fill="#5c6280"/>
<circle cx="324.9" cy="243.8" r="1.6" fill="#5c6280"/>
<circle cx="330.0" cy="243.1" r="1.6" fill="#5c6280"/>
<circle cx="337.2" cy="244.0" r="1.6" fill="#5c6280"/>
<circle cx="344.6" cy="244.0" r="1.6" fill="#5c6280"/>
<circle cx="352.1" cy="243.0" r="1.6" fill="#5c6280"/>
<circle cx="359.9" cy="243.7" r="1.6" fill="#5c6280"/>
<circle cx="526.9" cy="242.4" r="1.6" fill="#5c6280"/>
<circle cx="533.2" cy="242.3" r="1.6" fill="#5c6280"/>
<circle cx="540.9" cy="242.5" r="1.6" fill="#5c6280"/>
<circle cx="547.4" cy="243.5" r="1.6" fill="#5c6280"/>
<circle cx="555.6" cy="243.1" r="1.6" fill="#5c6280"/>
<circle cx="562.5" cy="242.4" r="1.6" fill="#5c6280"/>
<circle cx="569.7" cy="243.8" r="1.6" fill="#5c6280"/>
<circle cx="103.7" cy="250.0" r="1.6" fill="#5c6280"/>
<circle cx="109.2" cy="250.3" r="1.6" fill="#5c6280"/>
<circle cx="116.4" cy="249.3" r="1.6" fill="#5c6280"/>
<circle cx="123.6" cy="249.5" r="1.6" fill="#5c6280"/>
<circle cx="130.5" cy="249.5" r="1.6" fill="#5c6280"/>
<circle cx="138.5" cy="250.9" r="1.6" fill="#5c6280"/>
<circle cx="144.6" cy="250.4" r="1.6" fill="#5c6280"/>
<circle cx="151.0" cy="250.3" r="1.6" fill="#5c6280"/>
<circle cx="159.4" cy="249.1" r="1.6" fill="#5c6280"/>
<circle cx="312.2" cy="249.6" r="1.6" fill="#5c6280"/>
<circle cx="319.8" cy="250.0" r="1.6" fill="#5c6280"/>
<circle cx="327.8" cy="250.4" r="1.6" fill="#5c6280"/>
<circle cx="333.6" cy="249.2" r="1.6" fill="#5c6280"/>
<circle cx="341.8" cy="249.6" r="1.6" fill="#5c6280"/>
<circle cx="348.2" cy="249.4" r="1.6" fill="#5c6280"/>
<circle cx="354.3" cy="249.3" r="1.6" fill="#5c6280"/>
<circle cx="516.5" cy="250.2" r="1.6" fill="#5c6280"/>
<circle cx="522.8" cy="250.1" r="1.6" fill="#5c6280"/>
<circle cx="529.9" cy="250.1" r="1.6" fill="#5c6280"/>
<circle cx="537.3" cy="249.4" r="1.6" fill="#5c6280"/>
<circle cx="543.5" cy="250.5" r="1.6" fill="#5c6280"/>
<circle cx="551.7" cy="249.2" r="1.6" fill="#5c6280"/>
<circle cx="557.9" cy="250.4" r="1.6" fill="#5c6280"/>
<circle cx="564.2" cy="250.1" r="1.6" fill="#5c6280"/>
<circle cx="571.1" cy="250.1" r="1.6" fill="#5c6280"/>
<circle cx="100.0" cy="257.1" r="1.6" fill="#5c6280"/>
<circle cx="106.3" cy="256.7" r="1.6" fill="#5c6280"/>
<circle cx="114.0" cy="256.2" r="1.6" fill="#5c6280"/>
<circle cx="120.4" cy="257.2" r="1.6" fill="#5c6280"/>
<circle cx="127.2" cy="257.0" r="1.6" fill="#5c6280"/>
<circle cx="135.6" cy="256.9" r="1.6" fill="#5c6280"/>
<circle cx="142.0" cy="256.9" r="1.6" fill="#5c6280"/>
<circle cx="148.1" cy="256.9" r="1.6" fill="#5c6280"/>
<circle cx="156.6" cy="257.4" r="1.6" fill="#5c6280"/>
<circle cx="316.8" cy="257.6" r="1.6" fill="#5c6280"/>
<circle cx="324.5" cy="257.2" r="1.6" fill="#5c6280"/>
<circle cx="330.1" cy="256.7" r="1.6" fill="#5c6280"/>
<circle cx="337.1" cy="258.0" r="1.6" fill="#5c6280"/>
<circle cx="345.9" cy="256.1" r="1.6" fill="#5c6280"/>
<circle cx="352.9" cy="256.1" r="1.6" fill="#5c6280"/>
<circle cx="512.8" cy="257.5" r="1.6" fill="#5c6280"/>
<circle cx="520.5" cy="258.0" r="1.6" fill="#5c6280"/>
<circle cx="527.3" cy="256.8" r="1.6" fill="#5c6280"/>
<circle cx="535.0" cy="256.8" r="1.6" fill="#5c6280"/>
<circle cx="541.7" cy="257.8" r="1.6" fill="#5c6280"/>
<circle cx="547.8" cy="257.4" r="1.6" fill="#5c6280"/>
<circle cx="555.3" cy="257.1" r="1.6" fill="#5c6280"/>
<circle cx="562.3" cy="256.7" r="1.6" fill="#5c6280"/>
<circle cx="568.4" cy="257.1" r="1.6" fill="#5c6280"/>
<circle cx="576.1" cy="257.5" r="1.6" fill="#5c6280"/>
<circle cx="582.4" cy="256.0" r="1.6" fill="#5c6280"/>
<circle cx="102.0" cy="263.6" r="1.6" fill="#5c6280"/>
<circle cx="110.3" cy="264.1" r="1.6" fill="#5c6280"/>
<circle cx="117.1" cy="264.6" r="1.6" fill="#5c6280"/>
<circle cx="123.5" cy="263.7" r="1.6" fill="#5c6280"/>
<circle cx="130.6" cy="264.9" r="1.6" fill="#5c6280"/>
<circle cx="138.5" cy="263.2" r="1.6" fill="#5c6280"/>
<circle cx="145.6" cy="263.8" r="1.6" fill="#5c6280"/>
<circle cx="152.5" cy="264.8" r="1.6" fill="#5c6280"/>
<circle cx="333.0" cy="263.4" r="1.6" fill="#5c6280"/>
<circle cx="340.2" cy="263.1" r="1.6" fill="#5c6280"/>
<circle cx="509.2" cy="264.4" r="1.6" fill="#5c6280"/>
<circle cx="515.1" cy="264.5" r="1.6" fill="#5c6280"/>
<circle cx="522.8" cy="263.5" r="1.6" fill="#5c6280"/>
<circle cx="529.4" cy="264.7" r="1.6" fill="#5c6280"/>
<circle cx="536.1" cy="264.0" r="1.6" fill="#5c6280"/>
<circle cx="543.6" cy="264.6" r="1.6" fill="#5c6280"/>
<circle cx="551.5" cy="263.6" r="1.6" fill="#5c6280"/>
<circle cx="558.2" cy="264.3" r="1.6" fill="#5c6280"/>
<circle cx="564.6" cy="263.6" r="1.6" fill="#5c6280"/>
<circle cx="571.3" cy="264.3" r="1.6" fill="#5c6280"/>
<circle cx="578.4" cy="263.6" r="1.6" fill="#5c6280"/>
<circle cx="106.1" cy="271.9" r="1.6" fill="#5c6280"/>
<circle cx="114.8" cy="271.8" r="1.6" fill="#5c6280"/>
<circle cx="121.3" cy="270.9" r="1.6" fill="#5c6280"/>
<circle cx="128.0" cy="271.9" r="1.6" fill="#5c6280"/>
<circle cx="135.9" cy="271.3" r="1.6" fill="#5c6280"/>
<circle cx="142.6" cy="270.6" r="1.6" fill="#5c6280"/>
<circle cx="148.8" cy="270.3" r="1.6" fill="#5c6280"/>
<circle cx="505.8" cy="271.5" r="1.6" fill="#5c6280"/>
<circle cx="512.9" cy="271.7" r="1.6" fill="#5c6280"/>
<circle cx="519.6" cy="271.4" r="1.6" fill="#5c6280"/>
<circle cx="527.6" cy="271.8" r="1.6" fill="#5c6280"/>
<circle cx="534.1" cy="271.9" r="1.6" fill="#5c6280"/>
<circle cx="541.1" cy="270.3" r="1.6" fill="#5c6280"/>
<circle cx="547.5" cy="270.4" r="1.6" fill="#5c6280"/>
<circle cx="555.3" cy="271.8" r="1.6" fill="#5c6280"/>
<circle cx="562.7" cy="270.2" r="1.6" fill="#5c6280"/>
<circle cx="569.4" cy="270.4" r="1.6" fill="#5c6280"/>
<circle cx="575.5" cy="271.3" r="1.6" fill="#5c6280"/>
<circle cx="583.2" cy="271.7" r="1.6" fill="#5c6280"/>
<circle cx="109.4" cy="278.5" r="1.6" fill="#5c6280"/>
<circle cx="117.4" cy="278.1" r="1.6" fill="#5c6280"/>
<circle cx="124.0" cy="278.7" r="1.6" fill="#5c6280"/>
<circle cx="130.7" cy="278.9" r="1.6" fill="#5c6280"/>
<circle cx="137.0" cy="278.9" r="1.6" fill="#5c6280"/>
<circle cx="145.9" cy="277.2" r="1.6" fill="#5c6280"/>
<circle cx="517.0" cy="278.0" r="1.6" fill="#5c6280"/>
<circle cx="522.5" cy="278.2" r="1.6" fill="#5c6280"/>
<circle cx="529.4" cy="278.8" r="1.6" fill="#5c6280"/>
<circle cx="537.1" cy="278.6" r="1.6" fill="#5c6280"/>
<circle cx="543.8" cy="278.1" r="1.6" fill="#5c6280"/>
<circle cx="550.7" cy="277.5" r="1.6" fill="#5c6280"/>
<circle cx="558.0" cy="278.0" r="1.6" fill="#5c6280"/>
<circle cx="564.2" cy="279.0" r="1.6" fill="#5c6280"/>
<circle cx="571.9" cy="278.7" r="1.6" fill="#5c6280"/>
<circle cx="579.8" cy="277.7" r="1.6" fill="#5c6280"/>
<circle cx="519.8" cy="285.1" r="1.6" fill="#5c6280"/>
<circle cx="526.4" cy="284.3" r="1.6" fill="#5c6280"/>
<circle cx="533.5" cy="285.9" r="1.6" fill="#5c6280"/>
<circle cx="541.2" cy="284.8" r="1.6" fill="#5c6280"/>
<circle cx="547.3" cy="284.7" r="1.6" fill="#5c6280"/>
<circle cx="554.8" cy="285.7" r="1.6" fill="#5c6280"/>
<circle cx="562.0" cy="285.3" r="1.6" fill="#5c6280"/>
<circle cx="569.4" cy="284.5" r="1.6" fill="#5c6280"/>
<circle cx="537.6" cy="292.9" r="1.6" fill="#5c6280"/>
<circle cx="544.3" cy="292.0" r="1.6" fill="#5c6280"/>
<circle cx="550.3" cy="292.6" r="1.6" fill="#5c6280"/>
                {{-- connection arcs --}}
                <path d="M120 130 C220 60, 340 50, 500 110" stroke="#7c6cf8" stroke-width="1.8" fill="none" opacity="0.75"/>
                <path d="M120 130 C250 180, 380 200, 500 180" stroke="#7c6cf8" stroke-width="1.6" fill="none" opacity="0.55"/>
                {{-- pins --}}
                <circle cx="120" cy="130" r="6" fill="#8b7cf8"/>
                <circle cx="120" cy="130" r="12" stroke="#8b7cf8" stroke-width="1.5" fill="none" opacity="0.45"/>
                <circle cx="320" cy="95" r="5" fill="#8b7cf8"/>
                <circle cx="320" cy="95" r="10" stroke="#8b7cf8" stroke-width="1.5" fill="none" opacity="0.4"/>
                <circle cx="500" cy="185" r="7" fill="#22c55e"/>
                <circle cx="500" cy="185" r="14" stroke="#22c55e" stroke-width="1.8" fill="none" opacity="0.5"/>
                <circle cx="530" cy="120" r="4.5" fill="#8b7cf8" opacity="0.85"/>
            </svg>

            {{-- Singapore label --}}
            <div style="position:absolute;left:58%;top:48%;transform:translate(-20%,-10%)">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#22c55e;box-shadow:0 0 8px #22c55e;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">Singapore</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 32ms</div>
                    </div>
                </div>
            </div>

            {{-- US label --}}
            <div style="position:absolute;left:8%;top:22%">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#a78bfa;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">United States</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 28ms</div>
                    </div>
                </div>
            </div>

            {{-- Europe label --}}
            <div style="position:absolute;left:38%;top:8%">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                    <span style="width:8px;height:8px;border-radius:99px;background:#a78bfa;flex-shrink:0"></span>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#fff;white-space:nowrap">Europe</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);white-space:nowrap">Latency 24ms</div>
                    </div>
                </div>
            </div>

            {{-- status bar --}}
            <div style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);width:min(100%,18rem);display:flex;align-items:center;justify-content:space-between;gap:12px;background:rgba(18,20,31,0.95);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:10px 14px;box-shadow:0 8px 24px rgba(0,0,0,0.4)">
                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                    <span style="width:9px;height:9px;border-radius:99px;background:#22c55e;box-shadow:0 0 10px #22c55e;flex-shrink:0"></span>
                    <div style="min-width:0">
                        <div style="font-size:12px;font-weight:600;color:#fff">Infrastructure status</div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55)">All systems operational</div>
                    </div>
                </div>
                <a href="/docs" style="font-size:11px;font-weight:600;color:#c4b5fd;text-decoration:none;flex-shrink:0">View status →</a>
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
