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
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>{{ $item->typeLabel() }} #{{ $item->id }}</h1>
            <p>{{ $item->task_lesson }}</p>
            <div class="priority-head-badges">
                <span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span>
                <span class="badge priority-pill {{ $item->priorityBadgeClass() }} priority-{{ $item->priority }}">
                    <span class="priority-dot" aria-hidden="true"></span>{{ $item->priorityLabel() }}
                </span>
                <span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span>
                <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.priority-items.edit', $item) }}" class="btn btn-outline">Edit</a>
            @endif
            <a href="{{ route('admin.priority-items.index') }}" class="btn btn-outline">Back to List</a>
        </div>
    </div>

    {{-- Completed state, shown at the top of the page --}}
    @if ($item->isCompleted())
        <div class="priority-complete-banner">
            <div class="priority-complete-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="26" height="26"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="priority-complete-body">
                <div class="priority-complete-title">&#10003; COMPLETED</div>
                <div class="priority-complete-meta">
                    Completed on:
                    <strong>{{ $item->completionDateFormatted() ?? '—' }}</strong>
                    <span class="priority-complete-sep">&middot;</span>
                    Completed by:
                    <strong>{{ $item->completer?->name ?? '—' }}</strong>
                </div>
            </div>
        </div>
    @endif

    @if ($item->urgencyInstruction() && ! $item->isCompleted())
        <div class="urgency-banner urgency-{{ $item->priority }}">
            <span class="urgency-banner-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    @if ($item->priority === \App\Models\PriorityItem::PRIORITY_URGENT || $item->priority === \App\Models\PriorityItem::PRIORITY_HIGH)
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    @else
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    @endif
                </svg>
            </span>
            <span class="urgency-banner-label">Priority guidance</span>
            <strong class="urgency-banner-text">{{ $item->urgencyInstruction() }}</strong>
        </div>
    @endif

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Details</h2>
            </div>
            <ul class="detail-list priority-detail-list">
                <li>
                    <span class="k">Type</span>
                    <span class="v"><span class="badge {{ $item->typeBadgeClass() }}">{{ $item->typeLabel() }}</span></span>
                </li>
                <li>
                    <span class="k">Priority</span>
                    <span class="v">
                        <span class="badge priority-pill {{ $item->priorityBadgeClass() }} priority-{{ $item->priority }}">
                            <span class="priority-dot" aria-hidden="true"></span>{{ $item->priorityLabel() }}
                        </span>
                    </span>
                </li>
                <li>
                    <span class="k">Urgency</span>
                    <span class="v"><span class="badge {{ $item->urgencyBadgeClass() }}">{{ $item->urgencyLabel() }}</span></span>
                </li>
                <li class="{{ $overdueDays !== null ? 'priority-detail-overdue' : '' }}">
                    <span class="k">Deadline</span>
                    <span class="v">
                        <span class="priority-due-date">{{ $item->due_date?->format('F j, Y') ?? '—' }}</span>
                        <span class="badge {{ $item->deadlineBadgeClass() }}">{{ $item->deadlineLabel() }}</span>
                        @if ($overdueDays !== null)
                            <span class="priority-overdue-note">
                                <span aria-hidden="true">&#128308;</span>
                                {{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} overdue
                            </span>
                        @endif
                    </span>
                </li>
                <li>
                    <span class="k">Assigned Staff</span>
                    <span class="v">
                        @if ($item->assignedStaff)
                            {{ $item->assignedStaff->name }}
                        @else
                            <span class="badge badge-info">Unassigned (visible to all staff &amp; supervisors)</span>
                        @endif
                    </span>
                </li>
                <li class="{{ $item->isCompleted() ? 'priority-detail-completed' : '' }}">
                    <span class="k">Status</span>
                    <span class="v">
                        <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                        @if ($item->isCompleted())
                            <div class="priority-detail-completion">
                                Completed on <strong>{{ $item->completionDateFormatted() ?? '—' }}</strong>
                                @if ($item->completer)
                                    &middot; by <strong>{{ $item->completer->name }}</strong>
                                @endif
                            </div>
                        @endif
                    </span>
                </li>
            </ul>
        </div>

        @if ($item->description)
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Description</h2>
            </div>
            <div class="card-data">
                <div class="rich-text">{{ $item->description }}</div>
            </div>
        </div>
        @endif

        @if ($item->notes)
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">Notes</h2>
            </div>
            <div class="card-data">
                <div class="rich-text">{{ $item->notes }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Checklist --}}
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">Checklist</h2>
            @if ($item->canManageChecklist(auth()->user()))
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Item
                </button>
            @endif
        </div>

        @php
            $checklistTotal = $item->checklistItems->count();
            $checklistDone = $item->checklistItems->where('completed', true)->count();
        @endphp

        @if ($checklistTotal > 0)
            <div class="checklist-progress {{ $checklistComplete ? 'is-complete' : '' }}">
                <div class="checklist-progress-bar">
                    <span style="width: {{ $checklistPercent }}%"></span>
                </div>
                <span class="checklist-progress-label">{{ $checklistDone }} of {{ $checklistTotal }} complete</span>
                <span class="checklist-progress-percent">{{ $checklistPercent }}%</span>
            </div>
            @if ($checklistComplete)
                <div class="priority-checklist-done">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                    All checklist items completed
                    <span class="priority-checklist-done-note">This does not mark the item as completed on its own.</span>
                </div>
            @endif
        @endif

        @if ($item->checklistItems->isNotEmpty())
            <div class="table-wrap">
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
                                    @if ($item->canManageChecklist(auth()->user()))
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
            @if ($item->canManageChecklist(auth()->user()))
                <div class="text-center py-4 muted">No checklist items yet. Click "Add Item" to create one.</div>
            @else
                <div class="text-center py-4 muted">
                    <p class="mb-1">No checklist items have been added yet.</p>
                    <p class="mb-0">Checklist items will appear here once they are assigned.</p>
                </div>
            @endif
        @endif
    </div>

    {{-- Add Checklist Item Modal --}}
    @if ($item->canManageChecklist(auth()->user()))
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
    @if ($item->canManageChecklist(auth()->user()))
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

    {{-- Evidence of Completion --}}
    <div class="card" id="evidence" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0"><span aria-hidden="true">&#128206;</span> Evidence of Completion @if ($evidenceCount > 0)<span class="badge badge-info">{{ $evidenceCount }}</span>@endif</h2>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Evidence
            </button>
        </div>
        <p class="priority-evidence-hint">
            Proof of work that this item was carried out &mdash; photos, documents or signed records.
            Any staff member who can see this item can add evidence.
        </p>

        @if ($evidenceCount > 0)
            <div class="priority-evidence-grid">
                @foreach ($item->evidences as $evidence)
                    <div class="priority-evidence-card">
                        <div class="priority-evidence-icon" aria-hidden="true">
                            @if ($evidence->mime_type && str_starts_with($evidence->mime_type, 'image/'))
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            @elseif ($evidence->mime_type === 'application/pdf')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 19 9"/></svg>
                            @endif
                        </div>
                        <div class="priority-evidence-meta">
                            <div class="priority-evidence-name" title="{{ $evidence->original_name }}">{{ $evidence->original_name }}</div>
                            <div class="priority-evidence-sub">
                                @if ($evidence->mime_type)
                                    <span class="badge badge-info">{{ $evidence->mime_type }}</span>
                                @else
                                    <span class="badge badge-info">file</span>
                                @endif
                                &middot; {{ $evidence->uploader->name ?? '—' }}
                                &middot; {{ $evidence->created_at?->format('M j, Y g:i A') }}
                            </div>
                        </div>
                        <div class="priority-evidence-actions">
                            <a href="{{ URL::temporarySignedRoute('admin.priority-items.evidence.view', now()->addMinutes(30), ['item' => $item->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                            <a href="{{ URL::temporarySignedRoute('admin.priority-items.evidence.download', now()->addMinutes(30), ['item' => $item->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm">Download</a>
                            @if (auth()->user()->isAdmin() || auth()->user()->isSupervisor())
                                <form method="POST" action="{{ route('admin.priority-items.evidence.delete', [$item, $evidence]) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this evidence?', message: 'This file will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
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
            <div class="priority-evidence-empty">
                <span class="priority-evidence-empty-icon" aria-hidden="true">&#128206;</span>
                <div>
                    <div class="priority-evidence-empty-title">No implementation evidence attached yet.</div>
                    <div class="priority-evidence-empty-note">Click "Add Evidence" to upload a file.</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Record metadata --}}
    <div class="card" style="margin-top: 20px;">
        <div class="card-head">
            <h2 class="card-title">Record Information</h2>
        </div>
        <ul class="detail-list">
            <li><span class="k">Created By</span><span class="v">{{ $item->creator->name ?? '—' }}</span></li>
            <li><span class="k">Created At</span><span class="v">{{ $item->created_at?->format('F j, Y g:i A') }}</span></li>
            <li><span class="k">Completed On</span><span class="v">{{ $item->completionDateFormatted() ?? '—' }}</span></li>
            <li><span class="k">Completed By</span><span class="v">{{ $item->completer?->name ?? '—' }}</span></li>
        </ul>
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
@endsection

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