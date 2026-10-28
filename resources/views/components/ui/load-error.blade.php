{{-- Error state with retry for AJAX regions. `show` is an Alpine expression, `retry` an Alpine call --}}
@props(['show' => 'error', 'title' => 'Could not load data', 'retry' => 'load()'])
<div x-show="{{ $show }}" x-cloak class="lp-state" role="alert">
    <div><strong>{{ $title }}</strong>Check your connection and try again</div>
    <button type="button" class="c-btn c-btn-sm" x-on:click="{{ $retry }}">Try again</button>
</div>
