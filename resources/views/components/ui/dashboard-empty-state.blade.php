<div x-show="!hasData" x-cloak class="c-card" style="padding:48px 32px;text-align:center">
    <div style="margin:0 auto 16px;width:56px;height:56px;border-radius:16px;background:#eef2ff;display:grid;place-items:center;font-size:1.5rem">📊</div>
    <p style="margin:0;font-size:1.1rem;font-weight:700;color:var(--ink)">Your ledger is empty</p>
    <p style="margin:10px auto 0;max-width:380px;font-size:.9rem;color:var(--muted);line-height:1.5">
        Once subscribers pay, transactions appear here automatically.
        For the hackathon demo, seed a month of activity with one click.
    </p>
    <button type="button" class="c-btn c-btn-brand" style="margin-top:24px"
            x-on:click="seedDemoData()"
            x-bind:disabled="loadingSeed">
        <span x-show="!loadingSeed">Simulate 30 days</span>
        <span x-show="loadingSeed" x-cloak style="display:none">Working…</span>
    </button>
</div>
