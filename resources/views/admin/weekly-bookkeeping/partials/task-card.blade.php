{{--
    A single weekly task card with expandable details.

    Weekly-only. Preserves all existing forms, permissions, validation,
    activity logging hooks, evidence handling, payment rules, and CSRF
    protection. Never overwrites manual dates.

    Parameters:
      $target         WeeklyBookkeepingTarget
      $bookkeeping    WeeklyBookkeeping
      $canManage      bool
      $canReassign    bool
      $assignableStaff array|Collection
      $paymentMethods array
      $user           User
--}}
@php
    $user = $user ?? auth()->user();
    $isOwnTask = $target->isAssignedTo($user);
    $isOversight = $user->isAdmin() || $user->isSupervisor();
    $dateEditable = $target->isPending() || $isOversight;
    $remarksEditable = ! $target->isCompleted()
        && ($isOversight
            || ($isOwnTask && ($target->isPending() || $target->isInProgress())));
    $formVisible = $canManage && ($remarksEditable || $dateEditable);
    $isPayment = $target->task_type === 'payment';
    $offsets = \App\Models\WeeklyBookkeepingTarget::SEQUENCE_OFFSETS;
    $balanceStatuses = \App\Models\WeeklyBookkeepingTarget::PAYMENT_STATUSES;
    [$windowStart, $windowEnd] = $target->planWindow();
@endphp

<article class="wk-task-card" id="target-{{ $target->id }}">
    <header class="wk-task-card-header">
        <div class="wk-task-card-main">
            <span class="task-type-dot task-{{ $target->task_type }}"></span>
            <h3 class="wk-task-card-title">
                {{ $target->taskLabel() }} · {{ $target->displayClientName() }}
            </h3>
            <div class="wk-task-card-meta">
                <span class="wk-task-card-date">
                    {{ $target->target_date?->format('D, M j') ?? '—' }}
                </span>
                @include('admin.bookkeeping.partials.task-status-badge', ['target' => $target])
                @if ($target->dateIsAutomatic())
                    <span class="wk-timeline-flag is-auto">Auto-scheduled (follows Pick-Up)</span>
                @else
                    <span class="wk-timeline-flag is-manual">Manually set (won’t follow Pick-Up changes)</span>
                @endif
                @if ($isOwnTask)
                    <span class="schedule-own-badge" title="This task is assigned to you">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" aria-hidden="true"><path d="M10 2a6 6 0 016 6c0 4.418-6 10-6 10S4 12.418 4 8a6 6 0 016-6z"/><path d="M7 9l3 3 6-6"/></svg>
                        Your Task
                    </span>
                @endif
                @if (isset($offsets[$target->task_type]))
                    <span class="schedule-stage-offset">
                        +{{ $offsets[$target->task_type] }} {{ \Illuminate\Support\Str::plural('day', $offsets[$target->task_type]) }} from Pick-Up
                    </span>
                @endif
                @if ($balance = $target->balanceSummary())
                    <span class="wk-task-card-balance" title="Payment balance recorded for this task">{{ $balance }}</span>
                @endif
            </div>
        </div>
        <div class="wk-task-card-actions-toggle">
            <details class="wk-task-details">
                <summary class="wk-task-summary-toggle">
                    <span class="wk-task-toggle-text">Expand</span>
                </summary>
                <div class="wk-task-card-body">
                    <div class="wk-task-card-grid">
                        <div class="wk-task-card-section">
                            <h4 class="wk-task-section-title">Target Date</h4>
                            @if ($formVisible && $dateEditable)
                                <form method="POST" action="{{ route('admin.weekly-bookkeeping.update-target', [$bookkeeping, $target]) }}" class="schedule-form">
                                    @csrf
                                    @method('PATCH')
                                    <div class="schedule-field">
                                        <label class="schedule-label" for="date-{{ $target->id }}">Target Date</label>
                                        <input
                                            id="date-{{ $target->id }}"
                                            type="date"
                                            name="target_date"
                                            class="schedule-input"
                                            value="{{ $target->target_date?->format('Y-m-d') }}"
                                            @if ($windowStart) min="{{ $windowStart->format('Y-m-d') }}" @endif
                                            @if ($windowEnd) max="{{ $windowEnd->format('Y-m-d') }}" @endif
                                        >
                                        @if ($target->dateIsAutomatic())
                                            <small class="schedule-hint schedule-hint-auto">
                                                Auto-scheduled (follows Pick-Up). Change it to take this task out of the sequence.
                                            </small>
                                        @else
                                            <small class="schedule-hint">Manually set (won’t follow Pick-Up changes).</small>
                                        @endif
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm schedule-save">Save Changes</button>
                                </form>
                            @else
                                <div class="schedule-readonly">
                                    <div class="schedule-info-row">
                                        <span class="schedule-info-label">Target Date</span>
                                        <span class="schedule-info-value">{{ $target->target_date?->format('D, M j, Y') ?? '—' }}</span>
                                    </div>
                                    @if ($target->dateIsAutomatic())
                                        <small class="schedule-hint schedule-hint-auto">Auto-scheduled (follows Pick-Up)</small>
                                    @else
                                        <small class="schedule-hint">Manually set (won’t follow Pick-Up changes)</small>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="wk-task-card-section">
                            <h4 class="wk-task-section-title">Remarks</h4>
                            @if ($formVisible && $remarksEditable)
                                <form method="POST" action="{{ route('admin.weekly-bookkeeping.update-target', [$bookkeeping, $target]) }}" class="schedule-form">
                                    @csrf
                                    @method('PATCH')
                                    <div class="schedule-field">
                                        <label class="schedule-label" for="notes-{{ $target->id }}">
                                            Remarks <span class="text-muted">(Optional)</span>
                                            <span class="schedule-required-indicator" title="You can add notes to your own task">●</span>
                                        </label>
                                        <textarea
                                            id="notes-{{ $target->id }}"
                                            name="notes"
                                            class="schedule-input schedule-textarea"
                                            rows="3"
                                            maxlength="1000"
                                            placeholder="Add notes for this task (e.g., why it was delayed, client feedback, etc.)"
                                        >{{ $target->notes }}</textarea>
                                        <small class="schedule-hint schedule-hint-own">Your notes are saved with this task and visible to supervisors.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm schedule-save">Save Changes</button>
                                </form>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm add-remarks-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#bkRemarksModal"
                                    data-bk-remarks-for="{{ $target->id }}"
                                    data-bk-remarks-value="{{ $target->notes }}"
                                    data-bk-task-title="{{ $target->taskLabel() }} · {{ $target->displayClientName() }}"
                                    style="margin-top:8px;"
                                >
                                    Quick edit remarks
                                </button>
                            @else
                                <div class="schedule-readonly">
                                    <div class="schedule-info-row schedule-remarks-readonly">
                                        <span class="schedule-info-label">Remarks</span>
                                        <span class="schedule-info-value schedule-remarks-value">{{ $target->notes ?: '—' }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($isPayment)
                            <div class="wk-task-card-section">
                                <h4 class="wk-task-section-title">Payment & Balance</h4>
                                @if ($formVisible)
                                    <form method="POST" action="{{ route('admin.weekly-bookkeeping.update-target', [$bookkeeping, $target]) }}" class="schedule-form">
                                        @csrf
                                        @method('PATCH')
                                        <div class="schedule-field schedule-payment-fields">
                                            <label class="schedule-label" for="paystatus-{{ $target->id }}">Payment Status</label>
                                            <select id="paystatus-{{ $target->id }}" name="payment_status" class="schedule-input">
                                                <option value="">Not recorded</option>
                                                @foreach ($balanceStatuses as $statusKey => $statusLabel)
                                                    <option value="{{ $statusKey }}" @selected($target->payment_status === $statusKey)>
                                                        {{ $statusLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <label class="schedule-label" for="balance-{{ $target->id }}">Balance Amount</label>
                                            <input
                                                id="balance-{{ $target->id }}"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="balance_amount"
                                                class="schedule-input"
                                                value="{{ $target->balance_amount }}"
                                                placeholder="0.00"
                                            >
                                            <small class="schedule-hint">Required while the status is With Balance. Leave empty otherwise.</small>
                                            <label class="schedule-label" for="balnote-{{ $target->id }}">Balance Note</label>
                                            <input
                                                id="balnote-{{ $target->id }}"
                                                type="text"
                                                name="balance_note"
                                                class="schedule-input"
                                                maxlength="500"
                                                value="{{ $target->balance_note }}"
                                                placeholder="What is still outstanding"
                                            >
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm schedule-save">Save Changes</button>
                                    </form>
                                @else
                                    <div class="schedule-readonly">
                                        <div class="schedule-info-grid">
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Payment Status</span>
                                                <span class="schedule-info-value">{{ $target->paymentStatusLabel() ?? '—' }}</span>
                                            </div>
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Balance Amount</span>
                                                <span class="schedule-info-value">{{ $target->balanceLabel() ?? '—' }}</span>
                                            </div>
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Balance Note</span>
                                                <span class="schedule-info-value">{{ $target->balance_note ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="wk-task-card-section">
                            <h4 class="wk-task-section-title">Actual Progress</h4>
                            <div class="schedule-readonly">
                                <div class="schedule-info-grid">
                                    <div class="schedule-info-row">
                                        <span class="schedule-info-label">Status</span>
                                        <span class="schedule-info-value">@include('admin.bookkeeping.partials.task-status-badge', ['target' => $target])</span>
                                    </div>
                                    @if ($target->started_at)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Started</span>
                                            <span class="schedule-info-value">{{ $target->started_at->format('M j, Y g:i A') }}</span>
                                        </div>
                                    @endif
                                    @if ($target->ended_at)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Completed</span>
                                            <span class="schedule-info-value">{{ $target->ended_at->format('M j, Y g:i A') }}</span>
                                        </div>
                                    @endif
                                    @if ($target->durationHuman())
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Duration</span>
                                            <span class="schedule-info-value">{{ $target->durationHuman() }}</span>
                                        </div>
                                    @endif
                                    @if ($target->performed_by_id || $target->performed_by_name)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Performed By</span>
                                            <span class="schedule-info-value">
                                                {{ $target->performedByDisplayName() }}
                                                <span class="badge badge-sm performer-badge role-{{ $target->performed_by_role }}">{{ $target->performedByRoleLabel() }}</span>
                                            </span>
                                        </div>
                                    @endif
                                    @if ($target->assignedStaffDisplayName() !== '')
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Assigned Staff</span>
                                            <span class="schedule-info-value">{{ $target->assignedStaffDisplayName() }}</span>
                                        </div>
                                    @endif
                                    @if ($target->isCompleted())
                                        @if ($target->timingLabel())
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Timing</span>
                                                <span class="schedule-info-value">{{ $target->timingLabel() }}</span>
                                            </div>
                                        @endif
                                        @if ($target->paymentDetail())
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Payment Detail</span>
                                                <span class="schedule-info-value">{{ $target->paymentDetail() }}</span>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="wk-task-card-section">
                            <h4 class="wk-task-section-title">Evidence</h4>
                            @include('admin.bookkeeping.partials.evidence-cell', [
                                'target' => $target,
                                'bookkeeping' => $bookkeeping,
                                'prefix' => 'admin.weekly-bookkeeping',
                            ])
                            @if ($canManage && $target->isInProgress())
                                <form method="POST" action="{{ route('admin.weekly-bookkeeping.upload-attachment', [$bookkeeping, $target]) }}" enctype="multipart/form-data" class="tb-actions-form" style="margin-top:8px;">
                                    @csrf
                                    <input type="file" name="attachment" class="tb-file-input" accept="image/*,application/pdf,.doc,.docx"
                                           aria-label="Evidence file for {{ $target->taskLabel() }} — {{ $target->displayClientName() }}">
                                    <button type="submit" class="btn btn-outline btn-sm">Upload proof</button>
                                </form>
                            @endif
                        </div>

                        @if ($canManage)
                            <div class="wk-task-card-section">
                                <h4 class="wk-task-section-title">Actions</h4>
                                @if ($target->isPending())
                                    <form method="POST" action="{{ route('admin.weekly-bookkeeping.start-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Start</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.weekly-bookkeeping.destroy-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form" onsubmit="return egliane.confirm.form(this, { title: 'Remove this target?', message: 'This task will be removed from the weekly target list. Nothing has been recorded on it yet.', danger: true, confirmLabel: 'Remove' });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline btn-sm text-danger">Remove</button>
                                    </form>
                                @elseif ($target->isInProgress())
                                    <form method="POST" action="{{ route('admin.weekly-bookkeeping.complete-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form"
                                          onsubmit="return egliane.confirm.form(this, { title: 'Mark this task completed?', message: 'This records the {{ $target->taskLabel() }} work for {{ $target->displayClientName() }} as finished.', confirmLabel: 'Complete' });">
                                        @csrf
                                        @if ($isPayment)
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
                            </div>
                        @endif
                    </div>
                </div>
            </details>
        </div>
    </header>
</article>
