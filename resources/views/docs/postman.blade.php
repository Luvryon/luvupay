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
    <p class="lead" style="color:var(--docs-muted)">Import the official collection and call LuvuPay like Stripe: health → auth → resources → webhooks</p>

    <section id="download">
        <h2>Download</h2>
        <p>Collection file in the repo:</p>
        <pre><code>postman/LuvuPay.postman_collection.json</code></pre>
        <p>
            <a class="c-btn c-btn-brand" href="{{ asset('postman/LuvuPay.postman_collection.json') }}" download
               style="display:inline-block;padding:10px 16px;border-radius:10px;background:#09027b;color:#fff;text-decoration:none;font-weight:600">
                Download JSON
            </a>
        </p>
    </section>

    <section id="setup">
        <h2>Setup</h2>
        <ol>
            <li>Open Postman → <strong>Import</strong> → select the JSON file</li>
            <li>Set collection variables: <code>base_url</code> (e.g. <code>http://localhost:8000</code>), <code>api_key</code> (from Settings → API credentials)</li>
            <li>Dashboard session routes need a browser cookie (log in to the console first) or use API-key routes</li>
        </ol>
    </section>

    <section id="folders">
        <h2>Folders</h2>
        <ul>
            <li><strong>Health</strong> — <code>GET /api/v1/health</code>, <code>GET /up</code></li>
            <li><strong>Dashboard</strong> — summary, transactions, project, resale listings</li>
            <li><strong>Demo simulator</strong> — checkout, grace decision, webhook logs</li>
            <li><strong>Payouts</strong> — merchant withdrawal &amp; charity donation</li>
            <li><strong>Webhooks</strong> — PayPal webhook receiver</li>
        </ul>
    </section>

    <section id="auth">
        <h2>Authentication</h2>
        <p>Send the merchant secret as <code>Authorization: Bearer lp_live_…</code> on API routes, or use the console session cookie for <code>/api/v1/dashboard/*</code>.</p>
    </section>
</article>
@endsection
