@extends('layouts.dashboard')

@section('title', 'Service Tracker Summary — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.service-tracker.index') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Service Tracker
    </a>

    <div class="page-head">
        <h1>Service Tracker Summary</h1>
        <p>Completion overview by service and staff member.</p>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <span class="stat-label">Total instances</span>
            <b class="stat-value">{{ $overall['total'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Done</span>
            <b class="stat-value">{{ $overall['done'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">To Do</span>
            <b class="stat-value">{{ $overall['todo'] }}</b>
        </div>
    </div>

    <div class="summary-grid">
        {{-- Per-service summary --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">By Service</h2>
            </div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-muted">
                        <tr>
                            <th>Service</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Done</th>
                            <th class="text-center">To Do</th>
                            <th class="text-end">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($serviceSummary as $entry)
                            <tr>
                                <td class="fw-semibold">{{ $entry['service']->name }}</td>
                                <td class="text-center">{{ $entry['total'] }}</td>
                                <td class="text-center text-success">{{ $entry['done'] }}</td>
                                <td class="text-center text-warning">{{ $entry['todo'] }}</td>
                                <td class="text-end">
                                    @php($pct = $entry['total'] > 0 ? round(($entry['done'] / $entry['total']) * 100) : 0)
                                    <div class="progress-bar-sm" style="width:80px;display:inline-block;vertical-align:middle;">
                                        <div style="background:var(--surface-sunken);border-radius:var(--radius-btn);height:8px;width:100%;position:relative;">
                                            <div style="background:{{ $pct === 100 ? 'var(--success)' : 'var(--sky)' }};border-radius:var(--radius-btn);height:100%;width:{{ $pct }}%;position:absolute;top:0;left:0;"></div>
                                        </div>
                                    </div>
                                    <small class="muted" style="margin-left:6px;">{{ $pct }}%</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-cell">No tracked services yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per-staff summary --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">By Staff</h2>
            </div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-muted">
                        <tr>
                            <th>Staff</th>
                            <th class="text-center">Assigned</th>
                            <th class="text-center">Done</th>
                            <th class="text-end">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staffSummary as $entry)
                            <tr>
                                <td class="fw-semibold">{{ $entry['name'] }}</td>
                                <td class="text-center">{{ $entry['total'] }}</td>
                                <td class="text-center text-success">{{ $entry['done'] }}</td>
                                <td class="text-end">
                                    @php($pct = $entry['total'] > 0 ? round(($entry['done'] / $entry['total']) * 100) : 0)
                                    <div class="progress-bar-sm" style="width:80px;display:inline-block;vertical-align:middle;">
                                        <div style="background:var(--surface-sunken);border-radius:var(--radius-btn);height:8px;width:100%;position:relative;">
                                            <div style="background:{{ $pct === 100 ? 'var(--success)' : 'var(--sky)' }};border-radius:var(--radius-btn);height:100%;width:{{ $pct }}%;position:absolute;top:0;left:0;"></div>
                                        </div>
                                    </div>
                                    <small class="muted" style="margin-left:6px;">{{ $pct }}%</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-cell">No staff assignments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
