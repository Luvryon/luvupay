<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand['name'] }} · {{ $project->product() ?: 'Store' }}</title>
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
    <section class="mt-10" id="resale">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-lg font-bold">Resale marketplace</h2>
                <p class="text-sm text-slate-500">Unused access from other subscribers, verified by LuvuPay. Pay once, start instantly.</p>
            </div>
            <span class="text-xs font-semibold text-slate-400 whitespace-nowrap flex-none pl-3">{{ count($listings) }} available</span>
        </div>

        <div class="mt-4 grid gap-3">
            @forelse($listings as $l)
                <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full">Verified token</span>
                        <p class="mt-2 font-bold">{{ $l['days'] }} days of {{ $l['product'] }}</p>
                        <p class="text-xs text-slate-500">Transferred from {{ $l['seller'] }}</p>
                    </div>
                    <div class="text-right flex-none">
                        <p class="text-lg font-bold">{{ $l['price'] }}</p>
                        <a href="{{ url('/redeem/'.$l['token']) }}" class="mt-2 inline-block bg-[#0070ba] hover:bg-[#005ea6] text-white text-sm font-semibold rounded-xl px-4 py-2 shadow-sm">Buy</a>
                    </div>
                </article>
            @empty
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No resale access is listed right now. Check back soon.</div>
            @endforelse
        </div>
    </section>
    @endif

    <p class="mt-12 text-center text-xs text-slate-400">Secure checkout powered by LuvuPay &amp; PayPal</p>
</main>
</body>
</html>
