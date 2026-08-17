@extends('layouts.dashboard')

@section('title', $billing->periodTitle().' Billing — Egliane Accounting Services')

@section('content')
    @php
        $fromCollections = request('from') === 'collections';
    @endphp

    <a href="{{ $fromCollections ? route('client.collections.index') : route('client.billing.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        {{ $fromCollections ? 'Back to Collections' : 'Back to Billing' }}
    </a>

    <div class="page-head page-head-row no-print">
        <div>
            <h1>Billing statement</h1>
            <p>Period: {{ $billing->period_label }}</p>
        </div>
        <button type="button" class="btn btn-outline" onclick="window.print()">Download as PDF</button>
    </div>

    <div class="statement-wrap">
        @include('partials.billing-statement')
    </div>
@endsection
