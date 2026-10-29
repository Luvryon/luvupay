{{-- Favicon set. Pass $faviconUrl to override with a merchant logo (hosted pages) --}}
@php($icon = $faviconUrl ?? null)
@if($icon)
    <link rel="icon" href="{{ $icon }}">
    <link rel="apple-touch-icon" href="{{ $icon }}">
@else
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/webp" sizes="48x48" href="{{ asset('images/favicon-48.webp') }}">
    <link rel="icon" type="image/webp" sizes="512x512" href="{{ asset('images/favicon-512.webp') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.webp') }}">
@endif
<meta name="theme-color" content="#2a0a5e">
