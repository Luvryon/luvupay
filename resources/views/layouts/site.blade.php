<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    @include('partials.favicon')

    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LuvuPay')</title>
    <meta name="description" content="@yield('meta_description', 'Ethical subscription infrastructure - one API for second chances, resale and audit-ready money movement')">
    @include('partials.assets')
    <link rel="stylesheet" href="/css/loader.css?v={{ @filemtime(public_path('css/loader.css')) }}">
    @stack('head')
</head>
<body class="grid min-h-screen grid-rows-[auto_1fr_auto] bg-white text-ink antialiased" x-data="{ mobile: false }">

    <x-site.navbar />

    <div>
        @hasSection('guides')
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-8 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-[220px_minmax(0,1fr)_160px]">
                {{-- Left docs nav --}}
                <aside class="hidden lg:block lg:sticky lg:top-24 lg:self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Docs</p>
                    <nav class="space-y-0.5" aria-label="Documentation">
                        @foreach([
                            ['/docs', 'Overview'],
                            ['/docs/cookbook', 'Cookbook'],
                            ['/docs/sandbox', 'Sandbox'],
                            ['/docs/api', 'API reference'],
                            ['/docs/webhooks', 'Webhooks'],
                            ['/docs/resale-money', 'Resale money'],
                            ['/docs/ai', 'AI layer'],
                            ['/docs/security', 'Security'],
                            ['/docs/errors', 'Errors'],
                            ['/docs/postman', 'Postman'],
                            ['/docs/legal', 'Legal'],
                            ['/privacy', 'Privacy'],
                            ['/terms', 'Terms'],
                            ['/support', 'Support'],
                        ] as [$href, $label])
                            @php
                                $path = trim($href, '/');
                                $active = $path === '' ? request()->is('docs') : (request()->is($path) || request()->is($path.'/*'));
                            @endphp
                            <a href="{{ $href }}"
                               class="guide-link {{ $active ? 'active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                    @yield('guides')
                </aside>

                {{-- Main article --}}
                <article class="prose-site min-w-0 max-w-3xl">
                    @yield('content')
                </article>

                {{-- Right: on this page --}}
                <aside class="hidden xl:block xl:sticky xl:top-24 xl:self-start" aria-label="On this page">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">On this page</p>
                    <nav id="docs-toc-links" class="space-y-1 text-sm text-slate-500">
                        @hasSection('toc')
                            @yield('toc')
                        @else
                            <p class="text-xs text-slate-400">No sections on this page</p>
                        @endif
                    </nav>
                </aside>
            </div>
        @else
            @yield('content')
        @endif
    </div>


    <x-site.footer />

    @stack('scripts')
    <script src="/js/alpine.min.js" defer></script>
    <script src="/js/docs-toc.js" defer></script>
</body>
</html>
