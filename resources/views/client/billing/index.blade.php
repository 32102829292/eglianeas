@extends('layouts.dashboard')

@section('title', 'Billing — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Billing</h1>
            <p>View your billing statements from Egliane Accounting Services.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($summary['billed'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Total paid</span>
            <b class="stat-value">₱{{ number_format($summary['paid'], 2) }}</b>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Billing history</h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Billing period</th>
                        <th>Total payment</th>
                        <th>Status</th>
                        <th class="actions-cell">Statement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($billings as $billing)
                        <tr>
                            <td>
                                <div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="muted">{{ $billing->period_label }}</small>
                            </td>
                            <td><b>{{ $billing->money($billing->total) }}</b></td>
                            <td>
                                <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('client.billing.show', $billing) }}" class="btn btn-outline btn-sm">View statement</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-cell">No billing statements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $billings->links('pagination.simple') }}
    </div>
@endsection
