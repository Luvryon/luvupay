{{-- Charity Integration Workflow (Demo) — ethically accurate, no false "Verified Partners" claims --}}
<section class="mb-6 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl shadow-black/20" aria-label="Charity integration workflow">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-white">Charity Integration Workflow (Demo)</h2>
            <p class="mt-1 text-xs text-slate-400">Proposed technical path for future MoU-backed disbursements via PayPal Payouts</p>
        </div>
        <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">Prototype</span>
    </div>

    <div class="mt-5">
        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Example Target Partners</h3>
        <ul class="mt-3 space-y-2">
            @foreach([
                ['Red Cross', 'Disaster relief & humanitarian aid'],
                ['WWF', 'Conservation & climate programs'],
                ['Local Education Fund', 'Scholarships & school access'],
            ] as [$name, $desc])
                <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/60 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-100">{{ $name }}</p>
                        <p class="text-[11px] text-slate-500">{{ $desc }}</p>
                    </div>
                    <span class="shrink-0 rounded-full border border-slate-700 bg-slate-800/80 px-2 py-0.5 text-[10px] font-medium text-slate-400">(Workflow Model)</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-5 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Proof of Execution</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-300">
            Simulated Transaction: <span class="font-mono text-emerald-300">$10.00</span> routed to
            <span class="font-medium text-white">Red Cross</span> via PayPal Payouts API
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                    x-on:click="$dispatch('toast', { message: 'Mock transfer receipt opened', icon: '📄' })">
                📄 View Transfer Receipt (Mock)
            </button>
            <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                    x-on:click="$dispatch('toast', { message: 'Impact certificate template downloaded', icon: '📄' })">
                📄 Download Impact Certificate (Template)
            </button>
        </div>
    </div>

    <p class="mt-5 border-t border-slate-800 pt-4 text-[11px] leading-relaxed text-slate-500">
        <strong class="font-semibold text-slate-400">Disclaimer:</strong>
        This dashboard demonstrates the proposed technical workflow. LuvuPay will only disburse funds to charities
        upon signing a formal MoU and verifying their official PayPal business accounts
    </p>
</section>
