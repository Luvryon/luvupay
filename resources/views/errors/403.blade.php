@extends('errors.layout')
@section('code', '403')
@section('headline', "You don't have access to this page")
@section('message', "Your account doesn't have permission for this area. Sign in with a different account, or ask a workspace owner to update your role.")
@section('primary_href', '/login')
@section('primary_label', 'Sign in with another account')
@section('secondary_href', '/')
@section('secondary_label', 'Back to home')
@section('glyph')
    <path d="M48.5 56v-8.5a11.5 11.5 0 0 1 23 0V56" fill="none" stroke="#09027b" stroke-width="5.5" stroke-linecap="round"/>
    <rect x="41" y="55" width="38" height="27" rx="7" fill="#09027b"/>
    <circle cx="60" cy="65.5" r="4" fill="#efdca8"/>
    <path d="M60 68v7" stroke="#efdca8" stroke-width="3.6" stroke-linecap="round"/>
@endsection
