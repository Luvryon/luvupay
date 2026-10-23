@extends('layouts.console')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('content')
@php
    $project = $project ?? \App\Support\MerchantProject::current();
    $ppId = $merchant->paypal_merchant_id ?? null;
    $ppAt = $merchant->paypal_connected_at ?? null;
    $logoUrl = !empty($merchant->logo_path) ? asset('storage/'.$merchant->logo_path) : null;
@endphp
<div class="stack">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-sub">API credentials, webhooks, branding, and payout recipients.</p>
    </div>

    @if(!$merchant)
        <div class="c-alert">No merchant is linked to this account.</div>
    @else
    <div class="settings">
        <nav class="snav" aria-label="Settings sections">
            <a href="#paypal">PayPal</a>
            <a href="#branding">Company logo</a>
            <a href="#api">API credentials</a>
            <a href="#project">Project features</a>
            <a href="#webhooks">Webhooks</a>
            <a href="#payouts">Payout allow-list</a>
            <a href="#danger">Deactivate</a>
        </nav>

        <div class="sect" style="min-width:0">
            <section class="c-card" id="paypal">
                <div class="c-head">
                    <div>
                        <h2 class="c-title">PayPal connection</h2>
                        <p class="c-sub">Link Sandbox with a Merchant ID — no password on this screen.</p>
                    </div>
                </div>
                <div class="c-pad stack-sm">
                    @if($ppId)
                        <p style="margin:0;font-size:.9rem">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0f8f5f;margin-right:6px"></span>
                            Connected · <code>{{ \Illuminate\Support\Str::limit($ppId, 16) }}</code>
                            @if($ppAt)<span class="dim"> · {{ \Illuminate\Support\Carbon::parse($ppAt)->toDayDateTimeString() }}</span>@endif
                        </p>
                    @else
                        <p class="dim" style="margin:0">Not connected. Paste your PayPal Sandbox Merchant ID from developer.paypal.com.</p>
                    @endif
                    <form method="post" action="{{ url('/oauth/paypal/sandbox-connect') }}" class="stack-sm" style="margin-top:12px">
                        @csrf
                        <div class="field">
                            <label class="label" for="paypal_merchant_id">PayPal Sandbox Merchant ID</label>
                            <input id="paypal_merchant_id" name="paypal_merchant_id" type="text" class="input"
                                   placeholder="e.g. ABCDEFGHIJKLMN" value="{{ old('paypal_merchant_id', $ppId) }}" autocomplete="off" />
                        </div>
                        <div style="display:flex;flex-wrap:gap;gap:8px">
                            <button type="submit" class="c-btn c-btn-brand">Save connection</button>
                            <a href="{{ url('/oauth/paypal/redirect') }}" class="c-btn">Connect via OAuth</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="c-card" id="branding">
                <div class="c-head"><div><h2 class="c-title">Company logo</h2><p class="c-sub">Shown on invoices and emails.</p></div></div>
                <div class="c-pad">
                    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                        <div data-logo-box style="width:72px;height:72px;border-radius:12px;border:1px solid var(--line);background:#f8fafc;display:grid;place-items:center;overflow:hidden">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain">
                            @else
                                <span class="dim" style="font-size:.75rem">No logo</span>
                            @endif
                        </div>
                        <div class="stack-sm">
                            <form id="logo-upload-form" class="stack-sm" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="logo" id="logo-file" accept="image/png,image/jpeg,image/webp" class="input" required>
                                <button type="submit" class="c-btn c-btn-brand" id="logo-upload-btn">Upload logo</button>
                                <p class="help" id="logo-upload-hint">PNG, JPEG, or WebP · max 2 MB · no SVG</p>
                            </form>
                            <script>
                            (function () {
                              var form = document.getElementById('logo-upload-form');
                              if (!form) return;
                              form.addEventListener('submit', function (e) {
                                e.preventDefault();
                                var btn = document.getElementById('logo-upload-btn');
                                var fileInput = document.getElementById('logo-file');
                                if (!fileInput.files || !fileInput.files[0]) {
                                  if (window.LuvuToast) LuvuToast.warning('Choose a file', 'Select a PNG, JPEG, or WebP logo first.');
                                  return;
                                }
                                var fd = new FormData(form);
                                if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
                                fetch(@json(route('settings.logo')), {
                                  method: 'POST',
                                  credentials: 'same-origin',
                                  headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                                  },
                                  body: fd
                                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
                                  .then(function (res) {
                                    if (res.ok && res.j && res.j.ok) {
                                      if (window.LuvuToast) LuvuToast.success('Logo updated', res.j.message || 'Saved');
                                      if (res.j.logo_url) {
                                        var img = document.querySelector('#branding img');
                                        var box = document.querySelector('#branding [data-logo-box]');
                                        if (img) { img.src = res.j.logo_url; }
                                        else if (box) {
                                          while (box.firstChild) box.removeChild(box.firstChild);
                                          var n = document.createElement('img');
                                          n.src = res.j.logo_url;
                                          n.alt = 'Logo';
                                          n.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain';
                                          box.appendChild(n);
                                        }
                                      }
                                    } else {
                                      var msg = (res.j && (res.j.message || res.j.error)) || ('HTTP ' + res.status);
                                      if (window.LuvuToast) LuvuToast.error('Upload failed', msg);
                                    }
                                  })
                                  .catch(function (err) {
                                    if (window.LuvuToast) LuvuToast.error('Upload failed', String(err && err.message || err));
                                  })
                                  .finally(function () {
                                    if (btn) { btn.disabled = false; btn.textContent = 'Upload logo'; }
                                  });
                              });
                            })();
                            </script>
                            @if($logoUrl)
                                <form method="post" action="{{ route('settings.logo.remove') }}" onsubmit="return confirm('Remove company logo?')">
                                    @csrf
                                    <button type="submit" class="c-btn c-btn-sm">Remove logo</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="c-card" id="api">
                <div class="c-head"><div><h2 class="c-title">API credentials</h2><p class="c-sub">Secret key stays on your server only.</p></div></div>
                <div class="c-pad stack-sm">
                    <dl class="kv kv-2" style="margin:0">
                        <div><dt>Merchant ID</dt><dd style="word-break:break-all">{{ $merchant->id }}</dd></div>
                        <div><dt>Secret key prefix</dt><dd>{{ $merchant->api_key_prefix ?? '—' }}…</dd></div>
                        <div class="span"><dt>Publishable key</dt><dd style="word-break:break-all">{{ $merchant->publishable_key ?? '—' }}</dd></div>
                    </dl>
                    <form method="post" action="{{ route('settings.rotate') }}" data-confirm="Rotate the secret key? The old key stops working immediately.">
                        @csrf
                        <button type="submit" class="c-btn">Rotate secret key</button>
                    </form>
                </div>
            </section>

            <script type="application/json" id="project-features-seed">{!! json_encode([
                'marketplace_resale' => (bool) ($project->marketplaceResale ?? false),
                'charity_split' => (bool) ($project->charitySplit ?? false),
                'partial_refund_bps' => (int) ($project->partialRefundBps ?? 2000),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            <section class="c-card" id="project" x-data="projectFeatures()" x-init="init()">
                <div class="c-head">
                    <div>
                        <h2 class="c-title">Project features</h2>
                        <p class="c-sub">Options after a missed-cancellation grace period. Default is expire — no force.</p>
                    </div>
                </div>
                <div class="c-pad stack-sm">
                    <p class="dim" style="margin:0;font-size:.88rem">Merchant control: decide which retention paths are available after a failed payment.</p>
                    <label style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
                        <input type="checkbox" x-model="gracePeriod" style="margin-top:3px;accent-color:var(--brand)">
                        <span><strong style="font-size:.9rem">Enable 48-Hour Grace Period</strong><span class="dim" style="display:block;font-size:.82rem;margin-top:2px">Hold the subscription for 48 hours and offer partial refund / store credit.</span></span>
                    </label>
                    <label style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
                        <input type="checkbox" x-model="marketplaceResale" style="margin-top:3px;accent-color:var(--brand)">
                        <span><strong style="font-size:.9rem">Marketplace Resale</strong><span class="dim" style="display:block;font-size:.82rem;margin-top:2px">Users can sell remaining days after grace.</span></span>
                    </label>
                    <label style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer">
                        <input type="checkbox" x-model="charitySplit" style="margin-top:3px;accent-color:var(--brand)">
                        <span><strong style="font-size:.9rem">Charity Split</strong><span class="dim" style="display:block;font-size:.82rem;margin-top:2px">Users can donate residual subscription value.</span></span>
                    </label>
                    <div class="field" x-show="charitySplit" x-cloak>
                        <label class="label" for="charity-partner">Charity partner</label>
                        <select id="charity-partner" class="input" x-model="charityPartner">
                            <option value="red_cross">American Red Cross</option>
                            <option value="unicef">UNICEF</option>
                            <option value="doctors">Doctors Without Borders</option>
                            <option value="wwf">WWF</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="refund-pct">Partial refund offer (%)</label>
                        <input id="refund-pct" class="input" type="number" min="0" max="100" x-model.number="refundPct">
                    </div>
                    <div style="display:flex;align-items:center;gap:12px">
                        <button type="button" class="c-btn c-btn-brand" x-on:click="save()" x-bind:disabled="saving">
                            <span x-text="saving ? 'Saving…' : 'Save features'"></span>
                        </button>
                        <span x-show="saved" x-cloak class="dim" style="color:#0f8f5f">Saved</span>
                        <span x-show="error" x-cloak style="color:#b4232f;font-size:.85rem" x-text="error"></span>
                    </div>
                </div>
            </section>

            <section class="c-card" id="webhooks">
                <div class="c-head"><div><h2 class="c-title">Webhooks</h2><p class="c-sub">HTTPS endpoints for event delivery.</p></div></div>
                <div class="c-pad stack-sm">
                    @if($endpoints->isNotEmpty())
                        <ul style="margin:0;padding:0;list-style:none">
                            @foreach($endpoints as $ep)
                                <li style="padding:10px 0;border-bottom:1px solid var(--line);font-size:.88rem">
                                    <code>{{ $ep->url ?? $ep->target_url ?? '—' }}</code>
                                    <span class="dim"> · {{ $ep->status ?? 'pending' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="dim" style="margin:0">No webhook endpoints yet.</p>
                    @endif
                    <form method="post" action="{{ route('settings.webhook') }}" class="form-row fr-2">
                        @csrf
                        <div class="field"><label class="label" for="wh-url">Endpoint URL</label><input id="wh-url" class="input" type="url" name="url" required placeholder="https://example.com/webhooks/luvupay"></div>
                        <button type="submit" class="c-btn c-btn-brand">Request change</button>
                    </form>
                </div>
            </section>

            <section class="c-card" id="payouts">
                <div class="c-head"><div><h2 class="c-title">Payout allow-list</h2><p class="c-sub">PayPal emails that may receive withdrawals.</p></div></div>
                <form method="post" action="{{ route('settings.payout') }}" class="c-pad form-row fr-3" style="border-bottom:1px solid var(--line)">
                    @csrf
                    <div class="field"><label class="label" for="po-email">PayPal email</label><input id="po-email" class="input" type="email" name="email" required placeholder="paypal@recipient.com"></div>
                    <div class="field">
                        <label class="label" for="po-purpose">Purpose</label>
                        <select id="po-purpose" name="purpose" class="input">
                            @foreach(['charity','resale_seller','refund','general'] as $purpose)
                                <option value="{{ $purpose }}">{{ $purpose }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="c-btn c-btn-brand">Add recipient</button>
                </form>
                <div class="c-pad">
                    @if($recipients->isEmpty())
                        <p class="dim" style="margin:0">No recipients yet.</p>
                    @else
                        <ul style="margin:0;padding:0;list-style:none">
                            @foreach($recipients as $r)
                                <li style="padding:8px 0;border-bottom:1px solid var(--line);font-size:.88rem">
                                    {{ $r->email ?? '—' }} <span class="dim">· {{ $r->purpose ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="c-card" id="danger">
                <div class="c-head"><div><h2 class="c-title">Deactivate</h2><p class="c-sub">Stops API access for this merchant.</p></div></div>
                <div class="c-pad">
                    <form method="post" action="{{ route('settings.deactivate') }}" data-confirm="Deactivate this merchant? This cannot be undone from the console.">
                        @csrf
                        <button type="submit" class="c-btn c-btn-bad">Deactivate merchant</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="/js/project-features.js" defer></script>
<script src="/js/settings-spy.js" defer></script>
<style>
  .snav { position: sticky; top: 80px; align-self: start; }
  .snav a { display: block; padding: 8px 12px; margin-bottom: 2px; border-radius: 8px; color: var(--muted); text-decoration: none; font-size: .875rem; font-weight: 500; }
  .snav a:hover { color: var(--ink); background: var(--bg); }
  .snav a.is-active { color: #5b3df5 !important; font-weight: 700; background: rgba(91,61,245,.08); }
</style>
@endpush
