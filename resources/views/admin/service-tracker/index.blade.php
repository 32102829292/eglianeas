@extends('layouts.dashboard')

@section('title', 'Service Tracker — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service Tracker</h1>
            <p>Track service completion across all clients and staff.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.service-tracker.summary') }}" class="btn btn-outline btn-sm">Summary</a>
            <a href="{{ route('admin.service-tracker.create') }}" class="btn btn-primary">New instance</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total instances</span>
            <b class="stat-value">{{ $stats['total'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Done</span>
            <b class="stat-value">{{ $stats['done'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">To Do</span>
            <b class="stat-value">{{ $stats['todo'] }}</b>
        </div>
        <div class="stat-card stat-icon-info">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <span class="stat-label">Assignments done</span>
            <b class="stat-value">{{ $stats['assignmentsDone'] }} / {{ $stats['assignmentsTotal'] }}</b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.service-tracker.index') }}" class="filter-bar-form">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search client or service&hellip;">
            <select name="service_id" onchange="this.form.submit()">
                <option value="">All services</option>
                @foreach ($services as $st)
                    <option value="{{ $st->id }}" @selected($activeServiceId == $st->id)>{{ $st->name }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="todo" @selected($activeStatus === 'todo')>To Do</option>
                <option value="done" @selected($activeStatus === 'done')>Done</option>
            </select>
            <select name="staff" onchange="this.form.submit()">
                <option value="">All staff</option>
                @foreach ($allStaff as $s)
                    <option value="{{ $s }}" @selected($activeStaff === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Service</th>
                        <th>Client</th>
                        <th class="text-center">Status</th>
                        <th>Assigned Staff</th>
                        <th>Primary</th>
                        <th>Started</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($instances as $instance)
                        <tr>
                            <td data-col="Service">
                                <div class="fw-semibold">{{ $instance->service?->name }}</div>
                            </td>
                            <td data-col="Client">
                                <div class="fw-semibold">{{ $instance->client?->business_name ?: $instance->client?->name }}</div>
                                <small class="text-muted">{{ $instance->client?->name }}</small>
                            </td>
                            <td data-col="Status" class="text-center">
                                @php($s = $instance->status)
                                <span class="badge {{ $s === 'done' ? 'badge-success' : 'badge-warn' }}">{{ $instance->statusLabel() }}</span>
                                @if ($instance->assignments->count())
                                    <div><small class="text-muted">{{ $instance->completionPercent() }}%</small></div>
                                @endif
                            </td>
                            <td data-col="Assigned Staff">
                                @forelse ($instance->assignments as $assignment)
                                    <form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">
                                        @csrf
<button type="submit" class="badge {{ $assignment->completed ? 'badge-success' : 'badge-neutral' }}" title="Click to toggle">
                                            {{ $assignment->staff_name }} {{ $assignment->completed ? '✓' : '○' }}
                                        </button>
                                    </form>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td data-col="Primary">{{ $instance->primary_responsible ?? '—' }}</td>
                            <td data-col="Started">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Actions" class="text-end">
                                @if ($instance->notes)
                                    <span class="text-muted" title="{{ $instance->notes }}" style="cursor:help;">📝</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No service instances yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($instances as $instance)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value">{{ $instance->service?->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">{{ $instance->client?->business_name ?: $instance->client?->name }}<br><small class="text-muted">{{ $instance->client?->name }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $instance->status)<span class="badge {{ $s === 'done' ? 'badge-success' : 'badge-warn' }}">{{ $instance->statusLabel() }}</span>@if ($instance->assignments->count()) <small class="text-muted">{{ $instance->completionPercent() }}%</small>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Assigned Staff</span><span class="cv-value">@forelse ($instance->assignments as $assignment)<form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">@csrf<button type="submit" class="badge {{ $assignment->completed ? 'badge-success' : 'badge-neutral' }}" title="Click to toggle">{{ $assignment->staff_name }} {{ $assignment->completed ? '✓' : '○' }}</button></form>@empty<span class="text-muted">—</span>@endforelse</span></div>
                        <div class="cv-row"><span class="cv-label">Primary</span><span class="cv-value">{{ $instance->primary_responsible ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Started</span><span class="cv-value">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value">@if ($instance->notes)<span class="text-muted" title="{{ $instance->notes }}" style="cursor:help;">📝</span>@endif</span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No service instances yet.</p>
                @endforelse
            </div>
        </div>
        {{ $instances->links('pagination.simple') }}
    </div>
@endsection
