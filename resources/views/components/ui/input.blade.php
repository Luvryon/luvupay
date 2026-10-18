@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'type' => 'text',
    'id' => null,
    'value' => null,
])
@php
    $id = $id ?: ($name ? 'f-'.$name : 'f-'.substr(md5(uniqid('', true)), 0, 8));
    $error = ($name && isset($errors)) ? $errors->first($name) : null;

    // Never put objects/arrays into the value attribute (avoids "[object HTMLInputElement]")
    $safeValue = null;
    if ($value !== null && (is_string($value) || is_numeric($value) || is_bool($value))) {
        $safeValue = (string) $value;
    }

    // Strip value from bag; pass-through Alpine directives (x-model, :value, @input, …)
    $attrs = $attributes
        ->except(['value'])
        ->class('block w-full rounded-xl border border-edge bg-black/30 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none disabled:opacity-60 read-only:text-muted');
@endphp
<div class="space-y-1.5">
    @if($label)
        <label for="{{ $id }}" class="block text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</label>
    @endif
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        @if($error) aria-invalid="true" @endif
        @if($safeValue !== null) value="{{ $safeValue }}" @endif
        {{ $attrs }}
    >
    @if($error)
        <p class="text-xs text-bad">{{ $error }}</p>
    @elseif($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
