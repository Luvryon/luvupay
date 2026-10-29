{{-- AI Dispute Evidence Builder - data from API --}}
<div x-show="evidenceOpen" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="evidence-title"
     style="display:none">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" x-on:click="evidenceOpen = false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 text-slate-900"
         x-on:click.stop
         x-show="evidenceOpen"
         x-transition>

        <div class="flex items-start justify-between gap-3 mb-1">
            <h2 id="evidence-title" class="font-bold text-slate-900 flex items-center gap-2 text-lg">
                <span class="grid place-items-center rounded-lg bg-brand-50 text-brand-700" style="width:32px;height:32px;background:#eeedf8;color:#09027b">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/></svg>
                </span>
                AI Dispute Evidence Builder
            </h2>
            <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none px-1" x-on:click="evidenceOpen = false" aria-label="Close">&times;</button>
        </div>
        <p class="text-sm text-slate-500 mb-4">
            Evidence compiled from your ledger and subscription records for PayPal Resolution Center.
            <span class="block mt-1 text-xs text-slate-400" x-show="evidenceRow">
                Case · <span class="font-medium text-slate-600" x-text="(evidenceRow && (evidenceRow.display_name || evidenceRow.email)) || 'Subscriber'"></span>
            </span>
        </p>

        <div x-show="evidenceLoading" class="py-10 text-center text-sm text-slate-500">Building evidence pack…</div>

        <div x-show="!evidenceLoading && evidencePack" class="space-y-3 max-h-[50vh] overflow-y-auto">
            <p class="text-sm font-semibold text-slate-800" x-text="evidencePack && evidencePack.summary"></p>
            <template x-for="(sec, si) in (evidencePack && evidencePack.sections) || []" :key="si">
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2" x-text="sec.title"></p>
                    <ul class="space-y-2">
                        <template x-for="(pt, pi) in sec.points" :key="pi">
                            <li class="flex items-start gap-2 text-sm text-slate-700">
                                <span class="mt-0.5 text-emerald-600 shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                </span>
                                <span x-text="pt"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>

        <div x-show="!evidenceLoading && evidenceError" class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="evidenceError"></div>

        <div class="mt-4 rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2 text-xs text-rose-700">
            Review before upload. Fields marked unavailable must be filled from your app logs.
        </div>

        <button type="button"
                class="w-full font-bold py-3 rounded-lg mt-4 flex items-center justify-center gap-2 disabled:opacity-50 text-white"
                style="background:#09027b"
                x-on:click="exportEvidenceToPayPal()"
                x-bind:disabled="evidenceExporting || evidenceExported || evidenceLoading || !evidencePack">
            <span x-show="!evidenceExporting && !evidenceExported">Download evidence pack</span>
            <span x-show="evidenceExporting" x-cloak>Preparing file…</span>
            <span x-show="evidenceExported && !evidenceExporting" x-cloak>Downloaded</span>
        </button>
    </div>
</div>
