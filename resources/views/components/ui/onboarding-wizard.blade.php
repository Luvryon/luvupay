@props(['paypalConnected' => false, 'hasProject' => false])
@php
    $startStep = 1;
    if ($paypalConnected && ! $hasProject) $startStep = 2;
    elseif ($paypalConnected && $hasProject) $startStep = 3;
@endphp
<div
    x-data="{
        open: true,
        step: {{ (int) $startStep }},
        busy: false,
        projectName: 'StreamNetFly',
        planName: 'Premium',
        webhookUrl: @js(url('/api/v1/webhooks/merchant')),
        clientId: '',
        secretKey: '',
        copied: false,
        error: '',
        csrf() {
            const m = document.querySelector('meta[name=csrf-token]');
            return m ? m.getAttribute('content') : '';
        },
        async postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body || {}),
            });
            if (res.status === 419) throw new Error('Session expired (419). Refresh the page and try again');
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || data.error || ('HTTP ' + res.status));
            return data;
        },
        async createProject() {
            if (!(this.projectName || '').trim() || this.busy) return;
            this.busy = true; this.error = '';
            try {
                await this.postJson(@js(url('/onboarding/api/project')), {
                    name: this.projectName.trim(),
                    plan: this.planName.trim() || 'Premium',
                });
                this.step = 3;
            } catch (e) { this.error = e.message; }
            finally { this.busy = false; }
        },
        async issueKeys() {
            this.busy = true; this.error = '';
            try {
                const data = await this.postJson(@js(url('/onboarding/api/keys')), {});
                this.clientId = data.client_id;
                this.secretKey = data.secret_key;
                this.step = 4;
            } catch (e) { this.error = e.message; }
            finally { this.busy = false; }
        },
        async copySecrets() {
            try {
                await navigator.clipboard.writeText('Client ID: ' + this.clientId + '\nSecret: ' + this.secretKey);
                this.copied = true;
                setTimeout(() => { this.copied = false }, 2000);
            } catch (e) {}
        },
        async finish() {
            this.busy = true; this.error = '';
            try {
                const data = await this.postJson(@js(url('/onboarding/api/complete')), {});
                this.open = false;
                window.location.href = data.redirect || @js(url('/dashboard'));
            } catch (e) { this.error = e.message; this.busy = false; }
        }
    }"
    x-show="open"
    class="fixed inset-0 z-[90] flex items-center justify-center p-4"
    role="dialog" aria-modal="true"
>
    <div class="absolute inset-0 bg-slate-900/50"></div>
    <div class="relative flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <header class="border-b border-slate-200 px-6 py-5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-violet-600">Merchant onboarding</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900">Get ready in 5 steps</h2>
            <p class="mt-1 text-sm text-slate-500">PayPal must be linked before the console unlocks</p>
            <div class="mt-5 flex items-center gap-1.5">
                <template x-for="s in [1,2,3,4,5]" :key="s">
                    <div class="h-1.5 flex-1 rounded-full" :class="s <= step ? 'bg-violet-500' : 'bg-slate-200'"></div>
                </template>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Step <span x-text="step"></span> of 5</p>
        </header>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <p x-show="error" x-text="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" style="display:none"></p>

            {{-- Step 1: native HTML form = solid CSRF (no fetch) --}}
            <div x-show="step === 1" class="space-y-4">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>Required</strong> Connect PayPal before charges, payouts, or the console
                </div>
                <h3 class="text-lg font-semibold text-slate-900">1 · Connect PayPal Business</h3>
                <a href="{{ url('/oauth/paypal/redirect') }}"
                   class="inline-flex w-full items-center justify-center rounded-xl bg-[#ffc439] px-4 py-3.5 text-sm font-bold text-[#003087] hover:brightness-105">
                    Connect with PayPal (OAuth)
                </a>
                <form method="POST" action="{{ url('/oauth/paypal/sandbox-connect') }}" class="w-full">
                    @csrf
                    <input type="hidden" name="confirm" value="1" />
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        Connect PayPal Sandbox (Demo)
                    </button>
                </form>
                <p class="text-center text-[11px] text-slate-400">Sandbox uses a normal form POST so CSRF stays in sync with the session</p>
            </div>

            <div x-show="step === 2" class="space-y-4" style="display:none" x-cloak>
                <h3 class="text-lg font-semibold text-slate-900">2 · Create your first project</h3>
                <input type="text" x-model="projectName" maxlength="80" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900" placeholder="StreamNetFly" />
                <input type="text" x-model="planName" maxlength="40" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900" placeholder="Premium" />
                <button type="button" class="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-500 disabled:opacity-50"
                        @click="createProject()" :disabled="busy">Create project</button>
            </div>

            <div x-show="step === 3" class="space-y-4" style="display:none" x-cloak>
                <h3 class="text-lg font-semibold text-slate-900">3 · Webhook URL</h3>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs text-violet-700 break-all" x-text="webhookUrl"></div>
                <button type="button" class="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white" @click="issueKeys()" :disabled="busy">
                    <span x-text="busy ? 'Issuing keys…' : 'Continue · issue API keys'"></span>
                </button>
            </div>

            <div x-show="step === 4" class="space-y-4" style="display:none" x-cloak>
                <h3 class="text-lg font-semibold text-slate-900">4 · API keys (shown once)</h3>
                <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800" x-text="clientId"></code>
                <code class="block rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 font-mono text-xs text-amber-900" x-text="secretKey"></code>
                <button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800" @click="copySecrets()">
                    <span x-text="copied ? 'Copied' : 'Copy both'"></span>
                </button>
                <button type="button" class="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white" @click="step = 5">I stored my secret key</button>
            </div>

            <div x-show="step === 5" class="space-y-4 text-center" style="display:none" x-cloak>
                <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-50 text-3xl text-emerald-600">✓</div>
                <h3 class="text-xl font-bold text-slate-900">You're ready</h3>
                <button type="button" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white" @click="finish()" :disabled="busy">Go to Dashboard</button>
            </div>
        </div>
        <footer class="flex justify-between border-t border-slate-200 px-6 py-3 text-[11px] text-slate-400">
            <span>CSRF-safe onboarding</span>
            <button type="button" x-show="step > 1 && step < 5" @click="step--" class="text-slate-500 hover:text-slate-800">Back</button>
        </footer>
    </div>
</div>
