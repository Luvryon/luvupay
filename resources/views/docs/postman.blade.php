@extends('layouts.docs')
@section('title', 'Postman · LuvuPay Docs')
@section('meta_description', 'Import the official LuvuPay Postman collection for Sandbox and local API testing')

@section('toc')
    <a href="#download" data-toc-id="download">Download</a>
    <a href="#setup" data-toc-id="setup">Setup</a>
    <a href="#folders" data-toc-id="folders">Folders</a>
    <a href="#auth" data-toc-id="auth">Authentication</a>
@endsection

@section('content')
<article class="apiref">
    <h1>Postman collection</h1>
    <p class="lead" style="color:var(--docs-muted)">Walk the whole hero flow with assertions: sign in, AI proposes, a human lowers the donation, PayPal pays, evidence matches.</p>

    <section id="download">
        <h2>Download</h2>
        <p>Import <strong>both</strong> files, then pick the <em>LuvuPay · Local / Render</em> environment:</p>
        <p>
            <a class="c-btn c-btn-brand" href="{{ asset('postman/LuvuPay.postman_collection.json') }}" download
               style="display:inline-block;padding:10px 16px;border-radius:10px;background:#09027b;color:#fff;text-decoration:none;font-weight:600">Collection (JSON)</a>
            <a class="c-btn" href="{{ asset('postman/LuvuPay.postman_environment.json') }}" download
               style="display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid #09027b;color:#09027b;text-decoration:none;font-weight:600;margin-left:8px">Environment (JSON)</a>
        </p>
    </section>

    <section id="setup">
        <h2>Setup</h2>
        <ol>
            <li>Postman → <strong>Import</strong> → select the collection and the environment</li>
            <li>Set <code>base_url</code>, <code>email</code>, <code>password</code> (seeded owner: <code>owner@demomerchant.test</code> / <code>SecurePass1</code>, no 2FA) and <code>api_key</code> (printed once by <code>php artisan db:seed</code>)</li>
            <li>Server needs <code>LUVUPAY_DEMO_SIMULATOR=true</code>. No Nebius/PayPal yet? Set collection variable <code>require_real</code> to <code>false</code></li>
            <li>Click the collection → <strong>Run</strong>. Keep the folder order</li>
        </ol>
    </section>

    <section id="folders">
        <h2>Folders</h2>
        <ul>
            <li><strong>1 · Hero flow</strong>: login + CSRF, preflight with live AI ping, propose, approve with a lowered donation, double-approve refused (409), evidence JSON and page, ledger, reject path</li>
            <li><strong>2 · Merchant API</strong>: idempotency replay / mismatch / missing key, wrong key, resale list, AI health report, export</li>
            <li><strong>3 · Security checks</strong>: forged webhook, no-session access, unguessable evidence ids</li>
        </ul>
        <p>The donation recipient is an <strong>example</strong>; PayPal Sandbox moves test money only.</p>
    </section>

    <section id="auth">
        <h2>Authentication</h2>
        <p>Folder 1 uses the console session cookie plus <code>X-CSRF-TOKEN</code> (Postman handles both). Folders 2 and 3 use <code>Authorization: Bearer lp_live_…</code> or nothing.</p>
    </section>
</article>
@endsection
