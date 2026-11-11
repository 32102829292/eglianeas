@extends('layouts.dashboard')

@section('title', 'Priority List / To-Do List — Egliane Accounting Services')

@section('content')
@php
    $isStaffView = auth()->user()->isStaff();
    $isAdmin = auth()->user()->isAdmin();
@endphp

<!-- Page Header -->
<header class="eas-page-head">
    <div class="eas-head-main">
        <div class="eas-head-left">
            <span class="eas-eyebrow">Priority List</span>
            <h1 class="eas-head-title">To-Do List</h1>
            <p class="eas-head-sub">
                @if ($isStaffView)
                    Your assigned tasks and lessons learned, plus unassigned work available to pick up.
                @else
                    Manage priority tasks, to-dos, and lessons learned.
                @endif
            </p>
        </div>
    </div>
    <div class="eas-head-actions">
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.priority-items.create') }}" class="btn btn-primary eas-btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Task
            </a>
        @endif
    </div>
</header>

<!-- Summary Statistics -->
<div class="eas-stat-grid">
    <div class="eas-stat-card eas-stat-action">
        <div class="eas-stat-icon eas-stat-icon-action">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Action Required</span>
            <span class="eas-stat-value">{{ $summary['action_required'] }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-overdue">
        <div class="eas-stat-icon eas-stat-icon-overdue">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Overdue</span>
            <span class="eas-stat-value">{{ $summary['overdue'] }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-duetoday">
        <div class="eas-stat-icon eas-stat-icon-duetoday">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Due Today</span>
            <span class="eas-stat-value">{{ $summary['due_today'] }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-duetomorrow">
        <div class="eas-stat-icon eas-stat-icon-duetomorrow">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Due Tomorrow</span>
            <span class="eas-stat-value">{{ $summary['due_tomorrow'] }}</span>
        </div>
    </div>
    <div class="eas-stat-card eas-stat-completed">
        <div class="eas-stat-icon eas-stat-icon-completed">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="eas-stat-info">
            <span class="eas-stat-label">Completed</span>
            <span class="eas-stat-value">{{ $summary['completed'] }}</span>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="eas-card">
    <div class="eas-card-head">
        <h2 class="eas-card-title">Filter Items</h2>
    </div>
    <div class="eas-card-body">
        <form method="GET" action="{{ route('admin.priority-items.index') }}" class="eas-filter-form" role="search" autocomplete="off">
            <div class="eas-filter-grid eas-filter-grid-three">
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_q">Search</label>
                    <div class="eas-search-wrapper">
                        <span class="eas-search-icon" aria-hidden="true">&#128269;</span>
                        <input class="eas-form-control" id="filter_q" name="q" type="text" value="{{ $q }}" placeholder="Search task, description, notes..." autocomplete="off" autocapitalize="off" spellcheck="false">
                    </div>
                </div>
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_type">Type</label>
                    <select class="eas-form-control eas-form-select" id="filter_type" name="type">
                        <option value="">All</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected($activeType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_priority">Priority</label>
                    <select class="eas-form-control eas-form-select" id="filter_priority" name="priority">
                        <option value="">All</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($activePriority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_urgency">Urgency</label>
                    <select class="eas-form-control eas-form-select" id="filter_urgency" name="urgency">
                        <option value="">All</option>
                        @foreach ($urgencies as $value => $label)
                            <option value="{{ $value }}" @selected($activeUrgency === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_status">Status</label>
                    <select class="eas-form-control eas-form-select" id="filter_status" name="status">
                        <option value="">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isStaffView)
                <div class="eas-filter-group">
                    <label class="eas-filter-label" for="filter_assigned_staff_id">Assigned Staff</label>
                    <select class="eas-form-control eas-form-select" id="filter_assigned_staff_id" name="assigned_staff_id">
                        <option value="">All</option>
                        <option value="unassigned" @selected($activeAssignedStaffId === 'unassigned')>Unassigned</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected($activeAssignedStaffId == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endunless
            </div>

            <div class="eas-filter-actions">
                <button type="submit" class="btn btn-primary eas-filter-apply">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Apply Filters
                </button>
                @if ($hasFilters)
                    <a href="{{ route('admin.priority-items.index') }}" class="btn btn-outline eas-filter-reset">Reset Filters</a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Items List -->
<div class="eas-card">
    <div class="eas-card-head">
        <div>
            <h2 class="eas-card-title">Priority Items</h2>
            <span class="eas-card-count">{{ $items->total() }} {{ Str::plural('item', $items->total()) }}</span>
        </div>
    </div>
    <div class="eas-card-body">
        <div class="eas-table-wrap table-card-view">
            <table class="eas-table eas-table-hover align-middle mb-0 priority-board-table">
                <thead class="eas-thead-muted">
                    <tr>
                        <th class="text-nowrap">Type</th>
                        <th data-col="Task / Lesson">Task / Lesson</th>
                        <th data-col="Priority" class="text-nowrap">Priority</th>
                        <th data-col="Urgency" class="text-nowrap">Urgency</th>
                        <th data-col="Due Date" class="text-nowrap">Due Date</th>
                        <th data-col="Assigned Staff" class="text-nowrap">Assigned Staff</th>
                        <th data-col="Status" class="text-nowrap">Status</th>
                        <th data-col="Evidence" class="text-nowrap">Evidence</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php $overdueDays = $item->overdueDays(); @endphp
                        <tr class="{{ $item->isOverdue() ? 'priority-row-overdue' : '' }} {{ $item->isCompleted() ? 'priority-row-completed' : '' }}">
                            <td data-col="Type">
                                <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                            </td>
                            <td data-col="Task / Lesson" class="priority-cell-task">
                                <div class="priority-task-title">{{ Str::limit($item->task_lesson, 90) }}</div>
                                @if ($item->description)
                                    <div class="priority-cell-sub">{{ Str::limit($item->description, 100) }}</div>
                                @endif
                                @if (strlen($item->task_lesson) > 90)
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="tooltip" title="{{ $item->task_lesson }}">Show full title</button>
                                @endif
                            </td>
                            <td data-col="Priority">
                                <span class="badge priority-pill {{ $item->priorityBadgeClass() }} priority-{{ $item->priority }}">
                                    <span class="priority-dot" aria-hidden="true"></span>{{ $item->priorityLabel() }}
                                </span>
                            </td>
                            <td data-col="Urgency">
                                <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                            </td>
                            <td data-col="Due Date" class="priority-cell-due {{ $overdueDays !== null ? 'is-overdue' : '' }}">
                                <div class="priority-due-date">{{ $item->due_date?->format('M j, Y') ?? '—' }}</div>
                                <span class="badge {{ $item->deadlineBadgeClass() }}">{{ $item->deadlineLabel() }}</span>
                                @if ($overdueDays !== null)
                                    <div class="priority-overdue-note">
                                        <span aria-hidden="true">&#128308;</span>
                                        {{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} overdue
                                    </div>
                                @endif
                            </td>
                            <td data-col="Assigned Staff" class="priority-cell-by">
                                @if ($item->assignedStaff)
                                    <div class="priority-assigned-name">{{ $item->assignedStaff->name }}</div>
                                @else
                                    <span class="badge badge-info">Unassigned</span>
                                @endif
                                @include('admin.priority-items.partials.assign-form', ['item' => $item])
                            </td>
                            <td data-col="Status">
                                <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                            </td>
                            <td data-col="Evidence" class="priority-cell-evidence">
                                @php
                                    $evidenceCount = $item->evidences_count > 0 ? $item->evidences_count : ($item->evidence_path ? 1 : 0);
                                @endphp
                                @if ($evidenceCount > 0)
                                    <span class="priority-evidence-pill">
                                        <span aria-hidden="true">&#128206;</span>
                                        {{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }}
                                    </span>
                                    <a href="{{ route('admin.priority-items.show', $item) }}#evidence" class="btn btn-outline btn-sm">View</a>
                                @else
                                    <span class="priority-evidence-empty">
                                        <span aria-hidden="true">&#128206;</span> No evidence
                                    </span>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end priority-cell-actions">
                                <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline btn-sm" title="View details">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    View
                                </a>
                                @if ($isAdmin)
                                    <a href="{{ route('admin.priority-items.edit', $item) }}" class="btn btn-outline btn-sm" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.priority-items.destroy', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this item?', message: 'This item will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline danger btn-sm" title="Delete">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-cell">
                            @if ($hasFilters)
                                No priority items match your current filters.
                            @elseif ($isStaffView)
                                Nothing has been assigned to you yet. Unassigned work will appear here as soon as it is created.
                            @else
                                No priority items found.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.simple') }}
    </div>
</div>

@include('admin.priority-items._due-date-script')
@endsection

@push('styles')
<style>
/* ============================================================
   Priority List / To-Do List — Premium Modern Design
   ============================================================ */

/* ---------- Page Header ---------- */
.eas-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 28px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-head-main {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    flex-wrap: wrap;
}

.eas-head-left {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.eas-eyebrow {
    margin: 0;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-head-title {
    margin: 0 0 6px;
    font-family: var(--font-head);
    font-size: 27px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.02em;
}

.eas-head-sub {
    margin: 0;
    font-size: 14.5px;
    color: var(--muted-text);
    line-height: 1.5;
}

.eas-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.eas-head-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.eas-head-actions .btn svg {
    width: 17px;
    height: 17px;
    flex: none;
}

/* ---------- Summary Statistics ---------- */
.eas-stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}

.eas-stat-card {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    padding: 18px 20px;
    background: var(--surface);
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-card);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.eas-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 24, 40, 0.08);
    border-color: var(--sky);
}

.eas-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #F1F5F9;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: var(--navy);
}

.eas-stat-icon-action { color: #F59E0B; background: #FFFBEB; }
.eas-stat-icon-overdue { color: #EF4444; background: #FEF2F2; }
.eas-stat-icon-duetoday { color: #F59E0B; background: #FFFBEB; }
.eas-stat-icon-duetomorrow { color: #2563EB; background: #EFF6FF; }
.eas-stat-icon-completed { color: #10B981; background: #ECFDF5; }

.eas-stat-info {
    min-width: 0;
}

.eas-stat-label {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
    display: block;
    margin-bottom: 4px;
}

.eas-stat-value {
    font-family: var(--font-head);
    font-size: 22px;
    font-weight: 800;
    color: var(--navy);
    line-height: 1.15;
    letter-spacing: -0.01em;
}

/* ---------- Cards ---------- */
.eas-card {
    margin-bottom: 24px;
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}

.eas-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border-subtle);
    background: #F6F8FB;
}

.eas-card-head h2 {
    margin: 0;
    font-family: var(--font-head);
    font-size: 17px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.eas-card-count {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--muted-text);
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: 999px;
    padding: 3px 10px;
}

.eas-card-body {
    padding: 0;
}

/* ---------- Filter Form ---------- */
.eas-filter-form {
    padding: 20px 22px;
}

.eas-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px 18px;
}

.eas-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.eas-filter-label {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
}

/* Consistent field styling for all inputs and selects. */
.eas-form-control {
    width: 100%;
    height: 45px;
    box-sizing: border-box;
    border: 1px solid #D6DDE6;
    border-radius: 10px;
    padding: 0 14px;
    padding-left: 38px;
    font-family: var(--font-body);
    font-size: 14px;
    line-height: 1.35;
    color: var(--text);
    background: #fff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.eas-form-control::placeholder {
    color: #9AA4B2;
}

.eas-form-control:focus {
    border-color: var(--sky);
    box-shadow: 0 0 0 3px rgba(90, 179, 240, 0.18);
}

/* Search input with icon. */
.eas-search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.eas-search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #9AA4B2;
    pointer-events: none;
    z-index: 2;
}

.eas-search-wrapper .eas-form-control {
    padding-left: 38px;
}

/* Custom dropdown arrow for selects. */
.eas-form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px 16px;
    padding-right: 40px;
}

.eas-form-select::-ms-expand {
    display: none;
}

/* Dedicated action row. */
.eas-filter-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--border-subtle);
}

.eas-filter-actions .btn {
    font-size: 13px;
    font-weight: 600;
    padding: 9px 20px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.eas-filter-actions .btn svg {
    width: 15px;
    height: 15px;
    flex: none;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .eas-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .eas-filter-grid {
        grid-template-columns: 1fr;
    }

    .eas-filter-actions {
        flex-direction: column;
    }

    .eas-filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

/* ---------- Table ---------- */
.eas-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    margin-bottom: 16px;
}

.eas-table {
    width: 100%;
    border-collapse: collapse;
}

.eas-thead-muted th {
    background: #F6F8FB;
    color: var(--muted-text);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-subtle);
    text-align: left;
    white-space: nowrap;
}

.eas-thead-muted th.text-end {
    text-align: right;
}

.eas-table td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-subtle);
    vertical-align: middle;
}

.eas-table tbody tr {
    transition: background 0.15s ease;
}

.eas-table tbody tr:hover {
    background: #F8FAFC;
}

.priority-board-table {
    min-width: 1120px;
}

.priority-row-overdue {
    background: #fef7f7 !important;
}

.priority-row-overdue > td:first-child {
    box-shadow: inset 3px 0 0 #dc2626;
}

.priority-row-overdue:hover {
    background: #fee2e2 !important;
}

.priority-row-completed {
    background: #f0fdf4 !important;
}

.priority-row-completed .priority-task-title {
    color: #6B7280;
}

.priority-row-completed .priority-task-title,
.priority-row-completed .priority-cell-sub {
    text-decoration: line-through;
    text-decoration-color: #cbd5e1;
    text-decoration-thickness: 1px;
}

/* ---------- Table Cells ---------- */
.priority-cell-task {
    min-width: 0;
}

.priority-task-title {
    font-weight: 600;
    color: var(--navy);
    line-height: 1.4;
    display: block;
    margin-bottom: 3px;
}

.priority-cell-task .priority-cell-sub {
    font-size: 12px;
    color: var(--muted-text);
    line-height: 1.45;
    display: block;
}

.priority-due-date {
    font-weight: 600;
    color: var(--navy);
    line-height: 1.5;
    display: block;
    margin-bottom: 3px;
}

.priority-cell-due .badge {
    margin-top: 0;
    display: inline-block;
}

.priority-overdue-note {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    font-size: 11.5px;
    font-weight: 700;
    color: #b91c1c;
    white-space: nowrap;
}

.priority-cell-by {
    color: var(--navy);
}

.priority-assigned-name {
    font-weight: 600;
    color: var(--navy);
    display: block;
    margin-bottom: 4px;
}

.priority-evidence-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #0369a1;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 999px;
    padding: 4px 11px;
    text-decoration: none;
    white-space: nowrap;
}

.priority-evidence-pill:hover {
    background: #e0f2fe;
}

.priority-evidence-pill + .btn {
    margin-top: 6px;
}

.priority-evidence-empty {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--muted-text);
}

.priority-cell-actions .btn {
    margin-left: 6px;
}

.priority-cell-actions .btn:first-child {
    margin-left: 0;
}

/* ---------- Empty State ---------- */
.empty-cell {
    text-align: center;
    color: var(--muted-text);
    padding: 40px 16px;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .eas-page-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .eas-head-main {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .eas-page-head h1 {
        font-size: 23px;
    }

    .eas-page-head-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .eas-stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .eas-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .eas-filter-grid {
        grid-template-columns: 1fr;
    }

    .eas-filter-actions {
        flex-direction: column;
    }

    .eas-filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
@endpush