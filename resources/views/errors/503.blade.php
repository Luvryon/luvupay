@extends('errors.layout')
@section('code', '503')
@section('headline', 'LuvuPay is under maintenance')
@section('message', "We're making an update and will be back shortly. Try again in a few minutes.")
@section('primary_href', url()->current())
@section('primary_label', 'Try again')
@section('glyph')
    <circle cx="60" cy="60" r="13" fill="none" stroke="#09027b" stroke-width="5"/>
    <g stroke="#09027b" stroke-width="7" stroke-linecap="round">
        <path d="M60 36v7" /><path d="M60 77v7"/><path d="M36 60h7"/><path d="M77 60h7"/>
        <path d="M43 43l5 5"/><path d="M72 72l5 5"/><path d="M77 43l-5 5"/><path d="M48 72l-5 5"/>
    </g>
    <circle cx="60" cy="60" r="4.5" fill="#c8a45d"/>
@endsection
