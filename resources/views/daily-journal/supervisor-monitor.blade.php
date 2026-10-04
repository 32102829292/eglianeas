@extends('layouts.dashboard')

@section('title', 'Team Journal Monitor')

@php
    use Carbon\Carbon;
    $statusOptions = [
        '' => 'All Status',
        'submitted' => 'Submitted',
        'late' => 'Late',
        'missing' => 'Missing',
    ];
@endphp

@section('content')
<div class="page-head page-head-row">
    <div>
        <h1>Team Journal Monitor</h1>
        <p>View journal submissions for your team — {{ $date->format('F j, Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.daily-journal.supervisor-monitor', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous
        </a>
        <a href="{{ route('admin.daily-journal.supervisor-monitor', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next
        </a>
        <a href="{{ route('admin.daily-journal.supervisor-monitor') }}" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/></svg>
            Today
        </a>
    </div>
</div>

<form method="GET" action="{{ route('admin.daily-journal.supervisor-monitor') }}" class="filter-bar mb-4">
    @csrf
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

    <div class="filter-group">
        <label for="status" class="form-label visually-hidden">Status</label>
        <select name="status" id="status" class="form-select filter-select">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ $selectedStatus == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label for="date" class="form-label visually-hidden">Date</label>
        <select name="date" id="date" class="form-select filter-select">
            @foreach ($availableDates as $availableDate)
                <option value="{{ $availableDate }}" {{ $availableDate == $date->format('Y-m-d') ? 'selected' : '' }}>
                    {{ Carbon::parse($availableDate)->format('F j, Y') }}
                </option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="{{ route('admin.daily-journal.supervisor-monitor', ['date' => $date->format('Y-m-d')]) }}" class="btn btn-outline">Reset</a>
</form>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Role</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($journals as $journal)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $journal->user->name }}</div>
                        <small class="text-muted">{{ $journal->user->email }}</small>
                    </td>
                    <td>
                        <span class="badge badge-{{ $journal->user->role === 'supervisor' ? 'info' : 'secondary' }}">
                            {{ ucfirst($journal->user->role) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $journal->getStatusBadgeClass() }}">
                            {{ $journal->getStatusIcon() }} {{ $journal->getStatusLabel() }}
                        </span>
                    </td>
                    <td>
                        @if ($journal->submitted_at)
                            {{ $journal->submitted_at->format('F j, Y g:i A') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('daily-journal.show', $journal) }}" class="btn btn-outline btn-sm" title="View details">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No journal entries found for this date.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($journals->isNotEmpty())
    <div class="mt-4">
        <h4>Team Summary for {{ $date->format('F j, Y') }}</h4>
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card stat-ok">
                    <div class="stat-value">{{ $journals->where('status', 'submitted')->count() }}</div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-warn">
                    <div class="stat-value">{{ $journals->where('status', 'late')->count() }}</div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-danger">
                    <div class="stat-value">{{ $journals->where('status', 'missing')->count() }}</div>
                    <div class="stat-label">Missing</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-info">
                    <div class="stat-value">{{ $journals->count() }}</div>
                    <div class="stat-label">Total Team</div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
    background: var(--surface, #fff);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: 8px;
    padding: 1rem;
}
.filter-group {
    flex: 1;
    min-width: 180px;
}
.filter-select {
    font-size: 0.875rem;
}
.table td, .table th {
    vertical-align: middle;
}
.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--navy, #1e2a4a);
}
.stat-label {
    font-size: 0.875rem;
    color: var(--muted, #6b7280);
}
</style>
@endpush