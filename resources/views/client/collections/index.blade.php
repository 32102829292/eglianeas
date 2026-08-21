@extends('layouts.dashboard')

@section('title', 'Collections & Follow-ups — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Collections &amp; Follow-ups</h1>
        <p>The payment status of each billing period.</p>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($summary['total'], 2) }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Total paid</span>
            <b class="stat-value">₱{{ number_format($summary['paid'], 2) }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding balance</span>
            <b class="stat-value">₱{{ number_format($summary['outstanding'], 2) }}</b>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Billing period</th>
                        <th>Total payment</th>
                        <th>Status</th>
                        <th>Date paid</th>
                        <th class="actions-cell">Receipt</th>
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
                            <td>{{ $billing->paid_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="actions-cell">
                                @if ($billing->isPaid())
                                    <a href="{{ route('client.billing.show', $billing) }}?from=collections" class="btn btn-outline btn-sm">View receipt</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No billing statements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
