@extends('layouts.dashboard')

@section('title', $billing->periodTitle().' — Receipt — Egliane Accounting Services')

@section('content')
    @php($client = $billing->client)

    <a href="{{ route('admin.billing.show', $client) }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to {{ $client?->business_name ?: $client?->name }}
    </a>

    <div class="page-head page-head-row no-print">
        <div>
            <h1>Billing statement</h1>
            <p>Period: {{ $billing->period_label }}</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('admin.billing.csv', $billing) }}" class="btn btn-outline">Download CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Download as PDF</button>
        </div>
    </div>

    <div class="statement-wrap">
        @include('partials.billing-statement')
    </div>
@endsection
