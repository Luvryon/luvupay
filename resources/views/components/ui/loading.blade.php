{{-- Spinner state for any AJAX region. `show` is an Alpine expression, e.g. show="loading && !loaded" --}}
@props(['show' => 'loading', 'title' => 'Loading', 'hint' => null, 'compact' => false])
<div x-show="{{ $show }}" x-cloak class="lp-state" @if($compact) style="min-height:96px;padding:20px" @endif role="status" aria-live="polite">
    <span class="lp-spinner {{ $compact ? '' : 'lp-spinner--lg' }}"></span>
    <div><strong>{{ $title }}</strong>{{ $hint }}</div>
</div>
