<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    @include('partials.favicon')

    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Documentation · LuvuPay')</title>
    <meta name="description" content="@yield('meta_description', 'Developer documentation for LuvuPay')">
    @include('partials.assets')
    <link rel="stylesheet" href="/css/docs.css?v={{ @filemtime(public_path('css/docs.css')) }}">
    @stack('head')
</head>
<body class="min-h-screen bg-white text-ink antialiased" x-data="{ mobile: false }">
{{-- Full-width top nav only --}}
@include('components.site.navbar')

<div class="docs-shell">
    {{-- Left sidebar --}}
    <aside class="docs-sidebar" aria-label="Documentation navigation">
        <div class="brand">Documentation</div>
        <nav class="docs-nav">
            <div class="docs-nav-section">
                <div class="docs-nav-section-title">Getting Started</div>
                <a href="/docs" class="{{ request()->is('docs') && !request()->is('docs/*') ? 'active' : '' }}">Overview</a>
                <a href="/docs/sandbox" class="{{ request()->is('docs/sandbox') ? 'active' : '' }}">Sandbox & environments</a>
                <a href="/docs/cookbook" class="{{ request()->is('docs/cookbook') ? 'active' : '' }}">Integration cookbook</a>
            </div>
            <div class="docs-nav-section">
                <div class="docs-nav-section-title">API</div>
                <a href="/docs/api" class="{{ request()->is('docs/api') ? 'active' : '' }}">API reference</a>
                <a href="/docs/webhooks" class="{{ request()->is('docs/webhooks') ? 'active' : '' }}">Webhooks</a>
                <a href="/docs/errors" class="{{ request()->is('docs/errors') ? 'active' : '' }}">Errors & status codes</a>
                <a href="/docs/postman" class="{{ request()->is('docs/postman') ? 'active' : '' }}">Postman collection</a>
            </div>
            <div class="docs-nav-section">
                <div class="docs-nav-section-title">Product</div>
                <a href="/docs/resale-money" class="{{ request()->is('docs/resale-money') ? 'active' : '' }}">Resale money flow</a>
                <a href="/docs/security" class="{{ request()->is('docs/security') ? 'active' : '' }}">Security model</a>
                <a href="/docs/ai" class="{{ request()->is('docs/ai') ? 'active' : '' }}">AI layer</a>
            </div>
            <div class="docs-nav-section">
                <div class="docs-nav-section-title">More</div>
                <a href="/docs/legal" class="{{ request()->is('docs/legal') ? 'active' : '' }}">Legal</a>
                <a href="/docs/octane" class="{{ request()->is('docs/octane') ? 'active' : '' }}">Octane</a>
            </div>
        </nav>
    </aside>

    {{-- Right column: content row + footer (footer does NOT span under sidebar) --}}
    <div class="docs-right">
        <div class="docs-body-row">
            <main class="docs-content">
                @yield('content')
            </main>
            <aside class="docs-toc" aria-label="On this page" id="docs-toc-panel">
                <div class="docs-toc-inner">
                    <div class="docs-toc-title">On this page</div>
                    <nav id="docs-toc-links">
                        @hasSection('toc')
                            @yield('toc')
                        @endif
                    </nav>
                    <button type="button" class="toc-back" id="docs-back-top" hidden>↑ Back to top</button>
                </div>
            </aside>
        </div>
    </div>
</div>

@include('components.site.footer')

@stack('scripts')
<script src="/js/alpine.min.js" defer></script>
<script src="/js/docs-toc.js" defer></script>
</body>
</html>
