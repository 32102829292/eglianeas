@extends('layouts.dashboard')

@section('title', 'Service Tracker — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service Tracker</h1>
            <p>Track service completion across all clients and staff.</p>
        </div>
        <div class="page-head-actions">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.service-tracker.summary') }}" class="btn btn-outline btn-sm">Summary</a>
                <a href="{{ route('admin.service-tracker.create') }}" class="btn btn-primary">New instance</a>
            @endif
        </div>
    </div>

    <div class="stat-grid cols-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total Services</span>
            <b class="stat-value">{{ $stats['total'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-9-9"/><path d="M21 3v6h-6"/></svg>
            </div>
            <span class="stat-label">In Progress</span>
            <b class="stat-value">{{ $stats['inProgress'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">On Hold</span>
            <b class="stat-value">{{ $stats['onHold'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Completed</span>
            <b class="stat-value">{{ $stats['done'] }}</b>
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
                <option value="in_progress" @selected($activeStatus === 'in_progress')>In Progress</option>
                <option value="on_hold" @selected($activeStatus === 'on_hold')>On Hold</option>
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
        @error('action')<div class="form-error" style="margin-bottom:12px;">{{ $message }}</div>@enderror
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 tracker-table">
                <thead class="thead-muted">
                    <tr>
                        <th>Service</th>
                        <th>Client</th>
                        <th class="text-center">Status</th>
                        <th>Assigned Staff</th>
                        <th>Started</th>
                        <th>Due</th>
                        <th>Completed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($instances as $instance)
                        <tr>
                            <td data-col="Service">
                                <div class="fw-semibold">{{ $instance->service?->name }}</div>
                                @if ($instance->notes)
                                    <div class="cell-note" title="{{ $instance->notes }}">{{ $instance->notes }}</div>
                                @endif
                            </td>
                            <td data-col="Client">
                                <div class="fw-semibold">{{ $instance->client?->business_name ?: $instance->client?->name }}</div>
                                @if ($instance->client?->business_name && $instance->client?->name)
                                    <small class="muted">{{ $instance->client?->name }}</small>
                                @endif
                            </td>
                            <td data-col="Status" class="text-center">
                                @php($s = $instance->status)
                                <span class="badge {{ $badgeClasses[$s] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
                                @if ($instance->assignments->count())
                                    <div><small class="muted">{{ $instance->completionPercent() }}%</small></div>
                                @endif
                            </td>
                            <td data-col="Assigned Staff">
                                @forelse ($instance->assignments as $assignment)
                                    <form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="staff-chip {{ $assignment->completed ? 'is-done' : '' }}" title="Toggle assignment status">
                                            {{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}
                                        </button>
                                    </form>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </td>
                            <td data-col="Started" class="text-nowrap">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Due" class="text-nowrap">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Completed" class="text-nowrap">{{ $instance->date_completed?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Actions" class="text-end">
                                <div class="cv-actions">
                                    @if ($instance->status === 'todo')
                                        <form method="POST" action="{{ route('admin.service-tracker.start', $instance) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline">Start</button>
                                        </form>
                                    @elseif ($instance->status === 'in_progress')
                                        <form method="POST" action="{{ route('admin.service-tracker.hold', $instance) }}" class="d-inline-flex align-items-center gap-1">
                                            @csrf
                                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason">
                                            <button type="submit" class="btn btn-sm btn-outline">Hold</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.service-tracker.complete', $instance) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Complete</button>
                                        </form>
                                    @elseif ($instance->status === 'on_hold')
                                        <form method="POST" action="{{ route('admin.service-tracker.resume', $instance) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline">Resume</button>
                                        </form>
                                    @endif
                                    <span class="actions-divider"></span>
                                    <a href="{{ route('admin.service-tracker.show', $instance) }}" class="btn btn-sm btn-link">History</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-cell">No service instances yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($instances as $instance)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value">{{ $instance->service?->name }}</span></div>
                        <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">
                            <div>{{ $instance->client?->business_name ?: $instance->client?->name }}</div>
                            @if ($instance->client?->business_name && $instance->client?->name)<small class="muted">{{ $instance->client?->name }}</small>@endif
                        </span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $instance->status)<span class="badge {{ $badgeClasses[$s] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>@if ($instance->assignments->count()) <small class="muted">{{ $instance->completionPercent() }}%</small>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Assigned Staff</span><span class="cv-value">@forelse ($instance->assignments as $assignment)<form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">@csrf<button type="submit" class="staff-chip {{ $assignment->completed ? 'is-done' : '' }}" title="Toggle assignment status">{{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}</button></form>@empty<span class="muted">—</span>@endforelse</span></div>
                        <div class="cv-row"><span class="cv-label">Started</span><span class="cv-value">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Due</span><span class="cv-value">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Completed</span><span class="cv-value">{{ $instance->date_completed?->format('M j, Y') ?? '—' }}</span></div>
                        @if ($instance->notes)
                            <div class="cv-row"><span class="cv-label">Notes</span><span class="cv-value">{{ $instance->notes }}</span></div>
                        @endif
                        <div class="cv-row">
                            <span class="cv-label">Actions</span>
                            <span class="cv-value">
                                <div class="cv-actions">
                                    @if ($instance->status === 'todo')
                                        <form method="POST" action="{{ route('admin.service-tracker.start', $instance) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline">Start</button></form>
                                    @elseif ($instance->status === 'in_progress')
                                        <form method="POST" action="{{ route('admin.service-tracker.hold', $instance) }}" class="d-inline-flex align-items-center gap-1">@csrf<input type="text" name="reason" class="form-control form-control-sm" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason"><button type="submit" class="btn btn-sm btn-outline">Hold</button></form>
                                        <form method="POST" action="{{ route('admin.service-tracker.complete', $instance) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-success">Complete</button></form>
                                    @elseif ($instance->status === 'on_hold')
                                        <form method="POST" action="{{ route('admin.service-tracker.resume', $instance) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline">Resume</button></form>
                                    @endif
                                    <span class="actions-divider"></span>
                                    <a href="{{ route('admin.service-tracker.show', $instance) }}" class="btn btn-sm btn-link">History</a>
                                </div>
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No service instances yet.</p>
                @endforelse
            </div>
        </div>
        {{ $instances->links('pagination.simple') }}
    </div>
@endsection
