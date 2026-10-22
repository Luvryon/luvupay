{{-- Dual-step: centered user picker → corner phone preview. Users from /api/v1/dashboard/subscriptions when available. --}}
<div
    x-data="{
        showSim: false,
        selectedUser: '',
        step: 1,
        lastAction: '',
        users: [],
        loadingUsers: false,
        async open() {
            this.showSim = true;
            this.step = 1;
            this.selectedUser = '';
            this.lastAction = '';
            this.loadingUsers = true;
            try {
                const r = await fetch('/api/v1/dashboard/subscriptions', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const j = await r.json();
                const rows = (j && j.data) ? j.data : [];
                this.users = rows.slice(0, 12).map((row, i) => ({
                    id: row.id || ('u' + i),
                    label: (row.display_name || row.email || 'Subscriber') + ' (' + (row.status || 'Active') + ')',
                    name: row.display_name || row.email || 'Subscriber',
                    email: row.email || '',
                    status: row.status || 'Active',
                    message: 'Your payment failed. You have 48 hours to claim a 20% partial refund, resell access, or donate residual value.',
                    offer: 'Choose an option below — merchant controls which paths are enabled.',
                }));
            } catch (e) {
                this.users = [];
            }
            if (!this.users.length) {
                this.users = [
                    { id: 'demo-sarah', label: 'Demo user (after Simulate 30 days)', name: 'Demo user', email: '', status: 'Grace Period',
                      message: 'Run Simulate 30 days first to load real subscribers. Demo placeholder only.',
                      offer: 'Resell or donate residual access.' },
                ];
            }
            this.loadingUsers = false;
        },
        close() { this.showSim = false; this.step = 1; this.selectedUser = ''; this.lastAction = ''; },
        connect() { if (!this.selectedUser) return; this.step = 2; },
        get user() {
            return this.users.find(u => u.id === this.selectedUser) || this.users[0] || { name: 'User', message: '', offer: '', status: '' };
        },
        act(kind) { this.lastAction = kind; this.step = 3; },
    }"
    x-cloak
>
    <button type="button"
            class="fixed bottom-6 right-6 z-40 rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg hover:bg-indigo-700"
            x-on:click="open()"
            x-show="!showSim || step === 1">
        Preview User Experience
    </button>

    {{-- Overlay: centered for step 1, transparent for phone steps --}}
    <div x-show="showSim" x-cloak
         class="fixed inset-0 z-[90]"
         x-bind:class="step === 1 ? 'flex items-center justify-center bg-slate-900/40 p-4' : 'pointer-events-none'"
         x-on:keydown.escape.window="if (showSim) close()">
        {{-- Step 1: centered card --}}
        <div x-show="step === 1" x-transition
             class="pointer-events-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
             x-on:click.stop>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Select User to Simulate</h3>
                    <p class="mt-1 text-sm text-slate-500">Sandbox session — no login required.</p>
                </div>
                <button type="button" class="rounded-lg bg-slate-100 px-2.5 py-1 text-slate-500 hover:bg-slate-200" x-on:click="close()">×</button>
            </div>
            <p x-show="loadingUsers" class="mt-4 text-sm text-slate-500">Loading subscribers…</p>
            <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-500">User</label>
            <div class="relative mt-1.5" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-left text-sm text-slate-900"
                        x-on:click="open = !open">
                    <span x-text="selectedUser ? (users.find(u => u.id === selectedUser)?.label || 'User') : 'Choose a subscriber…'"></span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div x-show="open" x-cloak class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    <template x-for="u in users" :key="u.id">
                        <button type="button" class="block w-full px-3.5 py-2 text-left text-sm hover:bg-slate-50"
                                x-on:click="selectedUser = u.id; open = false" x-text="u.label"></button>
                    </template>
                </div>
            </div>
            <button type="button"
                    class="mt-4 w-full rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white disabled:opacity-40"
                    x-bind:disabled="!selectedUser"
                    x-on:click="connect()">
                Connect &amp; Preview Session
            </button>
        </div>
    </div>

    {{-- Step 2–3: phone fixed bottom-right --}}
    <div x-show="showSim && step >= 2" x-cloak
         class="fixed bottom-6 right-6 z-[95] w-[300px]">
        <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-3 shadow-2xl">
            <div class="mb-2 flex items-center justify-between">
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Sandbox Authenticated</span>
                <button type="button" class="text-xs text-slate-400 hover:text-slate-600" x-on:click="close()">Close</button>
            </div>
            <p class="text-center text-sm font-semibold text-slate-800" x-text="user.name"></p>
            <p class="mb-3 text-center text-[11px] text-slate-500" x-text="user.status"></p>

            <div x-show="step === 2" class="rounded-xl border border-amber-200 bg-white p-3 shadow-sm">
                <p class="text-xs font-semibold text-amber-800">Payment failed</p>
                <p class="mt-1 text-[11px] leading-relaxed text-slate-600" x-text="user.message"></p>
                <p class="mt-2 text-[11px] text-slate-500" x-text="user.offer"></p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-lg bg-violet-600 py-2 text-[11px] font-semibold text-white" x-on:click="act('resale')">Resell Access</button>
                    <button type="button" class="rounded-lg bg-pink-500 py-2 text-[11px] font-semibold text-white" x-on:click="act('donate')">Donate</button>
                </div>
            </div>

            <div x-show="step === 3" class="rounded-xl border border-emerald-200 bg-white p-4 text-center">
                <p class="text-2xl">✓</p>
                <p class="mt-2 text-sm font-semibold text-emerald-700">Action recorded</p>
                <p class="mt-1 text-[11px] text-slate-500">Merchant ledger will reflect this path after you run the retention flow.</p>
                <button type="button" class="mt-3 text-xs font-semibold text-slate-600 underline" x-on:click="close()">Done</button>
            </div>
        </div>
    </div>
</div>
