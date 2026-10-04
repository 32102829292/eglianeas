@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment Monitor')

@php
    use Carbon\Carbon;
    $statusOptions = [
        '' => 'All Status',
        'submitted' => 'Submitted',
        'late' => 'Late',
        'missing' => 'Missing',
    ];
    $roleOptions = [
        '' => 'All Roles',
        'staff' => 'Staff',
        'supervisor' => 'Supervisor',
    ];
@endphp

@section('content')
<div class="page-head page-head-row">
    <div>
        <h1>Daily Accomplishment Monitor</h1>
        <p>Track employee journal submissions for {{ $date->format('F j, Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.daily-journal.admin-monitor', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous
        </a>
        <a href="{{ route('admin.daily-journal.admin-monitor', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next
        </a>
        <a href="{{ route('admin.daily-journal.admin-monitor') }}" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/></svg>
            Today
        </a>
    </div>
</div>

<form method="GET" action="{{ route('admin.daily-journal.admin-monitor') }}" class="filter-bar journal-filter-card mb-4">
    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

    <div class="filter-group">
        <label for="user_id" class="filter-label">Employee</label>
        <select name="user_id" id="user_id" class="form-select filter-select">
            <option value="">All Employees</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" {{ $selectedUserId == $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }} ({{ ucfirst($employee->role) }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label for="role" class="filter-label">Role</label>
        <select name="role" id="role" class="form-select filter-select">
            @foreach ($roleOptions as $value => $label)
                <option value="{{ $value }}" {{ $selectedRole == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label for="status" class="filter-label">Submission status</label>
        <select name="status" id="status" class="form-select filter-select">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ $selectedStatus == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label for="date" class="filter-label">Report date</label>
        <select name="date" id="date" class="form-select filter-select">
            @foreach ($availableDates as $availableDate)
                <option value="{{ $availableDate }}" {{ $availableDate == $date->format('Y-m-d') ? 'selected' : '' }}>
                    {{ Carbon::parse($availableDate)->format('F j, Y') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply filters</button>
        <a href="{{ route('admin.daily-journal.admin-monitor', ['date' => $date->format('Y-m-d')]) }}" class="btn btn-outline">Reset</a>
    </div>
</form>

<div class="journal-monitor-card">
    <div class="journal-monitor-card-head"><div><h2>Employee submissions</h2><p>{{ $journals->count() }} records shown</p></div></div>
    <div class="table-responsive">
    <table class="table table-hover journal-monitor-table">
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
                        <div class="employee-name">{{ $journal->user->name }}</div>
                        <small class="employee-email">{{ $journal->user->email }}</small>
                    </td>
                    <td>
                        <span class="badge badge-{{ $journal->user->role === 'supervisor' ? 'info' : 'secondary' }}">
                            {{ ucfirst($journal->user->role) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge journal-status {{ $journal->getStatusBadgeClass() }}">
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
                        <div class="journal-row-actions">
                            <a href="{{ route('daily-journal.show', $journal) }}" class="btn btn-outline btn-sm" title="View details">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            @if (auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.daily-journal.destroy', $journal) }}" class="inline-form" onsubmit="return confirm('Delete this journal entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm text-danger" title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
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
</div>

@if ($journals->isNotEmpty())
    <section class="journal-summary mt-4" aria-labelledby="journal-summary-title">
        <div class="journal-summary-head"><h2 id="journal-summary-title">Daily summary</h2><span>{{ $date->format('F j, Y') }}</span></div>
        <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-ok">
                    <div class="stat-value">{{ $journals->where('status', 'submitted')->count() }}</div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-warn">
                    <div class="stat-value">{{ $journals->where('status', 'late')->count() }}</div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-danger">
                    <div class="stat-value">{{ $journals->where('status', 'missing')->count() }}</div>
                    <div class="stat-label">Missing</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card stat-info">
                    <div class="stat-value">{{ $journals->count() }}</div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
        </div>
    </section>
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
    border-radius: 14px;
    padding: 1.125rem;
    box-shadow: 0 8px 24px rgba(15, 47, 91, .05);
}
.filter-group {
    flex: 1;
    min-width: 160px;
}
.filter-label { display:block; margin:0 0 .4rem; color:var(--muted,#64748b); font-size:.75rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.filter-select {
    font-size: 0.875rem;
    min-height: 2.55rem;
}
.filter-actions { display:flex; gap:.5rem; align-items:flex-end; }
.journal-monitor-card { overflow:hidden; background:var(--surface,#fff); border:1px solid var(--border-subtle,#e5e7eb); border-radius:14px; box-shadow:0 8px 24px rgba(15,47,91,.05); }
.journal-monitor-card-head { padding:1.1rem 1.25rem; border-bottom:1px solid var(--border-subtle,#e5e7eb); }.journal-monitor-card-head h2,.journal-summary-head h2 { margin:0; color:var(--navy,#1e3a5f); font-size:1rem; font-weight:700; }.journal-monitor-card-head p { margin:.2rem 0 0; color:var(--muted,#64748b); font-size:.825rem; }.journal-monitor-table { margin:0; }.journal-monitor-table thead th { padding:.8rem 1.25rem; color:var(--muted,#64748b); font-size:.7rem; letter-spacing:.055em; text-transform:uppercase; white-space:nowrap; }.journal-monitor-table tbody td { padding:1rem 1.25rem; }
.employee-name { color:var(--navy,#1e3a5f); font-weight:700; }.employee-email { color:var(--muted,#64748b); }.journal-status { display:inline-flex; align-items:center; justify-content:center; gap:.3rem; min-width:5.8rem; padding:.42rem .65rem; border-radius:999px; }.journal-row-actions { display:flex; gap:.45rem; align-items:center; }.journal-summary-head { display:flex; align-items:baseline; justify-content:space-between; margin:0 0 .8rem; }.journal-summary-head span { color:var(--muted,#64748b); font-size:.825rem; }
.table td, .table th {
    vertical-align: middle;
}
.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: 14px;
    padding: 1.15rem 1.25rem;
    text-align: left;
    min-height: 110px;
    box-shadow: 0 6px 18px rgba(15,47,91,.04);
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--navy, #1e2a4a);
}
.stat-label {
    font-size: 0.875rem;
    color: var(--muted, #6b7280);
}
@media (max-width:640px) { .filter-actions { width:100%; }.filter-actions .btn { flex:1; }.journal-monitor-table thead th,.journal-monitor-table tbody td { padding-left:1rem; padding-right:1rem; } }
</style>
@endpush
