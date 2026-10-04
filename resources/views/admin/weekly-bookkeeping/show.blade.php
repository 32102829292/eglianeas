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

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Target vs Actual</h2>
            <span class="card-sub">{{ $targets->count() }} {{ \Illuminate\Support\Str::plural('task', $targets->count()) }}</span>
        </div>

        @error('action')<div class="form-error" style="margin-bottom:12px;">{{ $message }}</div>@enderror

        @if ($targets->isNotEmpty())
            <div class="table-wrap table-card-view">
                <table class="table table-hover align-middle mb-0 target-table">
                    <colgroup>
                        <col style="width:10%">
                        <col style="width:12%">
                        <col style="width:17%">
                        <col style="width:11%">
                        <col style="width:11%">
                        <col style="width:13%">
                        <col style="width:10%">
                        <col style="width:9%">
                        <col style="width:7%">
                    </colgroup>
                    <thead class="thead-muted">
                        <tr>
                            <th>Target Date</th>
                            <th>Task Type</th>
                            <th>Client</th>
                            <th>Assigned Staff</th>
                            <th class="text-center">Target</th>
                            <th class="text-center">Actual</th>
                            <th>Performed By</th>
                            <th>Evidence</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($targets as $target)
                            @php
                                $eff = $target->effectiveStatus();
                                $badge = match(true) {
                                    $target->isCompleted() && $target->isLate() => 'badge-warn',
                                    $target->isCompleted() => 'badge-success',
                                    $target->isInProgress() => 'badge-info',
                                    $target->isPastDue() => 'badge-warn',
                                    default => 'badge-neutral',
                                };
                            @endphp
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
                                    <span class="badge {{ $badge }}">{{ $target->effectiveStatusLabel() }}</span>
                                    @if ($target->isCompleted())
                                        @if ($target->timingLabel())
                                            <div><small class="muted">{{ $target->timingLabel() }}</small></div>
                                        @endif
                                        @if ($target->paymentDetail())
                                            <div><small class="muted">{{ $target->paymentDetail() }}</small></div>
                                        @elseif ($target->ended_at)
                                            <div><small class="muted">{{ $target->ended_at->format('M j, g:i A') }}</small></div>
                                        @endif
                                        @if ($target->durationHuman())
                                            <div><small class="muted">{{ $target->durationHuman() }}</small></div>
                                        @endif
                                    @endif
                                </td>
                                <td data-col="Performed By">
                                    @if ($target->performed_by_id || $target->performed_by_name)
                                        <div>{{ $target->performedByDisplayName() }}</div>
                                        <small>
                                            <span class="badge badge-sm performer-badge role-{{ $target->performed_by_role }}">{{ $target->performedByRoleLabel() }}</span>
                                        </small>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td data-col="Evidence">
                                    @if ($target->attachment_path)
                                        <div class="evidence-links">
                                            <a href="{{ route('admin.weekly-bookkeeping.view-attachment', [$bookkeeping, $target]) }}" target="_blank">View</a>
                                            <a href="{{ route('admin.weekly-bookkeeping.download-attachment', [$bookkeeping, $target]) }}" download>Download</a>
                                        </div>
                                        <small class="muted">{{ $target->attachment_name }}</small>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
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
                        @php
                            $eff = $target->effectiveStatus();
                            $badge = match(true) {
                                $target->isCompleted() && $target->isLate() => 'badge-warn',
                                $target->isCompleted() => 'badge-success',
                                $target->isInProgress() => 'badge-info',
                                $target->isPastDue() => 'badge-warn',
                                default => 'badge-neutral',
                            };
                        @endphp
                        <div class="cv-card">
                            <div class="cv-card-head">
                                <div class="cv-head-main">
                                    <div class="cv-head-title">{{ $target->taskLabel() }} · {{ $target->displayClientName() }}</div>
                                    <div class="cv-head-sub">{{ $target->target_date?->format('D, M j') ?? 'Any day' }}</div>
                                </div>
                                <span class="badge {{ $badge }}">{{ $target->effectiveStatusLabel() }}</span>
                            </div>
                            <div class="cv-card-body">
                                <div class="cv-pair">
                                    <span class="cv-label">Target</span>
                                    <span class="cv-value"><span class="badge badge-primary">Yes</span></span>
                                </div>
                                <div class="cv-pair">
                                    <span class="cv-label">Performed By</span>
                                    <span class="cv-value">
                                        @if ($target->performed_by_id || $target->performed_by_name)
                                            {{ $target->performedByDisplayName() }}
                                            <span class="badge badge-sm performer-badge role-{{ $target->performed_by_role }}">{{ $target->performedByRoleLabel() }}</span>
                                        @else
                                            —
                                        @endif
                                    </span>
                                </div>
                                @if ($target->started_at)
                                    <div class="cv-pair"><span class="cv-label">Started</span><span class="cv-value">{{ $target->started_at->format('M j, g:i A') }}</span></div>
                                @endif
                                @if ($target->ended_at)
                                    <div class="cv-pair"><span class="cv-label">Ended</span><span class="cv-value">{{ $target->ended_at->format('M j, g:i A') }}</span></div>
                                @endif
                                @if ($target->durationHuman())
                                    <div class="cv-pair"><span class="cv-label">Duration</span><span class="cv-value">{{ $target->durationHuman() }}</span></div>
                                @endif
                                <div class="cv-pair">
                                    <span class="cv-label">Evidence</span>
                                    <span class="cv-value">
                                        @if ($target->attachment_path)
                                            <a href="{{ route('admin.weekly-bookkeeping.view-attachment', [$bookkeeping, $target]) }}" target="_blank">{{ $target->attachment_name }}</a>
                                        @else
                                            —
                                        @endif
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

.performer-badge.role-staff { background: var(--info-bg, #dbeafe); color: var(--info, #3b82f6); }
.performer-badge.role-supervisor { background: var(--warn-bg, #fef3c7); color: var(--warn, #df6b00); }
.performer-badge.role-admin { background: var(--primary-bg, #e0e7ff); color: var(--primary, #6366f1); }

.tb-actions-form { margin-bottom: 4px; }
.tb-file-input { display: block; max-width: 150px; font-size: 11px; margin-bottom: 4px; }
</style>
@endpush