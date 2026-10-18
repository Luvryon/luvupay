@extends('layouts.docs')
@section('title', 'Errors · LuvuPay')
@section('guides')
@endsection
@section('content')
<h1 class="text-3xl font-extrabold">Error catalog</h1>
<p class="mt-2 text-muted">JSON errors follow <code class="font-mono text-sm bg-white/6 px-1 rounded">{"error":"code","message":"..."}</code> via <code>LuvuApiException</code>.</p>

<div class="mt-8 overflow-x-auto rounded-2xl border bg-wash">
<table class="min-w-full text-sm">
<thead class="bg-wash text-left text-muted">
<tr><th class="p-3">HTTP</th><th class="p-3">error</th><th class="p-3">When</th></tr>
</thead>
<tbody class="divide-y">
<tr><td class="p-3 font-mono">401</td><td class="p-3 font-mono">unauthorized</td><td class="p-3">Missing/invalid API key</td></tr>
<tr><td class="p-3 font-mono">401</td><td class="p-3 font-mono">invalid_signature</td><td class="p-3">Webhook HMAC / PayPal signature failed</td></tr>
<tr><td class="p-3 font-mono">404</td><td class="p-3 font-mono">not_found</td><td class="p-3">Resource missing or not owned by merchant</td></tr>
<tr><td class="p-3 font-mono">409</td><td class="p-3 font-mono">conflict / window_closed / not_eligible</td><td class="p-3">Second Chance race, closed window, wrong status</td></tr>
<tr><td class="p-3 font-mono">409</td><td class="p-3 font-mono">idempotency_conflict</td><td class="p-3">Same Idempotency-Key still processing</td></tr>
<tr><td class="p-3 font-mono">422</td><td class="p-3 font-mono">validation / not_eligible / no_charity</td><td class="p-3">Validation or business rule</td></tr>
<tr><td class="p-3 font-mono">422</td><td class="p-3 font-mono">idempotency_payload_mismatch</td><td class="p-3">Same key, different body</td></tr>
<tr><td class="p-3 font-mono">429</td><td class="p-3 font-mono">too_many_requests</td><td class="p-3">Throttle</td></tr>
<tr><td class="p-3 font-mono">502</td><td class="p-3 font-mono">provider_error</td><td class="p-3">PayPal/mock provider failure</td></tr>
<tr><td class="p-3 font-mono">503</td><td class="p-3 font-mono">—</td><td class="p-3">Health check degraded</td></tr>
</tbody>
</table>
</div>

<section class="mt-8 rounded-2xl border bg-wash p-6">
<h2 class="font-bold">Idempotency</h2>
<ul class="mt-3 text-sm space-y-2 list-disc pl-5 text-ink/80">
<li>Required on financial <code>POST/PUT/PATCH/DELETE</code> with middleware <code>idempotent</code>.</li>
<li>Replay same key + same body → cached response + header <code>Idempotent-Replayed: true</code>.</li>
<li>Same key + different body → 422.</li>
<li>Generate keys with UUID v4 per logical operation (not per HTTP retry only).</li>
</ul>
</section>
@endsection
