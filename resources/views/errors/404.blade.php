@extends('errors.layout')
@section('code', '404')
@section('headline', "We can't find that page")
@section('message', 'The link may be old or mistyped. Check the address, or head back to the LuvuPay home page.')
@section('glyph')
    <circle cx="55" cy="55" r="17" fill="none" stroke="#09027b" stroke-width="5"/>
    <path d="M67.5 67.5 82 82" stroke="#09027b" stroke-width="6" stroke-linecap="round"/>
    <path d="M50 51a5.2 5.2 0 1 1 7.4 4.7c-1.7.8-2.4 1.9-2.4 3.5" fill="none" stroke="#c8a45d" stroke-width="3.6" stroke-linecap="round"/>
    <circle cx="55" cy="64.6" r="2" fill="#c8a45d"/>
@endsection
