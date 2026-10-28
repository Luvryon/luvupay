{{-- AI Dispute Evidence Builder --}}
<div x-show="evidenceOpen" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="evidence-title"
     style="display:none">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" x-on:click="evidenceOpen = false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 text-slate-900"
         x-on:click.stop
         x-show="evidenceOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0">

        <div class="flex items-start justify-between gap-3 mb-1">
            <h2 id="evidence-title" class="font-bold text-slate-900 flex items-center gap-2 text-lg">
                <span aria-hidden="true">⚖️</span>
                AI Dispute Evidence Builder
            </h2>
            <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none px-1" x-on:click="evidenceOpen = false" aria-label="Close">&times;</button>
        </div>
        <p class="text-sm text-slate-500 mb-5">
            Nebius AI has compiled the following evidence for PayPal Resolution Center.
            <span class="block mt-1 text-xs text-slate-400" x-show="evidenceRow">
                Case · <span class="font-medium text-slate-600" x-text="(evidenceRow && (evidenceRow.display_name || evidenceRow.email)) || 'Subscriber'"></span>
                <template x-if="evidenceRow && evidenceRow.id">
                    <span> · <span class="font-mono" x-text="String(evidenceRow.id).slice(0, 8) + '…'"></span></span>
                </template>
            </span>
        </p>

        <ul class="space-y-0 border border-slate-100 rounded-xl p-4 bg-slate-50/50">
            <li class="flex items-start gap-3 mb-3">
                <span class="text-emerald-500 mt-0.5 shrink-0" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </span>
                <p class="text-sm text-slate-700">User IP at signup (192.168.1.1) matches last login IP</p>
            </li>
            <li class="flex items-start gap-3 mb-3">
                <span class="text-emerald-500 mt-0.5 shrink-0" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </span>
                <p class="text-sm text-slate-700">User actively streamed 14 hours of content on Oct 24, 2 days before dispute</p>
            </li>
            <li class="flex items-start gap-3">
                <span class="text-emerald-500 mt-0.5 shrink-0" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </span>
                <p class="text-sm text-slate-700">User explicitly accepted Terms of Service v2.1 at 2023-01-15 10:00 AM</p>
            </li>
        </ul>

        <div class="mt-4 rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2 text-xs text-rose-700">
            Chargeback risk flagged · evidence is formatted for PayPal Resolution Center upload
        </div>

        <button type="button"
                class="w-full bg-slate-900 text-white font-bold py-3 rounded-lg hover:bg-slate-800 mt-4 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                x-on:click="exportEvidenceToPayPal()"
                x-bind:disabled="evidenceExporting || evidenceExported">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <span x-show="!evidenceExporting && !evidenceExported">Export PDF &amp; Submit to PayPal</span>
            <span x-show="evidenceExporting" x-cloak>Building PDF…</span>
            <span x-show="evidenceExported && !evidenceExporting" x-cloak>✓ Submitted to PayPal</span>
        </button>
    </div>
</div>
