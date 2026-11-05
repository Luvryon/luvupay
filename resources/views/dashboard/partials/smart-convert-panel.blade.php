{{-- 🧪 Run AI Smart-Convert Protocol. Logic: public/js/smart-convert.js. Every value shown comes from the server's smart_convert_runs row. --}}
<style>
    .sc-ok { background:#ecfdf5; color:#047857; border-color:#10b981; }
    .sc-warn { background:#fffbeb; color:#b45309; border-color:#f59e0b; }
    .sc-bad { background:#fef2f2; color:#b91c1c; border-color:#ef4444; }
    .sc-steps { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-top:12px; font-size:12px; font-weight:700; }
    .sc-step { padding:5px 12px; border-radius:999px; border:1px solid #e2e8f0; background:#fff; color:#64748b; }
    .sc-step.on { background:#ecfdf5; border-color:#10b981; color:#047857; }
    .sc-step.now { background:#eef2ff; border-color:#6366f1; color:#4338ca; }
    .sc-step.stop { background:#fef2f2; border-color:#ef4444; color:#b91c1c; }
    .sc-arrow { color:#94a3b8; }
    .sc-donate { border:1px solid #a7f3d0; background:#f0fdf9; border-radius:12px; padding:14px 16px; }
    .sc-donate h3 { margin:0; font-size:13px; font-weight:800; color:#065f46; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .sc-tag { font-size:10px; font-weight:800; letter-spacing:.06em; padding:2px 8px; border-radius:999px; background:#fffbeb; color:#b45309; border:1px solid #f59e0b; }
    .sc-donate dl { display:grid; grid-template-columns:auto 1fr; gap:4px 14px; margin:10px 0 0; font-size:12px; }
    .sc-donate dt { color:#64748b; } .sc-donate dd { margin:0; text-align:right; font-family:ui-monospace,Menlo,monospace; word-break:break-all; }
    .sc-donate .big { font-size:15px; font-weight:800; color:#065f46; }
    .sc-donate p { margin:8px 0 0; font-size:11px; color:#64748b; }
</style>
<section class="bg-white border border-slate-200 rounded-xl p-6 mt-4" x-data="luvuSmartConvert()" x-show="demoEnabled" x-cloak>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-xl">
            <h2 class="text-lg font-bold text-slate-900">AI Smart-Convert</h2>
            <p class="text-sm text-slate-600 mt-1">
                Instead of a chargeback, NVIDIA Nemotron (via Nebius) proposes how the unused value is settled. PayPal Payouts executes the charity share.
                <span class="font-semibold">AI proposes → you can intervene → PayPal executes.</span>
            </p>
        </div>
        <div class="flex flex-col items-end gap-2">
            <button type="button" id="run-smart-convert"
                    class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 font-semibold text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background-color:#0f766e;color:#fff"
                    x-on:click="run()" x-bind:disabled="busy">
                <span x-show="!busy">🧪 Run AI Smart-Convert Protocol</span>
                <span x-show="busy" x-cloak>Negotiating…</span>
            </button>
            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                <input type="checkbox" x-model="approvalMode" class="rounded border-slate-300">
                Require my approval before PayPal pays
            </label>
            <button type="button" class="text-xs text-slate-500 underline" x-on:click="preflight()" x-bind:disabled="busy">Check readiness (live AI ping)</button>
        </div>
    </div>

    {{-- Readiness --}}
    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs font-mono" x-show="checks" x-cloak>
        <p class="font-bold" x-text="checks && checks.ready_for_real_run ? '✅ Ready for a real run' : '⚠️ Not ready for a real run'"></p>
        <template x-for="(v, k) in ((checks && checks.checks) || {})" :key="k">
            <p><span class="text-slate-500" x-text="k + ': '"></span><span x-text="String(v)"></span></p>
        </template>
        <p x-show="checks && checks.live_ai_ping" class="mt-1">
            AI ping: <span x-text="checks && checks.live_ai_ping && checks.live_ai_ping.ok ? ('OK · ' + checks.live_ai_ping.model + ' · ' + checks.live_ai_ping.latency_ms + ' ms') : ('FAILED · ' + (checks && checks.live_ai_ping && checks.live_ai_ping.error))"></span>
        </p>
    </div>

    {{-- Error: honest failure, no fallback --}}
    <div class="mt-4 rounded-lg border p-4 sc-bad" x-show="error" x-cloak role="alert">
        <p class="font-bold" x-text="error && (error.stage === 'ai' ? 'AI step failed: nothing was executed' : (error.stage === 'paypal' ? 'PayPal step failed: no money moved' : 'Request failed'))"></p>
        <p class="text-sm mt-1" x-text="error && error.error"></p>
        <a class="text-sm underline" x-show="error && error.evidence_url" x-bind:href="error && error.evidence_url" target="_blank" rel="noopener">View evidence for this attempt</a>
    </div>

    {{-- Result --}}
    <div class="mt-4" x-show="result" x-cloak>
        <div class="rounded-lg border p-3 font-bold text-sm" x-bind:class="headlineClass()" x-text="result && result.headline"></div>

        {{-- Hero flow: forgot → AI proposes → guardrails → PayPal pays → donation → audit --}}
        <div class="sc-steps" aria-label="Hero flow">
            <span class="sc-step on">1 · Subscriber forgot</span><span class="sc-arrow">→</span>
            <span class="sc-step on">2 · AI proposes</span><span class="sc-arrow">→</span>
            <span class="sc-step" x-bind:class="result && result.status === 'proposed' ? 'now' : (result && (result.status === 'rejected' || result.status === 'failed') ? 'stop' : 'on')"
                  x-text="'3 · ' + (result && result.status === 'proposed' ? 'You decide' : 'Guardrails')"></span><span class="sc-arrow">→</span>
            <span class="sc-step" x-bind:class="result && result.status === 'executed' ? 'on' : (result && (result.status === 'rejected' || result.status === 'failed') ? 'stop' : '')">4 · PayPal pays</span><span class="sc-arrow">→</span>
            <span class="sc-step" x-bind:class="result && result.donation && (result.donation.status === 'sent' || result.donation.status === 'simulated') ? 'on' : (result && result.donation && result.donation.status === 'awaiting_approval' ? 'now' : '')">5 · Donation</span><span class="sc-arrow">→</span>
            <span class="sc-step" x-bind:class="result && result.status === 'executed' ? 'on' : ''">6 · Audit trail</span>
        </div>

        <div class="grid gap-3 mt-3 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">Model</p>
                <p class="font-mono text-xs break-all" x-text="result && result.ai && result.ai.name"></p>
                <p class="text-xs text-slate-600 mt-1">
                    <span x-text="result && result.ai && result.ai.tokens_used !== null ? (result.ai.tokens_used + ' tokens') : 'tokens n/a'"></span> ·
                    <span x-text="result && result.ai && result.ai.latency_ms !== null ? (result.ai.latency_ms + ' ms') : 'latency n/a'"></span>
                </p>
                <ul class="list-disc pl-5 mt-2 text-xs text-slate-700">
                    <template x-for="(s, i) in ((result && result.ai && result.ai.rationale) || [])" :key="i"><li x-text="s"></li></template>
                </ul>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">Split</p>
                <p class="font-mono text-xs">Store credit <b x-text="'$' + money(result && result.conversion && result.conversion.store_credit_minor)"></b></p>
                <p class="font-mono text-xs">Charity <b x-text="'$' + money(result && result.conversion && result.conversion.charity_minor)"></b></p>
                <p class="font-mono text-xs">Fee <b x-text="'$' + money(result && result.conversion && result.conversion.fee_minor)"></b></p>
                <p class="text-xs text-slate-500 mt-1" x-show="result && result.conversion && result.conversion.guardrail_adjustments && result.conversion.guardrail_adjustments.length"
                   x-text="'Adjusted by policy: ' + ((result && result.conversion && result.conversion.guardrail_adjustments) || []).join(' ')"></p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm md:col-span-2">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">PayPal Payouts</p>
                <p class="font-mono text-xs break-all">payout_batch_id: <b x-text="(result && result.paypal && result.paypal.payout_batch_id) || '—'"></b></p>
                <p class="font-mono text-xs">status: <span x-text="(result && result.paypal && result.paypal.batch_status) || '—'"></span> · <span x-text="result && result.paypal && result.paypal.real ? 'REAL (' + result.paypal.mode + ')' : (result && result.paypal && result.paypal.simulated ? 'SIMULATED' : 'not executed')"></span></p>
            </div>
        </div>

        {{-- Donation stage (example recipient, sandbox money) --}}
        <div class="sc-donate mt-3" x-show="result && result.donation" x-cloak>
            <h3>
                <span>🎗️ Donation</span>
                <span class="sc-tag" x-show="result && result.donation && result.donation.example_only">EXAMPLE · SANDBOX</span>
                <span class="sc-tag" style="background:#ecfdf5;color:#047857;border-color:#10b981"
                      x-text="result && result.donation ? ({ sent: 'SENT', simulated: 'SIMULATED', awaiting_approval: 'WAITING FOR YOUR APPROVAL', processing: 'PROCESSING', none: 'NO DONATION', not_sent: 'NOT SENT' }[result.donation.status] || result.donation.status) : ''"></span>
            </h3>
            <dl>
                <dt>Recipient</dt><dd x-text="result && result.donation && result.donation.recipient"></dd>
                <dt>Amount</dt><dd class="big" x-text="result && result.donation ? ('$' + money(result.donation.amount_minor) + ' · ' + result.donation.share_percent + '% of unused value') : ''"></dd>
                <dt>Receipt</dt><dd x-text="result && result.donation && result.donation.receipt_id"></dd>
                <dt>Moved through</dt><dd x-text="(result && result.donation && result.donation.network) || 'nothing moved yet'"></dd>
            </dl>
            <p x-text="result && result.donation && result.donation.recipient_note"></p>
        </div>

        {{-- Merchant intervention (only while a proposal is waiting) --}}
        <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm" x-show="result && result.status === 'proposed'" x-cloak>
            <p class="font-semibold text-amber-900">Waiting for you. No money has moved.</p>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <label class="text-xs text-slate-700">Donation (minor units, can only be lowered)
                    <input type="number" min="0" class="ml-2 w-28 rounded border border-slate-300 px-2 py-1 text-xs" x-model="charityEdit">
                </label>
                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" x-on:click="decide('approve')" x-bind:disabled="busy">Approve &amp; let PayPal execute</button>
                <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50" x-on:click="decide('reject')" x-bind:disabled="busy">Reject</button>
            </div>
        </div>

        <p class="mt-3 text-sm">
            <a class="font-semibold text-indigo-700 underline" x-bind:href="result && result.evidence_url" target="_blank" rel="noopener">Open AI Negotiation Evidence page ↗</a>
            <span class="text-xs text-slate-500"> · screenshot-ready · </span>
            <a class="text-xs text-slate-500 underline" x-bind:href="result && result.evidence_json_url" target="_blank" rel="noopener">JSON</a>
        </p>
    </div>
</section>
