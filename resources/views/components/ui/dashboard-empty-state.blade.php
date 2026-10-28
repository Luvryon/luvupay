{{-- Ledger loading and empty states for the Overview page --}}
<div class="c-card" x-show="loading && !hasData" x-cloak>
    <x-ui.loading show="true" title="Loading ledger" hint="Fetching live transactions" />
</div>

<div x-show="loaded && !loading && !hasData" x-cloak class="c-card" style="padding:48px 32px;text-align:center">
    <div style="margin:0 auto 16px;width:56px;height:56px;border-radius:16px;background:var(--brand-50);color:var(--brand);display:grid;place-items:center">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
    </div>
    <p style="margin:0;font-size:1.1rem;font-weight:700;color:var(--ink)">Your ledger is empty</p>
    <p style="margin:10px auto 0;max-width:400px;font-size:.9rem;color:var(--muted);line-height:1.5">
        Once subscribers pay, transactions appear here automatically
        Start with a new subscription checkout to see the full lifecycle
    </p>
    <div style="margin-top:24px;display:flex;justify-content:center">
        <button type="button" class="c-btn c-btn-brand" x-on:click.prevent="openCheckout()" x-bind:disabled="simBusy || checkoutPaying">New subscription checkout</button>
    </div>
</div>
