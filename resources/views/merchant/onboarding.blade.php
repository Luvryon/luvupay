@extends('layouts.console')
@section('title', 'Onboarding')
@section('heading', 'Onboarding')
@section('content')
@php
    $paypalConnected = (bool) ($paypalConnected ?? false);
    $hasProject = (bool) ($hasProject ?? false);
@endphp

{{-- Full-screen wizard until complete --}}
<x-ui.onboarding-wizard :paypal-connected="$paypalConnected" :has-project="$hasProject" />

{{-- Checklist remains for return visits --}}
<div class="mt-4 opacity-40 pointer-events-none" aria-hidden="true">
    <x-ui.page-header title="Onboarding checklist" subtitle="Complete PayPal OAuth before production traffic" />
</div>
@endsection
