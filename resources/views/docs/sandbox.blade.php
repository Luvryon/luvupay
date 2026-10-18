@extends('layouts.docs')
@section('title', 'Sandbox & environments · LuvuPay')
@section('meta_description', 'Configure LuvuPay mock, PayPal sandbox, and live environments. Env vars, verification commands, recommended rollout order, and local merchant samples.')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')

<style>
  .sbdoc { --code-bg:#13171f; --code-fg:#d9e0ec; --code-dim:#7d8aa0; --get:#0f8a5f; }
  .sbdoc section { scroll-margin-top: 5.5rem; padding-top: 2.75rem; }
  .sbdoc section + section { border-top: 1px solid rgba(127,127,127,.22); margin-top: 2.75rem; }
  .sbdoc h2 { margin-top: 0; }
  .sbdoc :focus-visible { outline: 2px solid currentColor; outline-offset: 3px; border-radius: 4px; }

  .sbdoc pre { background: var(--code-bg); color: var(--code-fg); border-radius: .75rem; padding: 1rem 1.25rem; font-size: .8125rem; line-height: 1.65; overflow-x: auto; max-width: 100%; }
  .sbdoc pre code { background: none; color: inherit; padding: 0; }
  .sbdoc .dim { color: var(--code-dim); }
  .sbdoc .prompt::before { content: "$ "; color: var(--code-dim); }

  /* facts strip */
  .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); border: 1px solid rgba(127,127,127,.28); border-radius: .75rem; overflow: hidden; }
  .facts > div { padding: .85rem 1.1rem; border-right: 1px solid rgba(127,127,127,.28); }
  .facts > div:last-child { border-right: 0; }
  .facts dt { font-size: .8125rem; opacity: .7; }
  .facts dd { margin: .15rem 0 0; font-weight: 600; }

  /* rollout sequence */
  .steps { list-style: none; padding: 0; margin: 1.25rem 0; counter-reset: s; }
  .steps li { counter-increment: s; position: relative; padding: 0 0 1.1rem 2.75rem; margin: 0; }
  .steps li::before { content: counter(s); position: absolute; left: 0; top: -.05rem; width: 1.75rem; height: 1.75rem; border-radius: 999px; border: 1.5px solid currentColor; display: grid; place-items: center; font-size: .8125rem; font-weight: 700; }
  .steps li::after { content: ""; position: absolute; left: .85rem; top: 1.85rem; bottom: .15rem; border-left: 1.5px solid rgba(127,127,127,.35); }
  .steps li:last-child { padding-bottom: 0; }
  .steps li:last-child::after { display: none; }
  .steps li.live::before { background: currentColor; }
  .steps li.live::before { color: inherit; }

  /* tables */
  .tbl { width: 100%; border-collapse: collapse; font-size: .875rem; }
  .tbl th { text-align: left; font-weight: 600; padding: .55rem 1rem .55rem 0; border-bottom: 2px solid rgba(127,127,127,.4); }
  .tbl td { padding: .6rem 1rem .6rem 0; border-bottom: 1px solid rgba(127,127,127,.22); vertical-align: top; }
  .tbl td:first-child { white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; }
  .scroll-x { overflow-x: auto; max-width: 100%; }
  .method { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .6875rem; font-weight: 700; color: #fff; padding: .15rem .45rem; border-radius: .3rem; background: var(--get); margin-right: .4rem; }
  .chip { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .6875rem; padding: .15rem .5rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.4); white-space: nowrap; }

  .note { border-left: 3px solid rgba(127,127,127,.4); padding: .1rem 0 .1rem 1rem; margin: 1.25rem 0; font-size: .875rem; }

  /* checklist */
  .checks { list-style: none; padding: 0; margin: 1rem 0; }
  .checks li { position: relative; padding: .55rem 0 .55rem 2rem; margin: 0; border-bottom: 1px solid rgba(127,127,127,.22); }
  .checks li::before { content: ""; position: absolute; left: .15rem; top: .8rem; width: .95rem; height: .95rem; border: 1.5px solid currentColor; border-radius: .25rem; opacity: .6; }

  /* related pages */
  .related { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }
  .related a { display: inline-flex; align-items: center; padding: .5rem 1.1rem; border: 1px solid rgba(127,127,127,.4); border-radius: 999px; font-size: .875rem; font-weight: 600; text-decoration: none; color: inherit; }
  .related a:hover { border-color: currentColor; }

  /* toc */
  .toc a { display: block; padding: .3rem 0 .3rem .9rem; font-size: .875rem; border-left: 2px solid rgba(127,127,127,.28); opacity: .75; text-decoration: none; }
  .toc a:hover { opacity: 1; }
  .toc a[aria-current="true"] { opacity: 1; font-weight: 600; border-left-color: currentColor; }
</style>

<div class="sbdoc mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
  <div class="xl:grid xl:grid-cols-[minmax(0,48rem)_13rem] xl:gap-12 xl:justify-center">

    <article class="min-w-0 w-full max-w-3xl mx-auto">
      <h1>Sandbox &amp; environments</h1>
      <p class="lead text-muted">Use mock for product UI and webhook shape, the PayPal sandbox to validate money paths, and live only after a formal sandbox sign-off.</p>

      <dl class="facts not-prose mt-6">
        <div><dt>Payment drivers</dt><dd>mock, paypal</dd></div>
        <div><dt>PayPal modes</dt><dd>sandbox, live</dd></div>
        <div><dt>Config check</dt><dd>luvupay:sandbox-check</dd></div>
      </dl>

      {{-- DRIVERS --}}
      <section id="drivers">
        <h2>Payment drivers</h2>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Driver</th><th>Use it for</th></tr></thead>
            <tbody>
              <tr><td>mock</td><td>No external PayPal calls. The fastest way to work on register, settings, product webhooks and the demo commands.</td></tr>
              <tr><td>paypal</td><td>The real PayPal API shape. Set the mode with <code>PAYPAL_MODE=sandbox|live</code>.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      {{-- ENV --}}
      <section id="env">
        <h2>Environment variables</h2>
<pre><code>LUVUPAY_PAYMENT_DRIVER=paypal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
PAYPAL_WEBHOOK_ID=...
LUVUPAY_ALLOW_INSECURE_WEBHOOK_TARGETS=true   <span class="dim"># local/sandbox only</span>

<span class="dim"># Shared</span>
AUDIT_HMAC_KEY=your-long-random-string
METRICS_TOKEN=optional-internal-token
APP_URL=https://your-public-host</code></pre>
        <p class="note text-muted">Variable names must match <code>config/services.php</code>. Use <code>PAYPAL_SECRET</code>, not <code>PAYPAL_CLIENT_SECRET</code>.</p>
      </section>

      {{-- VERIFY --}}
      <section id="verify">
        <h2>Verify configuration</h2>
<pre><code><span class="prompt">php artisan luvupay:sandbox-check</span></code></pre>
        <p>The command checks the database tables and the mailer. When the driver is <code>paypal</code>, it also tests OAuth against the sandbox API. Fix every reported failure before you exercise money flows.</p>
      </section>

      {{-- ORDER --}}
      <section id="order">
        <h2>Recommended rollout order</h2>
        <ol class="steps">
          <li><strong>Start on mock.</strong> Work through register, console, settings and your product webhook receivers. Try <code>php artisan demo:second-chance</code> and <code>demo:resale</code>.</li>
          <li><strong>Switch to the sandbox.</strong> Set the <code>paypal</code> driver with sandbox credentials, then run <code>luvupay:sandbox-check</code>.</li>
          <li><strong>Create a subscription.</strong> Call the API, then complete approval with a PayPal sandbox buyer account.</li>
          <li><strong>Confirm webhook delivery.</strong> PayPal events should reach LuvuPay, and then your entitlement receiver.</li>
          <li><strong>Run the money paths.</strong> Exercise Second Chance and resale end to end with sandbox money.</li>
          <li class="live"><strong>Request live credentials.</strong> Only after a documented sandbox sign-off.</li>
        </ol>
      </section>

      {{-- LOCAL PRODUCT --}}
      <section id="local-product">
        <h2>Local product integration</h2>
        <p><code>samples/laravel-merchant/</code> holds a drop-in client and webhook controller for another Laravel app, such as an AI tools product. Wire up three things:</p>
        <ul>
          <li>A server-side secret key for API calls</li>
          <li>A webhook route with HMAC verification</li>
          <li>Entitlement updates keyed by <code>merchant_user_id</code></li>
        </ul>
      </section>

      {{-- OBSERVABILITY --}}
      <section id="observability">
        <h2>Observability in sandbox</h2>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Endpoint</th><th>Access</th><th>Shows</th></tr></thead>
            <tbody>
              <tr><td><span class="method">GET</span>/up</td><td><span class="chip">public</span></td><td>Liveness</td></tr>
              <tr><td><span class="method">GET</span><a href="/status">/status</a></td><td><span class="chip">public</span></td><td>High-level status page</td></tr>
              <tr><td><span class="method">GET</span>/internal/health</td><td><span class="chip">Bearer METRICS_TOKEN</span></td><td>Detailed health</td></tr>
              <tr><td><span class="method">GET</span>/internal/metrics</td><td><span class="chip">Bearer METRICS_TOKEN</span></td><td>Metrics</td></tr>
            </tbody>
          </table>
        </div>
        <p>The two <code>/internal</code> endpoints take <code>Authorization: Bearer $METRICS_TOKEN</code>. They are not public.</p>
      </section>

      {{-- CHECKLIST --}}
      <section id="checklist">
        <h2>Go-live checklist</h2>
        <ul class="checks not-prose">
          <li>HTTPS everywhere, with insecure webhook targets disabled</li>
          <li>MFA required for console roles, if <code>LUVUPAY_REQUIRE_MFA=true</code></li>
          <li>Payout recipients allow-listed and cooled off</li>
          <li>Webhook URL locked after the final change</li>
          <li>Rate limits and scopes reviewed</li>
          <li>Audit HMAC key set, and rotated off any shared defaults</li>
        </ul>
        <nav class="related not-prose" aria-label="Related pages">
          <a href="/docs/api">API reference</a>
          <a href="/docs/webhooks">Webhooks</a>
          <a href="/docs/security">Security</a>
        </nav>
      </section>
    </article>

    <aside class="hidden xl:block">
      <nav class="toc not-prose sticky top-24" aria-label="On this page">
        <a href="#drivers">Payment drivers</a>
        <a href="#env">Environment variables</a>
        <a href="#verify">Verify configuration</a>
        <a href="#order">Rollout order</a>
        <a href="#local-product">Local integration</a>
        <a href="#observability">Observability</a>
        <a href="#checklist">Go-live checklist</a>
      </nav>
    </aside>
  </div>
</div>

<script>
  (function () {
    var links = document.querySelectorAll('.toc a');
    if (!('IntersectionObserver' in window) || !links.length) return;
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
  })();
</script>
@endsection