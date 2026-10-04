@extends('layouts.dashboard')

@section('title', 'Kaizen Concern #'. $concern->id .' — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Kaizen Concern #{{ $concern->id }}</h1>
            <p>Challenge: {{ Str::limit($concern->challenge, 80) }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.kaizen-concerns.edit', $concern) }}" class="btn btn-outline">Edit</a>
            @endif
            <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Back to List</a>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Details</h2>
            </div>
            <ul class="detail-list">
                <li><span class="k">Date Identified</span><span class="v">{{ $concern->date_identified?->format('F j, Y') }}</span></li>
                <li><span class="k">Target Date</span><span class="v">{{ $concern->target_date?->format('F j, Y') ?? '—' }}</span></li>
                <li><span class="k">Implementation Date</span><span class="v">{{ $concern->implementation_date?->format('F j, Y') ?? '—' }}</span></li>
                <li>
                    <span class="k">Assigned Staff</span>
                    <span class="v">
                        @if ($concern->assignedStaff)
                            {{ $concern->assignedStaff->name }}
                        @else
                            <span class="badge badge-info">Unassigned (visible to all staff & supervisors)</span>
                        @endif
                    </span>
                </li>
                <li><span class="k">Status</span><span class="v"><span class="badge {{ $concern->statusBadgeClass() }}">{{ $concern->statusLabel() }}</span></span></li>
                <li><span class="k">Created By</span><span class="v">{{ $concern->creator->name ?? '—' }}</span></li>
                <li><span class="k">Created At</span><span class="v">{{ $concern->created_at?->format('F j, Y g:i A') }}</span></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Challenge / Opportunity</h2>
            </div>
            <div class="card-data">
                <div class="p-4" style="white-space: pre-wrap;">{{ $concern->challenge }}</div>
            </div>
        </div>

        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">Recommended Solution</h2>
            </div>
            <div class="card-data">
                @if ($concern->recommended_solution)
                    <div class="p-4" style="white-space: pre-wrap;">{{ $concern->recommended_solution }}</div>
                @else
                    <div class="p-4 muted">— No solution recorded —</div>
                @endif
            </div>
        </div>

        @if ($concern->notes)
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">Notes</h2>
            </div>
            <div class="card-data">
                <div class="p-4" style="white-space: pre-wrap;">{{ $concern->notes }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Checklist --}}
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">Checklist</h2>
            @if ($concern->canManageChecklist(auth()->user()))
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Item
                </button>
            @endif
        </div>

        @php
            $checklistTotal = $concern->checklistItems->count();
            $checklistDone = $concern->checklistItems->where('completed', true)->count();
        @endphp

        @if ($checklistTotal > 0)
            <div class="checklist-progress">
                <div class="checklist-progress-bar">
                    <span style="width: {{ (int) round(($checklistDone / $checklistTotal) * 100) }}%"></span>
                </div>
                <span class="checklist-progress-label">{{ $checklistDone }} of {{ $checklistTotal }} complete</span>
            </div>
        @endif

        @if ($concern->checklistItems->isNotEmpty())
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
                        @foreach ($concern->checklistItems->sortBy('sort_order') as $index => $item)
                            <tr class="{{ $item->completed ? 'table-success' : '' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input" {{ $item->completed ? 'checked' : '' }} data-checklist-item="{{ $item->id }}" data-url="{{ route('admin.kaizen-concerns.checklist.toggle', $concern) }}">
                                        <span class="{{ $item->completed ? 'text-decoration-line-through text-muted' : '' }}">{{ $item->title }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $item->completed ? 'badge-success' : 'badge-warn' }}">
                                        {{ $item->completed ? 'Done' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $item->completer->name ?? '—' }}</td>
                                <td class="text-center">{{ $item->completed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($concern->canManageChecklist(auth()->user()))
                                        <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editChecklistModal"
                                                data-checklist-id="{{ $item->id }}"
                                                data-checklist-title="{{ $item->title }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.destroy', $concern) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this checklist item?', message: 'This item will be permanently removed.', danger: true, confirmLabel: 'Delete' });">
                                            @csrf
                                            <input type="hidden" name="checklist_item_id" value="{{ $item->id }}">
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
            @if ($concern->canManageChecklist(auth()->user()))
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
    @if ($concern->canManageChecklist(auth()->user()))
    <div class="modal fade" id="addChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.add', $concern) }}">
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
    @if ($concern->canManageChecklist(auth()->user()))
    <div class="modal fade" id="editChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.checklist.update', $concern) }}">
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

    {{-- Evidence of Implementation --}}
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">Evidence of Implementation</h2>
            @if ($concern->canUploadEvidence(auth()->user()))
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Evidence
                </button>
            @endif
        </div>

        @if ($concern->evidences->isNotEmpty())
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-muted">
                        <tr>
                            <th>File</th>
                            <th style="width: 140px;">Type</th>
                            <th style="width: 160px;">Uploaded By</th>
                            <th style="width: 180px;">Uploaded At</th>
                            <th class="text-end" style="width: 230px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($concern->evidences as $evidence)
                            <tr>
                                <td>{{ $evidence->original_name }}</td>
                                <td><span class="badge badge-info">{{ $evidence->mime_type ?? 'file' }}</span></td>
                                <td>{{ $evidence->uploader->name ?? '—' }}</td>
                                <td>{{ $evidence->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-end">
                                    <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                                    <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.download', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm">Download</a>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('admin.kaizen-concerns.evidence.delete', [$concern, $evidence]) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Remove this evidence?', message: 'This file will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
                                            @csrf
                                            @method('DELETE')
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
            @if ($concern->canUploadEvidence(auth()->user()))
                <div class="text-center py-4 muted">No implementation evidence yet. Click "Add Evidence" to upload a file.</div>
            @else
                <div class="text-center py-4 muted">No implementation evidence has been uploaded yet.</div>
            @endif
        @endif
    </div>

    {{-- Add Evidence Modal --}}
    @if ($concern->canUploadEvidence(auth()->user()))
    <div class="modal fade" id="addEvidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kaizen-concerns.evidence.add', $concern) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Evidence of Implementation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="evidence_file">File <span class="text-danger">*</span></label>
                            <input class="form-control" id="evidence_file" name="evidence" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted">Accepted: images, PDF, Word, Excel. Maximum 20 MB.</small>
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
    @endif
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Unable to update the checklist item.');
                }

                return response.json();
            })
            .then(data => {
                if (!data.status) {
                    throw new Error(data.message || 'Unable to update the checklist item.');
                }

                // Reload after the persisted update so every status/detail cell stays in sync.
                window.location.reload();
            })
            .catch(err => {
                console.error('Error toggling checklist:', err);
                checkbox.checked = !checkbox.checked; // revert on error
            });
        }
    });

    @if ($concern->canManageChecklist(auth()->user()))
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
    @endif
</script>
@endpush
