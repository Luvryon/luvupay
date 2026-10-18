{{--
  Simulate User Decision — slide-over / panel section.
  Standalone Alpine scope; can sit inside x-ui.slide-over or any parent.

  Usage:
    <x-ui.simulate-decision />
    or with show binding:
    <x-ui.slide-over show="decisionPanelOpen" title="Simulate User Decision">
      <x-ui.simulate-decision />
    </x-ui.slide-over>
--}}

<section
    x-data="{
        apiMode: 'mock',
        loading: false,
        currentLog: '',
        status: '',
        async simulateDecision(type) {
            this.loading = true;
            this.currentLog = '';
            this.status = '';

            const delay = this.apiMode === 'paypal' ? 1500 : 300;
            // paypal path: replace setTimeout with real fetch to Laravel when ready
            // e.g. await fetch('/api/v1/dashboard/demo/decision', { method:'POST', body: JSON.stringify({ type, mode: this.apiMode }) })
            await new Promise(r => setTimeout(r, delay));

            if (type === 'retry') {
                this.currentLog = '[PayPal API] Payment retry successful. Status: ACTIVE.';
                this.status = 'ACTIVE';
            } else if (type === 'expire') {
                this.currentLog = '[System] Initiating 48-Hour Grace Period... [AI] Offering 20% refund... [Marketplace] Auto-drafting resale listing.';
                this.status = 'GRACE PERIOD (48h)';
            } else if (type === 'resale') {
                this.currentLog = '[User Action] User clicked Resell. [LuvuPay AI] Auto-generating marketplace listing... Status: PENDING RESALE.';
                this.status = 'PENDING RESALE';
            }

            this.loading = false;
        }
    }"
    class="flex h-full flex-col"
    aria-label="Simulate User Decision"
>
    {{-- Header --}}
    <header class="border-b border-edge px-5 py-4">
        <h2 class="text-base font-bold text-slate-100">Simulate User Decision</h2>
        <p class="mt-1 text-xs text-slate-400">Replay the end-user choice after a missed cancellation.</p>
    </header>

    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">

        {{-- Demo Mode toggle --}}
        <div>
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Demo Mode</p>
            <div class="inline-flex w-full rounded-xl border border-edge bg-slate-900/80 p-1" role="group" aria-label="Demo mode">
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition"
                    x-bind:class="apiMode === 'mock'
                        ? 'bg-emerald-500/20 text-emerald-300 shadow-sm'
                        : 'text-slate-400 hover:text-slate-200'"
                    x-on:click="apiMode = 'mock'"
                    x-bind:disabled="loading"
                >Mock (Instant)</button>
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition"
                    x-bind:class="apiMode === 'paypal'
                        ? 'bg-emerald-500/20 text-emerald-300 shadow-sm'
                        : 'bg-slate-700/40 text-slate-400 hover:text-slate-200'"
                    x-on:click="apiMode = 'paypal'"
                    x-bind:disabled="loading"
                >Real PayPal Sandbox</button>
            </div>
            <p class="mt-2 text-[11px] text-slate-500"
               x-text="apiMode === 'mock'
                 ? 'Instant UI response · no network wait'
                 : 'Simulates PayPal Sandbox latency (~1.5s)'"></p>
        </div>

        {{-- Action buttons --}}
        <div class="space-y-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">User choice</p>

            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-left text-sm font-semibold text-emerald-200 transition hover:bg-emerald-500/20 disabled:pointer-events-none disabled:opacity-50"
                x-on:click="simulateDecision('retry')"
                x-bind:disabled="loading"
            >
                <span>Simulate User: Retries Payment ($20)</span>
                <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-400/80">ACTIVE</span>
            </button>

            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-xl border border-orange-500/30 bg-orange-500/10 px-4 py-3 text-left text-sm font-semibold text-orange-200 transition hover:bg-orange-500/20 disabled:pointer-events-none disabled:opacity-50"
                x-on:click="simulateDecision('expire')"
                x-bind:disabled="loading"
            >
                <span>Simulate User: Lets it Expire (Churn)</span>
                <span class="text-[10px] font-bold uppercase tracking-wide text-orange-400/80">CHURN</span>
            </button>

            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-xl border border-violet-500/30 bg-violet-500/10 px-4 py-3 text-left text-sm font-semibold text-violet-200 transition hover:bg-violet-500/20 disabled:pointer-events-none disabled:opacity-50"
                x-on:click="simulateDecision('resale')"
                x-bind:disabled="loading"
            >
                <span>✨ Simulate User: Chooses to RESALE (Marketplace)</span>
                <span class="text-[10px] font-bold uppercase tracking-wide text-violet-400/80">RESALE</span>
            </button>
        </div>

        {{-- Log output --}}
        <div>
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Event log</p>
                <span
                    class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                    x-show="status"
                    x-cloak
                    x-bind:class="{
                        'border-emerald-500/40 bg-emerald-500/15 text-emerald-300': status === 'ACTIVE',
                        'border-orange-500/40 bg-orange-500/15 text-orange-300': status === 'GRACE PERIOD (48h)',
                        'border-violet-500/40 bg-violet-500/15 text-violet-300': status === 'PENDING RESALE'
                    }"
                    x-text="status"
                ></span>
            </div>

            <div class="min-h-[7rem] rounded-xl border border-edge bg-slate-950/80 p-4 font-mono text-[11px] leading-relaxed text-slate-300">
                <template x-if="loading">
                    <p class="flex items-center gap-2 text-slate-400">
                        <svg class="size-3.5 animate-spin text-violet-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 3a9 9 0 1 0 9 9"/>
                        </svg>
                        <span x-text="apiMode === 'paypal' ? 'Connecting to PayPal Sandbox API…' : 'Running mock…'"></span>
                    </p>
                </template>
                <template x-if="!loading && currentLog">
                    <p class="whitespace-pre-wrap break-words" x-text="currentLog"></p>
                </template>
                <template x-if="!loading && !currentLog">
                    <p class="text-slate-600">Idle — pick a simulation above.</p>
                </template>
            </div>
        </div>
    </div>
</section>
