@extends('layouts.dashboard')

@section('title', 'Priority Item #'. $item->id .' — Egliane Accounting Services')

@section('content')
    @php
        $overdueDays = $item->overdueDays();
        $checklistTotal = $item->checklistItems->count();
        $checklistDone = $item->checklistItems->where('completed', true)->count();
        $checklistComplete = $checklistTotal > 0 && $checklistDone === $checklistTotal;
        $checklistPercent = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;
        $evidenceCount = $item->evidences->count();
        $canAssign = $item->canAssignStaff(auth()->user());
        $canManageChecklist = $item->canManageChecklist(auth()->user());
        $canComplete = $item->canComplete(auth()->user());
        $canReopen = $item->canReopen(auth()->user());
    @endphp

    {{-- Task Hero Header --}}
    <header class="pri-task-hero">
        <div class="pri-task-hero-inner">
            <div class="pri-task-hero-left">
                <span class="pri-task-hero-type">{{ $item->typeLabel() }} #{{ $item->id }}</span>
                <h1 class="pri-task-hero-title">{{ $item->task_lesson }}</h1>
                @if ($item->description)
                    <p class="pri-task-hero-desc">{{ $item->description }}</p>
                @endif
                <div class="pri-task-hero-badges">
                    <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                    <span class="badge priority-pill {{ $item->priorityBadgeClass() }} priority-{{ $item->priority }}">
                        <span class="priority-dot" aria-hidden="true"></span>{{ $item->priorityLabel() }}
                    </span>
                    <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                    <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                </div>
            </div>
            <div class="pri-task-hero-actions">
                @if ($canComplete)
                    <form method="POST" action="{{ route('admin.priority-items.complete', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Mark as Done?', message: 'Are you sure you want to mark this task as completed?', confirmLabel: 'Mark as Done' });">
                        @csrf
                        <button type="submit" class="btn btn-success pri-task-hero-btn-complete">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
                            Mark as Done
                        </button>
                    </form>
                @endif
                @if ($canReopen)
                    <form method="POST" action="{{ route('admin.priority-items.reopen', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Reopen this task?', message: 'This will set the task back to Pending and clear its completion record.', confirmLabel: 'Reopen Task' });">
                        @csrf
                        <button type="submit" class="btn btn-outline pri-task-hero-btn-back">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 109-9 9 9 0 00-6.36 2.64L3 8"/><path d="M3 3v5h5"/></svg>
                            Reopen Task
                        </button>
                    </form>
                @endif
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.priority-items.edit', $item) }}" class="btn btn-primary pri-task-hero-btn-edit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Task
                    </a>
                @endif
                <a href="{{ route('admin.priority-items.index') }}" class="btn btn-outline pri-task-hero-btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back to List
                </a>
            </div>
        </div>
    </header>

    {{-- Status Strip (subtle, non-banner) --}}
    @if (! $item->isCompleted() && $overdueDays !== null)
        <div class="pri-status-strip pri-status-strip-overdue priority-row-overdue">
            <span class="pri-status-strip-icon" aria-hidden="true">&#128308;</span>
            <span class="pri-status-strip-text">
                <strong>Overdue</strong> — due {{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} ago
            </span>
        </div>
    @elseif (! $item->isCompleted() && $item->due_date && $item->deadlineBadgeClass() === 'badge-warn')
        <div class="pri-status-strip pri-status-strip-due">
            <span class="pri-status-strip-icon" aria-hidden="true">&#128197;</span>
            <span class="pri-status-strip-text">
                <strong>Due Today</strong> — prioritize this task
            </span>
        </div>
    @elseif (! $item->isCompleted() && $item->due_date && $item->deadlineBadgeClass() === 'badge-info')
        <div class="pri-status-strip pri-status-strip-soon">
            <span class="pri-status-strip-icon" aria-hidden="true">&#128198;</span>
            <span class="pri-status-strip-text">
                <strong>Due {{ $item->due_date?->format('F j, Y') }}</strong> — {{ $item->deadlineLabel() }}
            </span>
        </div>
    @endif

    {{-- Completed State Strip --}}
    @if ($item->isCompleted())
        <div class="pri-status-strip pri-status-strip-complete priority-complete-banner">
            <span class="pri-status-strip-icon" aria-hidden="true">&#10003;</span>
            <span class="pri-status-strip-text">
                <strong>Completed on:</strong> {{ $item->completionDateTimeFormatted() ?? '—' }}
                @if ($item->completer)
                    &middot; <strong>Completed by:</strong> {{ $item->completer->name }}
                @endif
            </span>
        </div>
    @endif

    {{-- Main Content Grid --}}
    <div class="pri-detail-grid">

        {{-- Task Information (full width on top) --}}
        <div class="pri-detail-card pri-detail-card-full">
            <div class="pri-detail-card-head">
                <h2 class="pri-detail-card-title">Task Information</h2>
            </div>
            <div class="pri-detail-card-body">
                <div class="pri-detail-info-row">
                    <div class="pri-detail-info-cell">
                        <span class="pri-detail-info-label">Type</span>
                        <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                    </div>
                    <div class="pri-detail-info-cell">
                        <span class="pri-detail-info-label">Created</span>
                        <span class="pri-detail-info-value">{{ $item->created_at?->format('F j, Y') }}</span>
                    </div>
                    <div class="pri-detail-info-cell">
                        <span class="pri-detail-info-label">Status</span>
                        <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                    </div>
                </div>
                @if ($item->description)
                    <div class="pri-detail-description-block">
                        <span class="pri-detail-info-label">Description</span>
                        <div class="pri-detail-description-text">{{ $item->description }}</div>
                    </div>
                @endif
                @if ($item->notes)
                    <div class="pri-detail-description-block">
                        <span class="pri-detail-info-label">Notes</span>
                        <div class="pri-detail-description-text">{{ $item->notes }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Deadline & Urgency --}}
        <div class="pri-detail-card pri-detail-card-deadline">
            <div class="pri-detail-card-head">
                <h2 class="pri-detail-card-title">Deadline &amp; Urgency</h2>
            </div>
            <div class="pri-detail-card-body">
                <div class="pri-detail-deadline">
                    <span class="pri-detail-deadline-label">Due Date</span>
                    <div class="pri-detail-deadline-value">
                        <span class="pri-detail-deadline-date">{{ $item->due_date?->format('F j, Y') ?? '—' }}</span>
                        @if ($item->due_date)
                            <span class="badge {{ $item->deadlineBadgeClass() }}">{{ $item->deadlineLabel() }}</span>
                        @endif
                    </div>
                    @if ($overdueDays !== null)
                        <div class="pri-detail-deadline-overdue priority-detail-overdue">
                            <span aria-hidden="true">&#128308;</span>
                            <span>{{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} overdue</span>
                        </div>
                    @endif
                </div>
                <div class="pri-detail-urgency">
                    <span class="pri-detail-urgency-label">Urgency</span>
                    @if (! $item->isCompleted())
                        <span class="pri-detail-guidance-label">Priority guidance</span>
                    @endif
                    <div class="pri-detail-urgency-value">
                        <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                        @if ($item->urgencyInstruction() && ! $item->isCompleted())
                            <span class="pri-detail-urgency-hint">{{ $item->urgencyInstruction() }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Assigned Staff --}}
        <div class="pri-detail-card pri-detail-card-staff">
            <div class="pri-detail-card-head">
                <h2 class="pri-detail-card-title">Assigned Staff</h2>
            </div>
            <div class="pri-detail-card-body">
                <div class="pri-detail-staff">
                    @if ($item->assignedStaff)
                        <div class="pri-detail-staff-current">
                            <span class="pri-detail-staff-avatar">{{ strtoupper(mb_substr($item->assignedStaff->name, 0, 1)) }}</span>
                            <div class="pri-detail-staff-info">
                                <span class="pri-detail-staff-name">{{ $item->assignedStaff->name }}</span>
                                <span class="pri-detail-staff-role">Assigned to this task</span>
                            </div>
                        </div>
                    @else
                        <div class="pri-detail-staff-empty">
                            <span class="pri-detail-staff-avatar-empty">&#128100;</span>
                            <div>
                                <span class="pri-detail-staff-empty-text">Unassigned</span>
                                <span class="pri-detail-staff-empty-sub">Visible to all staff &amp; supervisors</span>
                            </div>
                        </div>
                    @endif
                    @if ($canAssign)
                        @include('admin.priority-items.partials.assign-form', ['item' => $item])
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Evidence & Checklist Row --}}
    <div class="pri-detail-grid-two">
        {{-- Evidence --}}
        <div class="pri-detail-card" id="evidence">
            <div class="pri-detail-card-head">
                <div class="pri-detail-card-title-wrapper">
                    <h2 class="pri-detail-card-title mb-0"><span aria-hidden="true">&#128206;</span> Evidence @if ($evidenceCount > 0)<span class="badge badge-info">{{ $evidenceCount }}</span>@endif</h2>
                    @if ($canManageChecklist || $item->isVisibleTo(auth()->user()))
                        <button type="button" class="btn btn-primary btn-sm pri-detail-btn-add-evidence" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Evidence
                        </button>
                    @endif
                </div>
            </div>
            <div class="pri-detail-card-body">
                <p class="pri-detail-evidence-hint priority-evidence-hint">
                    Proof of work that this item was carried out &mdash; photos, documents or signed records.
                    @if ($item->isVisibleTo(auth()->user()))
                        Any staff member who can see this item can add evidence.
                    @else
                        Add evidence to document completion.
                    @endif
                </p>

                @if ($evidenceCount > 0)
                    <div class="pri-detail-evidence-grid">
                        @foreach ($item->evidences as $evidence)
                            <div class="pri-detail-evidence-card priority-evidence-card">
                                <div class="pri-detail-evidence-icon" aria-hidden="true">
                                    @if ($evidence->mime_type && str_starts_with($evidence->mime_type, 'image/'))
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    @elseif ($evidence->mime_type === 'application/pdf')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 19 9"/></svg>
                                    @endif
                                </div>
                                <div class="pri-detail-evidence-meta">
                                    <div class="pri-detail-evidence-name" title="{{ $evidence->original_name }}">{{ $evidence->original_name }}</div>
                                    <div class="pri-detail-evidence-sub">
                                        @if ($evidence->mime_type)
                                            <span class="badge badge-info">{{ $evidence->mime_type }}</span>
                                        @else
                                            <span class="badge badge-info">file</span>
                                        @endif
                                        &middot; {{ $evidence->uploader->name ?? '—' }}
                                        &middot; {{ $evidence->created_at?->format('M j, Y g:i A') }}
                                    </div>
                                </div>
                                <div class="pri-detail-evidence-actions">
                                    <a href="{{ URL::temporarySignedRoute('admin.priority-items.evidence.view', now()->addMinutes(30), ['item' => $item->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                                    <a href="{{ URL::temporarySignedRoute('admin.priority-items.evidence.download', now()->addMinutes(30), ['item' => $item->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm">Download</a>
                                    @if (auth()->user()->isAdmin() || auth()->user()->isSupervisor())
                                        <form method="POST" action="{{ route('admin.priority-items.evidence.delete', [$item, $evidence]) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Remove this evidence?', message: 'This file will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pri-detail-evidence-empty">
                        <span class="pri-detail-evidence-empty-icon" aria-hidden="true">&#128206;</span>
                        <div>
                            <div class="pri-detail-evidence-empty-title">No evidence attached yet.</div>
                            <div class="pri-detail-evidence-empty-note">
                                @if ($item->isVisibleTo(auth()->user()))
                                    Click "Add Evidence" to upload a file.
                                @else
                                    Evidence can be added by staff with access to this task.
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Checklist --}}
        <div class="pri-detail-card">
            <div class="pri-detail-card-head">
                <div class="pri-detail-card-title-wrapper">
                    <h2 class="pri-detail-card-title">Checklist</h2>
                    @if ($canManageChecklist)
                        <button type="button" class="btn btn-primary btn-sm pri-detail-btn-add-item" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Item
                        </button>
                    @endif
                </div>
            </div>
            <div class="pri-detail-card-body">
                @php
                    $checklistTotal = $item->checklistItems->count();
                    $checklistDone = $item->checklistItems->where('completed', true)->count();
                @endphp

                @if ($checklistTotal > 0)
                    <div class="pri-detail-checklist-progress {{ $checklistComplete ? 'is-complete' : '' }}">
                        <div class="pri-detail-checklist-bar">
                            <span style="width: {{ $checklistPercent }}%"></span>
                        </div>
                        <span class="pri-detail-checklist-label">{{ $checklistDone }} of {{ $checklistTotal }} complete</span>
                        <span class="pri-detail-checklist-percent">{{ $checklistPercent }}%</span>
                    </div>
                    @if ($checklistComplete)
                        <div class="pri-detail-checklist-done">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                            All checklist items completed
                            <span class="pri-detail-checklist-done-note">This does not mark the item as completed on its own.</span>
                        </div>
                    @endif
                @endif

                @if ($item->checklistItems->isNotEmpty())
                    <div class="pri-detail-checklist-table-wrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="thead-muted">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Item</th>
                                    <th class="text-center" style="width: 100px;">Status</th>
                                    <th class="text-center" style="width: 140px;">Completed By</th>
                                    <th class="text-center" style="width: 140px;">Completed At</th>
                                    <th class="text-end" style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item->checklistItems->sortBy('sort_order') as $index => $checklistItem)
                                    <tr class="{{ $checklistItem->completed ? 'table-success' : '' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="checkbox" class="form-check-input" {{ $checklistItem->completed ? 'checked' : '' }} data-checklist-item="{{ $checklistItem->id }}" data-url="{{ route('admin.priority-items.checklist.toggle', $item) }}">
                                                <span class="{{ $checklistItem->completed ? 'text-decoration-line-through text-muted' : '' }}">{{ $checklistItem->title }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $checklistItem->completed ? 'badge-success' : 'badge-warn' }}">
                                                {{ $checklistItem->completed ? 'Done' : 'Pending' }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $checklistItem->completer->name ?? '—' }}</td>
                                        <td class="text-center">{{ $checklistItem->completed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                        <td class="text-end">
                                            @if ($canManageChecklist)
                                                <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editChecklistModal"
                                                        data-checklist-id="{{ $checklistItem->id }}"
                                                        data-checklist-title="{{ $checklistItem->title }}">Edit</button>
                                                <form method="POST" action="{{ route('admin.priority-items.checklist.destroy', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this checklist item?', message: 'This item will be permanently removed.', danger: true, confirmLabel: 'Delete' });">
                                                    @csrf
                                                    <input type="hidden" name="checklist_item_id" value="{{ $checklistItem->id }}">
                                                    <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    @if ($canManageChecklist)
                        <div class="pri-detail-empty-state py-4">
                            <span class="pri-detail-empty-icon">&#128204;</span>
                            <div class="pri-detail-empty-text">No checklist items yet.</div>
                            <div class="pri-detail-empty-sub">Click "Add Item" to create one.</div>
                        </div>
                    @else
                        <div class="pri-detail-empty-state py-4">
                            <span class="pri-detail-empty-icon">&#128204;</span>
                            <div class="pri-detail-empty-text">No checklist items have been added yet.</div>
                            <div class="pri-detail-empty-sub">Checklist items will appear here once they are assigned.</div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Completion Action --}}
    <div class="pri-detail-card pri-completion-card {{ $item->isCompleted() ? 'is-completed' : '' }}">
        <div class="pri-detail-card-body pri-completion-body">
            <div class="pri-completion-info">
                <span class="pri-completion-icon" aria-hidden="true">
                    @if ($item->isCompleted())
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 2"/></svg>
                    @endif
                </span>
                <div>
                    <h2 class="pri-completion-title">{{ $item->isCompleted() ? 'Task Completed' : 'Complete This Task' }}</h2>
                    <p class="pri-completion-text">
                        @if ($item->isCompleted())
                            Completed on {{ $item->completionDateTimeFormatted() ?? '—' }}
                            @if ($item->completer)
                                by {{ $item->completer->name }}
                            @endif
                        @elseif ($canComplete)
                            Finished the work? Mark this task as done to record the completion date, time and staff member.
                        @else
                            Only the assigned staff member, an administrator or a supervisor can complete this task.
                        @endif
                    </p>
                </div>
            </div>
            <div class="pri-completion-actions">
                @if ($canComplete)
                    <form method="POST" action="{{ route('admin.priority-items.complete', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Mark as Done?', message: 'Are you sure you want to mark this task as completed?', confirmLabel: 'Mark as Done' });">
                        @csrf
                        <button type="submit" class="btn btn-success pri-completion-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
                            Mark as Done
                        </button>
                    </form>
                @elseif ($canReopen)
                    <form method="POST" action="{{ route('admin.priority-items.reopen', $item) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Reopen this task?', message: 'This will set the task back to Pending and clear its completion record.', confirmLabel: 'Reopen Task' });">
                        @csrf
                        <button type="submit" class="btn btn-outline pri-completion-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 109-9 9 9 0 00-6.36 2.64L3 8"/><path d="M3 3v5h5"/></svg>
                            Reopen Task
                        </button>
                    </form>
                @else
                    <span class="badge {{ $item->statusBadgeClass() }} pri-completion-badge">{{ $item->statusLabel() }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Record Information --}}
    <div class="pri-detail-card pri-detail-card-record">
        <div class="pri-detail-card-head">
            <h2 class="pri-detail-card-title">Record Information</h2>
        </div>
        <div class="pri-detail-card-body">
            <ul class="pri-detail-list">
                <li><span class="pri-detail-k">Status</span><span class="pri-detail-v"><span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span></span></li>
                <li><span class="pri-detail-k">Created By</span><span class="pri-detail-v">{{ $item->creator->name ?? '—' }}</span></li>
                <li><span class="pri-detail-k">Created At</span><span class="pri-detail-v">{{ $item->created_at?->format('F j, Y g:i A') }}</span></li>
                <li><span class="pri-detail-k">Completed on:</span><span class="pri-detail-v">{{ $item->completionDateTimeFormatted() ?? '—' }}</span></li>
                <li><span class="pri-detail-k">Completed by:</span><span class="pri-detail-v">{{ $item->completer?->name ?? '—' }}</span></li>
            </ul>
        </div>
    </div>

    {{-- Add Evidence Modal --}}
    <div class="modal fade" id="addEvidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.priority-items.evidence.add', $item) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Evidence of Completion / Implementation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="evidence_file">File <span class="text-danger">*</span></label>
                            <input class="form-control" id="evidence_file" name="evidence" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                            <small class="text-muted">Accepted: images, PDF, Word, Excel, CSV. Maximum 20 MB.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Checklist Item Modal --}}
    @if ($canManageChecklist)
    <div class="modal fade" id="addChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.priority-items.checklist.add', $item) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Checklist Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="checklist_title">Item Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="checklist_title" name="title" type="text" maxlength="500" required placeholder="Enter checklist item...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Checklist Item Modal --}}
    @if ($canManageChecklist)
    <div class="modal fade" id="editChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.priority-items.checklist.update', $item) }}">
                    @csrf
                    <input type="hidden" name="checklist_item_id" id="edit_checklist_item_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Checklist Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="edit_checklist_title">Item Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="edit_checklist_title" name="title" type="text" maxlength="500" required placeholder="Enter checklist item...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('styles')
<style>
/* ============================================================
   Priority List - Task Details Page (Premium)
   ============================================================ */

/* ---------- Task Hero Header -- Dark Navy --*/
.pri-task-hero {
    background: #171C3B;
    border-radius: var(--radius-card);
    padding: 26px 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(27, 28, 58, 0.12);
    position: relative;
    overflow: hidden;
}

.pri-task-hero-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}

.pri-task-hero-left {
    flex: 1;
    min-width: 0;
}

.pri-task-hero-kicker {
    display: block;
    margin: 0 0 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94A3B8;
}

.pri-task-hero-type {
    display: block;
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 600;
    color: #94A3B8;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.pri-task-hero-title {
    margin: 0 0 10px;
    font-family: var(--font-head);
    font-size: 24px;
    font-weight: 700;
    color: #FFFFFF;
    letter-spacing: -0.02em;
    line-height: 1.3;
    word-break: break-word;
}

.pri-task-hero-desc {
    margin: 0 0 12px;
    font-size: 15px;
    color: #CBD5E1;
    line-height: 1.55;
    word-break: break-word;
}

.pri-task-hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.pri-task-hero-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.pri-task-hero-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    padding: 9px 18px;
    font-size: 14px;
}

.pri-task-hero-actions .btn svg {
    width: 15px;
    height: 15px;
    flex: none;
}

.pri-task-hero-actions .pri-task-hero-btn-edit {
    background: #FFFFFF;
    color: #171C3B;
    border-color: transparent;
}

.pri-task-hero-actions .pri-task-hero-btn-edit:hover {
    background: #F1F5F9;
    color: #171C3B;
}

.pri-task-hero-actions .pri-task-hero-btn-back {
    color: #FFFFFF;
    border-color: rgba(255, 255, 255, 0.5);
    background: transparent;
}

.pri-task-hero-actions .pri-task-hero-btn-back:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
    border-color: #FFFFFF;
}

.pri-task-hero-actions .pri-task-hero-btn-back:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.4);
    outline-offset: 2px;
}

/* ---------- Reusable: outline button on dark backgrounds --*/
/* Apply to any .btn-outline used on dark (navy) headers so text/icon and
   border are visible in all states. */
.btn-outline-on-dark {
    color: #FFFFFF;
    border-color: rgba(255, 255, 255, 0.5);
    background: transparent;
}

.btn-outline-on-dark:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
    border-color: #FFFFFF;
}

.btn-outline-on-dark:focus-visible {
    outline: 3px solid rgba(255, 255, 255, 0.4);
    outline-offset: 2px;
}

/* ---------- Status Strip (subtle) --*/
.pri-status-strip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    border-radius: var(--radius-card);
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
}

.pri-status-strip-icon {
    flex: 0 0 auto;
    font-size: 18px;
}

.pri-status-strip-text {
    flex: 1;
}

.pri-status-strip-overdue {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.pri-status-strip-overdue .pri-status-strip-text strong {
    color: #DC2626;
}

.pri-status-strip-due {
    background: #FFFBEB;
    color: #92400E;
    border: 1px solid #FDE68A;
}

.pri-status-strip-due .pri-status-strip-text strong {
    color: #D97706;
}

.pri-status-strip-soon {
    background: #EFF6FF;
    color: #1E3A8A;
    border: 1px solid #BAE6FD;
}

.pri-status-strip-soon .pri-status-strip-text strong {
    color: #2563EB;
}

.pri-status-strip-complete {
    background: #F0FDF4;
    color: #166534;
    border: 1px solid #A7F3D0;
}

.pri-status-strip-complete .pri-status-strip-text strong {
    color: #16A34A;
}

/* ---------- Main Grid --*/
.pri-detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

@media (max-width: 900px) {
    .pri-detail-grid {
        grid-template-columns: 1fr;
    }
}

/* ---------- Cards --*/
.pri-detail-card {
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pri-detail-card-full {
    grid-column: 1 / -1;
}

.pri-detail-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 22px;
    border-bottom: 1px solid var(--border-subtle);
    background: #F8FAFC;
}

.pri-detail-card-title-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.pri-detail-card-title {
    margin: 0;
    font-family: var(--font-head);
    font-size: 17px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.pri-detail-card-body {
    padding: 22px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* ---------- Task Information --*/
.pri-detail-info-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 18px;
}

.pri-detail-info-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pri-detail-info-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.pri-detail-info-value {
    font-size: 14px;
    color: var(--text);
}

.pri-detail-description-block {
    margin-top: 4px;
}

.pri-detail-description-text {
    font-size: 14.5px;
    color: var(--text);
    line-height: 1.6;
    white-space: pre-wrap;
    padding: 14px 16px;
    border-radius: var(--radius-sm);
    background: #F8FAFC;
    border: 1px solid var(--border-subtle);
    border-left: 3px solid var(--sky);
}

/* ---------- Deadline & Urgency --*/
.pri-detail-deadline {
    margin-bottom: 18px;
}

.pri-detail-deadline-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
    margin-bottom: 6px;
    display: block;
}

.pri-detail-deadline-value {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.pri-detail-deadline-date {
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.02em;
}

.pri-detail-deadline-overdue {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 10px 14px;
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: var(--radius-sm);
    color: #DC2626;
    font-size: 13px;
    font-weight: 600;
}

.pri-detail-urgency {
    margin-top: 4px;
}

.pri-detail-urgency-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
    margin-bottom: 6px;
    display: block;
}

.pri-detail-guidance-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
    margin-bottom: 6px;
    display: block;
}

.pri-detail-urgency-value {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pri-detail-urgency-hint {
    font-size: 13px;
    color: #1E3A8A;
    background: #EFF6FF;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    border: 1px solid #BAE6FD;
}

/* ---------- Assigned Staff --*/
.pri-detail-staff {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.pri-detail-staff-current,
.pri-detail-staff-empty {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 10px;
    background: #F8FAFC;
    border: 1px solid var(--border-subtle);
}

.pri-detail-staff-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--sky);
    color: var(--navy);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}

.pri-detail-staff-info {
    flex: 1;
    min-width: 0;
}

.pri-detail-staff-name {
    display: block;
    font-size: 17px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.pri-detail-staff-role {
    display: block;
    font-size: 13px;
    color: var(--muted-text);
    margin-top: 2px;
}

.pri-detail-staff-empty {
    justify-content: center;
    text-align: center;
    color: var(--muted-text);
}

.pri-detail-staff-avatar-empty {
    font-size: 32px;
}

.pri-detail-staff-empty div {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.pri-detail-staff-empty-text {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}

.pri-detail-staff-empty-sub {
    font-size: 13px;
    color: var(--muted-text);
}

.pri-detail-assignment .inline-assign-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    width: 100%;
}

.pri-detail-assignment .inline-assign-form select.form-control-sm {
    flex: 1;
    min-width: 150px;
    height: 44px;
    padding: 10px 14px;
    font-size: 14px;
    border: 1px solid #D6DDE6;
    border-radius: 10px;
    background: #fff;
}

.pri-detail-assignment .inline-assign-form button.btn-primary.btn-sm {
    height: 44px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ---------- Two-Column Row (Evidence + Checklist) --*/
.pri-detail-grid-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 0;
}

@media (max-width: 900px) {
    .pri-detail-grid-two {
        grid-template-columns: 1fr;
    }
}

/* ---------- Evidence --*/
.pri-detail-evidence-hint {
    font-size: 13.5px;
    color: var(--muted-text);
    margin-bottom: 16px;
    line-height: 1.5;
}

.pri-detail-evidence-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
}

.pri-detail-evidence-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: #F8FAFC;
}

.pri-detail-evidence-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid var(--border-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #64748b;
}

.pri-detail-evidence-meta {
    flex: 1;
    min-width: 0;
}

.pri-detail-evidence-name {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--navy);
    overflow-wrap: break-word;
}

.pri-detail-evidence-sub {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--muted-text);
    margin-top: 4px;
    flex-wrap: wrap;
}

.pri-detail-evidence-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.pri-detail-evidence-empty {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 28px;
    text-align: center;
    color: var(--muted-text);
    background: #F8FAFC;
    border-radius: var(--radius-sm);
    border: 1px dashed var(--border-subtle);
}

.pri-detail-evidence-empty-icon {
    font-size: 36px;
}

.pri-detail-evidence-empty div {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.pri-detail-evidence-empty-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}

.pri-detail-evidence-empty-note {
    font-size: 13px;
    color: var(--muted-text);
}

/* ---------- Checklist --*/
.pri-detail-checklist-progress {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    padding: 14px;
    border-radius: 10px;
    background: #F6F8FB;
    border: 1px solid var(--border-subtle);
}

.pri-detail-checklist-bar {
    flex: 1;
    height: 8px;
    border-radius: 4px;
    background: #E2E8F0;
    overflow: hidden;
}
.pri-detail-checklist-bar span {
    display: block;
    height: 100%;
    background: var(--navy);
    border-radius: 4px;
}
.pri-detail-checklist-progress.is-complete .pri-detail-checklist-bar span {
    background: #16A34A;
}

.pri-detail-checklist-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
}

.pri-detail-checklist-percent {
    font-size: 13px;
    font-weight: 700;
    color: var(--muted-text);
    white-space: nowrap;
}

.pri-detail-checklist-done {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    padding: 14px;
    border-radius: 10px;
    background: #DCFCE7;
    border: 1px solid #A7F3D0;
    color: #166534;
}

.pri-detail-checklist-done-note {
    font-size: 13px;
    color: #166534;
    opacity: 0.9;
}

.pri-detail-checklist-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
}

.pri-detail-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
    padding: 32px 20px;
    color: var(--muted-text);
    background: #F8FAFC;
    border-radius: var(--radius-sm);
    border: 1px dashed var(--border-subtle);
}

.pri-detail-empty-icon {
    font-size: 36px;
}

.pri-detail-empty-text {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}

.pri-detail-empty-sub {
    font-size: 13px;
    color: var(--muted-text);
}

/* ---------- Record Information --*/
.pri-detail-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.pri-detail-list li {
    display: flex;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #F1F5F9;
}

.pri-detail-list li:last-child {
    border-bottom: none;
}

.pri-detail-k {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
    min-width: 140px;
    flex-shrink: 0;
}

.pri-detail-v {
    flex: 1;
    min-width: 0;
    color: var(--text);
}

/* ---------- Completion Action --*/
.pri-task-hero-actions .pri-task-hero-btn-complete {
    background: #16A34A;
    border-color: #16A34A;
    color: #FFFFFF;
}

.pri-task-hero-actions .pri-task-hero-btn-complete:hover {
    background: #15803D;
    border-color: #15803D;
    color: #FFFFFF;
}

.pri-task-hero-actions .pri-task-hero-btn-complete:focus-visible {
    outline: 3px solid rgba(134, 239, 172, 0.65);
    outline-offset: 2px;
}

.pri-completion-card {
    margin-top: 20px;
}

.pri-completion-card.is-completed {
    border-color: #A7F3D0;
    background: #F0FDF4;
}

.pri-completion-body {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.pri-completion-info {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.pri-completion-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #DCFCE7;
    color: #16A34A;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pri-completion-icon svg {
    width: 24px;
    height: 24px;
}

.pri-completion-title {
    margin: 0 0 4px;
    font-family: var(--font-head);
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.pri-completion-text {
    margin: 0;
    font-size: 14px;
    color: var(--muted-text);
    line-height: 1.5;
}

.pri-completion-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.pri-completion-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    padding: 11px 22px;
    font-size: 14px;
}

.pri-completion-btn svg {
    width: 18px;
    height: 18px;
    flex: none;
}

.pri-completion-badge {
    font-size: 13px;
    padding: 8px 16px;
}

.pri-detail-card-record {
    margin-top: 20px;
}

/* ---------- Responsive --*/
@media (max-width: 640px) {
    .pri-task-hero {
        padding: 22px 24px;
    }

    .pri-task-hero-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .pri-task-hero-left {
        gap: 8px;
    }

    .pri-task-hero-title {
        font-size: 21px;
    }

    .pri-task-hero-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .pri-task-hero-actions .btn {
        width: 100%;
        justify-content: center;
    }

    .pri-task-hero-actions form {
        width: 100%;
    }

    .pri-completion-body {
        flex-direction: column;
        align-items: stretch;
    }

    .pri-completion-actions {
        width: 100%;
    }

    .pri-completion-actions form {
        width: 100%;
    }

    .pri-completion-actions .btn {
        width: 100%;
        justify-content: center;
    }

    .pri-detail-assignment .inline-assign-form {
        flex-direction: column;
    }

    .pri-detail-assignment .inline-assign-form select,
    .pri-detail-assignment .inline-assign-form button {
        width: 100%;
    }

    .pri-detail-card-head {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .pri-detail-card-title-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .pri-detail-info-row {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@push('scripts')
<script>
    // Handle checklist item toggle via AJAX
    document.addEventListener('change', function(e) {
        if (e.target.matches('[data-checklist-item]')) {
            const checkbox = e.target;
            const itemId = checkbox.dataset.checklistItem;
            const url = checkbox.dataset.url;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ checklist_item_id: itemId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    window.location.reload();
                }
            })
            .catch(err => {
                console.error('Error toggling checklist:', err);
                checkbox.checked = !checkbox.checked;
            });
        }
    });

    // Populate the edit checklist modal with the row that was clicked.
    document.addEventListener('show.bs.modal', function (event) {
        if (! event.target || event.target.id !== 'editChecklistModal') {
            return;
        }

        const button = event.relatedTarget;
        const idField = document.getElementById('edit_checklist_item_id');
        const titleField = document.getElementById('edit_checklist_title');

        if (button && idField) {
            idField.value = button.dataset.checklistId || '';
        }
        if (button && titleField) {
            titleField.value = button.dataset.checklistTitle || '';
        }
    });
</script>
@endpush