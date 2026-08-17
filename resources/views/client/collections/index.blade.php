@extends('layouts.dashboard')

@section('title', 'Collections — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Collections</h1>
        <p>The payment status of each billing period.</p>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱{{ number_format($summary['total'], 2) }}</b>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total paid</span>
            <b class="stat-value">₱{{ number_format($summary['paid'], 2) }}</b>
        </div>
        <div class="stat-card stat-warn">
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
                        <tr><td colspan="5" class="empty-cell">No billing records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
