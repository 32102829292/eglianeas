{{--
    The Pick-Up → Record → Return → Payment schedule for one bookkeeping plan.

    Dates are suggestions, not a locked chain: the offsets are applied on the
    server when the Pick-Up date changes, and only to stages whose date is still
    an untouched suggestion. Editing any date by hand opts that stage out of
    later reschedules, so the hint under each date says which kind it is.

    Parameters:
      $updateRoute  route name of the target update endpoint for this module
      $bookkeeping  the plan the targets belong to
      $targets      all targets in the plan
      $canManage    whether the signed-in user may edit any of these targets
--}}
@php
    /* Read through a model rather than the trait, because PHP does not allow a
       trait constant to be accessed directly. All three target models share the
       same values via HasBookkeepingTargetWorkflow. */
    $offsets = \App\Models\WeeklyBookkeepingTarget::SEQUENCE_OFFSETS;
    $balanceStatuses = \App\Models\WeeklyBookkeepingTarget::PAYMENT_STATUSES;

    $firstTarget = $targets->first();
    $labels = $firstTarget ? $firstTarget::TASK_TYPES : [];
    $window = $firstTarget ? $firstTarget->planWindow() : [null, null];
    [$windowStart, $windowEnd] = $window;

    $clients = $targets->groupBy('client_id');
    $hasErrors = $errors->any();
    $user = auth()->user();
@endphp

<div class="card schedule-card">
    <div class="card-head">
        <h2 class="card-title">Target Schedule</h2>
        <span class="card-sub">
            Pick-Up sets the sequence · a hand-edited date is never overwritten
        </span>
    </div>

    @if ($hasErrors)
        <div class="form-error schedule-error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @forelse ($clients as $clientTargets)
        @php
            $lead = $clientTargets->first();
            $stages = $clientTargets->keyBy('task_type')
                ->sortBy(fn ($target, $taskType) => $offsets[$taskType] ?? 99);
            $balance = $stages->get('payment')?->balanceSummary();
            $remarkCount = $stages->filter(fn ($target) => filled($target->notes))->count();
        @endphp

        <details class="schedule-client">
            <summary class="schedule-summary">
                <span class="schedule-client-name">{{ $lead->displayClientName() }}</span>

                <span class="schedule-chain">
                    @foreach ($stages as $taskType => $stage)
                        <span class="schedule-chain-step task-{{ $taskType }}">
                            {{ $labels[$taskType] ?? $taskType }}
                            <strong>{{ $stage->target_date?->format('M j') ?? '—' }}</strong>
                        </span>
                    @endforeach
                </span>

                <span class="schedule-summary-meta">
                    @if ($balance)
                        <span class="badge badge-warn schedule-chip">{{ $balance }}</span>
                    @endif
                    @if ($remarkCount > 0)
                        <span class="badge badge-neutral schedule-chip">
                            {{ $remarkCount }} {{ \Illuminate\Support\Str::plural('remark', $remarkCount) }}
                        </span>
                    @endif
                </span>
            </summary>

            <div class="schedule-stages">
                @foreach ($stages as $taskType => $stage)
                    @php
                        $isOwnTask = $stage->isAssignedTo($user);
                        $isOversight = $user->isAdmin() || $user->isSupervisor();

                        // Same rule as partials/remarks-cell.blade.php: a finished
                        // task is read-only, an In Progress task stays editable for
                        // the staff member doing it, and before the work starts
                        // whoever may edit the task at all may write a remark.
                        $remarksEditable = ! $stage->isCompleted()
                            && ($isOversight
                                || ($isOwnTask && ($stage->isPending() || $stage->isInProgress())));

                        // Target date only editable when pending (or oversight)
                        $dateEditable = $stage->isPending() || $isOversight;
                        // Form visible if any field is editable
                        $formVisible = $canManage && ($remarksEditable || $dateEditable);
                        $offset = $offsets[$taskType] ?? null;
                        $isPayment = $taskType === 'payment';
                    @endphp

                    <div class="schedule-stage">
                        {{-- Stage Header: Task Type, Status, Assignment Indicator --}}
                        <div class="schedule-stage-head">
                            <span class="task-type-dot task-{{ $taskType }}"></span>
                            <span class="schedule-stage-name">{{ $labels[$taskType] ?? $taskType }}</span>
                            @include('admin.bookkeeping.partials.task-status-badge', ['target' => $stage])
                            @if ($isOwnTask)
                                <span class="schedule-own-badge" title="This task is assigned to you">
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" aria-hidden="true"><path d="M10 2a6 6 0 016 6c0 4.418-6 10-6 10S4 12.418 4 8a6 6 0 016-6z"/><path d="M7 9l3 3 6-6"/></svg>
                                    Your Task
                                </span>
                            @endif
                            @if ($offset !== null)
                                <span class="schedule-stage-offset">
                                    +{{ $offset }} {{ \Illuminate\Support\Str::plural('day', $offset) }} from Pick-Up
                                </span>
                            @endif
                        </div>

                        @if ($formVisible)
                            <form method="POST" action="{{ route($updateRoute, [$bookkeeping, $stage]) }}" class="schedule-form">
                                @csrf
                                @method('PATCH')

                                <div class="schedule-field">
                                    <label class="schedule-label" for="date-{{ $stage->id }}">Target Date</label>
                                    <input
                                        id="date-{{ $stage->id }}"
                                        type="date"
                                        name="target_date"
                                        class="schedule-input"
                                        value="{{ $stage->target_date?->format('Y-m-d') }}"
                                        @if ($windowStart) min="{{ $windowStart->format('Y-m-d') }}" @endif
                                        @if ($windowEnd) max="{{ $windowEnd->format('Y-m-d') }}" @endif
                                        @if (! $dateEditable) disabled @endif
                                    >

                                    @if ($stage->dateIsAutomatic())
                                        <small class="schedule-hint schedule-hint-auto">
                                            Following the Pick-Up sequence. Change it to take this stage out of the sequence.
                                        </small>
                                    @else
                                        <small class="schedule-hint">Set by hand, so Pick-Up changes will not move it.</small>
                                    @endif
                                    @if (! $dateEditable)
                                        <small class="schedule-hint">Target date can only be changed while the task is pending.</small>
                                    @endif
                                </div>

                                {{-- REMARKS SECTION - Highlighted for assigned staff --}}
                                <div class="schedule-field schedule-remarks-field">
                                    <label class="schedule-label" for="notes-{{ $stage->id }}">
                                        Remarks <span class="text-muted">(Optional)</span>
                                        @if ($remarksEditable)
                                            <span class="schedule-required-indicator" title="You can add notes to your own task">●</span>
                                        @endif
                                    </label>
                                    <textarea
                                        id="notes-{{ $stage->id }}"
                                        name="notes"
                                        class="schedule-input schedule-textarea"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="{{ $remarksEditable ? 'Add notes for your task (e.g., why it was delayed, client feedback, etc.)' : 'Optional note for this task' }}"
                                        @if (! $remarksEditable) disabled @endif
                                    >{{ $stage->notes }}</textarea>
                                    @if ($remarksEditable)
                                        <small class="schedule-hint schedule-hint-own">Your notes are saved with this task and visible to supervisors.</small>
                                    @else
                                        <small class="schedule-hint">Optional note for this task.</small>
                                    @endif
                                </div>

                                @if ($isPayment)
                                    <div class="schedule-field schedule-payment-fields">
                                        <div class="schedule-field-divider">
                                            <span>Payment & Balance</span>
                                        </div>

                                        <label class="schedule-label" for="paystatus-{{ $stage->id }}">Payment Status</label>
                                        <select id="paystatus-{{ $stage->id }}" name="payment_status" class="schedule-input">
                                            <option value="">Not recorded</option>
                                            @foreach ($balanceStatuses as $statusKey => $statusLabel)
                                                <option value="{{ $statusKey }}" @selected($stage->payment_status === $statusKey)>
                                                    {{ $statusLabel }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <label class="schedule-label" for="balance-{{ $stage->id }}">Balance Amount</label>
                                        <input
                                            id="balance-{{ $stage->id }}"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="balance_amount"
                                            class="schedule-input"
                                            value="{{ $stage->balance_amount }}"
                                            placeholder="0.00"
                                        >
                                        <small class="schedule-hint">Required while the status is With Balance. Leave empty otherwise.</small>

                                        <label class="schedule-label" for="balnote-{{ $stage->id }}">Balance Note</label>
                                        <input
                                            id="balnote-{{ $stage->id }}"
                                            type="text"
                                            name="balance_note"
                                            class="schedule-input"
                                            maxlength="500"
                                            value="{{ $stage->balance_note }}"
                                            placeholder="What is still outstanding"
                                        >
                                    </div>
                                @endif

                                <button type="submit" class="btn btn-primary btn-sm schedule-save">Save Changes</button>
                            </form>
                        @else
                            {{-- Read-only view: Clean info grid --}}
                            <div class="schedule-readonly">
                                <div class="schedule-info-grid">
                                    <div class="schedule-info-row">
                                        <span class="schedule-info-label">Target Date</span>
                                        <span class="schedule-info-value">{{ $stage->target_date?->format('D, M j, Y') ?? '—' }}</span>
                                    </div>
                                    <div class="schedule-info-row">
                                        <span class="schedule-info-label">Status</span>
                                        <span class="schedule-info-value">@include('admin.bookkeeping.partials.task-status-badge', ['target' => $stage])</span>
                                    </div>
                                    @if ($stage->started_at)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Started</span>
                                            <span class="schedule-info-value">{{ $stage->started_at->format('M j, Y g:i A') }}</span>
                                        </div>
                                    @endif
                                    @if ($stage->ended_at)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Completed</span>
                                            <span class="schedule-info-value">{{ $stage->ended_at->format('M j, Y g:i A') }}</span>
                                        </div>
                                    @endif
                                    @if ($stage->durationHuman())
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Duration</span>
                                            <span class="schedule-info-value">{{ $stage->durationHuman() }}</span>
                                        </div>
                                    @endif
                                    @if ($stage->performed_by_id || $stage->performed_by_name)
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Performed By</span>
                                            <span class="schedule-info-value">
                                                {{ $stage->performedByDisplayName() }}
                                                <span class="badge badge-sm performer-badge role-{{ $stage->performed_by_role }}">{{ $stage->performedByRoleLabel() }}</span>
                                            </span>
                                        </div>
                                    @endif
                                    @if ($stage->assignedStaffDisplayName() !== '')
                                        <div class="schedule-info-row">
                                            <span class="schedule-info-label">Assigned To</span>
                                            <span class="schedule-info-value">{{ $stage->assignedStaffDisplayName() }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Remarks (Read-only) --}}
                                <div class="schedule-info-row schedule-remarks-readonly">
                                    <span class="schedule-info-label">Remarks</span>
                                    <span class="schedule-info-value schedule-remarks-value">{{ $stage->notes ?: '—' }}</span>
                                </div>

                                @if ($isPayment)
                                    <div class="schedule-payment-readonly">
                                        <div class="schedule-field-divider">
                                            <span>Payment & Balance</span>
                                        </div>
                                        <div class="schedule-info-grid">
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Payment Status</span>
                                                <span class="schedule-info-value">{{ $stage->paymentStatusLabel() ?? '—' }}</span>
                                            </div>
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Balance Amount</span>
                                                <span class="schedule-info-value">{{ $stage->balanceLabel() ?? '—' }}</span>
                                            </div>
                                            <div class="schedule-info-row">
                                                <span class="schedule-info-label">Balance Note</span>
                                                <span class="schedule-info-value">{{ $stage->balance_note ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </details>
    @empty
        <p class="muted schedule-empty">No targets in this plan yet.</p>
    @endforelse
</div>
