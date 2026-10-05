@extends('layouts.dashboard')

@section('title')
    {{ $config['noun_title'] }} — {{ $period->label() }} — Egliane Accounting Services
@endsection

@section('content')
    @php
        $prefix = $config['route_prefix'];
        $unit = strtolower($config['unit']);
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>{{ $config['noun_title'] }}</h1>
            <p>
                <span class="title-period">{{ $period->rangeLabel() }}</span>
                <span class="title-sep">&middot;</span>
                <span class="title-owner">
                    {{ $bookkeeping->displayOwnerName() }}
                    @if ($bookkeeping->staff)
                        <span class="badge badge-sm owner-role-badge role-{{ $bookkeeping->staff->role }}">{{ $bookkeeping->ownerRoleLabel() }}</span>
                    @endif
                </span>
                <span class="title-sep">&middot;</span>
                <span class="badge {{ $badgeClasses[$bookkeeping->status] ?? 'badge-neutral' }}">{{ $bookkeeping->statusLabel() }}</span>
            </p>
        </div>
        <div class="page-head-actions">
            @if ($canManage && now()->lte($period->end))
                <a href="{{ route($prefix.'.create', [$config['query_key'] => $activePeriodKey ?? $period->key()]) }}" class="btn btn-outline btn-sm">Edit {{ $config['noun_title'] }}</a>
            @endif
            <a href="{{ route($prefix.'.history', $bookkeeping) }}" class="btn btn-outline btn-sm">View History</a>
            <a href="{{ route($prefix.'.index', [$config['query_key'] => $period->key()]) }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    @include('admin.bookkeeping.partials.plan-strip', ['bookkeeping' => $bookkeeping, 'targets' => $targets])

    <div class="grid-2 bookkeeping-details">
        <div class="card">
            <div class="card-head"><h2 class="card-title">{{ $config['noun_title'] }} Summary</h2></div>
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
                    <span class="info-label">{{ $config['unit'] }}</span>
                    <div class="info-value">{{ $period->rangeLabel() }}</div>
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
        'updateRoute' => $prefix.'.update-target',
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
                                <td data-col="Task Type">
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
                                        'prefix' => $prefix,
                                    ])
                                </td>
                                <td data-col="Actions" class="text-end">
                                    @if ($canManage)
                                        @if ($target->isPending())
                                            <form method="POST" action="{{ route($prefix.'.start-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">Start</button>
                                            </form>
                                            <form method="POST" action="{{ route($prefix.'.destroy-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form" onsubmit="return confirm('Remove this target?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm text-danger">Remove</button>
                                            </form>
                                        @elseif ($target->isInProgress())
                                            <form method="POST" action="{{ route($prefix.'.upload-attachment', [$bookkeeping, $target]) }}" enctype="multipart/form-data" class="d-inline tb-actions-form">
                                                @csrf
                                                <input type="file" name="attachment" class="tb-file-input" accept="image/*,application/pdf,.doc,.docx" style="max-width:150px;">
                                                <button type="submit" class="btn btn-outline btn-sm">Upload proof</button>
                                            </form>
                                            <form method="POST" action="{{ route($prefix.'.complete-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form"
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
                                            <form method="POST" action="{{ route($prefix.'.reassign-target', [$bookkeeping, $target]) }}" class="d-inline tb-actions-form">
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
                                            'prefix' => $prefix,
                                        ])
                                    </span>
                                </div>
                            </div>
                            @if ($canManage)
                                <div class="cv-card-actions">
                                    @if ($target->isPending())
                                        <form method="POST" action="{{ route($prefix.'.start-target', [$bookkeeping, $target]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Start</button>
                                        </form>
                                        <form method="POST" action="{{ route($prefix.'.destroy-target', [$bookkeeping, $target]) }}" class="d-inline" onsubmit="return confirm('Remove this target?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm text-danger">Remove</button>
                                        </form>
                                    @elseif ($target->isInProgress())
                                        <form method="POST" action="{{ route($prefix.'.upload-attachment', [$bookkeeping, $target]) }}" enctype="multipart/form-data" class="d-inline">
                                            @csrf
                                            <input type="file" name="attachment" accept="image/*,application/pdf,.doc,.docx" style="max-width:150px;">
                                            <button type="submit" class="btn btn-outline btn-sm">Upload proof</button>
                                        </form>
                                        <form method="POST" action="{{ route($prefix.'.complete-target', [$bookkeeping, $target]) }}" class="d-inline" onsubmit="return confirm('Mark this task completed?');">
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
                No targets set for this {{ $unit }} yet.
                @if ($canManage)
                    <a href="{{ route($prefix.'.create', [$config['query_key'] => $period->key()]) }}" class="text-link">Set this {{ $unit }}&rsquo;s target</a>.
                @endif
            </div>
        @endif
    </div>

    {{-- Remarks Editor Modal --}}
    @include('admin.bookkeeping.partials.remarks-modal', [
        'updateRoute' => $prefix.'.update-target',
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
</style>
@include('admin.bookkeeping.partials.styles')
@endpush
