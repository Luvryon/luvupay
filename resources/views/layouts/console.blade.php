@php
    $user = auth('merchant_web')->user();
    $allows = fn (?string $perm) => $perm === null || ($user && $user->allows($perm));
    $safeRoute = function (string $name, string $fallback = '#') {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name) : $fallback; }
        catch (\Throwable $e) { return $fallback; }
    };
    $item = fn (string $route, string $label, string $icon, string $match, ?string $can = null) => $allows($can)
        ? ['href' => $safeRoute($route), 'label' => $label, 'icon' => $icon, 'active' => request()->routeIs($match)] : null;
    $groups = [
        'Workspace' => array_filter([
            $item('dashboard', 'Overview', 'grid', 'dashboard'),
            $item('wallet', 'Wallet & Transactions', 'card', 'wallet'),
            $item('subscriptions', 'Subscriptions', 'users', 'subscriptions'),
            $item('analytics', 'Analytics', 'chart', 'analytics', 'analytics.read'),
        ]),
        'Account' => array_filter([
            $item('team.index', 'Team', 'users', 'team.*', 'team.manage'),
            $item('security.index', 'Security', 'shield', 'security.*'),
            $item('settings', 'Settings', 'cog', 'settings*', 'settings.read'),
        ]),
        'Resources' => [
            ['href' => url('/marketplace'), 'label' => 'Marketplace', 'icon' => 'store', 'active' => false, 'external' => true],
            ['href' => url('/docs'), 'label' => 'Documentation', 'icon' => 'book', 'active' => false, 'external' => true],
            ['href' => url('/status'), 'label' => 'System status', 'icon' => 'pulse', 'active' => false, 'external' => true],
        ],
    ];
    $driver = config('luvupay.payment.driver', 'mock');
    [$envLabel, $envTone] = app()->environment('production') ? ['Live', 'ok'] : ($driver === 'mock' ? ['Mock', 'warn'] : ['Sandbox', 'brand']);
    $displayName = $user->name ?? $user->email ?? 'Merchant';
    $merchantLogo = null;
    try {
        $m = $user?->merchant ?? null;
        if ($m && ! empty($m->logo_path)) {
            $merchantLogo = asset('storage/'.$m->logo_path);
        }
    } catch (\Throwable $e) {}
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'">
    <title>@yield('title', 'Console') · LuvuPay</title>
    <link rel="stylesheet" href="/css/ui.css?v={{ @filemtime(public_path('css/ui.css')) }}">
    @stack('head')
        <style id="console-theme">
      :root{--bg:#f5f5f8;--ink:#16171d;--muted:#5d6272;--faint:#8e93a3;--line:#e6e7ed;--brand:#5b3df5;--brand-700:#4a2ed6;--brand-50:#f0edff;--deep:#1b1442;--ok:#0f8f5f;--r:14px}
      html{color-scheme:light!important}
      body.console-light{margin:0;background:var(--bg)!important;background-image:none!important;color:var(--ink)!important;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif!important;font-size:15px;line-height:1.5}
      body.console-light *{box-sizing:border-box}
      body.console-light .bg-surface,body.console-light .bg-canvas{background:#fff!important}
      body.console-light .bg-black\/30,body.console-light .bg-black\/40,body.console-light .bg-white\/4,body.console-light .bg-white\/6{background:var(--bg)!important}
      body.console-light .border-edge,body.console-light .border-line{border-color:var(--line)!important}
      body.console-light .text-white,body.console-light .text-ink{color:var(--ink)!important}
      body.console-light .text-muted{color:var(--muted)!important}body.console-light .text-faint{color:var(--faint)!important}
      body.console-light .text-brand-soft{color:var(--brand)!important}
      body.console-light input,body.console-light select,body.console-light textarea{background:#fff!important;color:var(--ink)!important;border-color:#d5d7e0!important}
      .app{display:flex;min-height:100vh}
      .side{display:none;flex-direction:column;flex:none;width:256px;height:100vh;position:sticky;top:0;background:#fff;border-right:1px solid var(--line)}
      @media(min-width:1024px){.side{display:flex}.burger{display:none!important}}
      .brand{display:flex;align-items:center;gap:10px;height:68px;padding:0 24px;font-size:1.1rem;font-weight:700;letter-spacing:-.02em;color:var(--ink)!important;text-decoration:none}
      .nav{flex:1;overflow-y:auto;padding:8px 16px 16px}
      .nav-group{margin-bottom:24px}
      .nav-h{margin:0;padding:0 12px 8px;font-size:.78rem;font-weight:600;color:var(--faint)}
      .nav-a{display:flex;align-items:center;gap:12px;padding:10px 12px;margin-bottom:2px;border-radius:10px;font-size:.92rem;font-weight:500;color:var(--muted)!important;text-decoration:none}
      .nav-a:hover{background:var(--bg);color:var(--ink)!important}
      .nav-a.on{background:var(--brand-50);color:var(--brand)!important;font-weight:600}
      .nav-ext{margin-left:auto;font-size:.75rem;color:var(--faint)}
      .side-foot{display:grid;gap:14px;padding:16px 20px 20px;border-top:1px solid var(--line)}
      .who{display:flex;align-items:center;gap:12px;min-width:0}
      .avatar{display:grid;place-items:center;flex:none;width:36px;height:36px;border-radius:50%;background:var(--deep);color:#fff;font-size:.8rem;font-weight:600}
      .who b{display:block;font-size:.88rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .who span{display:block;font-size:.75rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;background:var(--bg);color:var(--muted)}
      .pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
      .pill.ok{background:#e8f7f0;color:var(--ok)}.pill.warn{background:#fff4dc;color:#a65f00}.pill.brand{background:var(--brand-50);color:var(--brand)}
      .main{display:flex;flex-direction:column;flex:1;min-width:0}
      
      .nav-search{position:relative;flex:1;min-width:160px;max-width:420px;margin-left:12px}
      .nav-search-btn{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border:1px solid #d0d5dd;border-radius:12px;background:#f8fafc;color:#64748b;font-size:.875rem;cursor:pointer;box-shadow:0 1px 2px rgba(15,23,42,.04)}
      .nav-search-btn:hover{border-color:#94a3b8;color:#0f172a;background:#fff}
      .nav-search-btn span{flex:1;text-align:left}
      .nav-search-btn kbd,.nav-search-input-wrap kbd{font-size:.7rem;font-family:inherit;padding:2px 6px;border-radius:6px;border:1px solid #e2e8f0;background:#fff;color:#94a3b8}
      .nav-search-panel{position:absolute;left:0;right:0;top:0;z-index:80;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 12px 40px rgba(15,23,42,.12)}
      .nav-search-input-wrap{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #e2e8f0}
      .nav-search-input{flex:1;border:0;outline:0;font:inherit;font-size:.9rem;background:transparent;color:#0f172a}
      .nav-search-list{list-style:none;margin:0;padding:6px;max-height:280px;overflow:auto}
      .nav-search-item{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:8px;text-decoration:none;color:#0f172a;font-size:.875rem;font-weight:500}
      .nav-search-item:hover{background:#f8fafc}
      .nav-search-meta{font-size:.75rem;color:#94a3b8;font-weight:400}
      .nav-search-empty{padding:16px;text-align:center;color:#64748b;font-size:.875rem}

      
      .ns-trigger{display:flex;align-items:center;gap:8px;width:100%;height:38px;padding:0 12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#94a3b8;font-size:.8125rem;cursor:pointer;transition:border-color .15s,box-shadow .15s}
      .ns-trigger:hover{border-color:#c7d2fe;color:#64748b;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
      .ns-trigger span{flex:1;text-align:left;font-weight:500}
      .ns-trigger kbd,.ns-row kbd{font-size:10px;font-family:inherit;padding:2px 6px;border-radius:5px;border:1px solid #e2e8f0;background:#f8fafc;color:#94a3b8;line-height:1.4}
      .ns-panel{position:absolute;left:0;right:0;top:0;z-index:100;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 16px 40px rgba(15,23,42,.14);overflow:hidden}
      .ns-row{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#64748b}
      .ns-row input{flex:1;border:0;outline:0;font:inherit;font-size:.875rem;color:#0f172a;background:transparent}
      .ns-list{list-style:none;margin:0;padding:6px;max-height:260px;overflow:auto}
      .ns-list a{display:flex;justify-content:space-between;gap:12px;padding:9px 12px;border-radius:8px;text-decoration:none;color:#0f172a;font-size:.8125rem;font-weight:500}
      .ns-list a:hover{background:#f8fafc}
      .ns-meta{font-size:.7rem;color:#94a3b8;font-weight:400}
      .ns-empty{padding:14px;text-align:center;color:#94a3b8;font-size:.8125rem}

      .top{position:sticky;top:0;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:12px;height:68px;padding:0 40px;background:rgba(255,255,255,.92);border-bottom:1px solid var(--line);backdrop-filter:blur(12px)}
      .crumb{font-size:.88rem;color:var(--muted)}.crumb b{color:var(--ink);font-weight:600}
      
      
      
      
      
      
      
      
      
      
      
      
      .burger{border:0;background:none;padding:8px;margin-left:-8px;color:var(--muted)}
      .page{width:100%;max-width:1280px;margin:0 auto;padding:40px}
      @media(max-width:640px){.page{padding:24px 16px}.top{padding:0 16px}}
      .drawer{position:fixed;inset:0;z-index:50;display:flex}
      .drawer .panel{display:flex;flex-direction:column;width:280px;background:#fff;box-shadow:0 20px 60px rgba(20,21,26,.2)}
      .drawer .scrim{flex:1;background:rgba(20,21,26,.4)}
      .stack{display:grid;gap:32px}.stack-sm{display:grid;gap:20px}
      .row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px}
      .cols{display:grid;gap:24px}
      @media(min-width:1024px){.cols-wallet{grid-template-columns:2fr 1fr}.cols-ledger{grid-template-columns:3fr 2fr}}
      .page-title{margin:0;font-size:1.75rem;font-weight:700;letter-spacing:-.03em;line-height:1.2}
      .page-sub{margin:6px 0 0;font-size:.92rem;color:var(--muted)}
      .c-card{background:#fff;border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
      .c-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 24px;border-bottom:1px solid var(--line)}
      .c-pad{padding:24px}
      .c-title{margin:0;font-size:1rem;font-weight:650}.c-sub{margin:2px 0 0;font-size:.82rem;color:var(--muted)}
      .c-empty{padding:40px 24px;text-align:center;font-size:.88rem;color:var(--faint)}
      .c-item{display:flex;gap:12px;padding:16px 24px;border-bottom:1px solid var(--line);font-size:.9rem}.c-item:last-child{border-bottom:0}
      .c-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 18px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink)!important;font:600 .88rem/1.2 inherit;cursor:pointer;text-decoration:none}
      .c-btn:hover{background:var(--bg)}.c-btn:disabled{opacity:.4;cursor:not-allowed}
      .c-btn-sm{padding:7px 12px;font-size:.8rem}
      .c-btn-brand{background:var(--brand);border-color:var(--brand);color:#fff!important}.c-btn-brand:hover{background:var(--brand-700)}
      .c-btn-light{background:#fff;border-color:#fff;color:var(--deep)!important}.c-btn-light:hover{background:var(--brand-50)}
      .c-btn-warn{background:#fff4dc;border-color:#f6d58a;color:#8a5200!important}.c-btn-warn:hover{background:#ffeab8}
      .c-btn-bad{background:#fff0f1;border-color:#f7c3c8;color:#b4232f!important}.c-btn-bad:hover{background:#ffe1e4}
      .c-alert{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-radius:12px;border:1px solid #f6d58a;background:#fff9e8;color:#6b4300;font-size:.9rem}
      .c-alert a{color:#6b4300!important;font-weight:600;text-decoration:underline}
      .c-wallet{display:flex;flex-direction:column;justify-content:space-between;gap:40px;padding:32px;border-radius:var(--r);background:var(--deep);color:#fff}
      .c-wallet p{margin:0}.c-wallet .dim{color:rgba(255,255,255,.62);font-size:.9rem}
      .c-wallet .amt{margin:10px 0 12px;font-size:3rem;font-weight:700;letter-spacing:-.04em;line-height:1.05}
      .c-wallet .act{display:flex;flex-wrap:wrap;align-items:center;gap:16px}
      .c-kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--line)}
      @media(min-width:1024px){.c-kpis{grid-template-columns:repeat(5,1fr)}}
      .c-kpi{background:#fff;padding:22px 24px}
      .c-kpi .l{font-size:.85rem;color:var(--muted)}.c-kpi .v{margin:8px 0 4px;font-size:1.6rem;font-weight:700;letter-spacing:-.03em}.c-kpi .n{font-size:.76rem;color:var(--faint)}
      .c-tabs{display:inline-flex;gap:4px;padding:4px;background:#e9e9f0;border-radius:12px;justify-self:start}
      .c-tab{padding:8px 18px;border:0;border-radius:9px;background:none;font:600 .88rem/1 inherit;color:var(--muted);cursor:pointer}
      .c-tab[aria-selected=true]{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(20,21,26,.12)}
      .c-table{width:100%;border-collapse:collapse;font-size:.88rem}
      .c-table th{padding:12px 24px;text-align:left;font-size:.78rem;font-weight:600;color:var(--muted);background:var(--bg)}
      .c-table td{padding:14px 24px;border-top:1px solid var(--line)}
      .mono{font-family:ui-monospace,Menlo,monospace;font-size:.78rem}
      .c-log{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--line);font:.76rem ui-monospace,Menlo,monospace}
      .h-tx{height:440px}.h-rs{height:340px}
      .prev{display:flex;align-items:center;gap:14px;margin-top:20px}
      .prev .avatar{background:var(--brand-50);color:var(--brand);width:44px;height:44px}
      .chip{padding:4px 10px;border-radius:8px;background:var(--bg);font-size:.78rem;font-weight:600;color:var(--muted)}
      .box{margin-top:16px;padding:14px 16px;border-radius:10px;background:var(--bg);font-size:.88rem}
      .bar{height:6px;border-radius:99px;background:#e6e7ed;overflow:hidden}.bar>i{display:block;height:100%;background:var(--brand);transition:width .5s}
      .usage{width:190px}.usage .t{display:flex;justify-content:space-between;margin-bottom:6px;font-size:.78rem;color:var(--muted)}
      @media(max-width:640px){.usage{display:none}}
      .demo{border-style:dashed;border-color:#e3b64d;background:#fffdf6}
      .demo-bar{display:flex;align-items:center;gap:10px;padding:12px 24px;background:#fff4dc;border-bottom:1px dashed #e3b64d;font-size:.82rem;color:#7a4a00}
      .demo-body{padding:24px;display:grid;gap:20px}
      .demo-btns{display:flex;flex-wrap:wrap;gap:12px}
      .ag-theme-quartz,.ag-theme-quartz-dark{--ag-background-color:#fff;--ag-header-background-color:#f5f5f8;--ag-odd-row-background-color:#fff;--ag-row-hover-color:#f0edff;--ag-selected-row-background-color:#e5dfff;--ag-border-color:#e6e7ed;--ag-header-foreground-color:#5d6272;--ag-foreground-color:#16171d;--ag-font-size:13px;--ag-cell-horizontal-padding:20px;--ag-wrapper-border-radius:0;border:0!important}

      /* ---- forms, tags, settings-style pages ---- */
      .narrow{max-width:760px;margin:0 auto}
      .field{display:grid;gap:6px;min-width:0}
      .label{font-size:.85rem;font-weight:600;color:var(--ink)}
      .help{margin:0;font-size:.8rem;color:var(--muted)}
      .input{width:100%;padding:11px 14px;border:1px solid #d5d7e0;border-radius:10px;background:#fff;font:inherit;font-size:.92rem;color:var(--ink)}
      select.input,select{-webkit-appearance:none;appearance:none;background-image:none;padding-right:14px}
      select.input::-ms-expand{display:none}
      .input::placeholder{color:var(--faint)}
      .input:focus{outline:0;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-50)}
      .form-row{display:grid;gap:16px;align-items:end}
      @media(min-width:640px){.fr-2{grid-template-columns:1fr auto}.fr-3{grid-template-columns:1fr 190px auto}}
      .tag{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:600;background:var(--bg);color:var(--muted)}
      .tag.dot::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
      .tag.ok{background:#e8f7f0;color:var(--ok)}.tag.warn{background:#fff4dc;color:#a65f00}.tag.bad{background:#fff0f1;color:#b4232f}.tag.dark{background:var(--deep);color:#fff}
      .link-bad{border:0;background:none;padding:4px 0;font:600 .82rem/1 inherit;color:#b4232f;cursor:pointer}.link-bad:hover{text-decoration:underline}
      .member{display:flex;align-items:center;gap:12px}
      .member .avatar{background:var(--brand-50);color:var(--brand)}
      .member b{display:block;font-weight:600}.member span{display:block;font-size:.8rem;color:var(--muted)}
      .select-sm{padding:7px 10px;border:1px solid #d5d7e0;border-radius:8px;background:#fff;font:inherit;font-size:.88rem;color:var(--ink)}
      .kv{display:grid;gap:12px}
      @media(min-width:640px){.kv-2{grid-template-columns:1fr 1fr}.kv-2 .span{grid-column:1/-1}}
      .kv>div{padding:14px 16px;border-radius:10px;background:var(--bg);min-width:0}
      .kv dt{font-size:.78rem;color:var(--muted)}.kv dd{margin:4px 0 0;font:.82rem ui-monospace,Menlo,monospace;word-break:break-all}
      .codes{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px}
      .codes code{padding:9px 12px;border-radius:8px;background:#fff;border:1px solid #f6d58a;font:.82rem ui-monospace,Menlo,monospace}
      .secret{display:block;padding:14px 16px;border-radius:10px;background:var(--bg);border:1px solid var(--line);font:.95rem ui-monospace,Menlo,monospace;word-break:break-all}
      .step{display:grid;gap:10px;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid var(--line)}.step:last-child{border:0;margin:0;padding:0}
      .step-n{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--brand-50);color:var(--brand);font-size:.78rem;font-weight:700;margin-right:8px}
      .code-in{width:150px;text-align:center;letter-spacing:.3em;font-family:ui-monospace,Menlo,monospace}
      .rows>div{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:16px 24px;border-bottom:1px solid var(--line)}.rows>div:last-child{border:0}
      .sect{display:grid;gap:24px}
      @media(min-width:1024px){.settings{display:grid;grid-template-columns:200px 1fr;gap:40px;align-items:start}.team-cols{display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start}}
      .snav{display:none;position:sticky;top:96px}
      @media(min-width:1024px){.snav{display:block}}
      .snav a{display:block;padding:9px 12px;margin-bottom:2px;border-radius:8px;font-size:.9rem;font-weight:500;color:var(--muted)!important;text-decoration:none}
      .snav a:hover{background:#fff;color:var(--ink)!important}
      .c-danger{border-color:#f7c3c8}.c-danger .c-head{background:#fff8f8;border-color:#f7c3c8}
      .ep{padding:20px;border:1px solid var(--line);border-radius:12px;display:grid;gap:16px}
      .ep+.ep{margin-top:16px}

      /* ---- modal ---- */
      .modal{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:16px}
      .modal-scrim{position:absolute;inset:0;background:rgba(20,21,26,.55);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
      .modal-box{position:relative;z-index:1;display:flex;flex-direction:column;width:100%;max-height:88vh;overflow:hidden;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 24px 70px rgba(20,21,26,.35)}
      .modal-sm{max-width:400px}.modal-md{max-width:480px}.modal-lg{max-width:680px}.modal-xl{max-width:900px}
      .modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 24px;border-bottom:1px solid var(--line);flex-shrink:0}
      .modal-head h3{margin:0;font-size:1rem;font-weight:650;color:var(--ink)}
      .modal-x{display:grid;place-items:center;width:32px;height:32px;border:0;border-radius:8px;background:none;color:var(--muted);cursor:pointer}.modal-x:hover{background:var(--bg);color:var(--ink)}.modal-x:disabled{opacity:.4;cursor:not-allowed}
      .modal-body{overflow-y:auto;flex:1;min-height:0}
      .modal-foot{display:flex;gap:12px;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--line);background:var(--bg);flex-shrink:0}
      .m-enter{transition:opacity .2s ease-out,transform .2s ease-out}.m-from{opacity:0;transform:translateY(12px) scale(.97)}.m-to{opacity:1;transform:none}
      .pbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;padding:16px 24px}
      .pbar small{display:block;font-size:.78rem;color:var(--muted)}.pbar b{font-size:.95rem;font-weight:650}
      .switch-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid var(--line);border-radius:10px;background:var(--bg)}
      .switch{position:relative;flex:none;width:44px;height:24px;border:0;border-radius:99px;background:#c9ccd8;cursor:pointer;transition:background .2s}
      .switch::after{content:"";position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s}
      .switch.on{background:var(--ok)}.switch.on::after{transform:translateX(20px)}
      .dd-menu{position:absolute;left:0;right:0;z-index:50;margin-top:6px;max-height:15rem;overflow:auto;border-radius:12px;border:1px solid var(--line);background:#fff;padding:4px 0;box-shadow:0 12px 32px rgba(20,21,26,.14)}
      .dd-item{display:flex;width:100%;padding:10px 14px;text-align:left;font-size:.9rem;color:var(--ink);background:none;border:0;cursor:pointer}
      .dd-item:hover{background:var(--bg)}.dd-item.on{background:var(--brand-50);color:var(--brand);font-weight:600}
      .toast-pop{position:fixed;top:16px;right:16px;z-index:200;padding:14px 18px;border-radius:12px;background:#fff;border:1px solid #bfe7d4;color:#0b6b47;font-size:.9rem;font-weight:600;box-shadow:0 12px 32px rgba(20,21,26,.18)}
    </style>

<style id="toastify-luvu">
.luvu-toasts{position:fixed;top:1rem;right:1rem;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;max-width:min(100vw - 2rem,380px)}
.luvu-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:12px;background:#fff;border:1px solid #e6e7ed;box-shadow:0 12px 40px rgba(15,23,42,.12);color:#16171d;font-size:14px;line-height:1.45;animation:luvuIn .28s ease}
.luvu-toast.leaving{opacity:0;transform:translateX(12px);transition:.3s ease}
.luvu-toast .ico{font-size:18px;line-height:1}
.luvu-toast .msg{flex:1;font-weight:500}
@keyframes luvuIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
</style>

</head>
<body class="console-light" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
<a href="#main" class="sr-only">Skip to content</a>
<div class="app">
    <aside class="side">
        <a href="{{ route('dashboard') }}" class="brand"><x-ui.logo :size="30" /> LuvuPay</a>
        <x-console.nav :groups="$groups" />
        <div class="side-foot">
            <div class="row" style="gap:8px"><span style="font-size:.8rem;color:var(--muted)">Environment</span><span class="pill {{ $envTone }}">{{ $envLabel }}</span></div>
            <div class="who">
                @if(!empty($merchantLogo))
                    <img src="{{ $merchantLogo }}" alt="" class="merchant-logo" style="width:36px;height:36px;border-radius:10px;object-fit:contain;border:1px solid var(--line);background:#fff;flex-shrink:0" />
                @endif<span class="avatar">{{ strtoupper(substr($displayName, 0, 1)) }}</span><div style="min-width:0"><b>{{ $displayName }}</b><span>{{ $user->email ?? '' }}</span></div></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="c-btn c-btn-sm" style="width:100%">Sign out</button></form>
        </div>
    </aside>

    <div class="drawer" x-show="drawer" x-cloak style="display:none">
        <div class="panel">
            <div class="row" style="padding:0 20px;height:68px;border-bottom:1px solid var(--line);flex-wrap:nowrap"><span class="brand" style="padding:0;height:auto"><x-ui.logo :size="26" /> LuvuPay</span><button type="button" class="burger" x-on:click="drawer = false" aria-label="Close"><x-ui.icon name="x" /></button></div>
            <x-console.nav :groups="$groups" />
            <form method="post" action="{{ route('logout') }}" class="side-foot">@csrf<button type="submit" class="c-btn c-btn-sm">Sign out</button></form>
        </div>
        <div class="scrim" x-on:click="drawer = false"></div>
    </div>

    <div class="main">
        <header class="top">
            <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
                <button type="button" class="burger" x-on:click="drawer = true" aria-label="Open menu"><x-ui.icon name="menu" /></button>
                <p class="crumb" style="margin:0;flex-shrink:0">Console / <b>@yield('heading', 'Overview')</b></p>
                <div class="nav-search" id="luvupay-search" style="position:relative;flex:1;min-width:180px;max-width:360px;margin-left:8px">
                    <button type="button" class="ns-trigger" id="luvupay-search-btn" aria-label="Open search">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        <span>Search…</span>
                        <kbd>Ctrl&nbsp;K</kbd>
                    </button>
                    <div class="ns-panel" id="luvupay-search-panel" hidden>
                        <div class="ns-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            <input type="search" id="luvupay-search-input" placeholder="Search pages and actions…" autocomplete="off" />
                            <kbd id="luvupay-search-esc" style="cursor:pointer">Esc</kbd>
                        </div>
                        <ul class="ns-list" id="luvupay-search-list"></ul>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">@yield('header_actions')<span class="pill {{ $envTone }}">{{ $envLabel }}</span></div>
        </header>
        <main id="main" class="page">
            @if(session('status'))<div class="c-alert" style="margin-bottom:24px">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="c-alert" style="margin-bottom:24px;background:#fff0f1;border-color:#f7c3c8;color:#8f1d27">@if($errors->count() === 1){{ $errors->first() }}@else<ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>@endif
            @yield('content')
        </main>
    </div>
</div>

<script src="/js/ui.js?v={{ @filemtime(public_path('js/ui.js')) }}" defer></script>



@stack('scripts')

<script src="/js/console-search.js" defer></script>
<script src="/js/alpine.min.js" defer></script>
</body>
</html>
