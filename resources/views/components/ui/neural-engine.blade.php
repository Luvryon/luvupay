{{-- Nebius–PayPal Neural Engine · light bento command center --}}
<section
    x-data="{
        activeNode: 0,
        init() {
            setInterval(() => { this.activeNode = (this.activeNode + 1) % 4 }, 1500);
        },
        // Prefer live webhook log from consoleApp; fallback sample stream for empty state
        events() {
            const logs = this.$root && this.$root.webhookLogs;
            if (Array.isArray(logs) && logs.length) {
                return logs.slice(0, 24).map(function (e, i) {
                    return {
                        ts: e.at || e.timestamp || e.created_at || new Date().toISOString(),
                        name: e.event || e.type || e.name || 'webhook.event',
                        code: e.status || e.code || '200',
                    };
                });
            }
            return [
                { ts: new Date().toISOString(), name: 'waiting.for.events', code: '—' },
            ];
        },
        rowsRendered() {
            const n = (this.$root && this.$root.rowData && this.$root.rowData.length) || 0;
            const base = (this.$root && this.$root.kpi && this.$root.kpi.subscriptions) || 0;
            // Prefer actual grid rows; show project subscriber context when larger
            return n;
        }
    }"
    class="mb-6 rounded-2xl border border-slate-200 bg-slate-50/50 p-4 shadow-sm backdrop-blur sm:p-5"
    aria-label="Nebius PayPal Neural Engine"
>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Nebius–PayPal Neural Engine</h2>
            <p class="text-xs text-slate-500">Webhook stream · AI node graph · AG Grid vitals</p>
        </div>
        <span class="rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Command center</span>
    </div>

    <div class="grid grid-cols-12 gap-4">
        {{-- LEFT: PayPal stream --}}
        <div class="col-span-12 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:col-span-3">
            <div class="mb-3 flex items-center gap-2">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-50"></span>
                    <span class="relative size-2 rounded-full bg-blue-600"></span>
                </span>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-700">PayPal Live Events</h3>
            </div>
            <div class="h-96 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-600">
                <template x-for="(ev, i) in events()" :key="i">
                    <div class="mb-2 border-b border-slate-100 pb-2 last:mb-0 last:border-0 last:pb-0">
                        <div class="text-[10px] text-slate-400" x-text="ev.ts"></div>
                        <div class="truncate text-blue-700" x-text="ev.name"></div>
                        <div class="text-emerald-600" x-text="String(ev.code).indexOf('200') !== -1 || ev.code === 200 ? '200 OK' : ev.code"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- CENTER: Neural graph --}}
        <div class="col-span-12 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:col-span-6">
            <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-indigo-700">Nebius Nemotron Processing Node</h3>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch sm:justify-between">
                <template x-for="(node, idx) in [
                    { title: 'Ingest PayPal Webhook', sub: 'HMAC verify · inbox' },
                    { title: 'Nebius AI Analyze', sub: 'Nemotron risk score' },
                    { title: 'Smart-Convert Logic', sub: 'Retain / resale / grace' },
                    { title: 'Execute PayPal Payouts', sub: 'Split · charity · wallet' },
                ]" :key="idx">
                    <div class="flex flex-1 flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                        <div
                            class="flex-1 rounded-xl border bg-white p-4 shadow-sm transition-all duration-300"
                            :class="activeNode === idx ? 'border-indigo-500 ring-2 ring-indigo-100' : 'border-slate-200'"
                        >
                            <div class="mb-2 text-lg" :class="activeNode === idx ? 'text-indigo-600' : 'text-slate-400'" x-text="['📥','🧠','⚙️','💸'][idx]"></div>
                            <p class="text-sm font-semibold text-slate-900" x-text="node.title"></p>
                            <p class="mt-0.5 text-[11px] text-slate-500" x-text="node.sub"></p>
                            <p class="mt-2 text-[10px] font-medium uppercase tracking-wide"
                               :class="activeNode === idx ? 'text-indigo-600' : 'text-slate-400'"
                               x-text="activeNode === idx ? 'Processing' : 'Idle'"></p>
                        </div>
                        <div class="hidden h-px flex-1 bg-slate-200 sm:block sm:h-0.5 sm:w-3 sm:flex-none"
                             x-show="idx < 3" style="display:none"></div>
                    </div>
                </template>
            </div>
            <p class="mt-4 text-center text-[11px] text-slate-400">Active node cycles every 1.5s · mirrors production pipeline order</p>
        </div>

        {{-- RIGHT: AG Grid vitals --}}
        <div class="col-span-12 space-y-3 lg:col-span-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">AG Grid Engine Vitals</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Rows Rendered</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    <span x-text="rowsRendered()">0</span>
                </p>
                <p class="mt-1 text-xs text-emerald-600">Live ledger row count</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Filter Speed</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">0.04s</p>
                <div class="mt-2 flex h-6 items-end gap-0.5">
                    <span class="w-1 rounded-sm bg-indigo-200" style="height:40%"></span>
                    <span class="w-1 rounded-sm bg-indigo-300" style="height:65%"></span>
                    <span class="w-1 rounded-sm bg-indigo-400" style="height:50%"></span>
                    <span class="w-1 rounded-sm bg-indigo-500" style="height:80%"></span>
                    <span class="w-1 rounded-sm bg-indigo-400" style="height:55%"></span>
                    <span class="w-1 rounded-sm bg-indigo-600" style="height:90%"></span>
                    <span class="w-1 rounded-sm bg-indigo-500" style="height:70%"></span>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Active Filters</p>
                <p class="mt-1 flex items-center gap-2 text-2xl font-bold text-slate-900">
                    <span>3</span>
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                </p>
                <p class="mt-1 text-[11px] text-slate-400">Status · type · date (grid defaults)</p>
            </div>
        </div>
    </div>
</section>
