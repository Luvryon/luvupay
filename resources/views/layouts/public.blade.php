<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LuvuPay')</title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
      body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f8fafc;color:#0f172a}
      .pub-nav{display:flex;align-items:center;justify-content:space-between;max-width:960px;margin:0 auto;padding:20px 24px}
      .pub-nav a{color:#64748b;text-decoration:none;font-size:.9rem;margin-left:16px}
      .pub-nav a:hover{color:#0f172a}
      .pub-brand{font-weight:700;color:#0f172a;text-decoration:none;font-size:1.05rem}
      .pub-main{max-width:720px;margin:0 auto;padding:24px 24px 64px}
    </style>
</head>
<body>
<nav class="pub-nav">
    <a class="pub-brand" href="/">LuvuPay</a>
    <div>
        <a href="{{ route('privacy') }}">Privacy</a>
        <a href="{{ route('terms') }}">Terms</a>
        <a href="/login">Sign in</a>
    </div>
</nav>
<main class="pub-main">@yield('content')</main>
</body>
</html>
