@extends('layouts.docs')
@section('title', 'Octane · LuvuPay')
@section('guides')
@endsection
@section('content')
<h1 class="text-3xl font-extrabold">Laravel Octane</h1>
<p class="mt-2 text-muted max-w-3xl">LuvuPay is Octane-ready: request-scoped <code>AuditContext</code> / <code>AuditLogger</code>, and no reliance on Horizon.</p>

<section class="mt-8 rounded-2xl border bg-wash p-6 space-y-3 text-sm">
<h2 class="font-bold text-lg">Install</h2>
<pre class="text-xs  rounded-xl p-4 overflow-x-auto">composer require laravel/octane
php artisan octane:install
# choose frankenphp (recommended) or roadrunner / swoole</pre>
<p><strong>Windows + XAMPP:</strong> Swoole is often unavailable. Prefer <strong>WSL2</strong> + FrankenPHP, or keep <code>php artisan serve</code> for local UI work.</p>
</section>

<section class="mt-6 rounded-2xl border bg-wash p-6 space-y-3 text-sm">
<h2 class="font-bold text-lg">Run</h2>
<pre class="text-xs  rounded-xl p-4 overflow-x-auto"># .env
OCTANE_SERVER=frankenphp
QUEUE_CONNECTION=database   # or sync; NOT horizon

php artisan octane:start --server=frankenphp --port=8000 --watch

# separate terminal — queue worker (no Horizon)
php artisan queue:work --tries=3
php artisan schedule:work</pre>
</section>

<section class="mt-6 rounded-2xl border bg-wash p-6 text-sm">
<h2 class="font-bold text-lg">Octane safety in this codebase</h2>
<ul class="mt-3 list-disc pl-5 space-y-1 text-ink/80">
<li><code>SecurityServiceProvider</code> registers <code>AuditContext</code> and <code>AuditLogger</code> as <strong>scoped</strong> (fresh per request / job).</li>
<li>Avoid static mutable state in services.</li>
<li>Config/cache: run <code>php artisan config:cache</code> in production Octane deploys, then restart workers on change.</li>
<li>Database: Octane listeners disconnect DB between operations (see <code>config/octane.php</code>).</li>
</ul>
</section>

@endsection
