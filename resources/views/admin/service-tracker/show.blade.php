@extends('layouts.dashboard')

@section('title', 'Service History — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service history</h1>
            <p>{{ $instance->service?->name }} — {{ $instance->client?->business_name ?: $instance->client?->name }}</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.service-tracker.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    <div class="card">
        <div class="cv-card">
            <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value">{{ $instance->service?->name }}</span></div>
            <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">{{ $instance->client?->business_name ?: $instance->client?->name }} <small class="muted">({{ $instance->client?->name }})</small></span></div>
            <div class="cv-row">
                <span class="cv-label">Status</span>
                <span class="cv-value">
                    <span class="badge {{ $badgeClasses[$instance->status] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
                    @if ($instance->isOnHold() && $instance->on_hold_reason)
                        <small class="muted">— {{ $instance->on_hold_reason }}</small>
                    @endif
                </span>
            </div>
            <div class="cv-row"><span class="cv-label">Identified</span><span class="cv-value">{{ $instance->date_identified?->format('M j, Y') ?? '—' }}</span></div>
            <div class="cv-row"><span class="cv-label">Started</span><span class="cv-value">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</span></div>
            <div class="cv-row"><span class="cv-label">Due</span><span class="cv-value">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</span></div>
            <div class="cv-row"><span class="cv-label">Completed</span><span class="cv-value">{{ $instance->date_completed?->format('M j, Y') ?? '—' }}</span></div>
            <div class="cv-row"><span class="cv-label">Assigned Staff</span><span class="cv-value">
                @forelse ($instance->assignments as $assignment)
                    <span class="badge {{ $assignment->completed ? 'badge-success' : 'badge-neutral' }}">{{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}</span>
                @empty
                    <span class="muted">—</span>
                @endforelse
            </span></div>
            @if ($instance->otherService)
                <div class="cv-row"><span class="cv-label">Fill Up Form</span><span class="cv-value">#{{ $instance->otherService->id }} — {{ $instance->otherService->serviceName() }}</span></div>
            @endif
            @if ($instance->notes)
                <div class="cv-row"><span class="cv-label">Notes</span><span class="cv-value">{{ $instance->notes }}</span></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Timeline</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table">
                <thead>
                    <tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
                </thead>
                <tbody>
                    @forelse ($instance->history as $log)
                        <tr>
                            <td data-col="When" class="muted">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                            <td data-col="User" class="cell-name">{{ $log->user?->name ?? 'Guest' }}</td>
                            <td data-col="Action"><span class="fw-semibold">{{ $eventLabels[$log->action] ?? $log->action }}</span> <code class="code-pill">{{ $log->action }}</code></td>
                            <td data-col="Details">{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-cell">No history recorded yet for this service.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($instance->history as $log)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">When</span><span class="cv-value">{{ $log->created_at->format('M j, Y g:i A') }}</span></div>
                        <div class="cv-row"><span class="cv-label">User</span><span class="cv-value">{{ $log->user?->name ?? 'Guest' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Action</span><span class="cv-value"><span class="fw-semibold">{{ $eventLabels[$log->action] ?? $log->action }}</span> <code class="code-pill">{{ $log->action }}</code></span></div>
                        <div class="cv-row"><span class="cv-label">Details</span><span class="cv-value">{{ $log->description }}</span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No history recorded yet for this service.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection