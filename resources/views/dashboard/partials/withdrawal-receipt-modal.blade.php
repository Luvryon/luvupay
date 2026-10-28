{{-- In-app withdrawal receipt — view + download (PayPal already holds the payout) --}}
<div x-show="withdrawalReceiptOpen" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="wd-receipt-title"
     style="display:none">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" x-on:click="withdrawalReceiptOpen = false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md text-slate-900 overflow-hidden"
         x-on:click.stop>
        <div class="px-6 pt-5 pb-3 border-b border-slate-100 flex items-start justify-between gap-3">
            <div>
                <h2 id="wd-receipt-title" class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <span aria-hidden="true">🧾</span> Withdrawal receipt
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    PayPal has the payout on their side. This is your merchant copy for records
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none" x-on:click="withdrawalReceiptOpen = false" aria-label="Close">&times;</button>
        </div>

        <div class="px-6 py-5 space-y-3" id="withdrawal-receipt-print">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Merchant</span>
                <span class="font-semibold text-slate-900" x-text="withdrawalReceipt.merchant_name || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Wallet debit (gross)</span>
                <span class="font-mono font-semibold" x-text="withdrawalReceipt.gross || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">PayPal fee</span>
                <span class="font-mono text-rose-600" x-text="'-' + (withdrawalReceipt.fee || '—')"></span>
            </div>
            <div class="flex justify-between text-sm border-t border-slate-100 pt-3">
                <span class="text-slate-700 font-semibold">You receive (net)</span>
                <span class="font-mono text-lg font-bold text-emerald-600" x-text="withdrawalReceipt.net || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Status</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                      x-bind:class="String(withdrawalReceipt.status || '').toUpperCase() === 'SUCCESS' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                      x-text="withdrawalReceipt.status || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">PayPal batch</span>
                <span class="font-mono text-xs text-slate-700 max-w-[200px] truncate" x-text="withdrawalReceipt.batch_id || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">To</span>
                <span class="font-mono text-xs" x-text="withdrawalReceipt.receiver || '—'"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Ledger id</span>
                <span class="font-mono text-[10px] text-slate-400 max-w-[200px] truncate" x-text="withdrawalReceipt.ledger_id || '—'"></span>
            </div>
            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2" x-show="withdrawalReceipt.simulated" x-cloak>
                Sandbox / simulated payout — recorded in your ledger; confirm status in PayPal Developer Dashboard if needed
            </p>
            <p class="text-[11px] text-slate-400" x-show="withdrawalReceipt.issued_at" x-cloak>
                Issued <span x-text="(withdrawalReceipt.issued_at || '').replace('T',' ').slice(0,19)"></span>
            </p>
        </div>

        <div class="px-6 pb-5 flex flex-col sm:flex-row gap-2">
            <button type="button"
                    class="flex-1 rounded-lg bg-slate-900 text-white font-semibold py-2.5 text-sm hover:bg-slate-800 flex items-center justify-center gap-2"
                    x-on:click="downloadWithdrawalReceipt()">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M12 12v9m0-9l-3 3m3-3l3 3M12 3v9"/>
                </svg>
                Download receipt
            </button>
            <button type="button"
                    class="flex-1 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold py-2.5 text-sm hover:bg-slate-50"
                    x-on:click="printWithdrawalReceipt()">
                Print
            </button>
        </div>
    </div>
</div>
