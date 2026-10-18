{{-- AI Insights from live ledger (consoleApp $root) — light mode --}}
<section
    x-data="{
        refreshing: false,
        refresh() {
            if (this.refreshing) return;
            this.refreshing = true;
            const root = this.$root;
            if (root && typeof root.load === 'function') {
                root.load().finally(() => { this.refreshing = false; });
            } else {
                setTimeout(() => { this.refreshing = false }, 800);
            }
        },
        rows() {
            const r = this.$root && this.$root.rowData;
            return Array.isArray(r) ? r : [];
        },
        // High churn risk: grace + pending resale + already churned signals
        churnRisk() {
            return this.rows().filter(function (t) {
                const life = String(t.lifecycle || '').toUpperCase();
                return life.indexOf('GRACE') !== -1
                    || life.indexOf('CHURN') !== -1
                    || life.indexOf('PENDING RESALE') !== -1
                    || life === 'AWAITING_USER';
            }).length;
        },
        revenueSavedLabel() {
            const k = this.$root && this.$root.kpi;
            if (k && k.revenueSaved && k.revenueSaved !== '—') return k.revenueSaved;
            return '$0';
        },
        // Smart-Convert success: AI-RETAINED / (AI-RETAINED + CHURNED) among terminal outcomes
        convertRate() {
            const rows = this.rows();
            let retained = 0, churned = 0;
            rows.forEach(function (t) {
                const life = String(t.lifecycle || '').toUpperCase();
                if (life.indexOf('AI-RETAINED') !== -1 || life.indexOf('RETAINED') !== -1) retained++;
                if (life.indexOf('CHURN') !== -1) churned++;
            });
            const denom = retained + churned;
            if (denom === 0) return null;
            return Math.round((retained / denom) * 100);
        },
        convertRateLabel() {
            const r = this.convertRate();
            return r === null ? '—' : (r + '%');
        },
        convertBarWidth() {
            const r = this.convertRate();
            return r === null ? '0%' : (r + '%');
        },
        hasLedger() {
            return this.rows().length > 0;
        }
    }"
    class="mb-6 overflow-hidden rounded-xl border border-indigo-100 bg-white shadow-sm"
    aria-label="AI Insights and Forecast"
>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2.5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd"/></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-slate-900">AI Insights &amp; Forecast</h2>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                        <span class="relative inline-flex size-2 animate-pulse rounded-full bg-emerald-500"></span>
                    </span>
                    Live from ledger · Nebius when configured
                </p>
            </div>
        </div>
        <button type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200 disabled:opacity-60"
                :disabled="refreshing" @click="refresh()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5" :class="refreshing && 'animate-spin'"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.39a.75.75 0 00-.75.75v3.842a.75.75 0 001.5 0v-2.183l.345.345a7 7 0 0011.708-3.14.75.75 0 00-1.449-.388zm-10.624-2.85a5.5 5.5 0 019.201-2.466l.312.311H11.77a.75.75 0 000 1.5h3.842a.75.75 0 00.75-.75V3.327a.75.75 0 00-1.5 0V5.51l-.345-.345A7 7 0 002.767 8.305a.75.75 0 001.45.388z" clip-rule="evenodd"/></svg>
            <span x-text="refreshing ? 'Refreshing…' : 'Refresh'">Refresh</span>
        </button>
    </div>

    <div class="space-y-3 p-5" x-show="hasLedger()" x-cloak style="display:none">
        <div class="flex gap-4 rounded-lg border border-slate-100 bg-slate-50 p-4 transition-all hover:-translate-y-0.5 hover:shadow-md">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-rose-100 text-rose-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-2xl font-bold tracking-tight text-rose-600">
                    <span x-text="churnRisk()"></span>
                    <span class="text-base font-semibold text-rose-500">Users</span>
                </p>
                <p class="mt-0.5 text-sm text-slate-600">at high churn risk (grace / pending resale / churned)</p>
                <p class="mt-1 text-xs text-slate-400">Counted from live AG Grid ledger rows</p>
            </div>
        </div>

        <div class="flex gap-4 rounded-lg border border-slate-100 bg-slate-50 p-4 transition-all hover:-translate-y-0.5 hover:shadow-md">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25z" clip-rule="evenodd"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-2xl font-bold tracking-tight text-emerald-600" x-text="revenueSavedLabel()">$0</p>
                <p class="mt-0.5 text-sm text-slate-600">Revenue saved (merchant_retention from summary API)</p>
                <p class="mt-1 text-xs text-slate-400">Same figure as the Overview KPI card</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 transition-all hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex gap-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M2.25 13.5a8.25 8.25 0 018.25-8.25.75.75 0 01.75.75v6.75H18a.75.75 0 01.75.75 8.25 8.25 0 01-16.5 0z" clip-rule="evenodd"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold tracking-tight text-indigo-600" x-text="convertRateLabel()">—</p>
                    <p class="mt-0.5 text-sm text-slate-600">Smart-Convert success (AI-RETAINED ÷ retained+churned)</p>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-200" role="progressbar">
                        <div class="h-1.5 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500"
                             :style="'width:' + convertBarWidth()"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="px-5 py-8 text-center" x-show="!hasLedger()" x-cloak style="display:none">
        <p class="text-sm text-slate-600">No ledger rows yet</p>
        <p class="mt-1 text-xs text-slate-400">Seed demo data or run live traffic — metrics bind to real rowData &amp; summary.</p>
    </div>

    <div class="border-t border-slate-100 px-5 py-3">
        <p class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-400">
            Derived from ledger + dashboard summary · Nebius used when NEBIUS_API_KEY is set
        </p>
    </div>
</section>
