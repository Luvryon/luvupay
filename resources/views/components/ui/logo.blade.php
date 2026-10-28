@props(['size' => 28])
<img
    src="{{ asset('images/logo-luvupay-v2.png') }}"
    alt="LuvuPay"
    width="{{ (int) $size }}"
    height="{{ (int) $size }}"
    {{ $attributes->merge(['class' => 'lp-logo', 'style' => 'width:'.(int)$size.'px;height:'.(int)$size.'px;object-fit:contain;display:block;flex-shrink:0']) }}
/>
