@extends('layouts.dashboard')

@section('title', 'Admin Dashboard — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Admin dashboard</h1>
        <p>Overview of accounts, filings and activity.</p>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="stat-label">Clients</span>
            <b class="stat-value">{{ $stats['clients'] ?? 0 }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
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

    <div class="db-metric-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label" style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Revenue collected (14d)</span>
                <div class="stat-icon" style="background:var(--success-soft);color:var(--success);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
            </div>
            <div class="db-metric-value">₱{{ number_format($snapshotRevenue->sum(), 0) }}</div>
            <x-sparkline :values="$snapshotRevenue" :width="200" :height="32" color="var(--success)" />
        </div>

        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label" style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;font-weight:600;">New billings (14d)</span>
                <div class="stat-icon" style="background:var(--sky-soft);color:var(--sky-deep);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                </div>
            </div>
            <div class="db-metric-value">{{ $snapshotNewBillings->sum() }}</div>
            <x-sparkline :values="$snapshotNewBillings" :width="200" :height="32" color="var(--sky-deep)" />
        </div>

        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label" style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Overdue count (14d)</span>
                <div class="stat-icon" style="background:var(--danger-soft);color:var(--danger);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
            </div>
            <div class="db-metric-value">{{ $snapshotOverdue->last() ?? 0 }}</div>
            <x-sparkline :values="$snapshotOverdue" :width="200" :height="32" color="var(--danger)" />
        </div>
    </div>

    <div class="section-gap">
        <div class="grid-2">
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Revenue vs new billings (14d)</h3>
                    <a href="{{ route('admin.billing.index') }}">Manage billing</a>
                </div>
                <div class="chart-canvas-wrap">
                    <canvas id="revenueTrendChart" height="220"></canvas>
                </div>
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
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card glass-panel h-100">
                <div class="card-body d-flex flex-column align-items-center text-center gap-3">
                    <h3 class="card-title">Billing Status Breakdown</h3>
                    <x-donut-chart :segments="[
                        ['label' => 'Paid', 'value' => $paidCount, 'color' => 'var(--success)'],
                        ['label' => 'Pending', 'value' => $pendingCount, 'color' => 'var(--warning)'],
                        ['label' => 'Unpaid', 'value' => $unpaidCount, 'color' => 'var(--navy)'],
                        ['label' => 'Overdue', 'value' => $overdueCount, 'color' => 'var(--danger)'],
                    ]" />
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card glass-panel h-100">
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Billing status</h3>
                        <a href="{{ route('admin.billing.index') }}" class="text-decoration-none">Manage billing statements</a>
                    </div>
                    <div class="chart-canvas-wrap flex-grow-1">
                        @php $bsTotal = collect($analytics['charts']['billingStatus'] ?? [])->sum('count'); @endphp
                        @if ($bsTotal > 0)
                            <canvas id="billingStatusChart" height="220"></canvas>
                        @else
                            <p class="chart-empty">Not enough data yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card glass-panel h-100">
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Client account status</h3>
                        <a href="{{ route('admin.clients.index') }}" class="text-decoration-none">View clients</a>
                    </div>
                    <div class="chart-canvas-wrap flex-grow-1">
                        @php $csTotal = collect($analytics['charts']['clientStatus'] ?? [])->sum('count'); @endphp
                        @if ($csTotal > 0)
                            <canvas id="clientStatusChart" height="220"></canvas>
                        @else
                            <p class="chart-empty">Not enough data yet.</p>
                        @endif
                    </div>
                </div>
            </div>
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
            <div class="table-wrap">
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
            </div>
        @endif
    </div>

    <div class="grid-3">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Business types</h3>
            </div>
            <div class="chart-canvas-wrap chart-bar-wrap">
                @php $btTotal = collect($analytics['charts']['businessType'] ?? [])->sum('count'); @endphp
                @if ($btTotal > 0)
                    <canvas id="businessTypesChart" height="150"></canvas>
                @else
                    <p class="chart-empty">Not enough data yet.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Lines of business</h3>
            </div>
            <div class="chart-canvas-wrap chart-bar-wrap">
                @php $lobTotal = collect($analytics['charts']['lineOfBusiness'] ?? [])->sum('count'); @endphp
                @if ($lobTotal > 0)
                    <canvas id="lobChart" height="150"></canvas>
                @else
                    <p class="chart-empty">Not enough data yet.</p>
                @endif
            </div>
        </div>

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
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent accounts</h3>
                <a href="{{ route('admin.clients.index') }}">View all</a>
            </div>
            <div class="table-wrap">
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
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent filings</h3>
                <a href="{{ route('admin.dashboard') }}">Refresh</a>
            </div>
            <div class="table-wrap">
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
        <div class="table-wrap">
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
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var bsData = {!! json_encode($analytics['charts']['billingStatus'] ?: []) !!};
    var csData = {!! json_encode($analytics['charts']['clientStatus'] ?: []) !!};
    var btData = {!! json_encode($analytics['charts']['businessType'] ?: []) !!};
    var lobData = {!! json_encode($analytics['charts']['lineOfBusiness'] ?: []) !!};
    var snapLabels = {!! json_encode($snapshotLabels ?? []) !!};
    var snapRevenue = {!! json_encode($snapshotRevenue ?? []) !!};
    var snapNewBillings = {!! json_encode($snapshotNewBillings ?? []) !!};
    var catLabels = {!! json_encode($categoryChart['labels'] ?? []) !!};
    var catTotals = {!! json_encode($categoryChart['totals'] ?? []) !!};

    var total = function (d) { return d.reduce(function (s, v) { return s + v.count; }, 0); };
    var labels = function (d) { return d.map(function (v) { return v.label; }); };
    var counts = function (d) { return d.map(function (v) { return v.count; }); };

    var pctLabel = {
        id: 'pctLabel',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.font = '600 12px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            var total = chart.data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
            if (total === 0) return;
            var meta = chart.getDatasetMeta(0);
            meta.data.forEach(function (arc, i) {
                var val = chart.data.datasets[0].data[i];
                if (val === 0) return;
                var pct = ((val / total) * 100).toFixed(0) + '%';
                var pos = arc.tooltipPosition();
                ctx.fillStyle = '#fff';
                ctx.fillText(pct, pos.x, pos.y);
            });
            ctx.restore();
        }
    };

    var isMobile = window.innerWidth <= 640;

    // Resolve a CSS variable defined on :root to a concrete color string,
    // so Chart.js (canvas) can use the same design tokens as the SVG charts.
    var token = function (name) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name);
        return v ? v.trim() : '';
    };

    // Returns a Chart.js per-datapoint backgroundColor callback that fills the
    // segment with a soft vertical (top -> bottom) gradient from a light tint
    // to the full segment color.
    //
    // IMPORTANT: must be a SINGLE function, not an array of functions. Chart.js
    // resolves per-datapoint scriptable colors by invoking one function with a
    // context that carries context.dataIndex. An array-of-functions backgroundColor
    // is NOT invoked by Chart.js (it's treated as opaque), which is what caused the
    // black/never-logging donuts. Dispatch on context.dataIndex instead.
    var gradient = function (pairs) {
        return function (context) {
            var idx = context.dataIndex || 0;
            var p = pairs[idx >= 0 && idx < pairs.length ? idx : 0];
            var solid = p ? p[1] : 'rgba(0,0,0,0.85)';
            var ctx = context && context.chart && context.chart.ctx;
            var area = ctx && context.chart.chartArea;
            // If there's no real drawable context or the chart area is not ready
            // (legend/tooltip/hit-test phases), return the solid color rather than
            // build a gradient (which could throw or be 0-height and collapse to
            // the black default).
            if (!ctx || !area || (area.bottom - area.top) < 1) {
                return solid;
            }
            var g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
            g.addColorStop(0, p[0]);
            g.addColorStop(1, p[1]);
            return g;
        };
    };

    var legendMobile = isMobile
        ? { usePointStyle: true, padding: 6, font: { size: 10 }, boxWidth: 8 }
        : { usePointStyle: true, padding: 14, font: { size: 12 }, boxWidth: 12 };

    if (document.getElementById('billingStatusChart')) {
        new Chart(document.getElementById('billingStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labels(bsData),
                datasets: [{
                    data: counts(bsData),
                    // Billing status order: Pending, Unpaid, Overdue, Paid
                    // light tint -> full token: --warning, --navy, --danger, --success
                    backgroundColor: gradient([
                        ['#FADBC0', token('--warning')],
                        ['#AFAFBA', token('--navy')],
                        ['#F7C0BB', token('--danger')],
                        ['#B3E3C7', token('--success')],
                    ]),
                    borderWidth: 2,
                    borderColor: 'rgba(255,255,255,0.6)',
                    borderRadius: 6,
                }],
            },
            options: {
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: legendMobile,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var t = total(bsData);
                                var v = ctx.raw;
                                return ctx.label + ': ' + v + ' (' + ((v / t) * 100).toFixed(0) + '%)';
                            }
                        }
                    }
                },
                cutout: '78%',
                responsive: true,
                maintainAspectRatio: false,
            },
            plugins: [pctLabel],
        });
    }

    if (document.getElementById('clientStatusChart')) {
        new Chart(document.getElementById('clientStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labels(csData),
                datasets: [{
                    data: counts(csData),
                    // Existing colors kept exactly; each gains a light->full vertical gradient
                    backgroundColor: gradient([
                        ['#B2EBC7', '#22C55E'],
                        ['#FCDDAA', '#F59E0B'],
                        ['#FDCEAD', '#F97316'],
                        ['#F9BEBE', '#EF4444'],
                        ['#C5E4FA', '#5AB3F0'],
                        ['#D6C6FC', '#8B5CF6'],
                    ]),
                    borderWidth: 2,
                    borderColor: 'rgba(255,255,255,0.6)',
                    borderRadius: 6,
                }],
            },
            options: {
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: legendMobile,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var t = total(csData);
                                var v = ctx.raw;
                                return ctx.label + ': ' + v + ' (' + ((v / t) * 100).toFixed(0) + '%)';
                            }
                        }
                    }
                },
                cutout: '78%',
                responsive: true,
                maintainAspectRatio: false,
            },
            plugins: [pctLabel],
        });
    }

    function makeBarChart(canvasId, data, color) {
        var el = document.getElementById(canvasId);
        if (!el) return;

        if (isMobile && data.length > 0) {
            var h = Math.max(180, data.length * 34 + 60);
            el.parentElement.style.height = h + 'px';
            el.style.height = h + 'px';
        }

        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels(data),
                datasets: [{
                    data: counts(data),
                    backgroundColor: color,
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                layout: { padding: { top: 4, bottom: 4, right: 8 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: isMobile ? 10 : 12 }, maxTicksLimit: isMobile ? 5 : 10 },
                        grid: { display: !isMobile },
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: isMobile ? 11 : 12 },
                            autoSkip: false,
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                if (!isMobile) return label;
                                var max = Math.floor(((this.chart.chartArea || {}).right || 300) / 7);
                                if (label.length > max) return label.substring(0, max - 1) + '\u2026';
                                return label;
                            }
                        },
                    },
                },
                responsive: true,
                maintainAspectRatio: false,
            },
        });
    }

    makeBarChart('businessTypesChart', btData, '#5AB3F0');
    makeBarChart('lobChart', lobData, '#22C55E');

    /* Revenue vs new billings — line/area trend over the last 14 days */
    if (document.getElementById('revenueTrendChart') && snapLabels.length) {
        new Chart(document.getElementById('revenueTrendChart'), {
            type: 'line',
            data: {
                labels: snapLabels,
                datasets: [
                    {
                        label: 'Revenue collected',
                        data: snapRevenue,
                        borderColor: '#22C55E',
                        backgroundColor: 'rgba(34, 197, 94, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'New billings',
                        data: snapNewBillings,
                        borderColor: '#5AB3F0',
                        backgroundColor: 'rgba(90, 179, 240, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: legendMobile },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + (ctx.dataset.label.indexOf('Revenue') === 0 ? '₱' : '') + ctx.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: isMobile ? 10 : 12 } } },
                    y: { beginAtZero: true, grid: { color: 'rgba(27,27,58,0.06)' }, ticks: { font: { size: isMobile ? 10 : 12 } } },
                },
            },
        });
    }

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
                        grid: { color: 'rgba(27,27,58,0.06)' },
                        ticks: {
                            font: { size: isMobile ? 10 : 11 },
                            callback: function (value) { return '₱' + value; }
                        },
                    },
                },
            },
        });
    }
})();
</script>
@endpush