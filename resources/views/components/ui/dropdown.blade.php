{{--
  Tailwind-style custom dropdown (no native <select> arrow → no double chevron).
  Usage:
    <x-ui.dropdown name="role" label="Role" :options="['admin'=>'Admin']" :value="old('role')" placeholder="Select…" />
--}}
@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'id' => null,
    'value' => null,
    'placeholder' => null,
    'options' => null,
    'required' => false,
])
@php
    $id = $id ?: ($name ? 'dd-'.$name : 'dd-'.substr(md5(uniqid('', true)), 0, 8));
    $error = ($name && isset($errors)) ? $errors->first($name) : null;
    $current = $value ?? ($name ? old($name) : null);
    $opts = [];
    if (is_array($options)) {
        foreach ($options as $optValue => $optLabel) {
            if (is_int($optValue)) { $optValue = $optLabel; }
            $opts[] = ['value' => (string) $optValue, 'label' => (string) $optLabel];
        }
    }
@endphp
<div
    class="field"
    x-data="{
        open: false,
        value: @js($current !== null ? (string) $current : ''),
        options: @js($opts),
        placeholder: @js($placeholder ?: 'Select…'),
        get label() {
            const o = this.options.find(x => x.value === this.value);
            return o ? o.label : this.placeholder;
        },
        pick(v) { this.value = v; this.open = false; },
        close() { this.open = false; }
    }"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-bad"> *</span>@endif</label>
    @endif

    @if($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="value" @if($required) required @endif>
    @endif

    <div class="relative">
        <button
            type="button"
            id="{{ $id }}"
            class="input flex w-full items-center justify-between gap-2 text-left cursor-pointer"
            style="appearance:none;-webkit-appearance:none;background-image:none"
            x-on:click="open = !open"
            x-bind:aria-expanded="open.toString()"
            aria-haspopup="listbox"
        >
            <span class="truncate" x-bind:style="!value ? 'color:var(--faint)' : ''" x-text="label"></span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="flex-shrink:0;opacity:.55;transition:transform .15s" x-bind:style="open ? 'transform:rotate(180deg);opacity:.55;flex-shrink:0' : 'opacity:.55;flex-shrink:0'"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <div
            x-show="open"
            x-cloak
            style="display:none"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="dd-menu"
            role="listbox"
        >
            @if($placeholder)
                <button type="button" class="dd-item" role="option" x-on:click="pick('')">{{ $placeholder }}</button>
            @endif
            <template x-for="opt in options" x-bind:key="opt.value">
                <button
                    type="button"
                    class="dd-item"
                    role="option"
                    x-bind:aria-selected="(value === opt.value).toString()"
                    x-bind:class="value === opt.value ? 'dd-item on' : 'dd-item'"
                    x-on:click="pick(opt.value)"
                    x-text="opt.label"
                ></button>
            </template>
            @if(!is_array($options))
                {{ $slot }}
            @endif
        </div>
    </div>

    @if($error)
        <p class="help text-bad">{{ $error }}</p>
    @elseif($hint)
        <p class="help">{{ $hint }}</p>
    @endif
</div>
