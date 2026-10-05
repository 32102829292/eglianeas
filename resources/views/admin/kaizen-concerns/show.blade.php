@extends('layouts.dashboard')

@section('title', \App\Models\KaizenConcern::TYPES[$concern->type] ?? 'Employee Suggestion' .' #'. $concern->id .' — Egliane Accounting Services')

@section('content')
    @php
        /* Admin Concerns and Employee Suggestions share this screen, so the
           label follows the record's own type. A record from before the `type`
           column existed reads as an Employee Suggestion, which is how the board
           has always treated it. */
        $recordTypeLabel = \App\Models\KaizenConcern::TYPES[$concern->type] ?? 'Employee Suggestion';

        $isImplemented = $concern->isImplemented();
        $isNotImplemented = $concern->isNotImplemented();

        $checklistTotal = $concern->checklistItems->count();
        $checklistDone = $concern->checklistItems->where('completed', true)->count();
        $checklistComplete = $checklistTotal > 0 && $checklistDone === $checklistTotal;
        $checklistPercent = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;

        $evidenceCount = $concern->evidences->count();

        // The long date is used in the headline states, where there is room to
        // read it, and the fallback only covers the legacy records that were
        // finalised before an implementation date was recorded.
        $implementedOn = $concern->implementation_date?->format('F j, Y')
            ?? $concern->updated_at?->format('F j, Y');

        // Implementation confirmation is the existing admin-only edit step, so
        // it carries exactly the same guard. Employees and supervisors can
        // still work the checklist but cannot finalise the suggestion.
        $canConfirmImplementation = auth()->user()->isAdmin() && ! $isImplemented;

        // Finishing the checklist only means the work is ready to be confirmed.
        // The parent status is never changed by checklist progress.
        $showReadyPrompt = $checklistComplete && ! $isImplemented;
        $showCompletedSummary = $isImplemented && $checklistComplete;
    @endphp

    {{-- Top header --}}
    <div class="page-head page-head-row">
        <div>
            <h1>
                <span class="badge kaizen-suggestion-tag">{{ $recordTypeLabel }}</span>
                <span class="text-muted" style="font-size:0.55em; vertical-align: middle;">#{{ $concern->id }}</span>
            </h1>
            <p>Submitted by: <strong>{{ $concern->creator->name ?? '—' }}</strong></p>
        </div>
        <div class="kaizen-hero-status">
            <span class="kaizen-hero-status-label">Current Status</span>
            @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
        </div>
    </div>

    {{-- Employee Suggestion → Progress → Implementation → Evidence --}}
    @php
        /* Both the flow and the badge follow the derived status, so a record that
           has an implementation date always reads as Implemented even if the
           stored status column has not caught up yet. */
        $effectiveStatus = $concern->effectiveStatus();
    @endphp
    <div class="kaizen-flow">
        @foreach ([
            [$recordTypeLabel, 'done'],
            ['Progress', $effectiveStatus === \App\Models\KaizenConcern::STATUS_PENDING ? 'current' : 'done'],
            ['Implementation', $isImplemented ? 'done' : 'current'],
            ['Evidence', $evidenceCount > 0 ? 'done' : 'current'],
        ] as [$stageLabel, $stageState])
            <div class="kaizen-flow-step is-{{ $stageState }}">
                <span class="kaizen-flow-marker">
                    @if ($stageState === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="11" height="11" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <span class="kaizen-flow-dot" aria-hidden="true"></span>
                    @endif
                </span>
                <span class="kaizen-flow-label">{{ $stageLabel }}</span>
            </div>
        @endforeach
    </div>

    {{-- Implemented state, shown at the top of the page --}}
    @if ($isImplemented)
        <div class="kaizen-implemented-banner">
            <div class="kaizen-implemented-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="30" height="30">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="kaizen-implemented-body">
                <div class="kaizen-implemented-title">&#10003; IMPLEMENTED</div>
                <div class="kaizen-implemented-date">
                    Implemented on:
                    <strong>{{ $implementedOn }}</strong>
                </div>
                <div class="kaizen-implemented-note">This improvement has been implemented.</div>
            </div>
        </div>
    @elseif ($isNotImplemented)
        <div class="kaizen-implemented-banner kaizen-not-implemented-banner">
            <div class="kaizen-implemented-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="30" height="30">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="8" y1="8" x2="16" y2="16"/>
                </svg>
            </div>
            <div class="kaizen-implemented-body">
                <div class="kaizen-implemented-title">NOT IMPLEMENTED</div>
                <div class="kaizen-implemented-note">This improvement suggestion was not implemented.</div>
            </div>
        </div>
    @endif

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Details</h2>
            </div>
            <ul class="detail-list kaizen-detail-list">
                <li><span class="k">Date Identified</span><span class="v">{{ $concern->date_identified?->format('F j, Y') ?? '—' }}</span></li>
                <li><span class="k">Target Date</span><span class="v">{{ $concern->target_date?->format('F j, Y') ?? '—' }}</span></li>
                <li class="kaizen-detail-impl {{ $isImplemented ? 'is-implemented' : '' }}">
                    <span class="k">{{ $isImplemented ? 'Implemented on' : 'Implementation Date' }}</span>
                    <span class="v">
                        @if ($isImplemented)
                            <strong>{{ $implementedOn }}</strong>
                        @else
                            —
                        @endif
                    </span>
                </li>
                <li>
                    <span class="k">Assigned Staff</span>
                    <span class="v">
                        @if ($concern->assignedStaff)
                            {{ $concern->assignedStaff->name }}
                        @else
                            <span class="badge badge-info">Unassigned (visible to all staff &amp; supervisors)</span>
                        @endif
                    </span>
                </li>
                <li>
                    <span class="k">Status</span>
                    <span class="v">
                        @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
                    </span>
                </li>
                <li><span class="k">Submitted By</span><span class="v">{{ $concern->creator->name ?? '—' }}</span></li>
                <li><span class="k">Created At</span><span class="v">{{ $concern->created_at?->format('F j, Y g:i A') }}</span></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Challenge / Opportunity</h2>
            </div>
            <div class="card-data">
                <div class="kaizen-prose">{{ $concern->challenge }}</div>
            </div>
        </div>

        <div class="card kaizen-solution-card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0018 8 6 6 0 006 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 018.91 14"/></svg>
                    Suggested Solution
                </h2>
            </div>
            <div class="card-data">
                @if ($concern->recommended_solution)
                    <div class="kaizen-prose">{{ $concern->recommended_solution }}</div>
                @else
                    <div class="kaizen-prose muted">— No solution recorded —</div>
                @endif
            </div>
        </div>

        @if ($concern->notes)
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <h2 class="card-title">Notes</h2>
            </div>
            <div class="card-data">
                <div class="kaizen-prose">{{ $concern->notes }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Implementation Progress (the existing checklist) --}}
    <div class="card" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between">
            <h2 class="card-title mb-0">Implementation Progress</h2>
            @if ($concern->canManageChecklist(auth()->user()))
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Item
                </button>
            @endif
        </div>

        @if ($checklistTotal > 0)
            <div class="checklist-progress kaizen-progress">
                <div class="checklist-progress-bar">
                    <span style="width: {{ $checklistPercent }}%"></span>
                </div>
                <span class="checklist-progress-label">{{ $checklistDone }} of {{ $checklistTotal }} complete</span>
            </div>
        @endif

        @if ($checklistComplete)
            <div class="kaizen-progress-ready">
                <span class="kaizen-progress-ready-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span class="kaizen-progress-ready-body">
                    <strong>&#10003; All checklist items completed</strong>
                    <span class="kaizen-progress-ready-note">
                        @if ($isImplemented)
                            This suggestion has been confirmed as implemented.
                        @else
                            Ready for Implementation Confirmation
                        @endif
                    </span>
                </span>
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

    {{-- Completed summary: checklist complete AND officially implemented --}}
    @if ($showCompletedSummary)
        <div class="card kaizen-complete-card">
            <div class="kaizen-complete-head">
                <span class="kaizen-complete-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22" height="22"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <div>
                    <div class="kaizen-complete-title">&#10003; Improvement Implemented</div>
                    <div class="kaizen-complete-note">This employee suggestion has been successfully implemented.</div>
                </div>
            </div>
            <ul class="kaizen-complete-facts">
                <li><span>Implemented on</span><strong>{{ $implementedOn }}</strong></li>
                <li><span>Evidence</span><strong>{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }}</strong></li>
                <li><span>Submitted by</span><strong>{{ $concern->creator->name ?? '—' }}</strong></li>
            </ul>
        </div>
    @endif

    {{-- Implementation Evidence --}}
    <div class="card" id="evidence" style="margin-top: 20px;">
        <div class="card-head d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="card-title mb-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true" style="vertical-align:-3px"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                    Implementation Evidence
                </h2>
                <p class="kaizen-section-hint">Attach proof that this improvement was implemented.</p>
            </div>
            @if ($evidenceCount > 0)
                <span class="kaizen-evidence-count">{{ $evidenceCount }} Evidence {{ Str::plural('File', $evidenceCount) }}</span>
            @endif
            @if ($concern->canUploadEvidence(auth()->user()))
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvidenceModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Evidence
                </button>
            @endif
        </div>

        {{-- The Implemented state is repeated here so it is also visible next to the evidence it produced. --}}
        @if ($isImplemented)
            <div class="kaizen-evidence-implemented">
                <span class="kaizen-evidence-implemented-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span>
                    <strong>&#10003; IMPLEMENTED</strong> &middot; Implemented on:
                    <strong>{{ $implementedOn }}</strong>
                </span>
            </div>
        @endif

        @if ($concern->evidences->isNotEmpty())
            <div class="kaizen-evidence-list">
                @foreach ($concern->evidences as $evidence)
                    @php
                        $icon = str_starts_with((string) $evidence->mime_type, 'image/')
                            ? '&#128444;'
                            : (str_contains((string) $evidence->mime_type, 'pdf') ? '&#128213;' : '&#128206;');
                    @endphp
                    <div class="kaizen-evidence-item">
                        <span class="kaizen-evidence-icon" aria-hidden="true">{!! $icon !!}</span>
                        <div class="kaizen-evidence-meta">
                            <div class="kaizen-evidence-name">{{ $evidence->original_name }}</div>
                            <div class="kaizen-evidence-sub">
                                Added by {{ $evidence->uploader->name ?? '—' }} &middot; {{ $evidence->created_at?->format('M j, Y') }}
                                @if ($evidence->size)
                                    &middot; {{ number_format($evidence->size / 1024, 0) }} KB
                                @endif
                            </div>
                        </div>
                        <div class="kaizen-evidence-actions">
                            <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.view', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                            <a href="{{ URL::temporarySignedRoute('admin.kaizen-concerns.evidence.download', now()->addMinutes(30), ['concern' => $concern->id, 'evidence' => $evidence->id]) }}" class="btn btn-outline btn-sm">Download</a>
                            @if (auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.kaizen-concerns.evidence.delete', [$concern, $evidence]) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Remove this evidence?', message: 'This file will be permanently removed.', danger: true, confirmLabel: 'Remove' });">
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
            <div class="kaizen-evidence-empty">
                <p class="mb-1">No implementation evidence attached yet.</p>
                @if ($concern->canUploadEvidence(auth()->user()))
                    <p class="mb-0">Click "Add Evidence" to attach a photo, screenshot, PDF or document.</p>
                @endif
            </div>
        @endif

        {{-- Implementation confirmation --}}
        @if ($isImplemented)
            <div class="kaizen-confirm-done">
                <span class="kaizen-confirm-done-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="13" height="13" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span>Confirmed as implemented on <strong>{{ $implementedOn }}</strong>.</span>
            </div>
        @elseif ($canConfirmImplementation)
            <div class="kaizen-confirm">
                <div class="kaizen-confirm-text">
                    <strong>Confirm implementation</strong>
                    @if ($checklistComplete)
                        <span class="kaizen-confirm-hint">All implementation tasks are complete. Confirm to record the official Implemented status.</span>
                    @else
                        <span class="kaizen-confirm-hint">Records the official Implemented status. Checklist progress is tracked separately and does not change this status.</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.kaizen-concerns.implement', $concern) }}"
                      onsubmit="return egliane.confirm.form(this, { title: 'Mark as Implemented?', message: 'This records the improvement as officially implemented and stamps the implementation date.', confirmLabel: 'Mark as Implemented' });">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="15" height="15" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg>
                        Mark as Implemented
                    </button>
                </form>
            </div>
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
