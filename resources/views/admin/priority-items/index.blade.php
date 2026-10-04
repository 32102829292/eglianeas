@extends('layouts.dashboard')

@section('title', 'Priority List / To-Do List — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Priority List / To-Do List</h1>
            <p>Manage priority tasks, to-dos, and lessons learned.</p>
        </div>
    </div>

    {{-- Attention summary (always based on the records you may see) --}}
    <div class="stat-grid priority-summary">
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <span class="stat-label">Action Required</span>
            <b class="stat-value">{{ $summary['action_required'] }}</b>
            <div class="stat-meta">Overdue, urgent &amp; due today</div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Overdue</span>
            <b class="stat-value">{{ $summary['overdue'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="stat-label">Due Today</span>
            <b class="stat-value">{{ $summary['due_today'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-label">Due Tomorrow</span>
            <b class="stat-value">{{ $summary['due_tomorrow'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Completed</span>
            <b class="stat-value">{{ $summary['completed'] }}</b>
        </div>
    </div>

    {{-- Add item form (admin only) --}}
    @if (auth()->user()->isAdmin())
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Create Priority Item</h2>
        </div>
        <form method="POST" action="{{ route('admin.priority-items.store') }}" data-priority-due-form>
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="task_lesson">Task / Lesson <span class="text-danger">*</span></label>
                    <input class="form-control" id="task_lesson" name="task_lesson" type="text" maxlength="500" required placeholder="Brief title of the task or lesson..." value="{{ old('task_lesson') }}">
                    @error('task_lesson')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="">Select type...</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
                    <select class="form-control" id="priority" name="priority" required data-priority-select>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                    <select class="form-control" id="assigned_staff_id" name="assigned_staff_id">
                        <option value="">— Unassigned (visible to all staff & supervisors) —</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected(old('assigned_staff_id') == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_staff_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="due_date">Due Date</label>
                    <input class="form-control" id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" data-due-date-input>
                    <div class="form-hint">Leave blank to use the priority default (Urgent: today, High: tomorrow, Medium: 3 days, Low: 1 week).</div>
                    @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="5000" placeholder="Detailed description...">{{ old('description') }}</textarea>
                @error('description')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000" placeholder="Additional notes...">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Create Item</button>
        </form>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Filter Items</h2>
        </div>
        <form method="GET" action="{{ route('admin.priority-items.index') }}" class="filter-panel">
            <div class="form-grid three align-items-end gap-3">
                <div class="form-group">
                    <label class="form-label" for="filter_q">Search</label>
                    <input class="form-control" id="filter_q" name="q" type="text" value="{{ $q }}" placeholder="Search task, description, notes...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_type">Type</label>
                    <select class="form-control" id="filter_type" name="type">
                        <option value="">All</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected($activeType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_priority">Priority</label>
                    <select class="form-control" id="filter_priority" name="priority">
                        <option value="">All</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($activePriority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_urgency">Urgency</label>
                    <select class="form-control" id="filter_urgency" name="urgency">
                        <option value="">All</option>
                        @foreach ($urgencies as $value => $label)
                            <option value="{{ $value }}" @selected($activeUrgency === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_status">Status</label>
                    <select class="form-control" id="filter_status" name="status">
                        <option value="">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_assigned_staff_id">Assigned Staff</label>
                    <select class="form-control" id="filter_assigned_staff_id" name="assigned_staff_id">
                        <option value="">All</option>
                        <option value="unassigned" @selected($activeAssignedStaffId === 'unassigned')>Unassigned</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected($activeAssignedStaffId == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.priority-items.index') }}" class="btn btn-outline">Clear</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Items list --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Priority Items</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Type</th>
                        <th>Task / Lesson</th>
                        <th>Priority</th>
                        <th>Urgency</th>
                        <th>Due Date</th>
                        <th>Assigned Staff</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Evidence</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="{{ $item->isOverdue() ? 'table-danger' : '' }}">
                            <td data-col="Type">
                                <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                            </td>
                            <td data-col="Task / Lesson">
                                <div class="fw-semibold">{{ Str::limit($item->task_lesson, 80) }}</div>
                                @if (strlen($item->task_lesson) > 80)
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="tooltip" title="{{ $item->task_lesson }}">Show more</button>
                                @endif
                            </td>
                            <td data-col="Priority">
                                <span class="badge {{ $item->priorityBadgeClass() }}">{{ $item->priorityLabel() }}</span>
                            </td>
                            <td data-col="Urgency">
                                <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                            </td>
                            <td data-col="Due Date">
                                <div>{{ $item->due_date?->format('M j, Y') ?? '—' }}</div>
                                <span class="badge {{ $item->deadlineBadgeClass() }}">{{ $item->deadlineLabel() }}</span>
                            </td>
                            <td data-col="Assigned Staff">
                                @if ($item->assignedStaff)
                                    {{ $item->assignedStaff->name }}
                                @else
                                    <span class="badge badge-info">Unassigned</span>
                                @endif
                            </td>
                            <td data-col="Status" class="text-center">
                                <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                            </td>
                            <td data-col="Evidence" class="text-center">
                                @php
                                    $evidenceCount = $item->evidences_count > 0 ? $item->evidences_count : ($item->evidence_path ? 1 : 0);
                                @endphp
                                @if ($evidenceCount > 0)
                                    <a href="{{ route('admin.priority-items.show', $item) }}#evidence" class="badge badge-success" title="View implementation evidence">[{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }}]</a>
                                @else
                                    <span class="text-muted">No evidence</span>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end">
                                <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-link btn-sm">View</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('admin.priority-items.edit', $item) }}" class="btn btn-link btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.priority-items.destroy', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this item?', message: 'This item will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-cell">{{ $hasFilters ? 'No priority items match your current filters.' : 'No priority items found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($items as $item)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">
                                    <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                                    {{ Str::limit($item->task_lesson, 60) }}
                                </div>
                                <div class="cv-head-sub">
                                    <span class="badge {{ $item->priorityBadgeClass() }}">{{ $item->priorityLabel() }}</span>
                                    &middot;
                                    <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                                    &middot;
                                    <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                                    &middot;
                                    <span class="badge {{ $item->deadlineBadgeClass() }}">{{ $item->deadlineLabel() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair cv-full"><span class="cv-label">Description</span><span class="cv-value">@if ($item->description){{ $item->description }}@else<span class="muted">—</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Assigned Staff</span><span class="cv-value">@if ($item->assignedStaff){{ $item->assignedStaff->name }}@else<span class="badge badge-info">Unassigned</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Due Date</span><span class="cv-value">{{ $item->due_date?->format('M j, Y') ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Evidence</span><span class="cv-value">@php $evidenceCount = $item->evidences_count > 0 ? $item->evidences_count : ($item->evidence_path ? 1 : 0); @endphp @if ($evidenceCount > 0)<a href="{{ route('admin.priority-items.show', $item) }}#evidence">[{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }}]</a>@else<span class="muted">No evidence</span>@endif</span></div>
                            @if ($item->notes)
                                <div class="cv-pair cv-full"><span class="cv-label">Notes</span><span class="cv-value">{{ $item->notes }}</span></div>
                            @endif
                        </div>
                        <div class="cv-card-actions">
                            <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline btn-sm">View</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.priority-items.edit', $item) }}" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.priority-items.destroy', $item) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this item?', message: 'This item will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">{{ $hasFilters ? 'No priority items match your current filters.' : 'No priority items found.' }}</p>
                @endforelse
            </div>
        </div>
        {{ $items->links('pagination.simple') }}
    </div>

    @include('admin.priority-items._due-date-script')
@endsection