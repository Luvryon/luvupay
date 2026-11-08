@extends('errors.layout')
@section('code', '500')
@section('headline', 'Something broke on our side')
@section('message', 'We logged the error. Try again in a minute. If it keeps happening, contact support and tell us what you were doing.')
@section('primary_href', url()->current())
@section('primary_label', 'Try again')
@section('glyph')
    <path d="M65 28 45 65h13l-5 27 23-42H62z" fill="#be123c" stroke="#be123c" stroke-width="3" stroke-linejoin="round"/>
@endsection
