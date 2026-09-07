@extends('layouts.dashboard')

@section('title', 'Collections & Follow-ups — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Collections &amp; Follow-ups</h1>
        <p>Payments received from Egliane Accounting Services, grouped by the quarter you paid.</p>
    </div>

    <div class="card period-scope">
        <div class="card-head">
            <h2 class="card-title">Selected Quarter</h2>
            @include('client.partials.quarter-selector', [
                'action' => route('client.collections.index'),
                'availableQuarters' => $availableQuarters,
                'activeQuarter' => $activeQuarter,
            ])
        </div>
        <div class="stat-grid">
            <div class="stat-card stat-ok">
                <div class="stat-icon stat-icon-ok">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <span class="stat-label">Total collected</span>
                <b class="stat-value">₱{{ number_format($quarterSummary['paid'], 2) }}</b>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </div>
                <span class="stat-label">Payments received</span>
                <b class="stat-value">{{ number_format($quarterSummary['count']) }}</b>
            </div>
        </div>
    </div>

    @include('client.partials.unpaid-balance', ['amount' => $globalUnpaid])

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Billing period</th>
                        <th class="text-end">Total payment</th>
                        <th class="text-center">Status</th>
                        <th>Date paid</th>
                        <th class="actions-cell">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($collections as $billing)
                        <tr>
                            <td data-col="Billing period">
                                <div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div>
                                <small class="muted">{{ $billing->period_label }}</small>
                            </td>
                            <td data-col="Total payment" class="text-end fw-semibold">{{ $billing->money($billing->total) }}</td>
                            <td data-col="Status" class="text-center">
                                <span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span>
                            </td>
                            <td data-col="Date paid">
                                {{ $billing->paid_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td data-col="Receipt" class="actions-cell">
                                <a href="{{ route('client.billing.show', $billing) }}?from=collections" class="btn btn-outline btn-sm">View receipt</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No payments were recorded for this quarter.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($collections as $billing)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Billing period</span><span class="cv-value"><div class="cell-name">{{ $billing->periodTitleUppercase() }} BILLING</div><small class="muted">{{ $billing->period_label }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Total payment</span><span class="cv-value fw-semibold">{{ $billing->money($billing->total) }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value"><span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Date paid</span><span class="cv-value">{{ $billing->paid_at?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Receipt</span><span class="cv-value"><a href="{{ route('client.billing.show', $billing) }}?from=collections" class="btn btn-outline btn-sm">View receipt</a></span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No payments were recorded for this quarter.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection