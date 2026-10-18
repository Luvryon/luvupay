@props(['show', 'title' => null, 'size' => 'md', 'locked' => 'false', 'tone' => 'default'])
{{--
  Modal for console (light) theme.
  Uses layout CSS: .modal / .modal-scrim / .modal-box / .modal-head / .modal-body / .modal-x
  plus size classes .modal-sm|md|lg|xl defined in layouts.console.
  $show / $locked are Alpine expressions (developer-controlled).
--}}
@php
    $width = ['sm' => 'modal-sm', 'md' => 'modal-md', 'lg' => 'modal-lg', 'xl' => 'modal-xl'][$size] ?? 'modal-md';
@endphp
<div
    x-show="{{ $show }}"
    x-cloak
    style="display:none"
    class="modal"
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="modal-title-{{ md5((string) $title) }}" @endif
    x-on:keydown.escape.window="if (!({{ $locked }})) {{ $show }} = false"
>
    <div class="modal-scrim" x-on:click="if (!({{ $locked }})) {{ $show }} = false"></div>

    <div
        x-show="{{ $show }}"
        x-transition:enter="m-enter"
        x-transition:enter-start="m-from"
        x-transition:enter-end="m-to"
        class="modal-box {{ $width }}"
        x-on:click.stop
    >
        @isset($header)
            <div class="modal-head">{{ $header }}</div>
        @elseif($title)
            <div class="modal-head">
                <h3 id="modal-title-{{ md5((string) $title) }}">{{ $title }}</h3>
                <button
                    type="button"
                    class="modal-x"
                    x-bind:disabled="{{ $locked }}"
                    x-on:click="{{ $show }} = false"
                    aria-label="Close"
                >
                    <x-ui.icon name="x" :size="18" />
                </button>
            </div>
        @endisset

        <div class="modal-body">{{ $slot }}</div>

        @isset($footer)
            <div class="modal-foot">{{ $footer }}</div>
        @endisset
    </div>
</div>
