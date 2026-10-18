@extends('layouts.docs')
@section('title', 'Webhooks · LuvuPay')
@section('meta_description', 'LuvuPay webhook verification, event types, Entitlement Bridge, retries, nonce protection, and secure URL changes.')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')

<style>
  .whdoc { --code-bg:#13171f; --code-fg:#d9e0ec; --code-dim:#7d8aa0; }
  .whdoc section { scroll-margin-top: 5.5rem; padding-top: 2.75rem; }
  .whdoc section + section { border-top: 1px solid rgba(127,127,127,.22); margin-top: 2.75rem; }
  .whdoc h2 { margin-top: 0; }
  .whdoc :focus-visible { outline: 2px solid currentColor; outline-offset: 3px; border-radius: 4px; }

  .whdoc pre { background: var(--code-bg); color: var(--code-fg); border-radius: .75rem; padding: 1rem 1.25rem; font-size: .8125rem; line-height: 1.65; overflow-x: auto; max-width: 100%; }
  .whdoc pre code { background: none; color: inherit; padding: 0; }
  .whdoc .dim { color: var(--code-dim); }

  /* facts strip */
  .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); border: 1px solid rgba(127,127,127,.28); border-radius: .75rem; overflow: hidden; }
  .facts > div { padding: .85rem 1.1rem; border-right: 1px solid rgba(127,127,127,.28); }
  .facts > div:last-child { border-right: 0; }
  .facts dt { font-size: .8125rem; opacity: .7; }
  .facts dd { margin: .15rem 0 0; font-weight: 600; }

  /* real sequences only: verification and URL change */
  .steps { list-style: none; padding: 0; margin: 1.25rem 0; counter-reset: s; }
  .steps li { counter-increment: s; position: relative; padding: 0 0 1.1rem 2.75rem; margin: 0; }
  .steps li::before { content: counter(s); position: absolute; left: 0; top: -.05rem; width: 1.75rem; height: 1.75rem; border-radius: 999px; border: 1.5px solid currentColor; display: grid; place-items: center; font-size: .8125rem; font-weight: 700; }
  .steps li::after { content: ""; position: absolute; left: .85rem; top: 1.85rem; bottom: .15rem; border-left: 1.5px solid rgba(127,127,127,.35); }
  .steps li:last-child { padding-bottom: 0; }
  .steps li:last-child::after { display: none; }

  /* tabs */
  .tabs [role="tablist"] { display: flex; gap: .25rem; margin-bottom: .6rem; }
  .tabs [role="tab"] { font-size: .8125rem; font-weight: 600; padding: .35rem .9rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.4); background: transparent; color: inherit; cursor: pointer; opacity: .75; }
  .tabs [role="tab"][aria-selected="true"] { background: var(--code-bg); color: #fff; border-color: var(--code-bg); opacity: 1; }
  .tabs [role="tabpanel"][hidden] { display: none; }
  .tabs pre { margin: 0; }

  /* tables */
  .tbl { width: 100%; border-collapse: collapse; font-size: .875rem; }
  .tbl th { text-align: left; font-weight: 600; padding: .55rem 1rem .55rem 0; border-bottom: 2px solid rgba(127,127,127,.4); }
  .tbl td { padding: .6rem 1rem .6rem 0; border-bottom: 1px solid rgba(127,127,127,.22); vertical-align: top; }
  .tbl td:first-child { white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; }
  .scroll-x { overflow-x: auto; max-width: 100%; }

  /* split of responsibilities */
  .split { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); border-left: 3px solid rgba(127,127,127,.28); margin: 1.25rem 0; }
  .split > div { padding: .25rem 1.25rem; }
  .split > div + div { border-left: 1px solid rgba(127,127,127,.28); }
  .split strong { display: block; margin-bottom: .2rem; }

  /* checklist */
  .checks { list-style: none; padding: 0; margin: 1rem 0; }
  .checks li { position: relative; padding: .55rem 0 .55rem 2rem; margin: 0; border-bottom: 1px solid rgba(127,127,127,.22); }
  .checks li::before { content: ""; position: absolute; left: .15rem; top: .8rem; width: .95rem; height: .95rem; border: 1.5px solid currentColor; border-radius: .25rem; opacity: .6; }
  .checks li::after { content: ""; position: absolute; left: .45rem; top: .88rem; width: .3rem; height: .55rem; border: solid currentColor; border-width: 0 2px 2px 0; transform: rotate(45deg); opacity: .8; }

  .note { border-left: 3px solid rgba(127,127,127,.4); padding: .1rem 0 .1rem 1rem; margin: 1.25rem 0; font-size: .875rem; }

  /* related pages */
  .related { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }
  .related a { display: inline-flex; align-items: center; padding: .5rem 1.1rem; border: 1px solid rgba(127,127,127,.4); border-radius: 999px; font-size: .875rem; font-weight: 600; text-decoration: none; color: inherit; }
  .related a:hover { border-color: currentColor; }

  /* toc */
  .toc a { display: block; padding: .3rem 0 .3rem .9rem; font-size: .875rem; border-left: 2px solid rgba(127,127,127,.28); opacity: .75; text-decoration: none; }
  .toc a:hover { opacity: 1; }
  .toc a[aria-current="true"] { opacity: 1; font-weight: 600; border-left-color: currentColor; }
</style>

<div class="whdoc mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
  <div class="xl:grid xl:grid-cols-[minmax(0,48rem)_13rem] xl:gap-12 xl:justify-center">

    <article class="min-w-0 w-full max-w-3xl mx-auto">
      <h1>Webhooks</h1>
      <p class="lead text-muted">LuvuPay sends signed HTTPS callbacks to your application. You own user access, and we never write to your database. Every delivery can be verified, is protected against replay, and expires quickly.</p>

      <dl class="facts not-prose mt-6">
        <div><dt>Signature</dt><dd>HMAC-SHA256</dd></div>
        <div><dt>Timestamp tolerance</dt><dd>5 minutes</dd></div>
        <div><dt>Nonce store</dt><dd>about 10 minutes</dd></div>
        <div><dt>Transport</dt><dd>HTTPS only in production</dd></div>
      </dl>

      {{-- VERIFY --}}
      <section id="verify">
        <h2>Verify every request</h2>
        <p>Each delivery carries three headers. Check them in this order and reject the request at the first failure.</p>

        <ol class="steps">
          <li><strong>Check the timestamp.</strong> Read <code>X-LuvuPay-Timestamp</code> and reject anything older than <strong>5 minutes</strong>. The tolerance is configurable.</li>
          <li><strong>Check the nonce.</strong> Read <code>X-LuvuPay-Nonce</code> and reject replays. The nonce store TTL is typically 10 minutes.</li>
          <li><strong>Check the signature.</strong> Compute HMAC-SHA256 over the canonical string with your endpoint secret. Compare it to <code>X-LuvuPay-Signature</code> with a constant-time compare.</li>
        </ol>

        <h3>Canonical string</h3>
<pre><code>{timestamp}.{nonce}.{raw_body}</code></pre>
        <p class="text-sm text-muted">Use the raw request body exactly as received. Parsing and re-serializing the JSON changes the bytes and breaks the signature.</p>

        <h3>Example code</h3>
        <div class="tabs not-prose" id="code-tabs">
          <div role="tablist" aria-label="Language">
            <button role="tab" id="tab-node" aria-controls="panel-node" aria-selected="true" type="button">Node.js</button>
            <button role="tab" id="tab-php" aria-controls="panel-php" aria-selected="false" type="button" tabindex="-1">PHP / Laravel</button>
          </div>
          <div role="tabpanel" id="panel-node" aria-labelledby="tab-node">
<pre><code>const crypto = require('crypto');

function verifyLuvuPayWebhook(rawBody, signature, timestamp, nonce, secret) {
  if (Math.abs(Date.now() / 1000 - Number(timestamp)) &gt; 300) return false;
  const expected = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${nonce}.${rawBody}`)
    .digest('hex');
  try {
    return crypto.timingSafeEqual(
      Buffer.from(expected, 'utf8'),
      Buffer.from(signature, 'utf8')
    );
  } catch {
    return false;
  }
}</code></pre>
          </div>
          <div role="tabpanel" id="panel-php" aria-labelledby="tab-php" hidden>
<pre><code>$expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$rawBody, $secret);
if (! hash_equals($expected, $signature)) {
    abort(401, 'invalid_signature');
}
<span class="dim">// Also enforce timestamp window and nonce uniqueness in your app.</span></code></pre>
          </div>
        </div>

        <p class="note text-muted">If you customize header names or the canonical string, update this page to match <code>WebhookSigner</code> in <code>app/Services</code>.</p>
      </section>

      {{-- EVENTS --}}
      <section id="events">
        <h2>Event types</h2>
        <div class="scroll-x not-prose">
          <table class="tbl">
            <thead><tr><th>Event</th><th>When it fires</th></tr></thead>
            <tbody>
              <tr><td>entitlement.transfer</td><td>A resale completed. Revoke the seller and grant the buyer access until <code>access_expires_at</code>.</td></tr>
              <tr><td>subscription.cancelled</td><td>A Second Chance outcome, or a cancellation started by the merchant.</td></tr>
              <tr><td>second_chance.triggered</td><td>The end user accepted the 48-hour window outcome.</td></tr>
            </tbody>
          </table>
        </div>
        <p>Payloads include merchant-scoped IDs, the emails you supplied, and correlation fields. Process every event idempotently on your side.</p>
      </section>

      {{-- ENTITLEMENT --}}
      <section id="entitlement">
        <h2>Entitlement Bridge</h2>
        <p>LuvuPay never holds your product entitlements. On a resale or a Second Chance outcome, we send a signed webhook and your service updates access.</p>
        <div class="split not-prose">
          <div><strong>LuvuPay</strong><p class="text-sm text-muted">Sends the signed webhook. Retries failed deliveries with backoff.</p></div>
          <div><strong>Your service</strong><p class="text-sm text-muted">Verifies the request and grants or revokes access. Acknowledges through the signed entitlement ack endpoints when your product contract requires it.</p></div>
        </div>
      </section>

      {{-- URL CHANGE --}}
      <section id="url-change">
        <h2>Changing the webhook URL</h2>
        <ol class="steps">
          <li><strong>Request the change.</strong> In Console, open Settings and request a new URL.</li>
          <li><strong>Confirm by email.</strong> Enter the verification code we send you.</li>
          <li><strong>Wait for the cooling-off period.</strong> It ends at <code>pending_url_activates_at</code>. Until then the previous URL stays active.</li>
          <li><strong>Lock window (optional).</strong> <code>url_locked_until</code> blocks rapid successive changes after a compromise.</li>
        </ol>
        <p>The endpoint ID is shown on the settings page. Production accepts HTTPS endpoints only. For local development, set <code>LUVUPAY_ALLOW_INSECURE_WEBHOOK_TARGETS=true</code>. Production ignores this setting.</p>
      </section>

      {{-- LOCAL --}}
      <section id="local">
        <h2>Local development</h2>
        <ul>
          <li>Point the webhook URL at <code>http://127.0.0.1:PORT/your-path</code>.</li>
          <li>Use a tunnel such as ngrok or Cloudflare Tunnel if PayPal or a remote LuvuPay instance must reach you.</li>
          <li>Log the raw body and headers while you integrate. Never log the full secret.</li>
        </ul>
      </section>

      {{-- SECURITY --}}
      <section id="security">
        <h2>Security checklist</h2>
        <ul class="checks not-prose">
          <li>Reject unsigned requests and failed HMAC checks with <code>401</code>.</li>
          <li>Enforce timestamp skew and nonce uniqueness.</li>
          <li>Process deliveries idempotently, keyed on the event ID or a business key.</li>
          <li>Keep the endpoint secret in a secrets manager, and rotate it in Console if it is compromised.</li>
          <li>Add IP allow-lists at your edge if your stack supports them.</li>
        </ul>
        <nav class="related not-prose" aria-label="Related pages">
          <a href="/docs/api">API reference</a>
          <a href="/docs/security">Security model</a>
          <a href="/docs/sandbox">Sandbox</a>
        </nav>
      </section>
    </article>

    <aside class="hidden xl:block">
      <nav class="toc not-prose sticky top-24" aria-label="On this page">
        <a href="#verify">Verify every request</a>
        <a href="#events">Event types</a>
        <a href="#entitlement">Entitlement Bridge</a>
        <a href="#url-change">Changing the URL</a>
        <a href="#local">Local development</a>
        <a href="#security">Security checklist</a>
      </nav>
    </aside>
  </div>
</div>

<script>
  (function () {
    // tabs
    var tabs = document.querySelectorAll('#code-tabs [role="tab"]');
    function select(tab) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          var n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
          select(n); n.focus();
        }
      });
    });

    // table of contents highlight
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