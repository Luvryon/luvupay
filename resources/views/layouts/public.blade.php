{{-- Public pages share the single white theme with layouts.site --}}
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LuvuPay')</title>
    <meta name="description" content="@yield('meta_description', 'Ethical subscription infrastructure — one API for second chances, resale and audit-ready money movement.')">
    @include('partials.assets')
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-white text-ink antialiased" x-data="{ mobile: false }">

    <x-site.navbar />

    <div class="flex-1">
        @hasSection('guides')
            <div class="mx-auto grid max-w-7xl gap-12 px-5 py-10 sm:px-8 lg:grid-cols-[230px_1fr]">
                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-3 px-3 text-xs font-semibold text-muted">Guides</p>
                    <nav class="space-y-0.5" aria-label="Guides">
                        @foreach([
                            ['/docs', 'Overview'],
                            ['/docs/cookbook', 'Integration cookbook'],
                            ['/docs/sandbox', 'PayPal sandbox'],
                            ['/docs/api', 'API reference'],
                            ['/docs/webhooks', 'Webhooks'],
                            ['/docs/resale-money', 'Resale money flow'],
                            ['/docs/ai', 'AI layer'],
                            ['/docs/security', 'Security'],
                            ['/docs/legal', 'Legal'],
                            ['/docs/errors', 'Errors'],
                            ['/docs/postman', 'Postman'],
                            ['/privacy', 'Privacy'],
                            ['/terms', 'Terms'],
                            ['/support', 'Support'],
                        ] as [$href, $label])
                            <a href="{{ $href }}"
                               class="guide-link {{ request()->is(trim($href, '/')) || request()->is(trim($href, '/').'/*') ? 'active' : '' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                    @yield('guides')
                </aside>
                <div class="prose-site min-w-0 max-w-3xl">
                    @yield('content')
                </div>
            </div>
        @else
            @yield('content')
        @endif
    </div>

    <x-site.footer />

    @stack('scripts')
</body>
</html>
