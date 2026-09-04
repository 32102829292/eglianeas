@extends('layouts.dashboard')

@section('title', 'Service Tracker — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service Tracker</h1>
            <p>Track the progress of services for your account.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total services</span>
            <b class="stat-value">{{ $summary['total'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Completed</span>
            <b class="stat-value">{{ $summary['done'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">In progress</span>
            <b class="stat-value">{{ $summary['todo'] }}</b>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Your services</h2>
        </div>
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Service</th>
                        <th class="text-center">Status</th>
                        <th>Staff</th>
                        <th>Started</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($instances as $instance)
                        <tr>
                            <td class="fw-semibold">{{ $instance->service?->name }}</td>
                            <td class="text-center">
                                @php($s = $instance->status)
                                <span class="badge {{ $s === 'done' ? 'badge-success' : 'badge-warn' }}">{{ $instance->statusLabel() }}</span>
                                @if ($instance->assignments->count())
                                    <div><small class="text-muted">{{ $instance->completionPercent() }}% complete</small></div>
                                @endif
                            </td>
                            <td>
                                @forelse ($instance->assignments as $a)
                                    <span class="badge {{ $a->completed ? 'badge-success' : 'badge-neutral' }}">{{ $a->displayName() }} {{ $a->completed ? '✓' : '○' }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $instance->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $instance->notes ? Str::limit($instance->notes, 40) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No services tracked yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $instances->links('pagination.simple') }}
    </div>
@endsection
