@extends('layouts.dashboard')

@section('title', 'Create Billing Statement — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.billing.index') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head">
        <h1>Create billing statement</h1>
        <p>Generate a quarterly billing statement for a client.</p>
    </div>

    @include('admin.billing._form', ['formMode' => 'create'])
@endsection
