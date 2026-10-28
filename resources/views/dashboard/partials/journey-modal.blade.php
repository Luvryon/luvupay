{{-- Missed Cancellation Journey — DUAL-VIEW + AI features (thinking, safe refund, micro-pay, negotiator chat) --}}
<link rel="stylesheet" href="/css/journey.css?v={{ @filemtime(public_path('css/journey.css')) }}">

<x-ui.modal show="decisionOpen" title="Missed Cancellation Journey" size="xl">
    <div class="journey-scope space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data
         x-effect="if (decisionOpen && journeyStep === 'loading') { /* parent drives timers */ }">

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-500">
                Live on your real ledger for <strong class="text-slate-900" x-text="journey.name"></strong>
                · <span x-text="journey.email"></span>
            </p>
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                <span class="rounded-full px-2.5 py-1"
                      x-bind:class="journey.sandbox ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                      x-text="journey.sandbox ? 'PayPal Sandbox connected' : 'Mock ledger (no PayPal keys)'"></span>
                <span class="rounded-full px-2.5 py-1"
                      x-bind:class="journey.nebius ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                      x-text="journey.nebius ? 'Nebius AI live' : 'AI: local template'"></span>
            </div>
        </div>

        {{-- Feature 1: AI Processing Animation --}}
        <div x-show="journeyStep === 'loading'" x-cloak
             class="bg-slate-50 rounded-xl p-8 flex flex-col items-center justify-center h-64 border border-slate-100">
            <div class="relative mb-5">
                <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center animate-pulse">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                </div>
                <span class="absolute -top-1 -right-1 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                </span>
            </div>
            <p class="text-sm font-semibold text-slate-800 mb-1" x-text="aiThinkingLabel">Analyzing LTV…</p>
            <p class="text-xs text-slate-500 mb-5">Nebius AI is scoring retention options for this subscriber</p>
            <div class="w-48 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-full bg-indigo-500 rounded-full transition-all duration-500 ease-out"
                     x-bind:style="'width:' + aiThinkingProgress + '%'"></div>
            </div>
        </div>

        {{-- Dual view (after thinking) --}}
        <div x-show="journeyStep === 'ready'" x-cloak class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
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
                    <p x-show="decisionBusy" x-cloak class="text-slate-400">⏳ working…</p>
                </div>
            </section>

            {{-- RIGHT: user's phone --}}
            <section aria-label="User phone preview">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">User's phone</p>
                <div class="bg-slate-900 rounded-3xl p-4 sm:p-6 h-96 flex flex-col shadow-2xl border border-slate-700">
                    <div class="bg-white rounded-2xl w-full max-w-xs mx-auto flex flex-col h-full shadow-xl overflow-hidden">
                        <div class="px-4 pt-3 pb-2 border-b border-slate-100">
                            <h3 class="font-bold text-slate-900 text-center text-sm mb-2" x-text="journey.brand"></h3>
                            {{-- Feature 4: Options vs Chat toggle --}}
                            <div class="flex rounded-lg bg-slate-100 p-0.5 text-[11px] font-semibold">
                                <button type="button"
                                        class="flex-1 rounded-md py-1.5 transition-colors"
                                        x-bind:class="phoneTab === 'options' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        x-on:click="phoneTab = 'options'">Options</button>
                                <button type="button"
                                        class="flex-1 rounded-md py-1.5 transition-colors"
                                        x-bind:class="phoneTab === 'chat' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        x-on:click="phoneTab = 'chat'">Chat with AI</button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-3">
                            {{-- Options view --}}
                            <div x-show="phoneTab === 'options' && phoneView === 'options'">
                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                                    <p class="text-xs text-slate-700 leading-relaxed">
                                        <span aria-hidden="true">🔔</span>
                                        <span x-text="'Hi ' + journey.first + ', your payment of ' + journey.amount + ' failed. You have ' + journey.hours + ' hours to choose an option below:'"></span>
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <button type="button" x-on:click="choose('renew')" x-bind:disabled="!decisionReady || decisionBusy"
                                            class="bg-slate-900 text-white py-2 rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            x-text="'Retry Payment (' + journey.amount + ')'"></button>
                                    <button type="button" x-on:click="choose('partial_refund')" x-bind:disabled="!decisionReady || decisionBusy"
                                            class="bg-sky-600 text-white py-2 rounded-lg text-xs font-medium hover:bg-sky-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            x-text="'Partial refund (' + journey.refundPct + '%)'"></button>
                                    <button type="button" x-on:click="choose('resale')" x-bind:disabled="!decisionReady || decisionBusy || !journey.features.resale"
                                            x-bind:title="journey.features.resale ? '' : 'Enable Marketplace Resale in Settings'"
                                            class="bg-purple-600 text-white py-2 rounded-lg text-xs font-medium hover:bg-purple-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Resell Remaining Access</button>
                                    <button type="button" x-on:click="choose('donate')" x-bind:disabled="!decisionReady || decisionBusy || !journey.features.charity"
                                            class="bg-pink-500 text-white py-2 rounded-lg text-xs font-medium hover:bg-pink-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Donate residual</button>
                                    <button type="button" x-on:click="choose('cancel')" x-bind:disabled="!decisionReady || decisionBusy"
                                            class="border border-slate-300 bg-white text-slate-700 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Let it expire</button>
                                </div>
                            </div>

                            {{-- Feature 4: AI Negotiator Chat --}}
                            <div x-show="phoneTab === 'chat' && phoneView !== 'done'" x-cloak class="flex flex-col gap-2 h-full">
                                <div class="flex-1 space-y-2 overflow-y-auto pr-1">
                                    <div class="bg-slate-100 rounded-lg p-2 text-xs text-slate-700 max-w-[80%]">
                                        Hi <span x-text="journey.first || 'there'"></span>, payment failed. How can we help?
                                    </div>
                                    <div class="bg-indigo-600 text-white rounded-lg p-2 text-xs max-w-[80%] ml-auto">
                                        I'm short on cash, can I pay half?
                                    </div>
                                    <div class="bg-slate-100 rounded-lg p-2 text-xs text-slate-700 max-w-[85%]">
                                        Sure! I can split it: <strong>$25 now, $25 next week</strong>. Or I can refund <strong>$47.50</strong> safely. Which do you prefer?
                                    </div>
                                    <div class="bg-slate-100 rounded-lg p-2 text-xs text-slate-700 max-w-[85%] border border-indigo-100">
                                        <span class="text-[10px] font-semibold text-indigo-600 uppercase tracking-wide">AI offer</span>
                                        <p class="mt-1">Micro-payment keeps access active. Safe refund caps at amount − PayPal fee so your sandbox balance never goes negative</p>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-slate-100 mt-auto">
                                    <input type="text"
                                           placeholder="Type a message…"
                                           class="w-full bg-white border border-slate-200 rounded-full px-3 py-1.5 text-[10px] text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           disabled
                                           aria-label="Chat input (demo)">
                                </div>
                            </div>

                            {{-- Done state --}}
                            <div x-show="phoneView === 'done'" x-cloak class="text-center py-6">
                                <div class="mx-auto w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold" aria-hidden="true">✓</div>
                                <p class="mt-3 text-sm font-bold text-slate-900">Action Completed</p>
                                <p class="mt-1 text-xs font-semibold text-indigo-600" x-text="selectedAction"></p>
                                <p class="mt-2 text-xs text-slate-500 leading-relaxed" x-text="journey.doneMessage"></p>
                                <a x-show="journey.listingUrl" x-cloak x-bind:href="journey.listingUrl" target="_blank" rel="noopener"
                                   class="mt-3 inline-block text-xs font-semibold text-purple-600 hover:text-purple-700 underline">View on marketplace ↗</a>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 text-center py-2 border-t border-slate-50">Secure options powered by LuvuPay AI</p>
                    </div>
                </div>
            </section>
        </div>

        {{-- bottom actions --}}
        <div x-show="journeyStep === 'ready'" x-cloak>
            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <p class="text-xs text-slate-500" x-show="phoneView === 'options'">Pick what the user chose — from the phone or from here. Both views stay in sync</p>
                <p class="text-xs font-semibold text-emerald-600" x-show="phoneView === 'done'" x-cloak>✓ Completed — ledger, KPIs and wallet are updated</p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <button type="button" x-on:click="choose('renew')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed">Retry payment</button>
                <button type="button" x-on:click="choose('partial_refund')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed" x-text="'Partial refund (' + journey.refundPct + '%)'"></button>
                <button type="button" x-on:click="choose('resale')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done' || !journey.features.resale" class="rounded-lg bg-purple-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed">Resell remaining</button>
                <button type="button" x-on:click="choose('donate')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done' || !journey.features.charity" class="rounded-lg bg-pink-500 px-3.5 py-2 text-xs font-semibold text-white hover:bg-pink-600 disabled:opacity-40 disabled:cursor-not-allowed">Donate residual</button>
                <button type="button" x-on:click="choose('cancel')" x-bind:disabled="!decisionReady || decisionBusy || phoneView === 'done'" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Let it expire</button>
            </div>
        </div>
    </div>
</x-ui.modal>
