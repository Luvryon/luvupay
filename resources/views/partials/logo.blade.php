@php $size = (int) ($size ?? 32); @endphp
<span style="display:inline-flex;width:{{ $size }}px;height:{{ $size }}px;overflow:hidden;border-radius:8px;flex-shrink:0">
    <img src="{{ asset('images/logo-luvupay.png') }}" alt="LuvuPay" width="{{ $size }}" height="{{ $size }}" style="width:100%;height:100%;object-fit:cover;object-position:center;display:block" />
</span>
