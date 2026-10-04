@extends('layouts.dashboard')

@section('title', 'Account Status — Egliane Accounting Services')

@section('content')
    <div class="conf-ack-overlay">
        <div class="conf-ack-card">
            <div class="conf-ack-icon">
                @if ($status === 'approved')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/></svg>
                @elseif ($status === 'rejected')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><path d="M8 8l8 8M16 8l-8 8"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 3"/></svg>
                @endif
            </div>

            @if ($status === 'approved')
                <h1>Account Approved</h1>
                <p class="conf-ack-text">Your account is approved. You can now access the Egliane client portal, your billing statements, documents, and services.</p>
                <a href="{{ route('client.dashboard') }}" class="btn btn-primary btn-block">Go to my dashboard</a>
            @elseif ($status === 'rejected')
                <h1>Account Not Approved</h1>
                <p class="conf-ack-text">We're sorry, but your account application was not approved at this time.</p>
                @if ($client->decline_reason)
                    <div class="reject-reason">Reason: {{ $client->decline_reason }}</div>
                @endif
                <p class="conf-ack-text">If you believe this is a mistake or would like to discuss next steps, please reach out and we'll be happy to help.</p>
            @else
                <h1>Account Pending Approval</h1>
                <p class="conf-ack-text">Your registration is complete. An Egliane admin is reviewing your account, and you'll be notified here once it's approved.</p>
                <p class="conf-ack-text">You can still check your notifications and manage your security settings while you wait.</p>
                <div class="btn-group-row">
                    <a href="{{ route('security.index') }}" class="btn btn-outline">Security Settings</a>
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline">Notifications</a>
                </div>
            @endif

            <p class="conf-ack-footer">Questions? Contact Egliane Accounting Services for assistance.</p>
        </div>
    </div>
@endsection