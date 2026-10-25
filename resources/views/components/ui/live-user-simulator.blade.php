{{-- Preview User Experience: always-floating FAB bottom-right (own CSS: the compiled ui.css has no Tailwind position utilities); step1 centered modal; step2+ phone bottom-right --}}
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
                    id: String(row.id || ('u' + i)),
                    label: (row.display_name || row.email || 'Subscriber') + ' (' + (row.status || 'Active') + ')',
                    name: row.display_name || row.email || 'Subscriber',
                    email: row.email || '',
                    status: row.status || 'Active',
                    message: 'Your payment failed. You have 48 hours to claim a 20% partial refund, resell access, or donate residual value.',
                    offer: 'Choose an option below — your merchant controls which paths are enabled.',
                }));
            } catch (e) { this.users = []; }
            if (!this.users.length) {
                this.users = [{
                    id: 'demo',
                    label: 'Run Simulate 30 days first',
                    name: 'Demo subscriber',
                    status: 'Grace Period',
                    message: 'No real subscribers yet. After Simulate 30 days, this list fills from your ledger.',
                    offer: 'Resell or donate residual access.',
                }];
            }
            this.loadingUsers = false;
        },
        close() { this.showSim = false; this.step = 1; this.selectedUser = ''; },
        connect() { if (this.selectedUser) this.step = 2; },
        get user() {
            return this.users.find(u => u.id === this.selectedUser) || this.users[0] || { name: 'User', message: '', offer: '', status: '' };
        },
        act(kind) { this.lastAction = kind; this.step = 3; },
    }"
>
    {{-- FAB: always floating bottom-right on every console page --}}
    <button type="button" x-show="!showSim" x-cloak x-on:click="open()" class="lus-fab" aria-label="Preview user experience">
        <span aria-hidden="true">📱</span> Preview User Experience
    </button>

    {{-- Step 1: centered modal --}}
    <div x-show="showSim && step === 1" x-cloak class="lus-ov" x-on:keydown.escape.window="if (showSim && step === 1) close()" x-on:click.self="close()">
        <div class="lus-modal" x-on:click.stop>
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div>
                    <h3>Select User to Simulate</h3>
                    <p class="lus-sub">Sandbox session — no login required.</p>
                </div>
                <button type="button" class="lus-x" x-on:click="close()" aria-label="Close">×</button>
            </div>
            <p x-show="loadingUsers" class="lus-sub" style="margin-top:14px">Loading subscribers…</p>
            <label class="lus-label">User</label>
            <div class="lus-select" x-data="{ openDd: false }" x-on:click.outside="openDd = false">
                <button type="button" class="lus-sel-btn" x-on:click="openDd = !openDd">
                    <span x-text="selectedUser ? (users.find(u => u.id === selectedUser)?.label || 'User') : 'Choose a subscriber…'"></span>
                    <span style="color:#94a3b8">▾</span>
                </button>
                <div x-show="openDd" x-cloak class="lus-list">
                    <template x-for="u in users" :key="u.id">
                        <button type="button" x-on:click="selectedUser = u.id; openDd = false" x-text="u.label"></button>
                    </template>
                </div>
            </div>
            <button type="button" class="lus-go" x-bind:disabled="!selectedUser" x-on:click="connect()">Connect &amp; Preview Session</button>
        </div>
    </div>

    {{-- Step 2–3: phone, bottom-right --}}
    <div x-show="showSim && step >= 2" x-cloak class="lus-phone">
        <div class="lus-phone-in">
            <div class="lus-bar">
                <span class="lus-badge">🔒 Sandbox Authenticated</span>
                <button type="button" class="lus-link" x-on:click="close()">Close</button>
            </div>
            <p class="lus-name" x-text="user.name"></p>
            <p class="lus-st" x-text="user.status"></p>
            <div x-show="step === 2" class="lus-card">
                <h4>Payment failed</h4>
                <p x-text="user.message"></p>
                <div class="lus-acts">
                    <button type="button" class="lus-resell" x-on:click="act('resale')">Resell Access</button>
                    <button type="button" class="lus-donate" x-on:click="act('donate')">Donate</button>
                </div>
            </div>
            <div x-show="step === 3" class="lus-card ok">
                <p style="font-size:1.6rem;color:#059669;margin:0">✓</p>
                <p style="margin-top:8px;font-size:.9rem;font-weight:700;color:#047857" x-text="lastAction === 'resale' ? 'Listed for resale' : 'Donation recorded'"></p>
                <p>Open the dashboard to see it in the ledger. Resale offers appear on your public merchant page.</p>
                <button type="button" class="lus-link" style="margin-top:10px;text-decoration:underline;color:#475569" x-on:click="close()">Done</button>
            </div>
        </div>
    </div>
</div>

<style>
.lus-fab{position:fixed;right:24px;bottom:24px;z-index:9990;display:inline-flex;align-items:center;gap:8px;border:0;border-radius:999px;background:#4f46e5;color:#fff;padding:13px 20px;font:600 14px/1 inherit;font-family:inherit;cursor:pointer;box-shadow:0 10px 25px rgba(79,70,229,.35);transition:transform .15s,background .15s}
.lus-fab:hover{background:#4338ca;transform:translateY(-2px)}
.lus-fab:focus-visible{outline:3px solid #a5b4fc;outline-offset:2px}
.lus-ov{position:fixed;inset:0;z-index:9995;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:16px}
.lus-modal{width:100%;max-width:440px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 25px 60px rgba(15,23,42,.3)}
.lus-modal h3{margin:0;font-size:1.1rem;font-weight:700;color:#0f172a}
.lus-sub{margin:4px 0 0;font-size:.85rem;color:#64748b}
.lus-x{border:0;background:#f1f5f9;color:#64748b;border-radius:8px;width:30px;height:30px;font-size:18px;cursor:pointer}
.lus-label{display:block;margin:16px 0 6px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
.lus-select{position:relative}
.lus-sel-btn{display:flex;width:100%;justify-content:space-between;align-items:center;border:1px solid #e2e8f0;background:#f8fafc;border-radius:12px;padding:10px 14px;font:inherit;font-size:.9rem;color:#0f172a;text-align:left;cursor:pointer}
.lus-list{position:absolute;z-index:5;left:0;right:0;top:calc(100% + 4px);max-height:224px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:4px 0;box-shadow:0 12px 30px rgba(15,23,42,.15)}
.lus-list button{display:block;width:100%;border:0;background:none;text-align:left;padding:9px 14px;font:inherit;font-size:.88rem;cursor:pointer}
.lus-list button:hover{background:#f8fafc}
.lus-go{margin-top:16px;width:100%;border:0;border-radius:12px;background:#0f172a;color:#fff;padding:11px;font:600 .9rem inherit;font-family:inherit;cursor:pointer}
.lus-go:disabled{opacity:.4;cursor:not-allowed}
.lus-phone{position:fixed;right:24px;bottom:24px;z-index:9996;width:300px;max-width:calc(100vw - 32px)}
.lus-phone-in{background:#f8fafc;border:1px solid #e2e8f0;border-radius:28px;padding:14px;box-shadow:0 25px 60px rgba(15,23,42,.3)}
.lus-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.lus-badge{border-radius:999px;background:#d1fae5;color:#047857;padding:2px 8px;font-size:10px;font-weight:700}
.lus-link{border:0;background:none;color:#94a3b8;font-size:12px;cursor:pointer}
.lus-name{margin:0;text-align:center;font-size:.9rem;font-weight:700;color:#1e293b}
.lus-st{margin:2px 0 12px;text-align:center;font-size:11px;color:#64748b}
.lus-card{background:#fff;border:1px solid #fde68a;border-radius:14px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.lus-card.ok{border-color:#a7f3d0;text-align:center;padding:16px}
.lus-card h4{margin:0;font-size:.75rem;font-weight:700;color:#92400e}
.lus-card p{margin:4px 0 0;font-size:11px;line-height:1.5;color:#475569}
.lus-acts{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.lus-acts button{border:0;border-radius:10px;padding:8px;font:600 11px inherit;font-family:inherit;color:#fff;cursor:pointer}
.lus-resell{background:#7c3aed}.lus-donate{background:#ec4899}
</style>
