@extends('layouts.console')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('content')
<div class="stack">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-sub">API credentials, webhooks, and payout recipients for this merchant.</p>
    </div>

@if(!$merchant)
    <div class="c-alert">No merchant is linked to this account.</div>
@else
    <div class="settings">
        <nav class="snav" aria-label="Settings sections">
            <a href="#paypal">PayPal</a><a href="#branding">Company logo</a><a href="#api">API credentials</a><a href="#project">Project features</a><a href="#webhooks">Webhooks</a><a href="#payouts">Payout allow-list</a><a href="#danger">Deactivate</a>
        </nav>

        <div class="sect" style="min-width:0">
            
            
            <section class="c-card" id="paypal">
                <div class="c-head">
                    <div>
                        <h2 class="c-title">PayPal connection</h2>
                        <p class="c-sub">Link Sandbox with a Merchant ID only — no password on this screen. OAuth optional when credentials are configured.</p>
                    </div>
                </div>
                <div class="c-pad stack-sm">
                    @php
                        $ppId = $merchant->paypal_merchant_id ?? null;
                        $ppAt = $merchant->paypal_connected_at ?? null;
                        $ppMode = $merchant->paypal_connect_mode ?? null;
                    @endphp
                    @if($ppId)
                        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px;border:1px solid #a7f3d0;border-radius:12px;background:#ecfdf5">
                            <div>
                                <p style="margin:0;font-weight:700;color:#065f46">Connected</p>
                                <p style="margin:4px 0 0;font-size:.85rem;color:#047857">Merchant ID: <code>{{ \Illuminate\Support\Str::limit($ppId, 24) }}</code>
                                    @if($ppMode) · {{ $ppMode }} @endif
                                    @if($ppAt) · {{ \Illuminate\Support\Carbon::parse($ppAt)->toDayDateTimeString() }} @endif
                                </p>
                            </div>
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Live on record</span>
                        </div>
                    @else
                        <p style="margin:0;font-size:.9rem;color:var(--muted)">Not connected. Paste your PayPal Sandbox <strong>Merchant ID</strong> (from developer.paypal.com → Sandbox → Accounts), or use one-click demo connect.</p>
                    @endif

                    <form method="post" action="{{ route('oauth.paypal.sandbox-connect') }}" class="stack-sm" style="margin-top:12px">
                        @csrf
                        <input type="hidden" name="confirm" value="1" />
                        <div class="field">
                            <label class="label" for="paypal_merchant_id">PayPal Sandbox Merchant ID (optional)</label>
                            <input id="paypal_merchant_id" name="paypal_merchant_id" type="text" class="input" placeholder="e.g. ABCDEFGHIJKLMN" value="{{ old('paypal_merchant_id', $ppId) }}" autocomplete="off" />
                            <p class="help">If empty, we generate a deterministic sandbox demo ID bound to your user. Never type the Sandbox password here.</p>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px">
                            <button type="submit" class="c-btn c-btn-brand">Connect with Merchant ID</button>
                            <a href="{{ route('oauth.paypal.redirect') }}" class="c-btn">Connect via OAuth</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="c-card" id="branding">
                <div class="c-head">
                    <div>
                        <h2 class="c-title">Company logo</h2>
                        <p class="c-sub">Shown in the console sidebar and on customer-facing receipts when available.</p>
                    </div>
                </div>
                <div class="c-pad stack-sm">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:20px">
                        <div style="width:88px;height:88px;border-radius:16px;border:1px solid var(--line);background:var(--bg);display:grid;place-items:center;overflow:hidden;flex-shrink:0">
                            @if(!empty($merchant->logo_path))
                                <img src="{{ asset('storage/'.$merchant->logo_path) }}" alt="{{ $merchant->name }} logo" style="width:100%;height:100%;object-fit:contain" />
                            @else
                                <span style="font-size:.75rem;color:var(--faint);text-align:center;padding:8px">No logo</span>
                            @endif
                        </div>
                        <div style="flex:1;min-width:200px">
                            <form method="post" action="{{ route('settings.logo') }}" enctype="multipart/form-data" class="stack-sm">
                                @csrf
                                <div class="field">
                                    <label class="label" for="logo">Upload image</label>
                                    <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="input" required />
                                    <p class="help">PNG, JPG or WebP · max 1&nbsp;MB · SVG not allowed · square works best</p>
                                </div>
                                <button type="submit" class="c-btn c-btn-brand">Save logo</button>
                            </form>
                            @if(!empty($merchant->logo_path))
                                <form method="post" action="{{ route('settings.logo.remove') }}" style="margin-top:10px" onsubmit="return confirm('Remove company logo?')">
                                    @csrf
                                    <button type="submit" class="c-btn c-btn-sm">Remove logo</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="c-card" id="api">
                <div class="c-head"><div><h2 class="c-title">API credentials</h2><p class="c-sub">Use the secret key only on your server. Never embed it in a browser app.</p></div></div>
                <div class="c-pad stack-sm">
                    <dl class="kv kv-2" style="margin:0">
                        <div><dt>Merchant ID</dt><dd>{{ $merchant->id }}</dd></div>
                        <div><dt>Secret key prefix</dt><dd>{{ $merchant->api_key_prefix }}…</dd></div>
                        <div class="span"><dt>Publishable key</dt><dd>{{ $merchant->publishable_key }}</dd></div>
                    </dl>
                    <x-ui.confirm-form :action="route('settings.rotate')" message="Rotate the secret key? The old key stops working immediately.">
                        <button type="submit" class="c-btn">Rotate secret key</button>
                    </x-ui.confirm-form>
                </div>
            </section>

            <section class="c-card" id="project" x-data="projectFeatures()" x-init="init()">
                <div class="c-head">
                    <div>
                        <h2 class="c-title">Project features</h2>
                        <p class="c-sub">Merchant-controlled options after a missed cancellation grace period. Default is expire — no force.</p>
                    </div>
                </div>
                <div class="c-pad stack-sm">
                    <p style="margin:0;font-size:.88rem;color:var(--muted)">
                        When a user fails to pay, LuvuPay opens a <strong>48-hour grace period</strong> and AI offers a partial refund.
                        If they do nothing, the subscription <strong>expires</strong>. Extra paths only appear when you enable them below.
                    </p>
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
                        <input type="checkbox" x-model="marketplaceResale" style="margin-top:3px;accent-color:var(--brand)" />
                        <span>
                            <strong>Marketplace Resale</strong>
                            <span style="display:block;font-size:.85rem;color:var(--muted);margin-top:2px">Let users sell remaining days on the marketplace after grace.</span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
                        <input type="checkbox" x-model="charitySplit" style="margin-top:3px;accent-color:var(--brand)" />
                        <span>
                            <strong>Charity Split</strong>
                            <span style="display:block;font-size:.85rem;color:var(--muted);margin-top:2px">Let users donate residual subscription value to charity.</span>
                        </span>
                    </label>
                    <div class="field" style="max-width:200px">
                        <label class="label" for="refund-pct">Partial refund offer (%)</label>
                        <input id="refund-pct" type="number" min="0" max="100" class="input" x-model.number="refundPct" />
                    </div>
                    <div style="display:flex;align-items:center;gap:12px">
                        <button type="button" class="c-btn c-btn-brand" x-on:click="save()" x-bind:disabled="saving">
                            <span x-text="saving ? 'Saving…' : 'Save features'"></span>
                        </button>
                        <span x-show="saved" x-cloak style="display:none;font-size:.85rem;color:#067647;font-weight:600">Saved</span>
                        <span x-show="error" x-cloak style="display:none;font-size:.85rem;color:#b42318" x-text="error"></span>
                    </div>
                </div>
            </section>


            <section class="c-card" id="webhooks">
                <div class="c-head"><div><h2 class="c-title">Webhook endpoints</h2><p class="c-sub">Signed events are POSTed to these URLs. URL changes need a confirmation code and a cooling-off period.</p></div></div>
                <div class="c-pad">
                @forelse(($endpoints ?? []) as $ep)
                    <div class="ep">
                        <dl class="kv kv-2" style="margin:0">
                            <div><dt>Endpoint ID</dt><dd>{{ $ep->id }}</dd></div>
                            <div><dt>Status</dt><dd style="font-family:inherit"><span class="tag dot {{ $ep->is_active ? 'ok' : '' }}">{{ $ep->is_active ? 'Active' : 'Inactive' }}</span></dd></div>
                            <div class="span"><dt>Current URL</dt><dd>{{ $ep->url }}</dd></div>
                        </dl>
                        @if($ep->pending_url)
                            <div class="c-alert" style="font-size:.84rem">Pending: <span class="mono">{{ $ep->pending_url }}</span>@if($ep->pending_url_activates_at) <span>Activates {{ $ep->pending_url_activates_at }}</span>@endif</div>
                        @endif
                        <form method="post" action="{{ route('settings.webhook') }}" class="form-row fr-2">
                            @csrf
                            <input type="hidden" name="endpoint_id" value="{{ $ep->id }}">
                            <div class="field"><label class="label" for="url-{{ $ep->id }}">New URL</label><input id="url-{{ $ep->id }}" class="input" type="url" name="url" required placeholder="https://api.yoursite.com/webhooks/luvupay"></div>
                            <button type="submit" class="c-btn c-btn-brand">Request change</button>
                        </form>
                        <form method="post" action="{{ route('settings.webhook.confirm') }}" class="form-row fr-2" style="padding-top:16px;border-top:1px solid var(--line)">
                            @csrf
                            <input type="hidden" name="endpoint_id" value="{{ $ep->id }}">
                            <div class="field"><label class="label" for="code-{{ $ep->id }}">Confirmation code</label><input id="code-{{ $ep->id }}" class="input code-in" style="text-align:left;letter-spacing:.2em" type="text" name="code" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="6 digits"></div>
                            <button type="submit" class="c-btn">Confirm change</button>
                        </form>
                    </div>
                @empty
                    <p class="c-empty" style="border:1px dashed var(--line);border-radius:12px">No webhook endpoint yet. One is created at registration.</p>
                @endforelse
                </div>
            </section>

            <section class="c-card" id="payouts">
                <div class="c-head"><div><h2 class="c-title">Payout allow-list</h2><p class="c-sub">Payouts only go to these emails, after a 24-hour pending period.</p></div></div>
                <form method="post" action="{{ route('settings.payout') }}" class="c-pad form-row fr-3" style="border-bottom:1px solid var(--line)">
                    @csrf
                    <div class="field"><label class="label" for="po-email">PayPal email</label><input id="po-email" class="input" type="email" name="email" required placeholder="paypal@recipient.com"></div>
                    <div class="field"><label class="label" for="po-purpose">Purpose</label>
                        <select id="po-purpose" name="purpose" class="input">@foreach(['charity', 'resale_seller', 'refund', 'general'] as $purpose)<option value="{{ $purpose }}">{{ $purpose }}</option>@endforeach</select>
                    </div>
                    <button type="submit" class="c-btn c-btn-brand">Add recipient</button>
                </form>
                <div style="overflow-x:auto">
                    <table class="c-table">
                        <thead><tr><th>Email</th><th>Purpose</th><th>Status</th><th>Activates</th></tr></thead>
                        <tbody>
                        @forelse(($recipients ?? []) as $r)
                            <tr>
                                <td class="mono">{{ $r->email }}</td>
                                <td>{{ $r->purpose }}</td>
                                <td><span class="tag {{ $r->status === 'active' ? 'ok' : 'warn' }}">{{ $r->status }}</span></td>
                                <td style="color:var(--muted);font-size:.82rem">{{ $r->activates_at }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="c-empty">No recipients yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="c-card c-danger" id="danger">
                <div class="c-head"><div><h2 class="c-title" style="color:#8f1d27">Deactivate merchant</h2><p class="c-sub">Export your data first. After deactivation, data is kept for 90 days and then purged.</p></div></div>
                <div class="c-pad">
                    <x-ui.confirm-form :action="route('settings.deactivate')" message="Deactivate this merchant? This cannot be undone from the console.">
                        <button type="submit" class="c-btn c-btn-bad">Deactivate merchant</button>
                    </x-ui.confirm-form>
                </div>
            </section>
        </div>
    </div>
@endif
</div>

@push('scripts')
<script src="/js/settings-spy.js" defer></script>
<style>
  .snav { position: sticky; top: 80px; align-self: start; }
  .snav a { display: block; padding: 8px 12px; margin-bottom: 2px; border-radius: 8px; color: var(--muted); text-decoration: none; font-size: .875rem; font-weight: 500; }
  .snav a:hover { color: var(--ink); background: var(--bg); }
  .snav a.is-active, .snav a[aria-current="location"] {
    color: #5b3df5 !important; font-weight: 700; background: rgba(91,61,245,.08);
  }
</style>
@endpush

@endsection
