{{--
  Live User Simulator — realistic mobile app preview for merchants
--}}
<div
    x-data="{
        showSim: false,
        selectedUser: '',
        step: 1,
        lastAction: '',
        users: [
            {
                id: 'sarah',
                label: 'Sarah J. (Grace Period)',
                name: 'Sarah J.',
                handle: '@sarah',
                status: 'Grace Period · 47h left',
                statusTone: 'amber',
                message: 'Your payment for StreamNetFly Premium failed. You have 48 hours to decide.',
                offer: 'Claim a 20% partial refund if you cancel now — or keep watching.',
            },
            {
                id: 'mario',
                label: 'Mario C. (Pending Resale)',
                name: 'Mario C.',
                handle: '@mario',
                status: 'Pending Resale',
                statusTone: 'purple',
                message: 'Merchant enabled Marketplace Resale. List your remaining 15 days.',
                offer: 'Sell unused access to another fan on the marketplace.',
            },
            {
                id: 'emma',
                label: 'Emma W. (AI-Retained)',
                name: 'Emma W.',
                handle: '@emma',
                status: 'AI-Retained',
                statusTone: 'pink',
                message: 'Merchant enabled Charity Split. Donate residual value instead of wasting it.',
                offer: 'Send $10 of remaining value to Red Cross.',
            },
        ],
        get user() {
            return this.users.find(u => u.id === this.selectedUser) || this.users[0];
        },
        open() { this.showSim = true; this.step = 1; this.selectedUser = ''; this.lastAction = ''; },
        connect() { if (!this.selectedUser) return; this.step = 2; },
        act(label) { this.lastAction = label; this.step = 3; },
        close() { this.showSim = false; this.step = 1; this.selectedUser = ''; this.lastAction = ''; },
    }"
>
    {{-- Trigger — always visible --}}
    <button
        type="button"
        id="live-sim-trigger"
        x-on:click="open()"
        x-bind:style="showSim
            ? 'display:none'
            : 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:200;display:inline-flex;align-items:center;gap:0.5rem;background:#4f46e5;color:#fff;padding:0.85rem 1.35rem;border-radius:9999px;border:0;font-weight:600;font-size:0.9rem;box-shadow:0 12px 28px rgba(79,70,229,.4);cursor:pointer'"
    >
        <span aria-hidden="true">📱</span>
        Preview User Experience
    </button>

    {{-- Overlay --}}
    <div
        x-show="showSim"
        x-cloak
        style="display:none;position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.45);backdrop-filter:blur(8px)"
        x-bind:style="showSim
            ? 'position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.45);backdrop-filter:blur(8px)'
            : 'display:none'"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="if (showSim) close()"
    >
        <div style="position:absolute;inset:0" x-on:click="close()"></div>

        {{-- ========== STEP 1: pick user ========== --}}
        <div
            x-show="step === 1"
            x-cloak
            style="display:none"
            x-bind:style="step === 1 ? 'position:relative;width:24rem;max-width:100%;background:#fff;border-radius:1.25rem;padding:1.5rem;box-shadow:0 25px 50px rgba(0,0,0,.25);border:1px solid #e2e8f0' : 'display:none'"
            x-on:click.stop
        >
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                <div>
                    <h3 style="margin:0;font-size:1.125rem;font-weight:700;color:#0f172a">Select User to Simulate</h3>
                    <p style="margin:6px 0 0;font-size:0.875rem;color:#64748b">No login — sandbox session only.</p>
                </div>
                <button type="button" x-on:click="close()" aria-label="Close"
                        style="border:0;background:#f1f5f9;border-radius:8px;width:32px;height:32px;cursor:pointer;color:#64748b;font-size:1.1rem">×</button>
            </div>

            <label style="display:block;margin-top:1.25rem;font-size:0.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b" for="sim-user-select">User</label>
            <select id="sim-user-select" x-model="selectedUser"
                    style="margin-top:6px;width:100%;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;font-size:0.9rem;color:#0f172a;cursor:pointer">
                <option value="">Choose a sandbox user…</option>
                <template x-for="u in users" :key="u.id">
                    <option :value="u.id" x-text="u.label"></option>
                </template>
            </select>

            <button type="button" x-on:click="connect()" x-bind:disabled="!selectedUser"
                    style="margin-top:1rem;width:100%;padding:12px;border:0;border-radius:12px;background:#0f172a;color:#fff;font-weight:600;font-size:0.9rem;cursor:pointer"
                    x-bind:style="!selectedUser
                        ? 'margin-top:1rem;width:100%;padding:12px;border:0;border-radius:12px;background:#94a3b8;color:#fff;font-weight:600;font-size:0.9rem;cursor:not-allowed'
                        : 'margin-top:1rem;width:100%;padding:12px;border:0;border-radius:12px;background:#0f172a;color:#fff;font-weight:600;font-size:0.9rem;cursor:pointer'">
                Connect &amp; Preview Session
            </button>
        </div>

        {{-- ========== STEP 2: realistic phone ========== --}}
        <div
            x-show="step === 2"
            x-cloak
            style="display:none"
            x-bind:style="step === 2 ? 'position:relative' : 'display:none'"
            x-on:click.stop
        >
            {{-- Phone bezel --}}
            <div style="width:320px;max-width:92vw;background:#0f172a;border-radius:36px;padding:12px;box-shadow:0 30px 60px rgba(0,0,0,.45), inset 0 0 0 2px #334155">
                {{-- Screen --}}
                <div style="background:#f8fafc;border-radius:28px;overflow:hidden;min-height:560px;display:flex;flex-direction:column">
                    {{-- Status bar --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px 4px;font-size:11px;font-weight:600;color:#0f172a">
                        <span>9:41</span>
                        <div style="width:80px;height:22px;background:#0f172a;border-radius:999px;margin:0 auto"></div>
                        <span style="letter-spacing:-1px">●●● Wi‑Fi</span>
                    </div>

                    {{-- App header --}}
                    <div style="padding:8px 16px 12px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;background:#fff">
                        <button type="button" x-on:click="step = 1" style="border:0;background:transparent;color:#4f46e5;font-size:13px;font-weight:600;cursor:pointer;padding:0">← Users</button>
                        <div style="text-align:center">
                            <div style="font-size:13px;font-weight:700;color:#0f172a">LuvuPay</div>
                            <div style="font-size:10px;color:#64748b">Member app</div>
                        </div>
                        <button type="button" x-on:click="close()" style="border:0;background:transparent;color:#94a3b8;font-size:13px;cursor:pointer;padding:0">Close</button>
                    </div>

                    {{-- Sandbox badge --}}
                    <div style="padding:10px 16px 0;text-align:center">
                        <span style="display:inline-block;background:#d1fae5;color:#047857;font-size:10px;font-weight:700;padding:4px 10px;border-radius:999px;letter-spacing:.02em">
                            🔒 Sandbox Authenticated Session
                        </span>
                    </div>

                    {{-- Profile --}}
                    <div style="margin:14px 16px 0;padding:14px;background:#fff;border-radius:16px;border:1px solid #e2e8f0;display:flex;gap:12px;align-items:center;box-shadow:0 1px 2px rgba(0,0,0,.04)">
                        <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;flex-shrink:0"
                             x-text="(user.name || '?').charAt(0)"></div>
                        <div style="min-width:0">
                            <div style="font-weight:700;font-size:15px;color:#0f172a" x-text="user.name"></div>
                            <div style="font-size:12px;color:#64748b" x-text="user.handle + ' · StreamNetFly'"></div>
                            <div style="margin-top:4px;display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px"
                                 x-bind:style="user.statusTone === 'amber' ? 'background:#fef3c7;color:#b45309' : (user.statusTone === 'purple' ? 'background:#f3e8ff;color:#7e22ce' : 'background:#fce7f3;color:#be185d')"
                                 x-text="user.status"></div>
                        </div>
                    </div>

                    {{-- Alert card --}}
                    <div style="margin:12px 16px 0;padding:16px;background:#fffbeb;border:1px solid #fcd34d;border-radius:16px">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#b45309">Action required</div>
                        <p style="margin:8px 0 0;font-size:14px;line-height:1.45;color:#78350f;font-weight:500" x-text="user.message"></p>
                        <p style="margin:8px 0 0;font-size:13px;line-height:1.4;color:#92400e" x-text="user.offer"></p>
                    </div>

                    {{-- Primary actions --}}
                    <div style="margin:16px 16px 0;display:grid;gap:10px">
                        <button type="button" x-on:click="act('Resell Access')"
                                style="width:100%;padding:14px;border:0;border-radius:14px;background:#7c3aed;color:#fff;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 4px 12px rgba(124,58,237,.35)">
                            Resell Access
                        </button>
                        <button type="button" x-on:click="act('Donate to Charity')"
                                style="width:100%;padding:14px;border:0;border-radius:14px;background:#db2777;color:#fff;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 4px 12px rgba(219,39,119,.35)">
                            Donate to Charity
                        </button>
                        <button type="button" x-on:click="act('20% Partial Refund')"
                                style="width:100%;padding:14px;border:1.5px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a;font-weight:700;font-size:14px;cursor:pointer">
                            Claim 20% refund
                        </button>
                    </div>

                    <p style="margin:auto 16px 16px;padding-top:20px;text-align:center;font-size:11px;color:#94a3b8">
                        Sandbox only · no real charges
                    </p>

                    {{-- Home indicator --}}
                    <div style="padding:8px 0 10px;display:flex;justify-content:center">
                        <div style="width:120px;height:5px;border-radius:999px;background:#cbd5e1"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== STEP 3: success ========== --}}
        <div
            x-show="step === 3"
            x-cloak
            style="display:none"
            x-bind:style="step === 3 ? 'position:relative;width:22rem;max-width:100%;background:#fff;border-radius:1.25rem;padding:2rem;text-align:center;box-shadow:0 25px 50px rgba(0,0,0,.25);border:1px solid #e2e8f0' : 'display:none'"
            x-on:click.stop
        >
            <div style="margin:0 auto 1rem;width:56px;height:56px;border-radius:999px;background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.75rem;font-weight:700">✓</div>
            <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">Action Successful!</p>
            <p style="margin:8px 0 0;font-size:0.9rem;color:#64748b">
                <span x-text="lastAction || 'Decision'"></span> recorded · merchant dashboard updated.
            </p>
            <button type="button" x-on:click="close()"
                    style="margin-top:1.5rem;width:100%;padding:12px;border:0;border-radius:12px;background:#0f172a;color:#fff;font-weight:600;font-size:0.9rem;cursor:pointer">
                Close simulator
            </button>
        </div>
    </div>
</div>
