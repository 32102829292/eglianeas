@extends('layouts.dashboard')

@section('title', 'Daily Goals — Egliane Accounting Services')

@php
    use Carbon\Carbon;

    $weekLabel = $weekStart->format('M j') . ' – ' . $weekEnd->format('M j, Y');
    $goalsByEmployee = $grouped;
@endphp

@section('content')
<div class="page-head page-head-row">
    <div>
        <nav class="dg-crumbs" aria-label="Breadcrumb">
            <span>Daily Accomplishment</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="is-current">Daily Goals</span>
        </nav>
        <h1>Daily Goals</h1>
        <p>Set and manage targeted goals for employees. Goals set for a workweek appear as "Goals for the Day" on the Daily Accomplishment Monitor and are used as the AI evaluation reference.</p>
    </div>
    <div class="dg-head-side">
        <a href="{{ route('admin.daily-goals.index', ['week_start' => $previousWeek->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous Week
        </a>
        <a href="{{ route('admin.daily-goals.index', ['week_start' => $nextWeek->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next Week
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGoalModal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Goal
        </button>
    </div>
</div>

<div class="dg-week-nav">
    <span class="dg-week-label">{{ $weekLabel }}</span>
</div>

@if ($goals->isEmpty())
    <div class="dg-empty">
        <span class="dg-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
        </span>
        <h3>No active goals for this week</h3>
        <p>Use "Add Goal" to create targeted goals for employees during {{ $weekLabel }}.</p>
    </div>
@else
    <div class="dg-goal-grid">
        @foreach ($employees as $employee)
            @php
                $empGoals = $grouped->get($employee->id, collect());
            @endphp
            <div class="dg-employee-card">
                <div class="dg-emp-header">
                    <span class="dg-emp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                    <div class="dg-emp-info">
                        <span class="dg-emp-name">{{ $employee->name }}</span>
                        <span class="dg-emp-role">{{ ucfirst($employee->role) }}</span>
                    </div>
                    <span class="dg-goal-count">{{ $empGoals->count() }} goal{{ $empGoals->count() !== 1 ? 's' : '' }}</span>
                </div>
                <div class="dg-goal-list">
                    @if ($empGoals->isEmpty())
                        <div class="dg-empty-goal">
                            <span class="dg-empty-text">No goals set</span>
                        </div>
                    @else
                        @foreach ($empGoals as $goal)
                            <div class="dg-goal-item">
                                <div class="dg-goal-main">
                                    <span class="dg-goal-title">{{ $goal->title }}</span>
                                    @if ($goal->description)
                                        <span class="dg-goal-desc">{{ $goal->description }}</span>
                                    @endif
                                    @if ($goal->success_criteria)
                                        <span class="dg-goal-criteria">
                                            <span class="dg-criteria-label">Success:</span> {{ $goal->success_criteria }}
                                        </span>
                                    @endif
                                    <span class="dg-goal-date">{{ $goal->target_date?->format('D, M j') }}</span>
                                </div>
                                <div class="dg-goal-actions">
                                    <button type="button" class="btn btn-outline btn-sm dg-edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#editGoalModal"
                                            data-goal-id="{{ $goal->id }}"
                                            data-goal-user-id="{{ $goal->user_id }}"
                                            data-goal-week-start="{{ $goal->week_start_date }}"
                                            data-goal-target-date="{{ $goal->target_date }}"
                                            data-goal-title="{{ e($goal->title) }}"
                                            data-goal-description="{{ e($goal->description ?? '') }}"
                                            data-goal-success-criteria="{{ e($goal->success_criteria ?? '') }}"
                                            data-goal-status="{{ $goal->status }}"
                                            data-goal-change-reason="">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('admin.daily-goals.destroy', $goal) }}" class="d-inline" onsubmit="return confirm('Cancel this goal? The goal will no longer appear as a daily target.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline btn-sm text-danger">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

@push('styles')
<style>
/* Scoped to .dg-* for Daily Goals management page */
.dg-head-side { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.dg-week-nav { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; margin-bottom: 1rem; }
.dg-week-label { font-weight: 700; color: var(--navy, #1e3a5f); font-size: 1rem; }
.dg-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 3rem 1.5rem; text-align: center; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; }
.dg-empty-icon { width: 48px; height: 48px; border-radius: 14px; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; }
.dg-empty-icon svg { width: 24px; height: 24px; }
.dg-empty h3 { margin: .35rem 0 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.dg-empty p { max-width: 30rem; margin: 0; color: var(--muted, #64748b); font-size: .85rem; }
.dg-goal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.dg-employee-card { border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; background: var(--surface, #fff); overflow: hidden; display: flex; flex-direction: column; }
.dg-emp-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle, #e5e7eb); background: #f8fafc; }
.dg-emp-info { min-width: 0; }
.dg-emp-name { color: var(--navy, #1e3a5f); font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dg-emp-role { color: var(--muted, #64748b); font-size: .78rem; }
.dg-emp-avatar { width: 34px; height: 34px; border-radius: 50%; background: #eef4fd; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; flex: none; }
.dg-goal-count { font-size: .75rem; font-weight: 700; color: var(--muted, #64748b); background: #f1f5f9; padding: .2rem .5rem; border-radius: 999px; white-space: nowrap; }
.dg-goal-list { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .75rem; }
.dg-empty-goal { text-align: center; padding: 1rem; color: var(--muted, #64748b); font-size: .85rem; font-style: italic; }
.dg-goal-item { display: flex; flex-direction: column; gap: .5rem; padding: .75rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 10px; }
.dg-goal-main { display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
.dg-goal-title { font-weight: 700; color: var(--navy, #1e3a5f); font-size: .9rem; overflow-wrap: break-word; }
.dg-goal-desc { font-size: .82rem; color: var(--muted, #64748b); overflow-wrap: break-word; }
.dg-goal-criteria { font-size: .78rem; color: #2563eb; background: #eef4fd; padding: .2rem .5rem; border-radius: 6px; display: inline-block; margin-top: .2rem; overflow-wrap: break-word; }
.dg-criteria-label { font-weight: 700; }
.dg-goal-date { font-size: .75rem; font-weight: 700; color: #6b7280; }
.dg-goal-actions { display: flex; gap: .4rem; margin-top: .5rem; }
.dg-edit-btn { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; padding: .45rem .8rem; }
.dg-edit-btn svg { width: 14px; height: 14px; flex: none; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var editModal = document.getElementById('editGoalModal');
    if (! editModal) { return; }

    var editModalInstance = new bootstrap.Modal(editModal);

    document.addEventListener('show.bs.modal', function (event) {
        if (! event.target || event.target.id !== 'editGoalModal') { return; }

        var btn = event.relatedTarget;
        if (! btn) { return; }

        var form = document.getElementById('editGoalForm');
        if (! form) { return; }

        form.action = btn.dataset.action || form.action;
        document.getElementById('edit_goal_id').value = btn.dataset.goalId || '';
        document.getElementById('edit_goal_title').value = btn.dataset.goalTitle || '';
        document.getElementById('edit_goal_description').value = btn.dataset.goalDescription || '';
        document.getElementById('edit_goal_success_criteria').value = btn.dataset.goalSuccessCriteria || '';
        document.getElementById('edit_goal_target_date').value = btn.dataset.goalTargetDate || '';
        document.getElementById('edit_goal_status').value = btn.dataset.goalStatus || 'active';
        document.getElementById('edit_goal_change_reason').value = btn.dataset.goalChangeReason || '';
    });
})();
</script>
@endpush

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.daily-goals.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Daily Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-control" name="user_id" required>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ ucfirst($employee->role) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Week Start (Monday) <span class="text-danger">*</span></label>
                        <input type="date" name="week_start_date" class="form-control" value="{{ $weekStart->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Target Date <span class="text-danger">*</span></label>
                        <input type="date" name="target_date" class="form-control" value="{{ $weekStart->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Goal Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255" placeholder="e.g. Complete client file review">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="1000" placeholder="Describe what the employee should accomplish..."></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Success Criteria (Measurable)</label>
                        <input type="text" name="success_criteria" class="form-control" maxlength="500" placeholder="e.g. All 15 files reconciled and signed off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Goal Modal -->
<div class="modal fade" id="editGoalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editGoalForm" action="{{ route('admin.daily-goals.update', 0) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="goal_id" id="edit_goal_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Daily Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Goal Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_goal_title" class="form-control" required maxlength="255">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_goal_description" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Success Criteria (Measurable)</label>
                        <input type="text" name="success_criteria" id="edit_goal_success_criteria" class="form-control" maxlength="500">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Target Date <span class="text-danger">*</span></label>
                        <input type="date" name="target_date" id="edit_goal_target_date" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_goal_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Change Reason</label>
                        <input type="text" name="change_reason" id="edit_goal_change_reason" class="form-control" maxlength="500" placeholder="Why is this goal being changed?">
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

@endsection
