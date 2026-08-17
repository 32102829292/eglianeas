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
            <span class="stat-label">Income ({{ now()->year }})</span>
            <b class="stat-value">&#8369; {{ number_format($stats['income'], 2) }}</b>
        </div>
        <div class="stat-card stat-danger">
            <span class="stat-label">Expenses ({{ now()->year }})</span>
            <b class="stat-value">&#8369; {{ number_format($stats['expenses'], 2) }}</b>
        </div>
        <div class="stat-card">
            <span class="stat-label">Transactions</span>
            <b class="stat-value">{{ $stats['transactions'] }}</b>
        </div>
        <div class="stat-card {{ $stats['pendingFilings'] > 0 ? 'stat-warn' : 'stat-ok' }}">
            <span class="stat-label">Pending filings</span>
            <b class="stat-value">{{ $stats['pendingFilings'] }}</b>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent transactions</h3>
            </div>
            <div class="table-wrap">
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
