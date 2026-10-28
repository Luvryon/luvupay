{{-- LuvuPay logo mark. Usage: @include('partials.logo', ['size' => 28]) --}}
@php($size = (int) ($size ?? 32))
<img src="{{ asset('images/logo-mark.png') }}" alt="" width="{{ $size }}" height="{{ $size }}"
     style="width:{{ $size }}px;height:{{ $size }}px;border-radius:{{ round($size * 0.28) }}px;display:block;flex-shrink:0">
