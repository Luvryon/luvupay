@extends('layouts.docs')
@section('title', 'AI layer · LuvuPay')
@section('guides')
@endsection
@section('content')
<h1 class="text-3xl font-extrabold">Nebius AI layer</h1>
<p class="mt-2 text-muted">Churn prediction, dynamic resale pricing, ecosystem health — via OpenAI-compatible Nebius API</p>
<pre class="mt-6 text-xs  rounded-xl p-4 overflow-x-auto">POST /api/v1/ai/churn-prediction
POST /api/v1/ai/resale-price-suggestion
GET  /api/v1/ai/subscription-health-report?email=
GET  /api/v1/ai/job-result?result_key=

NEBIUS_API_KEY=
NEBIUS_BASE_URL=https://api.tokenfactory.eu-north1.nebius.com/v1
NEBIUS_MODEL=nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B</pre>
<p class="text-sm text-muted mt-4">Responses cached 24h (cache store + <code>ai_cached_responses</code>). Logs in <code>ai_request_logs</code>. Use <code>"async": true</code> to queue jobs</p>
@endsection
