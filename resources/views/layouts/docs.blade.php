<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-luvupay.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-luvupay.png') }}">

    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Documentation · LuvuPay')</title>
    <meta name="description" content="@yield('meta_description', 'Developer documentation for LuvuPay')">
    @include('partials.assets')
    <style>
      :root {
        --docs-bg: #ffffff;
        --docs-sidebar: #f8fafc;
        --docs-border: #e2e8f0;
        --docs-text: #0f172a;
        --docs-muted: #64748b;
        --docs-accent: #5b3df5;
        --docs-accent-soft: rgba(91,61,245,0.08);
      }
      body.docs-body { background: var(--docs-bg); color: var(--docs-text); font-family: Inter, ui-sans-serif, system-ui, sans-serif; margin: 0; }
      /* Shell under navbar: sidebar | (content + footer) */
      .docs-shell {
        display: flex;
        align-items: stretch;
        min-height: calc(100vh - 4rem);
      }
      .docs-sidebar {
        width: 260px;
        flex-shrink: 0;
        background: var(--docs-sidebar);
        border-right: 1px solid var(--docs-border);
        position: sticky;
        top: 4rem;
        height: calc(100vh - 4rem);
        overflow-y: auto;
        padding: 1.25rem 0.75rem 2rem;
      }
      .docs-sidebar .brand {
        padding: 0.35rem 0.75rem 1rem;
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--docs-muted);
      }
      .docs-nav-section { margin-top: 1rem; }
      .docs-nav-section-title {
        padding: 0.35rem 0.75rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--docs-muted);
      }
      .docs-nav a {
        display: block;
        padding: 0.4rem 0.75rem;
        border-radius: 0.4rem;
        font-size: 0.875rem;
        color: var(--docs-muted);
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
      }
      .docs-nav a:hover { background: #f1f5f9; color: var(--docs-text); }
      .docs-nav a.active { background: var(--docs-accent-soft); color: var(--docs-accent); font-weight: 600; }

      /* Right column: content + footer only (not under sidebar) */
      .docs-right {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
      }
      .docs-body-row {
        flex: 1;
        display: flex;
        min-width: 0;
      }
      .docs-content {
        flex: 1;
        min-width: 0;
        padding: 2.5rem 2rem 3rem;
        max-width: 48rem;
      }
      .docs-toc {
        width: 220px;
        flex-shrink: 0;
        position: sticky;
        top: 4rem;
        height: calc(100vh - 4rem);
        overflow-y: auto;
        padding: 2.25rem 1rem 2rem 0;
        font-size: 0.8125rem;
      }
      .docs-toc-inner {
        border-left: 1px solid var(--docs-border);
        padding-left: 0;
      }
      .docs-toc-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--docs-text);
        margin: 0 0 0.75rem 1rem;
      }
      .docs-toc a {
        display: block;
        padding: 0.35rem 0.75rem 0.35rem 1rem;
        color: var(--docs-muted);
        text-decoration: none;
        border-left: 2px solid transparent;
        line-height: 1.4;
        transition: color .15s, border-color .15s, background .15s;
      }
      .docs-toc a:hover { color: var(--docs-text); }
      .docs-toc a.active {
        color: var(--docs-accent);
        border-left-color: var(--docs-accent);
        background: var(--docs-accent-soft);
        font-weight: 600;
      }
      .docs-toc a.toc-h3 { padding-left: 1.6rem; font-size: 0.78rem; }
      .docs-toc .toc-back {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin: 1rem 0 0 1rem;
        padding: 0;
        border: 0;
        background: none;
        font-size: 0.78rem;
        color: var(--docs-muted);
        cursor: pointer;
      }
      .docs-toc .toc-back:hover { color: var(--docs-accent); }

      /* Footer sits only in the right column */
      .docs-footer-wrap {
        border-top: 1px solid var(--docs-border);
        background: #fff;
        margin-top: auto;
      }
      .docs-footer-wrap .site-footer-inner {
        max-width: none;
        padding-left: 2rem;
        padding-right: 2rem;
      }

      .docs-content h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.75rem; color: #0f172a; }
      .docs-content h2 { font-size: 1.25rem; font-weight: 650; margin: 2.25rem 0 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--docs-border); color: #0f172a; scroll-margin-top: 5rem; }
      .docs-content h2:first-of-type { border-top: 0; padding-top: 0; }
      .docs-content h3 { font-size: 1.05rem; font-weight: 600; margin: 1.5rem 0 0.5rem; color: #0f172a; scroll-margin-top: 5rem; }
      .docs-content p, .docs-content ul, .docs-content ol { margin: 0.75rem 0; line-height: 1.7; color: #334155; }
      .docs-content a { color: var(--docs-accent); text-decoration: underline; text-underline-offset: 2px; }
      .docs-content code { font-size: 0.85em; background: #f1f5f9; padding: 0.15em 0.4em; border-radius: 0.3rem; color: #0f172a; }
      .docs-content pre { background: #0f172a; border: 1px solid #1e293b; color: #e2e8f0; padding: 1rem 1.15rem; border-radius: 0.75rem; overflow-x: auto; font-size: 0.8rem; line-height: 1.55; margin: 1rem 0; }
      .docs-content pre code { background: transparent; padding: 0; color: inherit; }
      .docs-content table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin: 1rem 0; }
      .docs-content th, .docs-content td { border: 1px solid var(--docs-border); padding: 0.55rem 0.75rem; text-align: left; }
      .docs-content th { background: #f8fafc; font-weight: 600; }
      .docs-content ul { list-style: disc; padding-left: 1.25rem; }
      .docs-content ol { list-style: decimal; padding-left: 1.25rem; }

      @media (max-width: 1100px) { .docs-toc { display: none; } }
      @media (max-width: 768px) {
        .docs-sidebar { display: none; }
        .docs-content { padding: 1.5rem 1.25rem; }
        .docs-footer-wrap .site-footer-inner { padding-left: 1.25rem; padding-right: 1.25rem; }
      }
    </style>
    @stack('head')
</head>
<body class="docs-body min-h-full" x-data="{ mobile: false }">
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
        <div class="docs-footer-wrap">
            @include('components.site.footer')
        </div>
    </div>
</div>

<script>
(function () {
  var content = document.querySelector('.docs-content');
  var nav = document.getElementById('docs-toc-links');
  var back = document.getElementById('docs-back-top');
  if (!content || !nav) return;

  if (nav.children.length === 0) {
    var heads = content.querySelectorAll('h2, h3');
    var html = '';
    heads.forEach(function (h, i) {
      if (!h.id) {
        h.id = 'sec-' + i + '-' + (h.textContent || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 48);
      }
      var isH3 = h.tagName === 'H3';
      html += '<a href="#' + h.id + '" data-toc-id="' + h.id + '" class="' + (isH3 ? 'toc-h3' : '') + '">' + (h.textContent || '').trim() + '</a>';
    });
    nav.innerHTML = html || '<span style="color:#94a3b8;font-size:12px;padding-left:1rem">No sections</span>';
  }

  var links = Array.prototype.slice.call(nav.querySelectorAll('a[data-toc-id], a[href^="#"]'));
  var sections = links.map(function (a) {
    var id = a.getAttribute('data-toc-id') || (a.getAttribute('href') || '').slice(1);
    return document.getElementById(id);
  }).filter(Boolean);

  function setActive() {
    var y = window.scrollY + 100;
    var current = sections[0];
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].offsetTop <= y) current = sections[i];
    }
    links.forEach(function (a) {
      var id = a.getAttribute('data-toc-id') || (a.getAttribute('href') || '').slice(1);
      a.classList.toggle('active', current && id === current.id);
    });
    if (back) back.hidden = window.scrollY < 240;
  }
  window.addEventListener('scroll', setActive, { passive: true });
  setActive();

  if (back) {
    back.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
})();
</script>
@stack('scripts')






<script src="/js/docs-toc.js" defer></script>
</body>
</html>
