{{-- New Subscription Checkout. Alpine state/logic lives in public/js/dashboard.js (openCheckout, confirmCheckoutBatch, ...). --}}
<link rel="stylesheet" href="/css/checkout-modal.css?v={{ @filemtime(public_path('css/checkout-modal.css')) }}">

<div class="cm-root" x-show="showCheckoutModal" x-cloak style="display:none"
     role="dialog" aria-modal="true" aria-labelledby="cm-title"
     x-on:keydown.escape.window="showCheckoutModal && closeCheckout()">
    <div class="cm-backdrop" x-on:click="closeCheckout()"></div>

    <div class="cm-panel" x-on:click.stop>
        <header class="cm-head">
            <div class="cm-badge-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19"/><path d="M6.5 15h4"/></svg>
            </div>
            <div class="cm-title">
                <h2 id="cm-title">New subscription checkout</h2>
                <p>
                    <template x-if="checkoutMode === 'paypal'"><span>Each subscriber approves the payment once on PayPal. After PayPal confirms, the subscription turns active and the invoice is emailed</span></template>
                    <template x-if="checkoutMode !== 'paypal'"><span>Simulated checkout: subscribers are charged instantly, with no call to PayPal</span></template>
                </p>
            </div>
            <button type="button" class="cm-x" x-on:click="closeCheckout()" x-bind:disabled="checkoutPaying" aria-label="Close">&times;</button>
        </header>

        <div class="cm-body">
            <section class="cm-plan" aria-label="Plan">
                <div class="cm-plan-name">
                    <small>Plan</small>
                    <b>{{ $project->product() ?: 'Your plan' }}</b>
                </div>
                <div class="cm-plan-price">{{ $project->price() }} <span>/ {{ $project->interval === 'month' ? 'month' : ($project->interval ?? 'month') }}</span></div>
                <span class="cm-mode" x-bind:class="checkoutMode === 'paypal' ? 'cm-mode--paypal' : 'cm-mode--mock'">
                    <i></i>
                    <span x-text="checkoutMode === 'paypal' ? ('PayPal ' + (checkoutEnv === 'live' ? 'Live' : 'Sandbox')) : 'Mock · instant'"></span>
                </span>
            </section>

            <ol class="cm-steps" x-show="checkoutMode === 'paypal'" x-cloak>
                <li><b>1</b><span>Approve on PayPal in a popup</span></li>
                <li><b>2</b><span>PayPal confirms the payment</span></li>
                <li><b>3</b><span>Subscription active, invoice emailed</span></li>
            </ol>

            <div class="cm-list-head">
                <h3>Subscribers</h3>
                <span><span x-text="checkoutSelectedCount"></span> selected</span>
            </div>

            <ul class="cm-list">
                <template x-for="(row, idx) in checkoutRows" x-bind:key="idx">
                    <li class="cm-row"
                        x-bind:class="{ 'is-selected': row.selected && row.status === 'idle', 'is-done': row.status === 'done', 'is-failed': row.status === 'failed', 'is-awaiting': row.status === 'awaiting', 'is-working': row.status === 'working' }">
                        <input type="checkbox" class="cm-check" x-model="row.selected" aria-label="Include subscriber"
                               x-bind:disabled="checkoutPaying || row.status === 'done' || row.status === 'awaiting'">
                        <div class="cm-avatar" aria-hidden="true" x-text="(row.name || '?').charAt(0).toUpperCase()"></div>
                        <div class="cm-fields">
                            <input type="text" class="cm-input" placeholder="Name" aria-label="Name" maxlength="120" x-model="row.name"
                                   x-bind:disabled="checkoutPaying || row.status === 'done' || row.status === 'awaiting'">
                            <input type="email" class="cm-input" placeholder="email@example.com" aria-label="Email" maxlength="190" x-model="row.email"
                                   x-bind:disabled="checkoutPaying || row.status === 'done' || row.status === 'awaiting'">
                        </div>
                        <div class="cm-row-side">
                            <span class="cm-amount" x-text="row.amount"></span>
                            <span class="cm-chip" x-bind:class="'cm-chip--' + row.status">
                                <span class="cm-spin" x-show="row.status === 'working' || row.status === 'awaiting'" x-cloak></span>
                                <span x-text="({ idle: 'Ready', working: 'Starting…', awaiting: 'Waiting for PayPal', done: (checkoutMode === 'paypal' ? 'Paid' : 'Active'), failed: 'Needs attention' })[row.status] || 'Ready'"></span>
                            </span>
                        </div>
                        <div class="cm-note" x-show="row.note" x-cloak>
                            <span x-text="row.note"></span>
                            <button type="button" class="cm-remove" x-show="row.status === 'idle' || row.status === 'failed'" x-on:click="removeCheckoutRow(idx)" x-bind:disabled="checkoutPaying">Remove</button>
                        </div>
                    </li>
                </template>
            </ul>

            <button type="button" class="cm-add" x-on:click="addCheckoutRow()" x-bind:disabled="checkoutPaying">+ Add another subscriber</button>

            <p class="cm-hint cm-hint--warn" x-show="checkoutMode === 'paypal'" x-cloak>
                <b>One approval per subscriber</b> PayPal never moves money without the buyer's consent, even in the sandbox. Log in with a
                <b>sandbox personal (buyer)</b> account in the popup; the funds land in your sandbox <b>business</b> balance.
                Allow popups for this site, or you will be redirected to PayPal instead
            </p>
        </div>

        <footer class="cm-foot">
            <div class="cm-total">
                <small>Due now</small>
                <b x-text="checkoutTotalLabel"></b>
                <span><span x-text="checkoutRemaining"></span> subscriber<span x-show="checkoutRemaining !== 1">s</span> left</span>
            </div>
            <div class="cm-actions">
                <button type="button" class="cm-btn" x-on:click="closeCheckout()" x-bind:disabled="checkoutPaying"
                        x-text="checkoutAllDone ? 'Close' : 'Cancel'"></button>
                <button type="button" class="cm-btn"
                        x-bind:class="checkoutMode === 'paypal' && !checkoutAllDone ? 'cm-btn--paypal' : 'cm-btn--primary'"
                        x-on:click="confirmCheckoutBatch()"
                        x-bind:disabled="checkoutPaying || checkoutSelectedCount === 0">
                    <span class="cm-spin" x-show="checkoutPaying" x-cloak></span><span x-text="checkoutCta"></span>
                </button>
            </div>
        </footer>
    </div>
</div>
