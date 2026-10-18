@props(['action', 'method' => 'POST', 'message'])
{{-- Destructive/irreversible actions: the prompt text lives in a data attribute (escaped by Blade) and is read by ui.js. No inline JS. --}}
<form method="post" action="{{ $action }}" data-confirm="{{ $message }}" {{ $attributes }}>
    @csrf
    @if(strtoupper($method) !== 'POST')@method($method)@endif
    {{ $slot }}
</form>
