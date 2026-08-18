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
            <b class="stat-value">{{ $stats['clients'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Transactions</span>
            <b class="stat-value">{{ $stats['transactions'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Filings</span>
            <b class="stat-value">{{ $stats['filings'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Pending filings</span>
            <b class="stat-value">{{ $stats['pendingFilings'] }}</b>
        </div>
    </div>

    <div class="section-gap">
        <h2 class="text-section">Account analytics</h2>
        <div class="grid-2">
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Billing status</h3>
                    <a href="{{ route('admin.billing.index') }}">Manage billing</a>
                </div>
                <div class="chart-canvas-wrap">
                    @php $bsTotal = $analytics['charts']['billingStatus']->sum('count'); @endphp
                    @if ($bsTotal > 0)
                        <canvas id="billingStatusChart" height="200"></canvas>
                    @else
                        <p class="chart-empty">Not enough data yet.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Client account status</h3>
                    <a href="{{ route('admin.clients.index') }}">View clients</a>
                </div>
                <div class="chart-canvas-wrap">
                    @php $csTotal = $analytics['charts']['clientStatus']->sum('count'); @endphp
                    @if ($csTotal > 0)
                        <canvas id="clientStatusChart" height="200"></canvas>
                    @else
                        <p class="chart-empty">Not enough data yet.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Business types</h3>
                </div>
                <div class="chart-canvas-wrap">
                    @php $btTotal = $analytics['charts']['businessType']->sum('count'); @endphp
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
                <div class="chart-canvas-wrap">
                    @php $lobTotal = $analytics['charts']['lineOfBusiness']->sum('count'); @endphp
                    @if ($lobTotal > 0)
                        <canvas id="lobChart" height="150"></canvas>
                    @else
                        <p class="chart-empty">Not enough data yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Billing reminders</h3>
            <a href="{{ route('admin.billing.index') }}">Manage billing</a>
        </div>
        <div class="alert-list">
            <div class="alert-list-item @if ($billingAlerts['dueSoon']) alert-warn @endif">
                <div class="alert-list-main">
                    <b>Bills due within 7 days</b>
                    <small>Unpaid bills entering the reminder window. Reminders start one week before the due date.</small>
                </div>
                <span class="alert-list-count">{{ $billingAlerts['dueSoon'] }}</span>
            </div>
            <div class="alert-list-item @if ($billingAlerts['overdue']) alert-danger @endif">
                <div class="alert-list-main">
                    <b>Overdue bills</b>
                    <small>Past-due bills, escalated wording until marked paid.</small>
                </div>
                <span class="alert-list-count">{{ $billingAlerts['overdue'] }}</span>
            </div>
            <div class="alert-list-item @if ($billingAlerts['outstanding'] > 0) alert-warn @endif">
                <div class="alert-list-main">
                    <b>Outstanding balance</b>
                    <small>Pending + unpaid + overdue billing totals.</small>
                </div>
                <span class="alert-list-count">₱{{ number_format($billingAlerts['outstanding'], 2) }}</span>
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
                                <td><span class="badge badge-{{ $filing->status }}">{{ \App\Models\Filing::STATUSES[$filing->status] }}</span></td>
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
            <a href="{{ route('admin.activity-logs') }}">View all</a>
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

    var bsData = {!! json_encode($analytics['charts']['billingStatus']) !!};
    var csData = {!! json_encode($analytics['charts']['clientStatus']) !!};
    var btData = {!! json_encode($analytics['charts']['businessType']) !!};
    var lobData = {!! json_encode($analytics['charts']['lineOfBusiness']) !!};

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

    if (document.getElementById('billingStatusChart')) {
        new Chart(document.getElementById('billingStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labels(bsData),
                datasets: [{
                    data: counts(bsData),
                    backgroundColor: ['#F59E0B', '#1B1B3A', '#F97316', '#22C55E'],
                    borderWidth: 0,
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } },
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
                cutout: '65%',
                responsive: true,
                maintainAspectRatio: true,
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
                    backgroundColor: ['#22C55E', '#F59E0B', '#F97316', '#EF4444'],
                    borderWidth: 0,
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } },
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
                cutout: '65%',
                responsive: true,
                maintainAspectRatio: true,
            },
            plugins: [pctLabel],
        });
    }

    function makeBarChart(canvasId, data, color) {
        var el = document.getElementById(canvasId);
        if (!el) return;
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
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { grid: { display: false } },
                },
                responsive: true,
                maintainAspectRatio: true,
            },
        });
    }

    makeBarChart('businessTypesChart', btData, '#5AB3F0');
    makeBarChart('lobChart', lobData, '#22C55E');
})();
</script>
@endpush
