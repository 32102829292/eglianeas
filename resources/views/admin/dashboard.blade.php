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

    // Client connection (Active / Disconnected), plus the 12-month trend.
    $ccClients = \App\Models\User::with('profile')->where('role', \App\Models\User::ROLE_CLIENT)->get();
    $ccStartDates = $ccClients->mapWithKeys(function ($c) {
        $date = $c->profile?->date_started ?? $c->created_at;

        return [$c->id => $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay() : null];
    });
    $ccRecords = \App\Models\ClientDisconnection::whereIn('client_id', $ccStartDates->keys())->get();
    $ccTrend = app(\App\Services\ClientGrowthStats::class)->monthly($ccStartDates, $ccRecords);

    $ccDisconnected = $ccRecords->whereNull('reconnected_at')->unique('client_id')->count();
    $ccActive = $ccClients->count() - $ccDisconnected;
    $ccChange = end($ccTrend['connected']) - $ccTrend['connected'][0];
    $ccSegments = collect([
        ['label' => 'Active', 'value' => $ccActive, 'color' => 'var(--success)', 'tint' => '#B3E3C7'],
        ['label' => 'Disconnected', 'value' => $ccDisconnected, 'color' => 'var(--danger)', 'tint' => '#F7C0BB'],
    ])->filter(fn ($s) => $s['value'] > 0)->sortByDesc('value')->values();
    $ccTotal = $ccSegments->sum('value');
    $ccActivePct = $ccTotal > 0 ? round($ccActive / $ccTotal * 100, 1) : 0.0;

    // Items that need action, shown first.
    $overdue = (int) ($billingAlerts['overdue'] ?? 0);
    $dueSoon = (int) ($billingAlerts['dueSoon'] ?? 0);
    $outstanding = (float) ($billingAlerts['outstanding'] ?? 0);
    $pendingFilings = (int) ($stats['pendingFilings'] ?? 0);

    $quarters = collect($quarterlySummary ?? []);
    $maxOutstanding = (float) (collect($topOutstanding ?? [])->max('total') ?? 0);
@endphp

@section('content')
    <style>
        .ds-section { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 4px 12px; margin: 28px 0 12px; }
        .ds-section h2 { margin: 0; font-size: 1.05rem; color: var(--navy); }
        .ds-section span { font-size: .8rem; color: #6B7280; }
        .ds-grid { display: grid; gap: 16px; margin-bottom: 16px; }
        .ds-grid > .card { margin: 0; min-width: 0; }
        .ds-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .ds-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .ds-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ds-alert { display: block; border-left: 4px solid #D1D5DB; color: inherit; text-decoration: none; }
        a.ds-alert:hover, a.ds-alert:focus-visible { box-shadow: 0 6px 18px rgba(27,27,58,.10); }
        .ds-alert.is-danger { border-left-color: var(--danger); }
        .ds-alert.is-warn { border-left-color: var(--warning); }
        .ds-alert.is-ok { border-left-color: var(--success); }
        .ds-alert-label, .ds-alert-note { display: block; font-size: .8rem; color: #6B7280; }
        .ds-alert-value { display: block; margin: 4px 0; font-size: 1.6rem; line-height: 1.2; color: var(--navy); overflow-wrap: anywhere; }
        .ds-meter { height: 5px; margin-top: 6px; border-radius: 99px; background: rgba(27,27,58,.07); overflow: hidden; }
        .ds-meter i { display: block; height: 100%; border-radius: inherit; background: var(--danger); }
        .ds-chart { position: relative; height: 260px; }
        .ds-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ds-grid > .card, .ds-card { border-radius: 16px; border: 1px solid rgba(27,27,58,.07); box-shadow: 0 4px 16px rgba(27,27,58,.06); padding: 20px; }
        .ds-alert { --tone: #6B7280; border-left-width: 1px; border-top: 4px solid var(--tone); background: linear-gradient(160deg, color-mix(in srgb, var(--tone) 13%, #fff), #fff 70%); transition: transform .15s, box-shadow .15s; }
        .ds-alert.is-danger { --tone: var(--danger); border-top-color: var(--tone); }
        .ds-alert.is-warn { --tone: var(--warning); border-top-color: var(--tone); }
        .ds-alert.is-ok { --tone: var(--success); border-top-color: var(--tone); }
        a.ds-alert:hover { transform: translateY(-2px); }
        .ds-alert-value { font-size: 2rem; }
        .ds-grid .analytics-value { font-size: 2.2rem; }
        .ds-chart { height: 360px; }
        .ds-donut { position: relative; height: 250px; }
        .ds-donut-total { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
        .ds-donut-total b { font-size: 2rem; line-height: 1; color: var(--navy); }
        .ds-donut-total small { color: #6B7280; }
        .ds-legend { list-style: none; margin: 16px 0 0; padding: 0; font-size: .85rem; }
        .ds-legend li { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid rgba(27,27,58,.06); }
        .ds-legend i { width: 10px; height: 10px; border-radius: 50%; }
        .ds-legend span { flex: 1; }
        .ds-scroll { max-height: 320px; overflow-y: auto; }
        .ds-scroll thead th { position: sticky; top: 0; z-index: 1; background: #fff; }
        @media (max-width: 1100px) {
            .ds-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ds-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .ds-grid-4, .ds-grid-3, .ds-grid-2 { grid-template-columns: minmax(0, 1fr); }
        }
    </style>

    @include('partials.daily-journal-alert')

    <div class="page-head page-head-row page-head-dash">
        <div class="dash-headline">
            @php
                $dashLabel = 'Accounting Overview';
                $dashTitle = 'Admin Dashboard';
                $dashSubtitle = 'Manage accounts, oversee operations, and monitor accounting activities.';
                if (auth()->user()->isSupervisor()) {
                    $dashLabel = 'Team Oversight';
                    $dashTitle = 'Supervisor Dashboard';
                    $dashSubtitle = 'Monitor team progress, review daily accomplishments, and keep operations on track.';
                } elseif (auth()->user()->isStaff()) {
                    $dashLabel = 'Daily Tasks';
                    $dashTitle = 'Staff Dashboard';
                    $dashSubtitle = 'Stay organized, manage your daily tasks, and track your accomplishments.';
                }
            @endphp
            <span class="dash-headline-icon" aria-hidden="true">
                @if (auth()->user()->isSupervisor())
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                @elseif (auth()->user()->isStaff())
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                @endif
            </span>
            <div class="dash-headline-text">
                <span class="dash-headline-label">{{ $dashLabel }}</span>
                <h1>{{ $dashTitle }}</h1>
                <p>{{ $dashSubtitle }}</p>
            </div>
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

    {{-- 1. Needs attention --}}
    <div class="ds-section"><h2>Needs attention</h2></div>
    <div class="ds-grid ds-grid-4">
        <a class="card ds-alert {{ $overdue > 0 ? 'is-danger' : 'is-ok' }}" href="{{ route('admin.billing.index') }}">
            <span class="ds-alert-label">Overdue bills</span>
            <b class="ds-alert-value">{{ number_format($overdue) }}</b>
            <span class="ds-alert-note">Past due until marked paid</span>
        </a>
        <a class="card ds-alert {{ $dueSoon > 0 ? 'is-warn' : 'is-ok' }}" href="{{ route('admin.billing.index') }}">
            <span class="ds-alert-label">Due within 7 days</span>
            <b class="ds-alert-value">{{ number_format($dueSoon) }}</b>
            <span class="ds-alert-note">Unpaid bills in the reminder window</span>
        </a>
        <a class="card ds-alert {{ $outstanding > 0 ? 'is-warn' : 'is-ok' }}" href="{{ route('admin.billing.index') }}">
            <span class="ds-alert-label">Outstanding balance</span>
            <b class="ds-alert-value">₱{{ number_format($outstanding, 2) }}</b>
            <span class="ds-alert-note">Pending, unpaid and overdue</span>
        </a>
        <div class="card ds-alert {{ $pendingFilings > 0 ? 'is-warn' : 'is-ok' }}">
            <span class="ds-alert-label">Pending filings</span>
            <b class="ds-alert-value">{{ number_format($pendingFilings) }}</b>
            <span class="ds-alert-note">Out of {{ number_format($stats['filings'] ?? 0) }} filings</span>
        </div>
    </div>

    <div class="card ds-card">
        <div class="card-head">
            <h3 class="card-title">Billing reminders</h3>
            <a href="{{ route('admin.billing.index') }}">Manage billing statements</a>
        </div>
        @if ($dueBills->count())
            <div class="table-wrap table-card-view ds-scroll">
                <table class="table">
                    <thead>
                        <tr><th>Client</th><th>Period</th><th class="ds-num">Total</th><th>Status</th><th>Due date</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($dueBills as $billing)
                            <tr>
                                <td class="cell-name">{{ $billing->client?->name ?? '—' }}</td>
                                <td>{{ $billing->periodTitle() }}</td>
                                <td class="ds-num">{{ $billing->money($billing->total) }}</td>
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
        @else
            <p class="chart-empty">No billing reminders right now.</p>
        @endif
    </div>

    {{-- 2. Performance for the selected period --}}
    <div class="ds-section">
        <h2>Performance</h2>
        <span>{{ $periodLabel }}</span>
    </div>
    <div class="ds-grid ds-grid-2">
        <div class="card analytics-card">
            <div class="analytics-head">
                <span class="analytics-title">Revenue collected</span>
                <span class="analytics-icon analytics-icon-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </span>
            </div>
            <div class="analytics-value">₱{{ number_format($periodRevenue, 0) }}</div>
            <span class="analytics-trend">Paid billings in the selected period</span>
        </div>
        <div class="card analytics-card">
            <div class="analytics-head">
                <span class="analytics-title">New billings</span>
                <span class="analytics-icon analytics-icon-blue" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                </span>
            </div>
            <div class="analytics-value">{{ number_format($periodNewBillings) }}</div>
            <span class="analytics-trend">Active billings created in the selected period</span>
        </div>
    </div>

    <div class="ds-grid">
        <div class="card">
            <div class="card-head"><h3 class="card-title">Fee &amp; remittance</h3></div>
            @if ($quarters->isNotEmpty())
                <div class="ds-chart"><canvas id="quarterlyFeeRemittanceChart" role="img" aria-label="Stacked bar chart of fee and remittance. Values are listed in the summary table."></canvas></div>
            @else
                <p class="chart-empty">No billing data for {{ $periodLabel }}.</p>
            @endif
        </div>
        <div class="card">
            <div class="card-head"><h3 class="card-title">{{ $periodLabel }} summary</h3></div>
            <div class="table-wrap ds-scroll">
                <table class="table">
                    <thead><tr><th>Period</th><th class="ds-num">Fee</th><th class="ds-num">Remittance</th></tr></thead>
                    <tbody>
                        @forelse ($quarters as $quarter)
                            <tr>
                                <td>{{ $quarter['label'] }}</td>
                                <td class="ds-num">₱{{ number_format($quarter['fee'], 2) }}</td>
                                <td class="ds-num">₱{{ number_format($quarter['remittance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">No billing data for {{ $periodLabel }}.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($quarters->count() > 1)
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="ds-num">₱{{ number_format($quarters->sum('fee'), 2) }}</th>
                                <th class="ds-num">₱{{ number_format($quarters->sum('remittance'), 2) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="ds-grid">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Highest outstanding clients</h3>
                <a href="{{ route('admin.billing.index') }}">View billing</a>
            </div>
            @if (count($topOutstanding ?? []) > 0)
                <div class="top-list">
                    @foreach ($topOutstanding as $row)
                        <div class="top-list-row">
                            <div class="top-list-main" style="flex:1; min-width:0;">
                                <b>{{ $row->client_name }}</b>
                                <small class="muted">{{ $row->business_name }}</small>
                                <div class="ds-meter" aria-hidden="true"><i style="width:{{ $maxOutstanding > 0 ? round($row->total / $maxOutstanding * 100, 1) : 0 }}%"></i></div>
                            </div>
                            <span class="top-list-amount">₱{{ number_format($row->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="chart-empty">No outstanding balances yet.</p>
            @endif
        </div>
    </div>

    {{-- 3. Clients --}}
    <div class="ds-section">
        <h2>Clients</h2>
        <span>{{ number_format($stats['clients'] ?? 0) }} clients · {{ number_format($stats['transactions'] ?? 0) }} transactions</span>
    </div>
    <div class="ds-grid ds-grid-3">
        @foreach ([
            ['statusChart', 'Billing status', $bsSegments, $bsTotal, 'billings', $fmtPct($bsPaidPct).'% of billings are paid'],
            ['accountChart', 'Client account status', $csSegments, $csTotal, 'accounts', $fmtPct($csCurrentPct).'% of accounts are current'],
            ['connectionChart', 'Client connection', $ccSegments, $ccTotal, 'clients', $fmtPct($ccActivePct).'% of clients are active'],
        ] as [$id, $title, $segments, $total, $unit, $note])
            <div class="card">
                <div class="card-head"><h3 class="card-title">{{ $title }}</h3></div>
                @if ($total > 0)
                    <div class="ds-donut">
                        <canvas class="ds-donut-canvas" id="{{ $id }}" data-segments='@json($segments)' role="img" aria-label="{{ $title }}. {{ $note }}."></canvas>
                        <div class="ds-donut-total"><b>{{ number_format($total) }}</b><small>{{ $unit }}</small></div>
                    </div>
                    <ul class="ds-legend">
                        @foreach ($segments as $segment)
                            <li><i style="background:{{ $segment['color'] }}"></i><span>{{ $segment['label'] }}</span><b>{{ $segment['value'] }}</b><small class="muted">{{ $fmtPct(round($segment['value'] / $total * 100, 1)) }}%</small></li>
                        @endforeach
                    </ul>
                    <div class="summary-strip"><span>{{ $note }}</span></div>
                @else
                    <p class="chart-empty">No data yet.</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card ds-card">
        <div class="card-head">
            <h3 class="card-title">Active vs disconnected clients</h3>
            <a href="{{ route('admin.clients.disconnected') }}">View disconnected clients</a>
        </div>
        <p class="muted">
            Last 12 months, month-end totals. Active clients
            {{ $ccChange > 0 ? 'grew by '.$ccChange : ($ccChange < 0 ? 'fell by '.abs($ccChange) : 'did not change') }}
            since {{ $ccTrend['labels'][0] }}.
        </p>
        <div class="ds-chart"><canvas id="clientTrendChart" role="img" aria-label="Line graph of active and disconnected clients per month"></canvas></div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    var clientTrend = @json($ccTrend);
    var quarterlySummary = {!! json_encode(collect($quarterlySummary ?? [])->values()->all()) !!};

    var isMobile = window.innerWidth <= 640;
    var gridLine = 'rgba(27,27,58,0.06)';
    var tick = { font: { size: isMobile ? 9 : 11 }, color: '#8A93A2' };

    function peso(value) {
        return '₱' + Number(value).toLocaleString('en-PH', { maximumFractionDigits: 2 });
    }

    function pesoShort(value) {
        return '₱' + Number(value).toLocaleString('en-PH', { notation: 'compact', maximumFractionDigits: 1 });
    }

    /* Status doughnuts. Canvas cannot read CSS variables, so resolve them first. */
    function resolveColor(color) {
        var match = /^var\((--[\w-]+)\)$/.exec(color);
        return match ? getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() : color;
    }

    document.querySelectorAll('.ds-donut-canvas').forEach(function (canvas) {
        var segments = JSON.parse(canvas.dataset.segments);
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: segments.map(function (s) { return s.label; }),
                datasets: [{
                    data: segments.map(function (s) { return s.value; }),
                    backgroundColor: segments.map(function (s) { return resolveColor(s.color); }),
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false } } }
        });
    });

    /* Active vs disconnected clients */
    var trendCanvas = document.getElementById('clientTrendChart');
    if (trendCanvas) {
        var success = resolveColor('var(--success)');
        var danger = resolveColor('var(--danger)');
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: clientTrend.labels,
                datasets: [
                    { label: 'Active', data: clientTrend.connected, borderColor: success, backgroundColor: success, pointRadius: 3 },
                    { label: 'Disconnected', data: clientTrend.disconnected, borderColor: danger, backgroundColor: danger, borderDash: [6, 4], pointRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { footer: function (items) {
                        var i = items[0].dataIndex;
                        return 'Difference: ' + (clientTrend.connected[i] - clientTrend.disconnected[i]);
                    } } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: tick },
                    y: { beginAtZero: true, grid: { color: gridLine }, ticks: Object.assign({ precision: 0 }, tick) }
                }
            }
        });
    }

    var quarterlyCanvas = document.getElementById('quarterlyFeeRemittanceChart');
    if (quarterlyCanvas && quarterlySummary.length) {
        new Chart(quarterlyCanvas, {
            type: 'bar',
            data: {
                labels: quarterlySummary.map(function (row) { return row.label; }),
                datasets: [
                    { label: 'Fee', data: quarterlySummary.map(function (row) { return row.fee; }), backgroundColor: 'rgba(46,155,222,.85)', borderRadius: 5 },
                    { label: 'Remittance', data: quarterlySummary.map(function (row) { return row.remittance; }), backgroundColor: 'rgba(39,174,96,.85)', borderRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + peso(ctx.raw); } } }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: tick },
                    y: { stacked: true, beginAtZero: true, grid: { color: gridLine }, ticks: Object.assign({ callback: pesoShort }, tick) }
                }
            }
        });
    }
})();
</script>
@endpush