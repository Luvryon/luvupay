@extends('layouts.site')
@section('title', 'Marketplace · LuvuPay Resale')
@section('meta_description', 'Buy and sell unused subscription time. AI-priced listings. Instant transfer via LuvuPay.')

@push('head')
    <script src="/js/marketplace.js?v={{ @filemtime(public_path('js/marketplace.js')) }}"></script>
@endpush

@section('content')
<div x-data="marketplaceApp()" x-init="init()" class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">

    <section class="mb-12 flex flex-wrap items-end justify-between gap-6">
        <div class="max-w-2xl">
            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-600">B2C resale · powered by Nebius AI</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight text-ink sm:text-5xl">Unused days shouldn’t die.</h1>
            <p class="mt-4 text-base leading-relaxed text-muted">
                Buy leftover subscription time at a fair price — or list yours and let AI write the listing in seconds.
                Transfers settle through LuvuPay · PayPal Sandbox.
            </p>
            <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full border border-line bg-wash px-3 py-1 text-muted">Instant entitlement transfer</span>
                <span class="rounded-full border border-line bg-wash px-3 py-1 text-muted">AI-optimized pricing</span>
                <span class="rounded-full border border-line bg-wash px-3 py-1 text-muted">Chargeback-safe settlement</span>
            </div>
        </div>
        <button type="button" class="lp-btn lp-btn-dark lp-btn-lg" x-on:click="openSell()">Sell your subscription</button>
    </section>

    <div class="mb-5 flex items-end justify-between gap-3">
        <h2 class="text-lg font-semibold text-ink">
            Live listings
            <span class="text-sm font-medium text-muted" x-text="'(' + listings.length + ')'"></span>
        </h2>
        <p class="text-xs text-muted">Sorted by newest</p>
    </div>

    <div x-show="error" x-cloak class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" x-show="!loading || listings.length" x-cloak>
        <template x-for="item in listings" x-bind:key="item.id">
            <article class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md">
                <div class="flex items-start gap-3 p-4 pb-2">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl text-xl" x-bind:class="tone(item.app)" x-text="item.emoji"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink" x-text="item.app"></p>
                        <p class="mt-0.5 text-[11px] font-semibold text-amber-600" x-text="item.daysLeft + ' days left'"></p>
                    </div>
                </div>
                <p class="line-clamp-2 flex-1 px-4 pb-3 text-xs leading-relaxed text-muted" x-text="item.description"></p>
                <div class="flex items-end justify-between gap-2 border-t border-line px-4 py-3">
                    <div>
                        <p class="text-[11px] text-muted line-through" x-text="'$' + Number(item.original).toFixed(2)"></p>
                        <p class="text-lg font-bold text-emerald-600" x-text="'$' + Number(item.price).toFixed(2)"></p>
                    </div>
                    <button type="button" class="lp-btn lp-btn-outline text-sm" x-on:click="openBuy(item)">Buy now</button>
                </div>
            </article>
        </template>
    </div>

    <div x-show="!loading && !listings.length && !error" x-cloak class="rounded-2xl border border-dashed border-line bg-wash px-6 py-16 text-center">
        <p class="font-semibold text-ink">No active listings yet</p>
        <p class="mt-1 text-sm text-muted">Be the first — connect an app and list your remaining days.</p>
    </div>

    <p class="mt-10 text-center text-xs text-muted">
        Sandbox marketplace · LuvuPay never asks for your app password · payments run on the PayPal Sandbox
    </p>

    {{-- Toast --}}
    <div class="pointer-events-none fixed bottom-4 right-4 z-[60] w-[min(100%-2rem,340px)]" aria-live="polite">
        <div x-show="toast" x-cloak style="display:none" x-transition
             class="pointer-events-auto rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-ink shadow-lg"
             x-text="toast"></div>
    </div>

    {{-- Sell modal --}}
    <div x-show="sellOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink/40" x-on:click="!sellBusy && (sellOpen = false)"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-line bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="font-semibold text-ink">List unused subscription time</h3>
                <button type="button" class="text-muted hover:text-ink" x-on:click="sellOpen = false" x-bind:disabled="sellBusy">✕</button>
            </div>
            <div class="p-5">
                <div class="mb-1 flex items-center gap-2">
                    @foreach([1, 2, 3] as $s)
                        <span class="size-2.5 rounded-full transition" x-bind:class="step >= {{ $s }} ? 'bg-brand-600' : 'bg-line'"></span>
                        @if($s < 3)<span class="h-px flex-1 bg-line"></span>@endif
                    @endforeach
                </div>
                <p class="mb-4 text-[11px] text-muted" x-text="stepLabel"></p>

                <div x-show="step === 1" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">App</label>
                        <select class="w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm" x-model="sell.app">
                            <template x-for="n in appNames" x-bind:key="n"><option x-bind:value="n" x-text="n"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted">Your email</label>
                        <input type="email" maxlength="190" autocomplete="email" placeholder="you@email.com"
                               class="w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm"
                               x-model="sell.email" />
                        <p class="mt-1 text-[11px] text-muted">We email a confirmation link here. LuvuPay never asks for your app password.</p>
                    </div>
                    <p class="text-xs text-red-600" x-show="formError" x-text="formError" role="alert"></p>
                    <button type="button" class="lp-btn lp-btn-primary w-full" x-on:click="startVerify()">Continue</button>
                </div>

                <div x-show="step === 2" x-cloak class="flex flex-col items-center gap-4 py-8 text-center">
                    <span class="grid size-14 place-items-center rounded-2xl border border-brand-200 bg-brand-50 text-brand-600 text-2xl">🔐</span>
                    <div>
                        <p class="font-semibold text-ink" x-text="verifyDone ? 'Verified' : 'Verifying ownership…'"></p>
                        <p class="mt-1 text-xs text-muted" x-text="verifyDone ? 'Secure link established with ' + sell.app : 'Checking your subscription'"></p>
                    </div>
                    <div class="h-1.5 w-48 overflow-hidden rounded-full bg-wash" x-show="!verifyDone">
                        <div class="h-full bg-brand-600 transition-all duration-1000" x-bind:style="'width:' + verifyPct + '%'"></div>
                    </div>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-3">
                    <div x-show="!aiReady" class="flex flex-col items-center gap-3 py-8 text-center">
                        <p class="text-2xl">✨</p>
                        <p class="font-semibold text-ink">Nebius AI is analyzing your usage…</p>
                        <p class="max-w-xs text-xs text-muted">Generating the listing title, description and an optimized resale price.</p>
                        <div class="mt-1 h-1.5 w-56 overflow-hidden rounded-full bg-wash">
                            <div class="h-full bg-brand-600 transition-all duration-500" x-bind:style="'width:' + aiPct + '%'"></div>
                        </div>
                    </div>
                    <div x-show="aiReady" x-cloak class="space-y-3">
                        <div class="rounded-xl border border-brand-200 bg-brand-50 p-4">
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-brand-600">AI-generated title</p>
                            <p class="text-sm font-semibold text-ink" x-text="listing.title"></p>
                        </div>
                        <div class="rounded-xl border border-line bg-wash p-4">
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-muted">Description</p>
                            <p class="text-sm leading-relaxed text-ink/80" x-text="listing.description"></p>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Suggested price</p>
                                <p class="text-2xl font-bold text-emerald-700" x-text="'$' + Number(listing.price).toFixed(2)"></p>
                            </div>
                            <div class="text-right text-[11px] text-muted">
                                <p x-text="listing.days + ' days left'"></p>
                                <p class="line-through" x-text="'$' + Number(listing.original).toFixed(2) + ' original'"></p>
                            </div>
                        </div>
                        <p class="text-xs text-red-600" x-show="formError" x-text="formError" role="alert"></p>
                        <button type="button" class="lp-btn lp-btn-primary w-full" x-on:click="publish()" x-bind:disabled="sellBusy">
                            <span x-text="sellBusy ? 'Publishing…' : 'Publish to marketplace'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Buy modal --}}
    <div x-show="buyOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink/40" x-on:click="!buyBusy && (buyOpen = false)"></div>
        <div class="relative w-full max-w-sm rounded-2xl border border-line bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="font-semibold text-ink">Buy this listing</h3>
                <button type="button" class="text-muted hover:text-ink" x-on:click="buyOpen = false" x-bind:disabled="buyBusy">✕</button>
            </div>
            <form class="space-y-4 p-5" x-on:submit.prevent="confirmBuy()">
                <div class="rounded-xl border border-line bg-wash p-3" x-show="buyItem">
                    <p class="text-sm font-semibold text-ink" x-text="buyItem && buyItem.app"></p>
                    <p class="text-xs text-muted">
                        <span x-text="buyItem && buyItem.daysLeft"></span> days left ·
                        <span class="font-bold text-emerald-600" x-text="buyItem && ('$' + Number(buyItem.price).toFixed(2))"></span>
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Email for your receipt</label>
                    <input type="email" maxlength="190" required autocomplete="email" placeholder="you@email.com"
                           class="w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm"
                           x-model="buyEmail" />
                </div>
                <p class="text-xs text-red-600" x-show="buyError" x-text="buyError" role="alert"></p>
                <button type="submit" class="lp-btn lp-btn-primary w-full" x-bind:disabled="buyBusy">
                    <span x-text="buyBusy ? 'Reserving…' : 'Continue to PayPal'"></span>
                </button>
                <p class="text-center text-[11px] text-muted">The listing is held for you while you pay.</p>
            </form>
        </div>
    </div>
</div>
@endsection
