@props(['label' => null, 'name' => null, 'hint' => null, 'id' => null, 'value' => null, 'placeholder' => null, 'options' => null, 'required' => false])
<x-ui.dropdown
    :label="$label"
    :name="$name"
    :hint="$hint"
    :id="$id"
    :value="$value"
    :placeholder="$placeholder"
    :options="$options"
    :required="$required"
    {{ $attributes }}
>{{ $slot }}</x-ui.dropdown>
