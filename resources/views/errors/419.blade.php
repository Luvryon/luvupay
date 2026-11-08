@extends('errors.layout')
@section('code', '419')
@section('headline', 'This page expired')
@section('message', 'Your session timed out before the form was sent, so the request was not processed. Reload the page and submit it again.')
@section('primary_href', url()->previous())
@section('primary_label', 'Reload the page')
@section('secondary_href', '/')
@section('secondary_label', 'Back to home')
@section('glyph')
    <path d="M45 34h30M45 86h30" stroke="#09027b" stroke-width="5" stroke-linecap="round"/>
    <path d="M48 34c0 14 12 18 12 26s-12 12-12 26M72 34c0 14-12 18-12 26s12 12 12 26" fill="none" stroke="#09027b" stroke-width="4.4" stroke-linecap="round"/>
    <path d="M52 79c4-4.5 12-4.5 16 0v6.5H52z" fill="#c8a45d"/>
@endsection
