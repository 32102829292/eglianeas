@extends('layouts.dashboard')

@section('title', 'My Dashboard — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Hello, {{ auth()->user()->name }}</h1>
            <p>Here&rsquo;s what&rsquo;s happening with your books and filings.</p>
        </div>
    </div>

    <div class="status-banner status-{{ $profile->status }}">
        <span class="status-dot" aria-hidden="true"></span>
        <span class="status-label">Account Status:</span>
        <span class="status-value">{{ $profile->statusLabel() }}</span>
        @if ($profile->status !== \App\Models\ClientProfile::STATUS_CURRENT)
            <span class="status-note">{{ \App\Models\ClientProfile::STATUS_NOTES[$profile->status] ?? '' }}</span>
        @endif
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
            <span class="stat-label">Income ({{ now()->year }})</span>
            <b class="stat-value">&#8369; {{ number_format($stats['income'], 2) }}</b>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
            </div>
            <span class="stat-label">Expenses ({{ now()->year }})</span>
            <b class="stat-value">&#8369; {{ number_format($stats['expenses'], 2) }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Transactions</span>
            <b class="stat-value">{{ $stats['transactions'] }}</b>
        </div>
        <div class="stat-card {{ $stats['pendingFilings'] > 0 ? 'stat-warn' : 'stat-ok' }}">
            <div class="stat-icon {{ $stats['pendingFilings'] > 0 ? 'stat-icon-warn' : 'stat-icon-ok' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Pending filings</span>
            <b class="stat-value">{{ $stats['pendingFilings'] }}</b>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent transactions</h3>
            </div>
            <div class="table-wrap table-card-view">
                <table class="table">
                    <thead><tr><th>Title</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse ($recentTransactions as $tx)
                            <tr>
                                <td>
                                    <div class="cell-name">{{ $tx->title }}</div>
                                    <small class="muted">{{ $tx->category ?? '—' }}</small>
                                </td>
                                <td>
                                    <span class="{{ $tx->type === 'income' ? 'amount-income' : 'amount-expense' }}">{{ $tx->type === 'income' ? '+' : '−' }} &#8369; {{ number_format($tx->amount, 2) }}</span>
                                </td>
                                <td class="muted">{{ $tx->transaction_date->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">No transactions recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="card-view-list">
                    @forelse ($recentTransactions as $tx)
                        <div class="cv-card">
                            <div class="cv-row"><span class="cv-label">Title</span><span class="cv-value">{{ $tx->title }}<br><small class="muted">{{ $tx->category ?? '—' }}</small></span></div>
                            <div class="cv-row"><span class="cv-label">Amount</span><span class="cv-value"><span class="{{ $tx->type === 'income' ? 'amount-income' : 'amount-expense' }}">{{ $tx->type === 'income' ? '+' : '−' }} &#8369; {{ number_format($tx->amount, 2) }}</span></span></div>
                            <div class="cv-row"><span class="cv-label">Date</span><span class="cv-value">{{ $tx->transaction_date->format('M j, Y') }}</span></div>
                        </div>
                    @empty
                        <p class="cv-card" style="text-align:center;color:var(--text-muted);">No transactions recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent filings</h3>
            </div>
            <ul class="list">
                @forelse ($recentFilings as $filing)
                    <li>
                        <div>
                            <b>{{ $filing->type }}</b>
                            <small class="muted">{{ $filing->period }} &middot; due {{ $filing->due_date?->format('M j, Y') ?? '—' }}</small>
                        </div>
                        <span class="badge badge-{{ $filing->status }}">{{ \App\Models\Filing::STATUSES[$filing->status] }}</span>
                    </li>
                @empty
                    <li class="empty-cell">No filings yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
