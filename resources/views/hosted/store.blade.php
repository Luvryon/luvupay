<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand['name'] }} · {{ $project->product() ?: 'Store' }}</title>
    @include('partials.favicon', ['faviconUrl' => $brand['logo'] ?? null])
    <link rel="stylesheet" href="/css/ui.css">
    <link rel="stylesheet" href="/css/loader.css">
    <link rel="stylesheet" href="/css/hosted.css?v={{ @filemtime(public_path('css/hosted.css')) }}">
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-900 antialiased">
<main class="mx-auto w-full max-w-2xl px-4 py-10">
    <header class="flex items-center gap-4">
        @if($brand['logo'])
            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo" class="h-14 w-14 rounded-full object-cover border border-slate-200 bg-white">
        @else
            <div class="h-14 w-14 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg font-bold" aria-hidden="true">{{ $brand['initials'] }}</div>
        @endif
        <div>
            <h1 class="text-2xl font-bold">{{ $brand['name'] }}</h1>
            @if($project->description)<p class="text-sm text-slate-500">{{ $project->description }}</p>@endif
        </div>
    </header>

    @if($project->isSet())
    <section class="mt-8 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Subscription</p>
            <p class="mt-1 text-lg font-bold">{{ $project->product() }}</p>
            <p class="text-sm text-slate-500">{{ $price }} / {{ $project->interval === 'year' ? 'year' : 'month' }}</p>
        </div>
        <a href="{{ url('/checkout/'.$brand['id']) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 shadow-sm">Subscribe</a>
    </section>
    @endif

    @if($resaleOn)
    <section class="mt-10" id="resale" data-listings-url="{{ url('/m/'.$brand['id'].'/listings') }}">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Resale marketplace</h2>
                <p class="text-sm text-slate-500">Unused access from other subscribers, verified by LuvuPay. Pay once, start instantly</p>
            </div>
            <span id="listing-count" class="whitespace-nowrap text-xs font-semibold text-slate-400"></span>
        </div>
        <div id="listing-list" class="mt-4 grid gap-3" aria-live="polite">
            <div class="lp-state rounded-2xl border border-slate-200 bg-white"><span class="lp-spinner lp-spinner--lg"></span><div><strong>Loading listings</strong>Checking what is available right now</div></div>
        </div>
    </section>
    <script src="/js/loader.js" defer></script>
    <script src="/js/store.js" defer></script>
    @endif

    <p class="mt-12 text-center text-xs text-slate-400">Secure checkout powered by LuvuPay &amp; PayPal</p>
</main>
</body>
</html>
