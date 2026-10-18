@extends('layouts.docs')
@section('title', 'API reference · LuvuPay')
@section('meta_description', 'Complete LuvuPay REST API reference: authentication, scopes, subscriptions, Second Chance, resale listings, export, AI endpoints, errors, and rate limits.')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')

<style>
  .apiref { --code-bg:#13171f; --code-fg:#d9e0ec; --code-dim:#7d8aa0; --get:#0f8a5f; --post:#2458d6; --del:#c23a3a; }
  .apiref section { scroll-margin-top: 5.5rem; padding-top: 2.75rem; }
  .apiref section + section { border-top: 1px solid var(--tw-line, rgba(127,127,127,.22)); margin-top: 2.75rem; }
  .apiref h2 { margin-top: 0; }
  .apiref :focus-visible { outline: 2px solid currentColor; outline-offset: 3px; border-radius: 4px; }

  /* code panel */
  .apiref pre { background: var(--code-bg); color: var(--code-fg); border-radius: .75rem; padding: 1rem 1.25rem; font-size: .8125rem; line-height: 1.65; overflow-x: auto; max-width: 100%; }
  .apiref pre code { background: none; color: inherit; padding: 0; }
  .apiref .dim { color: var(--code-dim); }

  /* endpoint block */
  .ep { border: 1px solid rgba(127,127,127,.28); border-radius: .75rem; overflow: hidden; margin: 1.5rem 0; }
  .ep-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; padding: .7rem 1rem; background: rgba(127,127,127,.08); border-bottom: 1px solid rgba(127,127,127,.28); }
  .ep-path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .875rem; font-weight: 600; word-break: break-all; }
  .ep-body { padding: 1rem 1.25rem 1.1rem; }
  .ep-body > :first-child { margin-top: 0; }
  .ep-body > :last-child { margin-bottom: 0; }
  .ep-body h3 { margin-top: 0; }
  .method { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; font-weight: 700; letter-spacing: .02em; color: #fff; padding: .2rem .55rem; border-radius: .35rem; }
  .m-get { background: var(--get); } .m-post { background: var(--post); } .m-del { background: var(--del); }
  .chip { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .6875rem; padding: .15rem .5rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.4); }
  .chip.idem { border-style: dashed; }
  .ep-spacer { margin-left: auto; display: flex; gap: .4rem; flex-wrap: wrap; }

  /* facts strip */
  .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); border: 1px solid rgba(127,127,127,.28); border-radius: .75rem; overflow: hidden; }
  .facts > div { padding: .85rem 1.1rem; border-right: 1px solid rgba(127,127,127,.28); }
  .facts > div:last-child { border-right: 0; }
  .facts dt { font-size: .8125rem; opacity: .7; }
  .facts dd { margin: .15rem 0 0; font-weight: 600; }
  .baseurl { display: flex; align-items: center; gap: .75rem; margin-top: 1rem; background: var(--code-bg); color: var(--code-fg); border-radius: .75rem; padding: .7rem .7rem .7rem 1.1rem; }
  .baseurl code { flex: 1; font-size: .875rem; word-break: break-all; background: none; color: inherit; padding: 0; }
  .baseurl button { font-size: .8125rem; font-weight: 600; padding: .35rem .8rem; border-radius: .5rem; background: #fff; color: #13171f; border: 0; cursor: pointer; }

  /* lanes: environments */
  .lanes { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); border-left: 3px solid rgba(127,127,127,.28); margin: 1.25rem 0; }
  .lanes > div { padding: .25rem 1.25rem; }
  .lanes > div + div { border-left: 1px solid rgba(127,127,127,.28); }
  .lanes strong { display: block; margin-bottom: .2rem; }
  .lanes code { font-size: .75rem; }

  /* tables */
  .tbl { width: 100%; border-collapse: collapse; font-size: .875rem; }
  .tbl th { text-align: left; font-weight: 600; padding: .55rem 1rem .55rem 0; border-bottom: 2px solid rgba(127,127,127,.4); }
  .tbl td { padding: .6rem 1rem .6rem 0; border-bottom: 1px solid rgba(127,127,127,.22); vertical-align: top; }
  .tbl td:first-child { white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; }
  .scroll-x { overflow-x: auto; max-width: 100%; }

  /* toc */
  .toc a { display: block; padding: .3rem 0 .3rem .9rem; font-size: .875rem; border-left: 2px solid rgba(127,127,127,.28); opacity: .75; text-decoration: none; }
  .toc a:hover { opacity: 1; }
  .toc a[aria-current="true"] { opacity: 1; font-weight: 600; border-left-color: currentColor; }
  .toc .grp { font-size: .8125rem; font-weight: 600; margin: 1rem 0 .3rem; }
</style>

<div class="apiref mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
  <div class="xl:grid xl:grid-cols-[minmax(0,48rem)_13rem] xl:gap-12 xl:justify-center">

    <article class="min-w-0 w-full max-w-3xl mx-auto">
      <h1>API reference</h1>
      <p class="lead text-muted">Everything a merchant server can call. Use a secret API key, send an <code>Idempotency-Key</code> on every request that moves money, and quote the <code>X-Request-Id</code> from any response when you contact support.</p>

      <dl class="facts not-prose mt-6">
        <div><dt>Version</dt><dd>v1</dd></div>
        <div><dt>Default rate limit</dt><dd>300 requests/min</dd></div>
        <div><dt>AI endpoints</dt><dd>100 requests/min</dd></div>
      </dl>
      <div class="baseurl not-prose">
        <code id="base-url">{{ rtrim(config('app.url'), '/') }}/api/v1</code>
        <button type="button" id="copy-base" aria-label="Copy base URL">Copy</button>
      </div>

      {{-- AUTH --}}
      <section id="auth">
        <h2>Authentication</h2>
        <p>Send your <strong>secret</strong> key as a Bearer token. Publishable keys are only for the browser badge and snippet. Never put a secret key in a frontend bundle.</p>
<pre><code><span class="dim">Authorization:</span>  Bearer lp_live_xxxxxxxxxxxxxxxx
<span class="dim">Idempotency-Key:</span> 550e8400-e29b-41d4-a716-446655440000
<span class="dim">Content-Type:</span>    application/json
<span class="dim">Accept:</span>          application/json</code></pre>
        <h3>Idempotency</h3>
        <p>Required on every POST and DELETE that moves money. Keys are 8 to 255 characters.</p>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>You send</th><th>You get</th></tr></thead>
            <tbody>
              <tr><td>Same key, same body</td><td>The cached response from the first call</td></tr>
              <tr><td>Same key, different body</td><td><code>422 idempotency_key_reused</code></td></tr>
              <tr><td>Same key while the first call is running</td><td><code>409 request_in_progress</code></td></tr>
            </tbody>
          </table>
        </div>
        <p>Rotate keys in Console, under Settings. Old keys stop working once rotation completes.</p>
      </section>

      {{-- SCOPES --}}
      <section id="scopes">
        <h2>Scopes</h2>
        <p>Every key is limited to explicit scopes, set when you create or rotate it. A call without the right scope returns <code>403 insufficient_scope</code>.</p>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Scope</th><th>Allows</th></tr></thead>
            <tbody>
              <tr><td>subscriptions:write</td><td>Create subscription</td></tr>
              <tr><td>second-chance:write</td><td>Trigger Second Chance</td></tr>
              <tr><td>resale:read</td><td>List resale listings</td></tr>
              <tr><td>resale:write</td><td>Create, cancel, check out and complete listings</td></tr>
              <tr><td>export:read</td><td>Data export</td></tr>
              <tr><td>snippet:read</td><td>Embed snippet</td></tr>
              <tr><td>ai:read</td><td>AI intelligence endpoints</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      {{-- ENVIRONMENTS --}}
      <section id="environments">
        <h2>Environments</h2>
        <p>Build in this order. Each step uses the same API shape.</p>
        <div class="lanes not-prose">
          <div><strong>Mock</strong><p class="text-sm text-muted">No external PayPal calls. Good for UI and webhook shape work.</p><code>LUVUPAY_PAYMENT_DRIVER=mock</code></div>
          <div><strong>Sandbox</strong><p class="text-sm text-muted">PayPal sandbox credentials. Real API shape, test money. See <a href="/docs/sandbox">Sandbox</a>.</p></div>
          <div><strong>Live</strong><p class="text-sm text-muted">Production PayPal, after sandbox sign-off.</p></div>
        </div>
      </section>

      {{-- RATE LIMITS --}}
      <section id="rate-limits">
        <h2>Rate limits</h2>
        <p>The per-merchant limit is set with <code>LUVUPAY_API_RATE_LIMIT</code> and defaults to 300 requests per minute. AI endpoints have a tighter limit of 100 per minute. Going over returns <code>429</code> with standard rate-limit headers.</p>
      </section>

      {{-- SUBSCRIPTIONS --}}
      <section id="subscriptions">
        <h2>Subscriptions</h2>
        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/subscriptions</span>
            <span class="ep-spacer"><span class="chip">subscriptions:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body">
            <h3>Create subscription</h3>
<pre><code>{
  "merchant_user_id": "user_123",
  "email": "customer@example.com",
  "plan_id": "pro_monthly",
  "amount_minor": 2000,
  "currency": "USD",
  "billing_interval": "month",
  "return_url": "https://app.example.com/billing/ok",
  "cancel_url": "https://app.example.com/billing/cancel"
}</code></pre>
            <p><strong>201</strong> returns the subscription object with provider references and status. Keep the returned IDs for Second Chance and resale.</p>
          </div>
        </div>
      </section>

      {{-- SECOND CHANCE --}}
      <section id="second-chance">
        <h2>Second Chance</h2>
        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/transactions/{transaction}/second-chance</span>
            <span class="ep-spacer"><span class="chip">second-chance:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body">
            <p>Starts the fair-split flow between user, charity and merchant, inside the configured window (48 hours by default). Returns <code>409</code> when the transaction is not eligible or the window has closed.</p>
            <p>The merchant's original capture is partially clawed back under the platform split rules. See the product docs for the details.</p>
          </div>
        </div>
      </section>

      {{-- RESALE --}}
      <section id="resale">
        <h2>Resale listings</h2>
        <p>A listing sells the remaining access days of the <strong>current</strong> billing cycle. The merchant's original cycle charge is <strong>not</strong> refunded. See <a href="/docs/resale-money">Resale money flow</a>.</p>

        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-get">GET</span><span class="ep-path">/api/v1/resale-listings</span>
            <span class="ep-spacer"><span class="chip">resale:read</span><span class="chip">resale:write</span></span>
          </div>
          <div class="ep-body"><h3>List listings</h3><p>Either scope works.</p></div>
        </div>

        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/resale-listings</span>
            <span class="ep-spacer"><span class="chip">resale:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body">
            <h3>Create listing</h3>
<pre><code>{
  "subscription_id": "uuid",
  "asking_price_minor": 1000,
  "currency": "USD"
}</code></pre>
          </div>
        </div>

        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-del">DELETE</span><span class="ep-path">/api/v1/resale-listings/{listing}</span>
            <span class="ep-spacer"><span class="chip">resale:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body"><h3>Cancel listing</h3></div>
        </div>

        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/resale-listings/{listing}/checkout</span>
            <span class="ep-spacer"><span class="chip">resale:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body">
            <h3>Check out</h3>
<pre><code>{
  "buyer_merchant_user_id": "user_456",
  "buyer_email": "buyer@example.com"
}</code></pre>
            <p>Reserves the listing and creates a PayPal order for the buyer.</p>
          </div>
        </div>

        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/resale-listings/{listing}/complete</span>
            <span class="ep-spacer"><span class="chip">resale:write</span><span class="chip idem">Idempotency-Key</span></span>
          </div>
          <div class="ep-body">
            <h3>Complete sale</h3>
            <p>Captures payment and pays the seller, minus the platform fee. It then sends an entitlement transfer webhook. On that webhook, revoke the seller and grant the buyer access until <code>access_expires_at</code>.</p>
          </div>
        </div>
      </section>

      {{-- EXPORT --}}
      <section id="export">
        <h2>Export</h2>
        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-get">GET</span><span class="ep-path">/api/v1/export</span>
            <span class="ep-spacer"><span class="chip">export:read</span></span>
          </div>
          <div class="ep-body"><p>Returns your merchant-scoped operational data, suited to offboarding or compliance exports.</p></div>
        </div>
      </section>

      {{-- AI --}}
      <section id="ai">
        <h2>AI endpoints</h2>
        <p>Optional. Requires Nebius (OpenAI-compatible) configuration. All four share the <span class="chip">ai:read</span> scope and the 100 per minute limit. See <a href="/docs/ai">AI layer</a>.</p>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Endpoint</th><th>Notes</th></tr></thead>
            <tbody>
              <tr><td><span class="method m-post">POST</span> /api/v1/ai/churn-prediction</td><td></td></tr>
              <tr><td><span class="method m-post">POST</span> /api/v1/ai/resale-price-suggestion</td><td></td></tr>
              <tr><td><span class="method m-get">GET</span> /api/v1/ai/subscription-health-report</td><td>Query parameter: <code>email</code></td></tr>
              <tr><td><span class="method m-get">GET</span> /api/v1/ai/job-result</td><td></td></tr>
            </tbody>
          </table>
        </div>
      </section>

      {{-- PAYPAL INGRESS --}}
      <section id="paypal-ingress">
        <h2>PayPal webhooks (ingress)</h2>
        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-post">POST</span><span class="ep-path">/api/v1/webhooks/paypal</span>
            <span class="ep-spacer"><span class="chip">no API key</span></span>
          </div>
          <div class="ep-body"><p>A public, signature-verified endpoint that you configure in the PayPal dashboard. Events are stored and processed asynchronously. It does not use merchant API keys.</p></div>
        </div>
      </section>

      {{-- BADGE --}}
      <section id="badge">
        <h2>Public badge</h2>
        <div class="ep not-prose">
          <div class="ep-head">
            <span class="method m-get">GET</span><span class="ep-path">/api/v1/badge.js</span>
            <span class="ep-spacer"><span class="chip">publishable key</span></span>
          </div>
          <div class="ep-body"><p>A publishable snippet for the ethical directory and badge embeds. No secret key needed.</p></div>
        </div>
      </section>

      {{-- ERRORS --}}
      <section id="errors">
        <h2>Errors</h2>
        <p>Errors return JSON with a stable <code>message</code> and, where useful, a <code>code</code>.</p>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
            <tbody>
              <tr><td>401</td><td>Missing or invalid API key</td></tr>
              <tr><td>403</td><td>Key lacks the required scope (<code>insufficient_scope</code>)</td></tr>
              <tr><td>409</td><td>Conflict, such as <code>request_in_progress</code> or an ineligible Second Chance</td></tr>
              <tr><td>422</td><td>Validation failed, or <code>idempotency_key_reused</code></td></tr>
              <tr><td>429</td><td>Rate limit exceeded</td></tr>
            </tbody>
          </table>
        </div>
        <p>The full list is in <a href="/docs/errors">Errors</a>.</p>
      </section>

      {{-- VERSIONING --}}
      <section id="versioning">
        <h2>Versioning</h2>
        <p>The current surface is <code>/api/v1</code>. Breaking changes ship under a new prefix, and each one is announced in these docs and in the release notes.</p>
        <p class="not-prose flex flex-wrap gap-x-6 gap-y-2 text-sm mt-6">
          <a href="/docs/webhooks" class="text-brand-600 font-medium">Webhooks</a>
          <a href="/docs/sandbox" class="text-brand-600 font-medium">Sandbox</a>
          <a href="/docs/cookbook" class="text-brand-600 font-medium">Cookbook</a>
          <a href="/docs/resale-money" class="text-brand-600 font-medium">Resale money</a>
        </p>
      </section>
    </article>

    {{-- On this page --}}
    <aside class="hidden xl:block">
      <nav class="toc not-prose sticky top-24" aria-label="On this page">
        <div class="grp" style="margin-top:0">Basics</div>
        <a href="#auth">Authentication</a>
        <a href="#scopes">Scopes</a>
        <a href="#environments">Environments</a>
        <a href="#rate-limits">Rate limits</a>
        <div class="grp">Endpoints</div>
        <a href="#subscriptions">Subscriptions</a>
        <a href="#second-chance">Second Chance</a>
        <a href="#resale">Resale listings</a>
        <a href="#export">Export</a>
        <a href="#ai">AI endpoints</a>
        <a href="#paypal-ingress">PayPal webhooks</a>
        <a href="#badge">Public badge</a>
        <div class="grp">Reference</div>
        <a href="#errors">Errors</a>
        <a href="#versioning">Versioning</a>
      </nav>
    </aside>
  </div>
</div>

<script>
  (function () {
    var btn = document.getElementById('copy-base');
    var url = document.getElementById('base-url');
    if (btn && url && navigator.clipboard) {
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(url.textContent.trim()).then(function () {
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
        });
      });
    }
    var links = document.querySelectorAll('.toc a');
    if ('IntersectionObserver' in window && links.length) {
      var map = {};
      links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting && map[e.target.id]) {
            links.forEach(function (a) { a.removeAttribute('aria-current'); });
            map[e.target.id].setAttribute('aria-current', 'true');
          }
        });
      }, { rootMargin: '-20% 0px -70% 0px' });
      Object.keys(map).forEach(function (id) {
        var s = document.getElementById(id);
        if (s) io.observe(s);
      });
    }
  })();
</script>
@endsection