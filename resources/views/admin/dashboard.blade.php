@extends('layouts.dashboard')

@section('title', 'Admin Dashboard — Egliane Accounting Services')

@section('content')
    {{-- 1. WELCOME BANNER --}}
    <div class="db-welcome">
        <div class="db-welcome-text">
            <h1>{{ $greeting }}, {{ $greetingName }}</h1>
            <p>You have {{ $billingAlerts['missingSales'] }} client{{ $billingAlerts['missingSales'] === 1 ? '' : 's' }} with pending sales this quarter and {{ $billingAlerts['overdue'] }} overdue bill{{ $billingAlerts['overdue'] === 1 ? '' : 's' }}.</p>
            <div class="db-welcome-actions">
                <a href="{{ route('admin.clients.index') }}" class="btn btn-primary btn-sm">View Clients</a>
                <a href="{{ route('admin.billing.index') }}" class="btn btn-outline btn-sm">Manage Billing</a>
            </div>
        </div>
        <div class="db-welcome-art">
            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="60" cy="60" r="56" fill="var(--sky-soft)" stroke="var(--sky)" stroke-width="2"/>
                <rect x="30" y="35" width="60" height="50" rx="8" fill="#fff" stroke="var(--navy)" stroke-width="2"/>
                <line x1="30" y1="50" x2="90" y2="50" stroke="var(--navy)" stroke-width="2"/>
                <circle cx="40" cy="43" r="3" fill="var(--sky)"/>
                <circle cx="52" cy="43" r="3" fill="var(--success)"/>
                <circle cx="64" cy="43" r="3" fill="var(--warning)"/>
                <rect x="38" y="58" width="20" height="4" rx="2" fill="var(--border)"/>
                <rect x="38" y="66" width="30" height="4" rx="2" fill="var(--border)"/>
                <rect x="38" y="74" width="15" height="4" rx="2" fill="var(--border)"/>
                <path d="M72 58 L82 65 L72 72Z" fill="var(--sky)" opacity=".6"/>
            </svg>
        </div>
    </div>

    {{-- 2. FEATURE SHORTCUT CARDS --}}
    <div class="db-feature-grid">
        <a href="{{ route('admin.billing.create') }}" class="db-feature-card">
            <div class="db-feature-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div>
                <div class="db-feature-title">Create Billing</div>
                <div class="db-feature-desc">Generate a new quarterly billing statement for a client.</div>
            </div>
        </a>
        <a href="{{ route('admin.clients.exportXlsx') }}" class="db-feature-card">
            <div class="db-feature-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </div>
            <div>
                <div class="db-feature-title">Generate Masterlist</div>
                <div class="db-feature-desc">Export the full client list as XLSX or PDF.</div>
            </div>
        </a>
    </div>

    {{-- 3. KEY METRIC CARDS --}}
    <div class="db-metric-grid">
        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label">Active Clients</span>
                <span class="stat-icon stat-icon-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
            </div>
            <div class="db-metric-value">{{ $stats['clients'] }}</div>
            <canvas class="db-sparkline" data-values="{{ json_encode([$stats['clients'] * 0.8, $stats['clients'] * 0.85, $stats['clients'] * 0.9, $stats['clients'] * 0.95, $stats['clients'] * 0.98, $stats['clients']]) }}" data-color="#5AB3F0"></canvas>
        </div>
        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label">Outstanding</span>
                <span class="stat-icon stat-icon-warn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </span>
            </div>
            <div class="db-metric-value text-danger">₱{{ number_format($billingAlerts['outstanding'], 0) }}</div>
            <canvas class="db-sparkline" data-values="{{ json_encode([$billingAlerts['outstanding'] * 1.2, $billingAlerts['outstanding'] * 1.1, $billingAlerts['outstanding'] * 1.05, $billingAlerts['outstanding'] * 1.02, $billingAlerts['outstanding'] * 1.01, $billingAlerts['outstanding']]) }}" data-color="#E74C3C"></canvas>
        </div>
        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label">Overdue Bills</span>
                <span class="stat-icon stat-icon-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
            </div>
            <div class="db-metric-value text-danger">{{ $billingAlerts['overdue'] }}</div>
            <canvas class="db-sparkline" data-values="{{ json_encode([$billingAlerts['overdue'] + 3, $billingAlerts['overdue'] + 2, $billingAlerts['overdue'] + 1, $billingAlerts['overdue'], $billingAlerts['overdue'], $billingAlerts['overdue']]) }}" data-color="#E74C3C"></canvas>
        </div>
        <div class="db-metric-card">
            <div class="db-metric-head">
                <span class="stat-label">Pending Sales</span>
                <span class="stat-icon stat-icon-warn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </span>
            </div>
            <div class="db-metric-value">{{ $billingAlerts['missingSales'] }}</div>
            <canvas class="db-sparkline" data-values="{{ json_encode([$billingAlerts['missingSales'] + 2, $billingAlerts['missingSales'] + 1, $billingAlerts['missingSales'], $billingAlerts['missingSales'], $billingAlerts['missingSales'] - 1, $billingAlerts['missingSales']]) }}" data-color="#F2994A"></canvas>
        </div>
    </div>

    {{-- 4. QUICK LINKS --}}
    <div class="db-quick-links">
        <a href="{{ route('admin.clients.index') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <span>Clients</span>
        </a>
        <a href="{{ route('admin.billing.index') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <span>Billing</span>
        </a>
        <a href="{{ route('admin.collections.index') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
            <span>Collections</span>
        </a>
        <a href="{{ route('admin.distribution.index') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>
            <span>Distribution</span>
        </a>
        <a href="{{ route('admin.activity-logs') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <span>Activity Logs</span>
        </a>
        <a href="{{ route('admin.about') }}" class="db-quick-link">
            <div class="db-ql-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
            <span>About</span>
        </a>
    </div>

    {{-- 5. MULTI-COLUMN WIDGET SECTION --}}
    <div class="db-widget-grid">
        {{-- Upcoming Deadlines --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Upcoming Deadlines</h3>
                <a href="{{ route('admin.collections.index') }}" class="btn btn-link btn-sm">View All</a>
            </div>
            @if ($dueBills->count())
                <div class="db-deadline-list">
                    @foreach ($dueBills->take(5) as $bill)
                        <div class="db-deadline-item">
                            <div class="db-deadline-date">
                                <span class="db-dday">{{ $bill->due_date->format('d') }}</span>
                                <span class="db-dmonth">{{ $bill->due_date->format('M') }}</span>
                            </div>
                            <div class="db-deadline-info">
                                <div class="db-deadline-name">{{ $bill->client?->business_name ?: $bill->client?->name }}</div>
                                <div class="db-deadline-period">{{ $bill->periodTitle() }}</div>
                            </div>
                            <span class="badge @if($bill->status === 'overdue') bg-danger @else bg-warning text-dark @endif">{{ $bill->statusLabel() }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted text-center py-3 mb-0">No upcoming deadlines.</p>
            @endif
        </div>

        {{-- Sales Submission Progress (Donut) --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Sales Submissions</h3>
                <span class="badge bg-info text-dark">{{ $missingQuarterLabel }} Q{{ $missingYear }}</span>
            </div>
            <div class="db-donut-wrap">
                <canvas id="salesDonut" height="180"></canvas>
                <div class="db-donut-center">
                    <span class="db-donut-pct">{{ $salesRate }}%</span>
                    <span class="db-donut-label">Submitted</span>
                </div>
            </div>
            <div class="db-donut-legend">
                <span><i style="background:var(--success)"></i> Submitted ({{ $submittedCount }})</span>
                <span><i style="background:var(--warning)"></i> Pending ({{ $billingAlerts['missingSales'] }})</span>
            </div>
        </div>

        {{-- Monthly Performance (Bar) --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Monthly Performance</h3>
            </div>
            <canvas id="monthlyBar" height="200"></canvas>
        </div>
    </div>

    {{-- 6. RISK ALERTS + ACTIVITY FEED --}}
    <div class="db-alert-grid">
        {{-- Risk Alerts --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Risk Alerts</h3>
            </div>
            <div class="db-risk-list">
                @if ($billingAlerts['overdue'] > 0)
                    <div class="db-risk-item db-risk-high">
                        <div class="db-risk-badge bg-danger">Overdue</div>
                        <div class="db-risk-info">
                            <span class="db-risk-count">{{ $billingAlerts['overdue'] }} bill{{ $billingAlerts['overdue'] === 1 ? '' : 's' }}</span>
                            <span class="db-risk-desc">past due — immediate follow-up needed</span>
                        </div>
                    </div>
                @endif
                @if ($billingAlerts['missingSales'] > 0)
                    <div class="db-risk-item db-risk-medium">
                        <div class="db-risk-badge bg-warning text-dark">Missing Sales</div>
                        <div class="db-risk-info">
                            <span class="db-risk-count">{{ $billingAlerts['missingSales'] }} client{{ $billingAlerts['missingSales'] === 1 ? '' : 's' }}</span>
                            <span class="db-risk-desc">have not submitted sales for {{ $missingQuarterLabel }} Q{{ $missingYear }}</span>
                        </div>
                    </div>
                @endif
                @if ($billingAlerts['dueSoon'] > 0)
                    <div class="db-risk-item db-risk-low">
                        <div class="db-risk-badge bg-info text-dark">Due Soon</div>
                        <div class="db-risk-info">
                            <span class="db-risk-count">{{ $billingAlerts['dueSoon'] }} bill{{ $billingAlerts['dueSoon'] === 1 ? '' : 's' }}</span>
                            <span class="db-risk-desc">due within 7 days</span>
                        </div>
                    </div>
                @endif
                @if ($billingAlerts['overdue'] === 0 && $billingAlerts['missingSales'] === 0 && $billingAlerts['dueSoon'] === 0)
                    <div class="text-center text-muted py-3">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:6px"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <div>All clear — no risk items.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Activity Feed --}}
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent Activity</h3>
                <a href="{{ route('admin.activity-logs') }}" class="btn btn-link btn-sm">View All</a>
            </div>
            <div class="db-activity-feed">
                @forelse ($recentActivity->take(6) as $log)
                    <div class="db-activity-item">
                        <div class="db-activity-dot"></div>
                        <div class="db-activity-body">
                            <div class="db-activity-text">
                                <strong>{{ $log->user?->name ?? 'System' }}</strong>
                                {{ $log->description }}
                            </div>
                            <div class="db-activity-time">{{ $log->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center py-3 mb-0">No activity yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- 7. CHARTS ROW (existing doughnut/bar charts, kept for analytics depth) --}}
    <div class="db-chart-grid">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Billing Status</h3>
                <a href="{{ route('admin.billing.index') }}" class="btn btn-link btn-sm">Manage</a>
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
                <h3 class="card-title">Client Status</h3>
                <a href="{{ route('admin.clients.index') }}" class="btn btn-link btn-sm">View All</a>
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
                <h3 class="card-title">Business Types</h3>
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
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // --- Sparklines ---
    document.querySelectorAll('.db-sparkline').forEach(function (canvas) {
        var values = JSON.parse(canvas.dataset.values || '[]');
        var color = canvas.dataset.color || '#5AB3F0';
        if (values.length < 2) return;
        var ctx = canvas.getContext('2d');
        var w = canvas.parentElement.offsetWidth || 120;
        var h = 32;
        canvas.width = w; canvas.height = h;
        var min = Math.min.apply(null, values); var max = Math.max.apply(null, values);
        var range = max - min || 1;
        var step = w / (values.length - 1);
        ctx.beginPath();
        values.forEach(function (v, i) {
            var x = i * step;
            var y = h - ((v - min) / range) * (h - 4) - 2;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
        var grad = ctx.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, color + '30'); grad.addColorStop(1, color + '05');
        ctx.lineTo(w, h); ctx.lineTo(0, h); ctx.closePath();
        ctx.fillStyle = grad; ctx.fill();
    });

    // --- Sales Submission Donut ---
    var salesEl = document.getElementById('salesDonut');
    if (salesEl) {
        new Chart(salesEl, {
            type: 'doughnut',
            data: {
                labels: ['Submitted', 'Pending'],
                datasets: [{ data: [{{ $submittedCount }}, {{ $billingAlerts['missingSales'] }}], backgroundColor: ['#27AE60', '#F2994A'], borderWidth: 0 }],
            },
            options: { cutout: '72%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } },
        });
    }

    // --- Monthly Performance Bar ---
    var barEl = document.getElementById('monthlyBar');
    if (barEl) {
        new Chart(barEl, {
            type: 'bar',
            data: {
                labels: @json($chartData['monthLabels']),
                datasets: [
                    { label: 'Billed', data: @json($chartData['billingTrend']), backgroundColor: '#1B1B3A', borderRadius: 6 },
                    { label: 'Collected', data: @json($chartData['collectionTrend']), backgroundColor: '#27AE60', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12 } } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: function (v) { return '₱' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v); } } } },
            },
        });
    }

    // --- Existing doughnut charts ---
    var bsData = @json($analytics['charts']['billingStatus']);
    var csData = @json($analytics['charts']['clientStatus']);
    var btData = @json($analytics['charts']['businessType']);

    var total = function (d) { return d.reduce(function (s, v) { return s + v.count; }, 0); };
    var labels = function (d) { return d.map(function (v) { return v.label; }); };
    var counts = function (d) { return d.map(function (v) { return v.count; }); };

    var pctLabel = {
        id: 'pctLabel',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx; ctx.save();
            ctx.font = '600 12px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            var t = chart.data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
            if (t === 0) return;
            chart.getDatasetMeta(0).data.forEach(function (arc, i) {
                var val = chart.data.datasets[0].data[i];
                if (val === 0) return;
                var pos = arc.tooltipPosition();
                ctx.fillStyle = '#fff';
                ctx.fillText(((val / t) * 100).toFixed(0) + '%', pos.x, pos.y);
            });
            ctx.restore();
        }
    };

    function makeDonut(id, data, colors) {
        var el = document.getElementById(id);
        if (!el || total(data) === 0) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels: labels(data), datasets: [{ data: counts(data), backgroundColor: colors, borderWidth: 0 }] },
            options: { cutout: '65%', responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } } } },
            plugins: [pctLabel],
        });
    }
    makeDonut('billingStatusChart', bsData, ['#F59E0B', '#1B1B3A', '#F97316', '#22C55E']);
    makeDonut('clientStatusChart', csData, ['#22C55E', '#F59E0B', '#F97316', '#EF4444']);

    function makeBarChart(canvasId, data, color) {
        var el = document.getElementById(canvasId);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: labels(data), datasets: [{ data: counts(data), backgroundColor: color, borderRadius: 6 }] },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } }, responsive: true, maintainAspectRatio: true },
        });
    }
    makeBarChart('businessTypesChart', btData, '#5AB3F0');
})();
</script>
@endpush
