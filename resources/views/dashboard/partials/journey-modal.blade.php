{{-- Missed Cancellation Journey — DUAL-VIEW SIMULATOR: backend (real server events) + user phone, side by side.
     Styles come from public/css/journey.css (Tailwind utilities compiled from this file; the console's ui.css has no Tailwind palette). --}}
<link rel="stylesheet" href="/css/journey.css?v={{ @filemtime(public_path('css/journey.css')) }}">

<x-ui.modal show="decisionOpen" title="Missed Cancellation Journey" size="xl">
    <div class="journey-scope space-y-4 text-slate-900 px-6 pt-4 pb-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-500">
                Live on your real ledger for <strong class="text-slate-900" x-text="journey.name"></strong> · <span x-text="journey.email"></span>
            </p>
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                <span class="rounded-full px-2.5 py-1" x-bind:class="journey.sandbox ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'" x-text="journey.sandbox ? 'PayPal Sandbox connected' : 'Mock ledger (no PayPal keys)'"></span>
                <span class="rounded-full px-2.5 py-1" x-bind:class="journey.nebius ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'" x-text="journey.nebius ? 'Nebius AI live' : 'AI: local template'"></span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
            {{-- LEFT: backend logs --}}
            <section aria-label="Backend logs">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Backend</p>
                <div class="bg-slate-50 rounded-lg p-4 font-mono text-xs space-y-2 h-96 overflow-y-auto" role="log" aria-live="polite" x-ref="journeyLog">
                    <template x-for="(l, i) in decisionLogs" x-bind:key="i">
                        <p class="leading-relaxed break-words"
                           x-bind:class="{
                               'text-emerald-600': l.tone === 'green',
                               'text-rose-600': l.tone === 'warn',
                               'text-indigo-600': l.tone === 'gold',
                               'text-blue-600': l.tone === 'blue',
                               'text-purple-600': l.tone === 'purple',
                               'text-slate-500': !l.tone
                           }" x-text="l.text"></p>
                    </template>
                    <p x-show="decisionBusy" x-cloak class="text-slate-400">▍ working…</p>
                </div>
            </section>

            {{-- RIGHT: user's phone --}}
            <section aria-label="User phone preview">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">User's phone</p>
                <div class="bg-slate-900 rounded-3xl p-6 h-96 flex flex-col items-center justify-center shadow-2xl border border-slate-700">
                    <div class="bg-white rounded-2xl w-full max-w-xs p-4 shadow-xl">
                        <h3 class="font-bold text-slate-900 text-center mb-4" x-text="journey.brand"></h3>

                        {{-- options view --}}
                        <div x-show="phoneView === 'options'">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                                <p class="text-sm text-slate-700 leading-relaxed">
                                    <span aria-hidden="true">🔔</span>
                                    <span x-text="'Hi ' + journey.first + ', your payment of ' + journey.amount + ' failed. You have ' + journey.hours + ' hours to choose an option below:'"></span>
                                </p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <button type="button" x-on:click="choose('renew')" x-bind:disabled="!decisionReady || decisionBusy"
                                        class="bg-slate-900 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="'Retry Payment (' + journey.amount + ')'"></button>
                                <button type="button" x-on:click="choose('resale')" x-bind:disabled="!decisionReady || decisionBusy || !journey.features.resale"
                                        x-bind:title="journey.features.resale ? '' : 'Enable Marketplace Resale in Settings'"
                                        class="bg-purple-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Resell Remaining Access</button>
                                <button type="button" x-on:click="choose('donate')" x-bind:disabled="!decisionReady || decisionBusy || !journey.features.charity"
                                        x-bind:title="journey.features.charity ? '' : 'Enable Charity Split in Settings'"
                                        class="bg-pink-500 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-pink-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="'Donate to Charity (' + journey.amount + ')'"></button>
                            </div>
                        </div>

                        {{-- completed view --}}
                        <div x-show="phoneView === 'done'" x-cloak class="text-center py-2">
                            <div class="mx-auto h-12 w-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold" aria-hidden="true">✓</div>
                            <p class="mt-3 text-sm font-bold text-slate-900">Action Completed</p>
                            <p class="mt-1 text-xs font-semibold text-indigo-600" x-text="selectedAction"></p>
                            <p class="mt-2 text-xs text-slate-500 leading-relaxed" x-text="journey.doneMessage"></p>
                            <a x-show="journey.listingUrl" x-cloak x-bind:href="journey.listingUrl" target="_blank" rel="noopener" class="mt-3 inline-block text-xs font-semibold text-purple-600 hover:text-purple-700 underline">View on marketplace ↗</a>
                        </div>

                        <p class="text-[10px] text-slate-400 text-center mt-3">Secure options powered by LuvuPay AI</p>
                    </div>
                </div>
            </section>
        </div>

        {{-- bottom: the 5 backend decisions (synced with the phone) --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <p class="text-xs text-slate-500" x-show="phoneView === 'options'">Pick what the user chose — from the phone or from here. Both views stay in sync.</p>
            <p class="text-xs font-semibold text-emerald-600" x-show="phoneView === 'done'" x-cloak>✓ Completed — ledger, KPIs and wallet are updated.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" x-on:click="choose('renew')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed">Retry payment</button>
            <button type="button" x-on:click="choose('partial_refund')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed" x-text="'Partial refund (' + journey.refundPct + '%)'"></button>
            <button type="button" x-on:click="choose('resale')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done' || !journey.features.resale" class="rounded-lg bg-purple-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed">Resell remaining</button>
            <button type="button" x-on:click="choose('donate')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done' || !journey.features.charity" class="rounded-lg bg-pink-500 px-3.5 py-2 text-xs font-semibold text-white hover:bg-pink-600 disabled:opacity-40 disabled:cursor-not-allowed">Donate residual</button>
            <button type="button" x-on:click="choose('cancel')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Let it expire</button>
        </div>
    </div>
</x-ui.modal>
