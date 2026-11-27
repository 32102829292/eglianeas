@extends('layouts.dashboard')

@section('title', 'Disconnected Clients — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Clients
    </a>

    <div class="page-head">
        <h1>Disconnected Clients</h1>
        <p>Connection history and client growth for the last 12 months.</p>
    </div>

    <div class="profile-meta">
        <div class="profile-meta-chip"><span class="meta-k">Connected now</span><span class="meta-v">{{ $connectedNow }}</span></div>
        <div class="profile-meta-chip"><span class="meta-k">Disconnected now</span><span class="meta-v">{{ $disconnectedNow }}</span></div>
        <div class="profile-meta-chip">
            <span class="meta-k">Connected change since {{ $chart['labels'][0] }}</span>
            <span class="meta-v {{ $netChange < 0 ? 'text-danger' : '' }}">
                {{ $netChange > 0 ? '+' : '' }}{{ $netChange }} &middot; {{ $netChange > 0 ? 'Growing' : ($netChange < 0 ? 'Shrinking' : 'No change') }}
            </span>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h2 class="card-title">Connected vs disconnected</h2></div>
            <p class="card-sub">Total clients at the end of each month.</p>
            <div style="position:relative;height:280px"><canvas id="totalsChart" role="img" aria-label="Connected and disconnected clients per month"></canvas></div>
        </div>
        <div class="card">
            <div class="card-head"><h2 class="card-title">Connection changes by reason</h2></div>
            <p class="card-sub">New clients against disconnections each month.</p>
            <div style="position:relative;height:280px"><canvas id="reasonsChart" role="img" aria-label="New clients and disconnections by reason per month"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">History</h2>
            <form method="GET" action="{{ route('admin.clients.disconnected') }}" class="btn-row">
                <select class="form-control form-control-sm" name="show" aria-label="Records to show" onchange="this.form.submit()">
                    <option value="current" @selected($show === 'current')>Currently disconnected</option>
                    <option value="all" @selected($show === 'all')>All history</option>
                </select>
                <select class="form-control form-control-sm" name="reason" aria-label="Reason" onchange="this.form.submit()">
                    <option value="">All reasons</option>
                    @foreach (\App\Models\ClientDisconnection::REASONS as $value => $label)
                        <option value="{{ $value }}" @selected($reason === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Client</th><th>Reason</th><th>Details</th><th>Disconnected</th><th>Reconnected</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                @if ($row->client)
                                    <a class="link" href="{{ route('admin.clients.show', $row->client_id) }}">{{ $row->client->business_name ?: $row->client->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge badge-neutral">{{ $row->reasonLabel() }}</span></td>
                            <td>{{ $row->details ?: '—' }}</td>
                            <td>{{ $row->disconnected_at->format('M j, Y') }}</td>
                            <td>{{ $row->reconnected_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if ($canManage && ! $row->reconnected_at && $row->client)
                                    <form method="POST" action="{{ route('admin.clients.reconnect', $row->client_id) }}" onsubmit="return egliane.confirm.form(this, { title: 'Reconnect this client?', message: 'The client will return to the connected list.', confirmLabel: 'Reconnect' });">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">Reconnect</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="form-hint">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var data = @json($chart);
            var line = function (label, values, color, dash) {
                return { label: label, data: values, borderColor: color, backgroundColor: color, borderDash: dash || [], tension: 0, pointRadius: 3 };
            };
            var draw = function (id, datasets) {
                new Chart(document.getElementById(id), {
                    type: 'line',
                    data: { labels: data.labels, datasets: datasets },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            };
            draw('totalsChart', [
                line('Connected', data.connected, '#2563eb'),
                line('Disconnected', data.disconnected, '#dc2626', [6, 4])
            ]);
            draw('reasonsChart', [
                line('New clients', data.new, '#2563eb'),
                line('Not active', data.reasons.not_active, '#d97706', [6, 4]),
                line('Agreed to close', data.reasons.agreed_closure, '#7c3aed', [2, 3]),
                line('Others', data.reasons.other, '#6b7280', [10, 4])
            ]);
        })();
    </script>
@endpush