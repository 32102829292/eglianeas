@extends('layouts.dashboard')

@section('title')
    Weekly Target — {{ $bookkeeping->week_start?->format('M j, Y') }} — Egliane Accounting Services
@endsection

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Weekly Target</h1>
            <p>
                <span class="title-week">{{ $bookkeeping->week_start?->format('M j, Y') }} – {{ $bookkeeping->week_end?->format('M j, Y') }}</span>
                <span class="title-sep">·</span>
                <span class="title-client">
                    {{ $bookkeeping->displayOwnerName() }}
                    @if ($bookkeeping->staff)
                        <span class="badge badge-sm owner-role-badge role-{{ $bookkeeping->staff->role }}">{{ $bookkeeping->ownerRoleLabel() }}</span>
                    @endif
                </span>
                <span class="title-sep">·</span>
                <span class="badge {{ $badgeClasses[$bookkeeping->status] ?? 'badge-neutral' }}">{{ $bookkeeping->statusLabel() }}</span>
            </p>
        </div>
        <div class="page-head-actions">
            @if ($canManage && $bookkeeping->week_end && now()->lte($bookkeeping->week_end))
                <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $bookkeeping->week_start?->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">Edit Weekly Target</a>
            @endif
            <a href="{{ route('admin.weekly-bookkeeping.history', $bookkeeping) }}" class="btn btn-outline btn-sm">View History</a>
            <a href="{{ route('admin.weekly-bookkeeping.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    @include('admin.bookkeeping.partials.plan-strip', ['bookkeeping' => $bookkeeping, 'targets' => $targets])

    <div class="grid-2 bookkeeping-details">
        <div class="card">
            <div class="card-head"><h2 class="card-title">Weekly Target Summary</h2></div>
            <div class="info-grid">
                <div class="info-field">
                    <span class="info-label">Target Owner</span>
                    <div class="info-value">
                        {{ $bookkeeping->displayOwnerName() }}
                        @if ($bookkeeping->staff)
                            <span class="badge badge-sm owner-role-badge role-{{ $bookkeeping->staff->role }}">{{ $bookkeeping->ownerRoleLabel() }}</span>
                        @endif
                    </div>
                </div>
                <div class="info-field">
                    <span class="info-label">Week</span>
                    <div class="info-value">{{ $bookkeeping->week_start?->format('M j, Y') }} – {{ $bookkeeping->week_end?->format('M j, Y') }}</div>
                </div>
                <div class="info-field">
                    <span class="info-label">Target Clients</span>
                    <div class="info-value">{{ $targets->pluck('client_id')->unique()->count() }}</div>
                </div>
                <div class="info-field">
                    <span class="info-label">Target Tasks</span>
                    <div class="info-value">{{ $targets->count() }}</div>
                </div>
                <div class="info-field wide">
                    <span class="info-label">Completion</span>
                    <div class="info-value">{{ $bookkeeping->completionPercent() }}%</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">Actual Progress</h2></div>
            <div class="info-grid">
                <div class="info-field">
                    <span class="info-label">Completed</span>
                    <div class="info-value"><span class="badge badge-success">{{ $targets->filter(fn($t) => $t->isCompleted())->count() }}</span></div>
                </div>
                <div class="info-field">
                    <span class="info-label">In Progress</span>
                    <div class="info-value"><span class="badge badge-info">{{ $targets->filter(fn($t) => $t->isInProgress())->count() }}</span></div>
                </div>
                <div class="info-field">
                    <span class="info-label">Pending</span>
                    <div class="info-value"><span class="badge badge-neutral">{{ $targets->filter(fn($t) => $t->isPending())->count() }}</span></div>
                </div>
                <div class="info-field">
                    <span class="info-label">Missed / Past due</span>
                    <div class="info-value"><span class="badge badge-warn">{{ $targets->filter(fn($t) => ! $t->isCompleted() && $t->isPastDue())->count() }}</span></div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.bookkeeping.partials.target-schedule', [
        'updateRoute' => 'admin.weekly-bookkeeping.update-target',
        'bookkeeping' => $bookkeeping,
        'targets' => $targets,
        'canManage' => $canManage,
    ])

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Target vs Actual</h2>
            <span class="card-sub">{{ $targets->count() }} {{ \Illuminate\Support\Str::plural('task', $targets->count()) }}</span>
        </div>

        @error('action')<div class="form-error" style="margin-bottom:12px;">{{ $message }}</div>@enderror

        @if ($targets->isNotEmpty())
            <div class="table-wrap table-card-view bk-table-wrap">
                <table class="table table-hover align-middle mb-0 target-table bk-target-table">
                    <thead class="thead-muted">
                        <tr>
                            <th>Target Date</th>
                            <th>Task Type</th>
                            <th>Client</th>
                            <th>Assigned Staff</th>
                            <th class="text-center">Target</th>
                            <th class="text-center">Actual</th>
                            <th class="bk-col-remarks">Remarks</th>
                            <th>Performed By</th>
                            <th>Evidence</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($targets as $target)
                            <tr id="target-{{ $target->id }}">
                                <td data-col="Target Date" class="td-date">
                                    {{ $target->target_date?->format('D, M j') ?? '—' }}
                                </td>
                                <td data-col="Task Type" data-bk-task-title>
                                    <span class="task-type-dot task-{{ $target->task_type }}"></span>
                                    {{ $target->taskLabel() }}
                                </td>
                                <td data-col="Client">
                                    <div class="client-cell">
                                        @if ($target->client?->profile_image_path)
                                            <img src="{{ $target->client->photoUrl() }}" alt="{{ $target->client->name }}" class="client-photo-sm">
                                        @else
                                            <span class="avatar">{{ mb_strtoupper(mb_substr($target->client?->name ?? '?', 0, 1)) }}</span>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $target->displayClientName() }}</div>
                                            @if ($target->client?->business_name && $target->client?->name)
                                                <small class="muted">{{ $target->client->name }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-col="Assigned Staff">
                                    @if ($target->assignedStaffDisplayName() !== '')
                                        <div>{{ $target->assignedStaffDisplayName() }}</div>
                                    @else
                                        <span class="muted">Unassigned</span>
                                    @endif
                                </td>
                                <td data-col="Target" class="text-center">
                                    <span class="badge badge-primary">Yes</span>
                                </td>
                                <td data-col="Actual" class="text-center">
                                    @include('admin.bookkeeping.partials.task-status-badge', ['target' => $target])
                                    @if ($target->isCompleted())
                                        @if ($target->timingLabel())
                                            <div><small class="muted">{{ $target->timingLabel() }}</small></div>
                                        @endif
                                        @if ($target->paymentDetail())
                                            <div><small class="muted">{{ $target->paymentDetail() }}</small></div>
                                        @endif
                                        @if ($target->durationHuman())
                                            <div><small class="muted">{{ $target->durationHuman() }}</small></div>
                                        @endif
                                    @endif
                                </td>
                                <td data-col="Remarks" class="bk-col-remarks">
                                    @include('admin.bookkeeping.partials.remarks-cell', ['target' => $target])
                                </td>
                                <td data-col="Performed By">
                                    @include('admin.bookkeeping.partials.performed-by-cell', ['target' => $target])
                                </td>
                                <td data-col="Evidence">
                                    @include('admin.bookkeeping.partials.evidence-cell', [
                                        'target' => $target,
                                        'bookkeeping' => $bookkeeping,
                                        'prefix' => 'admin.weekly-bookkeeping',
                                    ])
                                </td>
                                <td data-col="Actions" class="text-end">
                                    @if ($canManage)
                                        @if ($target->isPending())
                                            <form method="POST" action="{{ route('admin.weekly-bookkeeping.start-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">Start</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.weekly-bookkeeping.destroy-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form" onsubmit="return confirm('Remove this target?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm text-danger">Remove</button>
                                            </form>
                                        @elseif ($target->isInProgress())
                                            <form method="POST" action="{{ route('admin.weekly-bookkeeping.upload-attachment', [$bookkeeping, $target]) }}" enctype="multipart/form-data" class="d-inline tb-actions-form">
                                                @csrf
                                                <input type="file" name="attachment" class="tb-file-input" accept="image/*,application/pdf,.doc,.docx" style="max-width:150px;">
                                                <button type="submit" class="btn btn-outline btn-sm">Upload proof</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.weekly-bookkeeping.complete-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form"
                                                  onsubmit="return confirm('Mark this task completed?');">
                                                @csrf
                                                {{-- Billing records how it was paid, matching the
                                                     workbook's "paid cash" / "paid gcash" cells. --}}
                                                @if ($target->task_type === 'payment')
                                                    <select name="payment_method" class="tb-mini-select" aria-label="Payment method">
                                                        <option value="">Method…</option>
                                                        @foreach ($paymentMethods as $methodKey => $methodLabel)
                                                            <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="date" name="paid_at" class="tb-mini-select" aria-label="Payment date" value="{{ now()->format('Y-m-d') }}">
                                                @endif
                                                <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                            </form>
                                        @endif

                                        @if ($canReassign)
                                            <form method="POST" action="{{ route('admin.weekly-bookkeeping.reassign-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form">
                                                @csrf
                                                <select name="assigned_staff_id" class="tb-mini-select" aria-label="Assign {{ $target->taskLabel() }} to">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($assignableStaff as $member)
                                                        <option value="{{ $member->id }}" @selected($target->assigned_staff_id === $member->id)>
                                                            {{ $member->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-outline btn-sm">Assign</button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="card-view-list">
                    @foreach ($targets as $target)
                        <div class="cv-card">
                            <div class="cv-card-head">
                                <div class="cv-head-main">
                                    <div class="cv-head-title" data-bk-task-title>{{ $target->taskLabel() }} · {{ $target->displayClientName() }}</div>
                                    <div class="cv-head-sub">{{ $target->target_date?->format('D, M j') ?? 'Any day' }}</div>
                                </div>
                                @include('admin.bookkeeping.partials.task-status-badge', ['target' => $target])
                            </div>
                            <div class="cv-card-body">
                                <div class="cv-pair">
                                    <span class="cv-label">Target</span>
                                    <span class="cv-value"><span class="badge badge-primary">Yes</span></span>
                                </div>
                                <div class="cv-pair">
                                    <span class="cv-label">Assigned Staff</span>
                                    <span class="cv-value">
                                        @if ($target->assignedStaffDisplayName() !== '')
                                            {{ $target->assignedStaffDisplayName() }}
                                        @else
                                            <span class="muted">Unassigned</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="cv-pair cv-full">
                                    <span class="cv-label">Remarks</span>
                                    <span class="cv-value">
                                        @include('admin.bookkeeping.partials.remarks-cell', ['target' => $target])
                                    </span>
                                </div>
                                <div class="cv-pair cv-full">
                                    <span class="cv-label">Performed By</span>
                                    <span class="cv-value">
                                        @include('admin.bookkeeping.partials.performed-by-cell', ['target' => $target])
                                    </span>
                                </div>
                                @if ($target->durationHuman())
                                    <div class="cv-pair"><span class="cv-label">Duration</span><span class="cv-value">{{ $target->durationHuman() }}</span></div>
                                @endif
                                <div class="cv-pair cv-full">
                                    <span class="cv-label">Evidence</span>
                                    <span class="cv-value">
                                        @include('admin.bookkeeping.partials.evidence-cell', [
                                            'target' => $target,
                                            'bookkeeping' => $bookkeeping,
                                            'prefix' => 'admin.weekly-bookkeeping',
                                        ])
                                    </span>
                                </div>
                            </div>
                            @if ($canManage)
                                <div class="cv-card-actions">
                                    @if ($target->isPending())
                                        <form method="POST" action="{{ route('admin.weekly-bookkeeping.start-target', [$bookkeeping, $target]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Start</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.weekly-bookkeeping.destroy-target', [$bookkeeping, $target]) }}" class="d-inline" onsubmit="return confirm('Remove this target?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm text-danger">Remove</button>
                                        </form>
                                    @elseif ($target->isInProgress())
                                        <form method="POST" action="{{ route('admin.weekly-bookkeeping.upload-attachment', [$bookkeeping, $target]) }}" enctype="multipart/form-data" class="d-inline">
                                            @csrf
                                            <input type="file" name="attachment" accept="image/*,application/pdf,.doc,.docx" style="max-width:150px;">
                                            <button type="submit" class="btn btn-outline btn-sm">Upload proof</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.weekly-bookkeeping.complete-target', [$bookkeeping, $target]) }}" class="d-inline" onsubmit="return confirm('Mark this task completed?');">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="empty-state compact">
                No targets set for this week yet.
                @if ($canManage)
                    <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $bookkeeping->week_start?->format('Y-m-d')]) }}" class="text-link">Set this week's target</a>.
                @endif
            </div>
        @endif
    </div>

    {{-- Remarks Editor Modal --}}
    @include('admin.bookkeeping.partials.remarks-modal', [
        'updateRoute' => 'admin.weekly-bookkeeping.update-target',
        'bookkeeping' => $bookkeeping,
    ])
@endsection

@push('styles')
<style>
.tb-mini-select {
    max-width: 128px; min-height: 30px; padding: 0 .3rem;
    font-size: var(--text-xs); border: 1px solid var(--border-subtle);
    border-radius: 5px; background: var(--surface);
}
.badge-primary { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); font-weight: 600; }
.text-link { color: var(--primary, #6366f1); text-decoration: underline; }
.client-photo-sm { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.owner-role-badge.role-staff { background: var(--info-bg, #dbeafe); color: var(--info, #3b82f6); }
.owner-role-badge.role-supervisor { background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00); }
.owner-role-badge.role-admin { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); }

.task-type-dot {
    display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px;
}
.task-pickup { background: var(--info, #3b82f6); }
.task-record { background: var(--warn, #f59e0b); }
.task-return { background: var(--success, #10b981); }
.task-payment { background: var(--primary, #6366f1); }

.client-cell { display: flex; align-items: center; gap: 10px; }
.client-cell .client-photo-sm, .client-cell .avatar {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1);
    display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600;
}

        .evidence-links { display: inline-flex; gap: 10px; font-size: 13px; }
        .evidence-links a { color: var(--primary, #6366f1); }
        /* Long filenames truncate instead of stretching the table or card. */
        .wk-file { display: block; max-width: 22ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }


.performer-badge.role-staff { background: var(--info-bg, #dbeafe); color: var(--info, #3b82f6); }
.performer-badge.role-supervisor { background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00); }
.performer-badge.role-admin { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); }

.tb-actions-form { margin-bottom: 4px; }
.tb-file-input { display: block; max-width: 150px; font-size: 11px; margin-bottom: 4px; }

/* ---- Enhanced Target Schedule Stage UI ---- */
.schedule-stage {
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm, 12px);
    padding: 16px;
    margin-bottom: 12px;
    transition: box-shadow var(--transition-fast), border-color var(--transition-fast);
}
.schedule-stage:hover {
    box-shadow: var(--shadow-sm);
    border-color: var(--border);
}
.schedule-stage:last-child { margin-bottom: 0; }

.schedule-stage-head {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-light);
}
.schedule-stage-name {
    font-weight: 600;
    font-size: var(--text-base);
    color: var(--navy);
    flex: 1 1 auto;
    min-width: 150px;
}
.schedule-status-badge {
    font-size: var(--text-xs);
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 600;
    flex: 0 0 auto;
}
.schedule-own-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: var(--text-xs);
    font-weight: 600;
    color: var(--sky-deep);
    background: var(--sky-soft);
    border: 1px solid var(--sky-deep);
    border-radius: 999px;
    padding: 3px 10px;
    flex: 0 0 auto;
}
.schedule-stage-offset {
    width: 100%;
    font-size: var(--text-xs);
    color: var(--muted-text);
    margin-top: 4px;
    padding-top: 8px;
    border-top: 1px solid var(--border-light);
}

/* Editable Form */
.schedule-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.schedule-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.schedule-remarks-field {
    border-top: 2px solid var(--sky-soft);
    padding-top: 18px;
    margin-top: 4px;
}
.schedule-remarks-field .schedule-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--navy);
}
.schedule-remarks-field .text-muted { color: var(--muted-text); }
.schedule-required-indicator {
    color: var(--sky-deep);
    font-size: 10px;
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.schedule-hint-own {
    font-size: var(--text-xs);
    color: var(--sky-deep);
    background: var(--sky-soft);
    padding: 6px 10px;
    border-radius: 6px;
    display: inline-block;
    margin-top: 4px;
}
.schedule-payment-fields {
    border-top: 2px solid var(--warning-soft, #fef3c7);
    padding-top: 18px;
    margin-top: 4px;
}
.schedule-field-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-light);
}
.schedule-field-divider span {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.schedule-save {
    align-self: flex-start;
    margin-top: 6px;
    min-width: 140px;
}

/* Read-only Info Grid */
.schedule-readonly { padding-top: 4px; }
.schedule-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.schedule-info-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    background: var(--surface-sunken);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
}
.schedule-info-label {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted-text);
}
.schedule-info-value {
    font-size: var(--text-sm);
    color: var(--text);
    word-break: break-word;
}
.schedule-info-value .badge { font-size: var(--text-xs); }
.schedule-remarks-readonly {
    grid-column: 1 / -1;
    border-left: 3px solid var(--sky-deep);
    background: var(--sky-soft);
}
.schedule-remarks-readonly .schedule-info-value {
    white-space: pre-wrap;
    font-style: italic;
    color: var(--text);
}
.schedule-remarks-value { min-height: 2.5em; }
.schedule-payment-readonly { margin-top: 8px; }
.schedule-payment-readonly .schedule-info-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Responsive */
@media (max-width: 640px) {
    .schedule-stage { padding: 12px; }
    .schedule-stage-head { flex-direction: column; align-items: flex-start; gap: 8px; }
    .schedule-stage-name { min-width: 0; width: 100%; }
    .schedule-status-badge { width: fit-content; }
    .schedule-own-badge { width: fit-content; }
    .schedule-form { gap: 12px; }
    .schedule-field { gap: 5px; }
    .schedule-info-grid { grid-template-columns: 1fr; }
    .schedule-remarks-readonly { grid-column: auto; }
}

/* ---- Remarks Column (Main Table & Card View) ---- */
.remarks-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}
.remarks-text {
    font-size: var(--text-sm);
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}
.add-remarks-btn {
    white-space: nowrap;
    padding: 4px 10px;
    font-size: var(--text-xs);
    font-weight: 600;
    background: var(--primary);
    color: var(--primary-bg);
    border: none;
    border-radius: 999px;
    transition: background var(--transition-fast);
}
.add-remarks-btn:hover {
    background: var(--primary, #6366f1);
    color: #fff;
}
.edit-remarks-btn {
    color: var(--primary);
    text-decoration: none;
    padding: 2px;
    border-radius: 4px;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.edit-remarks-btn:hover {
    background: var(--primary-soft, #e0e7ff);
    color: var(--primary);
}
@media (max-width: 640px) {
    .remarks-text { max-width: 120px; }
    .add-remarks-btn { padding: 4px 8px; font-size: 10px; }
}
</style>
@include('admin.bookkeeping.partials.styles')
@endpush