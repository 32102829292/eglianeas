@extends('layouts.dashboard')

@section('title', 'Service History — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service history</h1>
            <p>
                <span class="title-service">{{ $instance->service?->name }}</span><span class="title-sep">·</span><span class="title-client">{{ $instance->client?->business_name ?: $instance->client?->name }}</span>
                <span class="badge badge-lg {{ $badgeClasses[$instance->status] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
            </p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.service-tracker.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    <div class="grid-2 details-timeline">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Details</h2>
            </div>

            <div class="info-grid">
                <div class="info-field wide">
                    <span class="info-label">Service</span>
                    <div class="info-value is-service">{{ $instance->service?->name }}</div>
                </div>
                <div class="info-field wide">
                    <span class="info-label">Client</span>
                    <div class="info-value">
                        {{ $instance->client?->business_name ?: $instance->client?->name }}
                        @if ($instance->client?->business_name && $instance->client?->name)
                            <span class="sub">{{ $instance->client?->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="info-field wide">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <span class="badge {{ $badgeClasses[$instance->status] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
                    </div>
                </div>
                <div class="info-field">
                    <span class="info-label">Identified</span>
                    <div class="info-value">{{ $instance->date_identified?->format('M j, Y') ?? '—' }}</div>
                </div>
                <div class="info-field">
                    <span class="info-label">Started</span>
                    <div class="info-value">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</div>
                </div>
                <div class="info-field">
                    <span class="info-label">Due</span>
                    <div class="info-value">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</div>
                </div>
                <div class="info-field">
                    <span class="info-label">Completed</span>
                    <div class="info-value">
                        @if ($instance->date_completed)
                            <span class="text-success">{{ $instance->date_completed->format('M j, Y') }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </div>
                </div>
                <div class="info-field wide">
                    <span class="info-label">Assigned Staff</span>
                    <div class="info-value">
                        @forelse ($instance->assignments as $assignment)
                            <span class="staff-chip @if ($assignment->completed) is-done @endif">{{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}</span>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                    </div>
                </div>
                <div class="info-field wide">
                    <span class="info-label">Hold Reason</span>
                    <div class="info-value">{{ $instance->on_hold_reason ?? '—' }}</div>
                </div>
                @if ($instance->otherService)
                    <div class="info-field wide">
                        <span class="info-label">Fill Up Form</span>
                        <div class="info-value">#{{ $instance->otherService->id }} — {{ $instance->otherService->serviceName() }}</div>
                    </div>
                @endif
                <div class="info-field wide">
                    <span class="info-label">Notes</span>
                    <div class="info-value">{{ $instance->notes ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Timeline <span class="card-sub">{{ $instance->history->count() }} {{ \Illuminate\Support\Str::plural('event', $instance->history->count()) }}</span></h2>
            </div>

            @if ($instance->history->isNotEmpty())
                <div class="timeline">
                    @foreach ($instance->history as $log)
                        @php
                            $userName = trim($log->user?->name ?? 'Guest');
                            $nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
                            $initials = strtoupper(($nameParts[0][0] ?? '') . ($nameParts[1][0] ?? ''));
                            $label = $eventLabels[$log->action] ?? $log->action;
                            if ($log->action === 'admin.tracker_assignment_toggled') {
                                $label = str_contains((string) $log->description, 'as done on') ? 'Staff assigned' : 'Staff assignment reopened';
                            }
                        @endphp
                        <div class="timeline-item">
                            <div class="timeline-top">
                                <span class="timeline-action">{{ $label }}</span>
                                <code class="code-pill">{{ $log->action }}</code>
                                <span class="timeline-when">{{ $log->created_at->format('M j, Y · g:i A') }}</span>
                            </div>
                            <div class="timeline-user">
                                <span class="timeline-user-avatar">{{ $initials ?: '?' }}</span>
                                {{ $userName }}
                            </div>
                            <div class="timeline-desc">{{ $log->description }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state compact">No history recorded yet for this service.</div>
            @endif
        </div>
    </div>
@endsection