@extends('layouts.dashboard')

@section('title')
    Weekly Target History — {{ $bookkeeping->week_start?->format('M j, Y') }} — Egliane Accounting Services
@endsection

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Weekly Target History</h1>
            <p>
                <span class="title-week">{{ $bookkeeping->week_start?->format('M j, Y') }} – {{ $bookkeeping->week_end?->format('M j, Y') }}</span>
                <span class="title-sep">·</span>
                <span class="title-client">{{ $bookkeeping->displayOwnerName() }}</span>
                <span class="title-sep">·</span>
                <span class="badge {{ $badgeClasses[$bookkeeping->status] ?? 'badge-neutral' }}">{{ $bookkeeping->statusLabel() }}</span>
            </p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.weekly-bookkeeping.show', $bookkeeping) }}" class="btn btn-outline btn-sm">Back to weekly target</a>
            <a href="{{ route('admin.weekly-bookkeeping.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Accountability Timeline <span class="card-sub">{{ $bookkeeping->history->count() }} {{ \Illuminate\Support\Str::plural('event', $bookkeeping->history->count()) }}</span></h2>
        </div>

        @if ($bookkeeping->history->isNotEmpty())
            <div class="timeline">
                @foreach ($bookkeeping->history as $log)
                    @php
                        $userName = trim($log->user?->name ?? 'Guest');
                        $userRole = $log->user ? $log->user->role : 'Unknown';
                        $roleLabel = match($userRole) {
                            'admin' => 'Admin',
                            'supervisor' => 'Supervisor',
                            'staff' => 'Staff',
                            'client' => 'Client',
                            default => ucfirst($userRole),
                        };
                        $nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
                        $initials = strtoupper(($nameParts[0][0] ?? '') . ($nameParts[1][0] ?? ''));
                        $label = $eventLabels[$log->action] ?? $log->action;
                    @endphp
                    <div class="timeline-item">
                        <div class="timeline-top">
                            <span class="timeline-action">{{ $label }}</span>
                            <span class="timeline-when">{{ $log->created_at->format('M j, Y · g:i A') }}</span>
                        </div>
                        <div class="timeline-user">
                            <span class="timeline-user-avatar">{{ $initials ?: '?' }}</span>
                            {{ $userName }}
                            <span class="timeline-role timeline-role-{{ $userRole }}">{{ $roleLabel }}</span>
                        </div>
                        <div class="timeline-desc">{{ $log->description }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state compact">No history recorded yet for this weekly target.</div>
        @endif
    </div>
@endsection

@push('styles')
<style>
.timeline-role {
    display: inline-block;
    margin-left: 8px;
    padding: 1px 6px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-radius: 9999px;
    background: var(--muted-bg, #f3f4f6);
    color: var(--muted, #6b7280);
}
.timeline-role-admin { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); }
.timeline-role-supervisor { background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00); }
.timeline-role-staff { background: var(--info-bg, #dbeafe); color: var(--info, #3b82f6); }
.timeline-role-client { background: var(--success-bg, #d1fae5); color: var(--success, #10b981); }
</style>
@endpush