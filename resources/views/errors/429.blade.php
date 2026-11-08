@extends('errors.layout')
@section('code', '429')
@section('headline', 'Too many requests')
@section('message', "You've sent a lot of requests in a short time. Wait a minute, then try again.")
@section('primary_href', url()->current())
@section('primary_label', 'Try again')
@section('glyph')
    <path d="M36 77a28 28 0 1 1 48 0" fill="none" stroke="#09027b" stroke-width="5.5" stroke-linecap="round"/>
    <path d="M60 72 74 52" stroke="#be123c" stroke-width="5" stroke-linecap="round"/>
    <circle cx="60" cy="72" r="6" fill="#09027b"/>
@endsection
