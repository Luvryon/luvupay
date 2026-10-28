@props(['size' => 28])
<span {{ $attributes->merge(['class' => 'lp-logo-wrap', 'style' => 'display:inline-flex;align-items:center;justify-content:center;width:'.(int)$size.'px;height:'.(int)$size.'px;flex-shrink:0;overflow:hidden;border-radius:8px']) }}>
    <img
        src="{{ asset('images/logo-luvupay.png') }}"
        alt="LuvuPay"
        width="{{ (int) $size }}"
        height="{{ (int) $size }}"
        style="width:100%;height:100%;object-fit:cover;object-position:center;display:block"
    />
</span>
