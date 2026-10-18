<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LuvuPay')</title>
    <meta name="description" content="@yield('meta_description', 'Ethical subscription infrastructure — one API for second chances, resale and audit-ready money movement.')">
    @include('partials.assets')
    <style>
      .prose-site { color: #334155; font-size: 0.975rem; line-height: 1.7; }
      .prose-site h1 { font-size: 1.875rem; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; margin: 0 0 0.75rem; line-height: 1.25; }
      .prose-site h2 { font-size: 1.25rem; font-weight: 650; color: #0f172a; margin: 2rem 0 0.75rem; padding-top: 0.5rem; }
      .prose-site h3 { font-size: 1.05rem; font-weight: 600; color: #0f172a; margin: 1.5rem 0 0.5rem; }
      .prose-site p, .prose-site ul, .prose-site ol { margin: 0.75rem 0; }
      .prose-site .lead { font-size: 1.05rem; color: #64748b; line-height: 1.65; }
      .prose-site a { color: #5b3df5; text-decoration: underline; text-underline-offset: 2px; }
      .prose-site code { font-size: 0.85em; background: #f1f5f9; padding: 0.15em 0.4em; border-radius: 0.35rem; color: #0f172a; }
      .prose-site pre { background: #0f172a; color: #e2e8f0; padding: 1rem 1.15rem; border-radius: 0.85rem; overflow-x: auto; font-size: 0.8rem; line-height: 1.55; margin: 1rem 0; }
      .prose-site pre code { background: transparent; padding: 0; color: inherit; }
      .prose-site table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin: 1rem 0; }
      .prose-site th, .prose-site td { border: 1px solid #e2e8f0; padding: 0.6rem 0.75rem; text-align: left; vertical-align: top; }
      .prose-site th { background: #f8fafc; font-weight: 600; color: #0f172a; }
      .prose-site ul { list-style: disc; padding-left: 1.25rem; }
      .prose-site ol { list-style: decimal; padding-left: 1.25rem; }
      .prose-site li { margin: 0.35rem 0; }
      .prose-site section { margin: 1.25rem 0; }
      .guide-link { display: block; border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.875rem; color: #64748b; }
      .guide-link:hover { background: #f8fafc; color: #0f172a; }
      .guide-link.active { background: #f5f3ff; color: #5b3df5; font-weight: 600; }
    </style>
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-white text-ink antialiased" x-data="{ mobile: false }">

    <x-site.navbar />

    <div class="flex-1">
        @hasSection('guides')
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-8 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-[220px_minmax(0,1fr)_180px]">
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
                    <nav id="docs-toc" class="space-y-1 text-sm text-slate-500">
                        @hasSection('toc')
                            @yield('toc')
                        @else
                            <p class="text-xs text-slate-400">Scroll the article for headings.</p>
                        @endif
                    </nav>
                </aside>
            </div>
            <script>
              // Auto-build TOC from h2/h3 in article when no @section('toc')
              (function () {
                var toc = document.getElementById('docs-toc');
                if (!toc || toc.querySelector('a')) return;
                var article = document.querySelector('article.prose-site');
                if (!article) return;
                var heads = article.querySelectorAll('h2[id], h3[id], h2, h3');
                if (!heads.length) return;
                toc.innerHTML = '';
                heads.forEach(function (h) {
                  if (!h.id) {
                    h.id = (h.textContent || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                  }
                  if (!h.id) return;
                  var a = document.createElement('a');
                  a.href = '#' + h.id;
                  a.textContent = (h.textContent || '').trim();
                  a.className = 'block rounded px-2 py-1 hover:bg-slate-50 hover:text-slate-900 ' + (h.tagName === 'H3' ? 'pl-4 text-xs' : 'text-sm');
                  toc.appendChild(a);
                });
              })();
            </script>
        @else
            @yield('content')
        @endif
    </div>


    <x-site.footer />

    @stack('scripts')
    <script src="/js/alpine.min.js" defer></script>
</body>
</html>
