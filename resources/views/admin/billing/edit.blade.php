@extends('layouts.dashboard')

@section('title', 'Edit Billing Statement — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.billing.index') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head">
        <h1>Edit billing statement</h1>
        <p>{{ $billing->period_label }}</p>
    </div>

    @if(! $billing->isDraft())
        <div class="alert-strip">
            This billing has already been finalized — changes made now may not match what the client has seen.
        </div>
    @endif

    @include('admin.billing._form', ['formMode' => 'edit'])
@endsection

@push('scripts')
    <script src="/js/billing.js" defer></script>
@endpush
