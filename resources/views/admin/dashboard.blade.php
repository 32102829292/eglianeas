@extends('layouts.dashboard')

@section('title', 'Admin Dashboard — Egliane Accounting Services')

@php
    $fmtPct = function (float $p): string {
        return number_format($p, (float) round($p) === $p ? 0 : 1);
    };

    // Billing status breakdown (Paid / Draft / Pending / Unpaid / Overdue), non-zero, by count desc.
    $bsSegments = collect([
        ['label' => 'Paid', 'value' => (int) ($paidCount ?? 0), 'color' => 'var(--success)', 'tint' => '#B3E3C7'],
        ['label' => 'Draft', 'value' => (int) ($draftCount ?? 0), 'color' => '#8E8E93', 'tint' => '#E5E5EA'],
        ['label' => 'Pending', 'value' => (int) ($pendingCount ?? 0), 'color' => 'var(--warning)', 'tint' => '#FADBC0'],
        ['label' => 'Unpaid', 'value' => (int) ($unpaidCount ?? 0), 'color' => 'var(--navy)', 'tint' => '#AFAFBA'],
        ['label' => 'Overdue', 'value' => (int) ($overdueCount ?? 0), 'color' => 'var(--danger)', 'tint' => '#F7C0BB'],
    ])->filter(fn ($s) => $s['value'] > 0)->sortByDesc('value')->values();
    $bsTotal = $bsSegments->sum('value');
    $bsPaid = (int) ($paidCount ?? 0);
    $bsPaidPct = $bsTotal > 0 ? round($bsPaid / $bsTotal * 100, 1) : 0.0;

    // Client account status (Current / Pending / Delinquent / Critical), non-zero, by count desc.
    $csMap = collect($analytics['charts']['clientStatus'] ?? [])->keyBy('label');
    $csSegments = collect([
        ['Current', 'var(--success)', '#B3E3C7'],
        ['Pending', 'var(--warning)', '#FADBC0'],
        ['Delinquent', 'var(--danger)', '#F7C0BB'],
        ['Critical', 'var(--navy)', '#AFAFBA'],
    ])
        ->map(fn (array $def) => ['label' => $def[0], 'value' => (int) ($csMap[$def[0]]['count'] ?? 0), 'color' => $def[1], 'tint' => $def[2]])
        ->filter(fn (array $s) => $s['value'] > 0)
        ->sortByDesc('value')
        ->values();
    $csTotal = $csSegments->sum('value');
    $csCurrent = (int) ($csMap['Current']['count'] ?? 0);
    $csCurrentPct = $csTotal > 0 ? round($csCurrent / $csTotal * 100, 1) : 0.0;

    $btRows = collect($analytics['charts']['businessType'] ?? [])->filter(fn ($r) => ((int) $r['count']) > 0)->values();
    $lobRows = collect($analytics['charts']['lineOfBusiness'] ?? [])->filter(fn ($r) => ((int) $r['count']) > 0)->values();
@endphp

@section('content')
    @include('partials.daily-journal-alert')
    <div class="page-head page-head-row page-head-dash">
        <div>
            <h1>Admin dashboard</h1>
            <p>Overview of accounts, filings and activity.</p>
        </div>
        <form method="GET" class="date-filter" aria-label="Dashboard period">
            <label class="toolbar-field"><span class="toolbar-label">Year:</span>
                <select name="year" class="toolbar-select" onchange="this.form.submit()">
                    @foreach ($availableYears as $year)<option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>@endforeach
                </select>
            </label>
            <label class="toolbar-field"><span class="toolbar-label">Period:</span>
                <select name="period" class="toolbar-select" onchange="this.form.submit()">
                    <option value="q1" @selected($selectedPeriod === 'q1')>Q1</option>
                    <option value="q2" @selected($selectedPeriod === 'q2')>Q2</option>
                    <option value="q3" @selected($selectedPeriod === 'q3')>Q3</option>
                    <option value="q4" @selected($selectedPeriod === 'q4')>Q4</option>
                    <option value="full" @selected($selectedPeriod === 'full')>Full Year</option>
                </select>
            </label>
        </form>
    </div>

    <div class="stat-grid cols-4 dash-kpi">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="stat-label">Clients</span>
            <b class="stat-value">{{ $stats['clients'] ?? 0 }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Transactions</span>
            <b class="stat-value">{{ $stats['transactions'] ?? 0 }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Filings</span>
            <b class="stat-value">{{ $stats['filings'] ?? 0 }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Pending filings</span>
            <b class="stat-value">{{ $stats['pendingFilings'] ?? 0 }}</b>
        </div>
    </div>

    <div class="dash-analytics">
        <div class="card analytics-card">
            <div class="analytics-head">
                <span class="analytics-title">Revenue collected ({{ $periodLabel }})</span>
                <span class="analytics-icon analytics-icon-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </span>
            </div>
            <div class="analytics-value">₱{{ number_format($periodRevenue, 0) }}</div>
            <span class="analytics-trend">Paid billings in the selected period</span>
        </div>

        <div class="card analytics-card">
            <div class="analytics-head">
                <span class="analytics-title">New billings ({{ $periodLabel }})</span>
                <span class="analytics-icon analytics-icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                </span>
            </div>
            <div class="analytics-value">{{ $periodNewBillings }}</div>
            <span class="analytics-trend">Active billings created in the selected period</span>
        </div>

        <div class="card analytics-card">
            <div class="card-head">
                <h3 class="card-title">Billing Status Breakdown</h3>
            </div>
            <x-donut-chart :segments="$bsSegments->all()" :size="116" :thickness="13" :show-total-label="true" legend="right" :show-pct="true" />
            @if ($bsTotal > 0)
                <div class="summary-strip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $fmtPct($bsPaidPct) }}% of billings are paid</span>
                </div>
            @endif
        </div>

        <div class="card analytics-card">
            <div class="card-head">
                <h3 class="card-title">Client Account Status</h3>
            </div>
            <x-donut-chart :segments="$csSegments->all()" :size="116" :thickness="13" :show-total-label="true" legend="right" :show-pct="true" empty-text="No client data yet." />
            @if ($csTotal > 0)
                <div class="summary-strip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $fmtPct($csCurrentPct) }}% of accounts are current</span>
                </div>
            @endif
        </div>

        <div class="card analytics-card">
            <div class="card-head">
                <h3 class="card-title">Business Types</h3>
            </div>
            @if ($btRows->isNotEmpty())
                <div class="hbar">
                    @foreach ($btRows as $row)
                        <div class="hbar-row">
                            <div class="hbar-label" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                            <div class="hbar-track"><div class="hbar-fill hbar-blue" style="width:{{ max((float) ($row['pct'] ?? 0), 2.5) }}%"></div></div>
                            <div class="hbar-value">{{ $row['count'] }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="chart-empty">Not enough data yet.</p>
            @endif
        </div>

        <div class="card analytics-card">
            <div class="card-head">
                <h3 class="card-title">Lines of Business</h3>
            </div>
            @if ($lobRows->isNotEmpty())
                <div class="hbar">
                    @foreach ($lobRows as $row)
                        <div class="hbar-row">
                            <div class="hbar-label" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                            <div class="hbar-track"><div class="hbar-fill hbar-green" style="width:{{ max((float) ($row['pct'] ?? 0), 2.5) }}%"></div></div>
                            <div class="hbar-value">{{ $row['count'] }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="chart-empty">Not enough data yet.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Billing reminders</h3>
            <a href="{{ route('admin.billing.index') }}">Manage billing statements</a>
        </div>
        <div class="alert-list">
            <div class="alert-list-item @if ($billingAlerts['dueSoon'] ?? false) alert-warn @endif">
                <div class="alert-list-main">
                    <b>Bills due within 7 days</b>
                    <small>Unpaid bills entering the reminder window. Reminders start one week before the due date.</small>
                </div>
                <span class="alert-list-count">{{ $billingAlerts['dueSoon'] ?? 0 }}</span>
            </div>
            <div class="alert-list-item @if ($billingAlerts['overdue'] ?? false) alert-danger @endif">
                <div class="alert-list-main">
                    <b>Overdue bills</b>
                    <small>Past-due bills, escalated wording until marked paid.</small>
                </div>
                <span class="alert-list-count">{{ $billingAlerts['overdue'] ?? 0 }}</span>
            </div>
            <div class="alert-list-item @if (($billingAlerts['outstanding'] ?? 0) > 0) alert-warn @endif">
                <div class="alert-list-main">
                    <b>Outstanding balance</b>
                    <small>Pending + unpaid + overdue billing totals.</small>
                </div>
                <span class="alert-list-count">₱{{ number_format($billingAlerts['outstanding'] ?? 0, 2) }}</span>
            </div>
        </div>

        @if ($dueBills->count())
            <div class="table-wrap table-card-view">
                <table class="table">
                    <thead>
                        <tr><th>Client</th><th>Period</th><th>Total</th><th>Status</th><th>Due date</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($dueBills as $billing)
                            <tr>
                                <td class="cell-name">{{ $billing->client?->name ?? '—' }}</td>
                                <td>{{ $billing->periodTitle() }}</td>
                                <td>{{ $billing->money($billing->total) }}</td>
                                <td><span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span></td>
                                <td>{{ $billing->due_date?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="card-view-list">
                    @foreach ($dueBills as $billing)
                        <div class="cv-card">
                            <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">{{ $billing->client?->name ?? '—' }}</span></div>
                            <div class="cv-row"><span class="cv-label">Period</span><span class="cv-value">{{ $billing->periodTitle() }}</span></div>
                            <div class="cv-row"><span class="cv-label">Total</span><span class="cv-value">{{ $billing->money($billing->total) }}</span></div>
                            <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value"><span class="badge badge-{{ $billing->status }}">{{ $billing->statusLabel() }}</span></span></div>
                            <div class="cv-row"><span class="cv-label">Due date</span><span class="cv-value">{{ $billing->due_date?->format('M j, Y') ?? '—' }}</span></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Highest outstanding clients</h3>
                <a href="{{ route('admin.billing.index') }}">View billing</a>
            </div>
            @if (count($topOutstanding ?? []) > 0)
                <div class="top-list">
                    @foreach ($topOutstanding as $row)
                        <div class="top-list-row">
                            <div class="top-list-main">
                                <b>{{ $row->client_name }}</b>
                                <small class="muted">{{ $row->business_name }}</small>
                            </div>
                            <span class="top-list-amount">₱{{ number_format($row->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="chart-empty">No outstanding balances yet.</p>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Billing amounts by category</h3>
                <a href="{{ route('admin.billing.index') }}">View statements</a>
            </div>
            <div class="chart-canvas-wrap">
                @if (count($categoryChart['labels'] ?? []) > 0)
                    <canvas id="categoryChart" height="220"></canvas>
                @else
                    <p class="chart-empty">Not enough data yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3 class="card-title">Fee &amp; remittance — {{ $periodLabel }}</h3></div>
            @if (($quarterlySummary ?? collect())->isNotEmpty())
                <div class="chart-canvas-wrap"><canvas id="quarterlyFeeRemittanceChart" height="220"></canvas></div>
            @else
                <p class="chart-empty">No billing data for {{ $periodLabel }}.</p>
            @endif
        </div>
        <div class="card">
            <div class="card-head"><h3 class="card-title">{{ $periodLabel }} summary</h3></div>
            <div class="table-wrap">
                <table class="table"><thead><tr><th>Period</th><th>Fee</th><th>Remittance</th></tr></thead><tbody>
                    @forelse (($quarterlySummary ?? collect()) as $quarter)
                        <tr><td>{{ $quarter['label'] }}</td><td>₱{{ number_format($quarter['fee'], 2) }}</td><td>₱{{ number_format($quarter['remittance'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="empty-cell">No billing data for {{ $periodLabel }}.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent accounts</h3>
                <a href="{{ route('admin.clients.index') }}">View all</a>
            </div>
            <div class="table-wrap table-card-view">
                <table class="table">
                    <thead>
                        <tr><th>Name</th><th>Role</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentUsers as $user)
                            <tr>
                                <td>
                                    <div class="cell-name">{{ $user->name }}</div>
                                    <small class="muted">{{ $user->business_name ?? $user->email }}</small>
                                </td>
                                <td><span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span></td>
                                <td class="muted">{{ $user->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">No accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="card-view-list">
                    @forelse ($recentUsers as $user)
                        <div class="cv-card">
                            <div class="cv-row"><span class="cv-label">Name</span><span class="cv-value">{{ $user->name }}<br><small class="muted">{{ $user->business_name ?? $user->email }}</small></span></div>
                            <div class="cv-row"><span class="cv-label">Role</span><span class="cv-value"><span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span></span></div>
                            <div class="cv-row"><span class="cv-label">Joined</span><span class="cv-value">{{ $user->created_at->diffForHumans() }}</span></div>
                        </div>
                    @empty
                        <p class="cv-card cv-empty">No accounts yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent filings</h3>
                <a href="{{ route('admin.dashboard') }}">Refresh</a>
            </div>
            <div class="table-wrap table-card-view">
                <table class="table">
                    <thead>
                        <tr><th>Type</th><th>Client</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentFilings as $filing)
                            <tr>
                                <td>{{ $filing->type }}</td>
                                <td class="cell-name">{{ $filing->client?->name ?? '—' }}</td>
                                <td><span class="badge badge-{{ $filing->status }}">{{ \App\Models\Filing::STATUSES[$filing->status] ?? ucfirst($filing->status ?? 'unknown') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">No filings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="card-view-list">
                    @forelse ($recentFilings as $filing)
                        <div class="cv-card">
                            <div class="cv-row"><span class="cv-label">Type</span><span class="cv-value">{{ $filing->type }}</span></div>
                            <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">{{ $filing->client?->name ?? '—' }}</span></div>
                            <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value"><span class="badge badge-{{ $filing->status }}">{{ \App\Models\Filing::STATUSES[$filing->status] ?? ucfirst($filing->status ?? 'unknown') }}</span></span></div>
                        </div>
                    @empty
                        <p class="cv-card cv-empty">No filings yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Recent activity</h3>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.activity-logs') }}">View all</a>
            @endif
        </div>
        <div class="table-wrap table-card-view">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Action</th><th>Details</th><th>When</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentActivity as $log)
                        <tr>
                            <td class="cell-name">{{ $log->user?->name ?? 'Guest' }}</td>
                            <td><code class="code-pill">{{ $log->action }}</code></td>
                            <td class="muted">{{ $log->description }}</td>
                            <td class="muted">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-cell">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($recentActivity as $log)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">User</span><span class="cv-value">{{ $log->user?->name ?? 'Guest' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Action</span><span class="cv-value"><code class="code-pill">{{ $log->action }}</code></span></div>
                        <div class="cv-row"><span class="cv-label">Details</span><span class="cv-value">{{ $log->description }}</span></div>
                        <div class="cv-row"><span class="cv-label">When</span><span class="cv-value">{{ $log->created_at->diffForHumans() }}</span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No activity yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var catLabels = {!! json_encode($categoryChart['labels'] ?? []) !!};
    var catTotals = {!! json_encode($categoryChart['totals'] ?? []) !!};
    var quarterlySummary = {!! json_encode(collect($quarterlySummary ?? [])->values()->all()) !!};

    var isMobile = window.innerWidth <= 640;
    var gridLine = 'rgba(27,27,58,0.06)';
    var tick = { font: { size: isMobile ? 9 : 11 }, color: '#8A93A2' };

    /* Billing amounts by category — vertical bar of ₱ totals */
    if (document.getElementById('categoryChart')) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: catLabels,
                datasets: [{
                    label: 'Amount',
                    data: catTotals,
                    backgroundColor: 'rgba(90, 179, 240, 0.85)',
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return '₱' + Number(ctx.raw).toLocaleString(undefined, { maximumFractionDigits: 2 }); }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: isMobile ? 9 : 11 },
                            autoSkip: false,
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                if (!isMobile) return label;
                                var max = Math.floor(((this.chart.chartArea || {}).right || 300) / 6);
                                if (label.length > max) return label.substring(0, max - 1) + '\u2026';
                                return label;
                            }
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridLine },
                        ticks: {
                            font: { size: isMobile ? 9 : 11 },
                            color: '#8A93A2',
                            callback: function (value) { return '₱' + value; }
                        },
                    },
                },
            },
        });
    }

    if (document.getElementById('quarterlyFeeRemittanceChart') && quarterlySummary.length) {
        new Chart(document.getElementById('quarterlyFeeRemittanceChart'), {
            type: 'bar',
            data: {
                labels: quarterlySummary.map(function (row) { return row.label; }),
                datasets: [
                    { label: 'Fee', data: quarterlySummary.map(function (row) { return row.fee; }), backgroundColor: 'rgba(46,155,222,.85)', borderRadius: 5 },
                    { label: 'Remittance', data: quarterlySummary.map(function (row) { return row.remittance; }), backgroundColor: 'rgba(39,174,96,.85)', borderRadius: 5 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: gridLine }, ticks: { callback: function (v) { return '₱' + Number(v).toLocaleString(); } } } } }
        });
    }
})();
</script>
@endpush
