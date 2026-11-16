@extends('layouts.dashboard')

@section('title', 'Work Plan — Daily Accomplishment')

@section('content')
@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
    use App\Models\WorkPlanTask;

    $weekStartLabel = $weekStart->format('M j') . ' — ' . $weekEnd->format('M j, Y');
    $isCurrentWeek  = $weekStart->format('o-W') === now()->format('o-W');
    $isAdmin        = auth()->user()->isAdmin();

    // Monday to Friday
    $weekDates = [];
    for ($i = 0; $i < 5; $i++) {
        $weekDates[] = $weekStart->copy()->addDays($i);
    }

    // Week-wide numbers for the summary strip (cancelled tasks don't count).
    $liveTasks      = $tasks->where('status', '!=', WorkPlanTask::STATUS_CANCELLED);
    $plannedTotal   = $liveTasks->count();
    $completedTotal = $liveTasks->where('status', WorkPlanTask::STATUS_COMPLETED)->count();
    $remainingTotal = $plannedTotal - $completedTotal;
    $weekRate       = $plannedTotal > 0 ? (int) round($completedTotal / $plannedTotal * 100) : 0;

    $updatedByName = $lastUpdatedBy ? \App\Models\User::find($lastUpdatedBy)?->name : null;
    $visibleLimit  = 3;
@endphp

<div class="wp">
    {{-- ===== Header ===== --}}
    <header class="wp-head">
        <div class="wp-head-main">
            <span class="wp-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8.01" y2="14"/><line x1="12" y1="14" x2="12.01" y2="14"/><line x1="16" y1="14" x2="16.01" y2="14"/><line x1="8" y1="18" x2="8.01" y2="18"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            </span>
            <div>
                <h1>Work Plan</h1>
                <p>Plan weekly tasks for employees. Each day supports multiple task codes.</p>
            </div>
        </div>

        <div class="wp-head-actions">
            <span class="wp-lock-pill {{ $editingEnabled ? 'is-open' : 'is-locked' }}">
                <span class="wp-lock-dot" aria-hidden="true"></span>
                Editing {{ $editingEnabled ? 'open' : 'locked' }}
            </span>
            <a href="{{ route('admin.daily-goals.index', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wp-btn wp-btn-ghost">Goals Board</a>
            <a href="{{ route('admin.work-plan-legends.index') }}" class="wp-btn wp-btn-ghost">Manage Legends</a>
            <a href="{{ route('admin.daily-work-plan.incomplete', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wp-btn wp-btn-ghost">Incomplete Tasks</a>
            <a href="{{ route('admin.daily-work-plan.performance', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wp-btn wp-btn-ghost">Performance</a>
            @if ($editingEnabled)
                <button type="button" class="wp-btn wp-btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="16" height="16" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Task
                </button>
            @endif
        </div>
    </header>

    {{-- ===== Week navigation ===== --}}
    <div class="wp-weeknav">
        <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $previousWeek->format('Y-m-d')]) }}" class="wp-nav-btn" rel="prev" aria-label="Previous week" title="Previous week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="wp-week-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>{{ $weekStartLabel }}</span>
        </div>
        <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $nextWeek->format('Y-m-d')]) }}" class="wp-nav-btn" rel="next" aria-label="Next week" title="Next week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="{{ route('admin.daily-work-plan.index') }}" class="wp-today-btn {{ $isCurrentWeek ? 'is-current' : '' }}" @if ($isCurrentWeek) aria-current="date" @endif>This week</a>
    </div>

    {{-- ===== Summary ===== --}}
    <section class="wp-stats" aria-label="Week summary">
        <div class="wp-stat">
            <span class="wp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <div><span class="wp-stat-value">{{ $employees->count() }}</span><span class="wp-stat-label">Employees</span></div>
        </div>
        <div class="wp-stat">
            <span class="wp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
            <div><span class="wp-stat-value">{{ $plannedTotal }}</span><span class="wp-stat-label">Planned tasks</span></div>
        </div>
        <div class="wp-stat wp-stat--ok">
            <span class="wp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            <div><span class="wp-stat-value">{{ $completedTotal }}</span><span class="wp-stat-label">Completed</span></div>
        </div>
        <div class="wp-stat wp-stat--warn">
            <span class="wp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
            <div><span class="wp-stat-value">{{ $remainingTotal }}</span><span class="wp-stat-label">Remaining</span></div>
        </div>
        <div class="wp-stat wp-stat--wide">
            <div class="wp-rate">
                <div class="wp-rate-top"><span>Week completion</span><strong>{{ $weekRate }}%</strong></div>
                <div class="wp-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $weekRate }}"><span style="width: {{ $weekRate }}%"></span></div>
            </div>
        </div>
    </section>

    {{-- ===== Editing lock (admin) ===== --}}
    @if ($isAdmin)
        <section class="wp-lockbar" aria-label="Editing status">
            <div class="wp-lockbar-info">
                <div>
                    <span class="wp-k">Editing status</span>
                    <strong class="{{ $editingEnabled ? 'is-ok' : 'is-bad' }}">{{ $editingEnabled ? 'Open for editing' : 'Locked for editing' }}</strong>
                    @if (! $editingEnabled && $lockedAt)
                        <small>Locked {{ Carbon::parse($lockedAt)->format('M j, Y g:i A') }}</small>
                    @endif
                </div>
                @if ($nextStateChange)
                    <div>
                        <span class="wp-k">Next change</span>
                        <strong>{{ Carbon::parse($nextStateChange)->format('l, M j, g:i A') }}</strong>
                    </div>
                @endif
                @if ($lastUpdated)
                    <div>
                        <span class="wp-k">Last updated</span>
                        <strong>{{ Carbon::parse($lastUpdated)->format('M j, Y g:i A') }}</strong>
                        @if ($updatedByName)<small>by {{ $updatedByName }}</small>@endif
                    </div>
                @endif
            </div>
            @unless ($editingEnabled)
                <form method="POST" action="{{ route('admin.daily-work-plan.toggle-lock') }}">
                    @csrf
                    <button type="submit" class="wp-btn wp-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                        Unlock editing
                    </button>
                </form>
            @endunless
        </section>
    @endif

    {{-- ===== Weekly grid ===== --}}
    <section class="wp-card" aria-label="Weekly work plan">
        @if ($employees->isEmpty())
            <div class="wp-empty">
                <span class="wp-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>
                <h3>No employees to plan for</h3>
                <p>Add staff or supervisors and they will appear here.</p>
            </div>
        @else
            <div class="wp-scroll">
                <div class="wp-grid">
                    {{-- Header row --}}
                    <div class="wp-hcell wp-hcell-emp">Employee</div>
                    @foreach ($weekDates as $date)
                        <div class="wp-hcell wp-hcell-day {{ $date->isToday() ? 'is-today' : '' }}">
                            <span class="wp-day-name">{{ $date->format('D') }}</span>
                            <span class="wp-day-date">{{ $date->format('M j') }}</span>
                        </div>
                    @endforeach
                    <div class="wp-hcell wp-hcell-total">Progress</div>

                    {{-- Employee rows --}}
                    @foreach ($employees as $employee)
                        @php
                            $empTasks       = $grouped[$employee->id] ?? collect();
                            $completedCount = $empTasks->where('status', WorkPlanTask::STATUS_COMPLETED)->count();
                            $totalCount     = $empTasks->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)->count();
                            $empRate        = $totalCount > 0 ? (int) round($completedCount / $totalCount * 100) : 0;
                        @endphp
                        <div class="wp-row">
                            <div class="wp-emp">
                                <span class="wp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                                <span class="wp-emp-text">
                                    <span class="wp-emp-name">{{ $employee->name }}</span>
                                    <span class="wp-emp-role">{{ ucfirst($employee->role) }}</span>
                                </span>
                            </div>

                            @foreach ($weekDates as $date)
                                @php
                                    $dateStr       = $date->toDateString();
                                    $dayTasks      = $groupedByUserAndDate[$employee->id . '|' . $dateStr] ?? collect();
                                    $activeTasks   = $dayTasks->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)->values();
                                    $selectedCodes = $activeTasks->pluck('legend_code')->filter()->all();
                                @endphp
                                <div class="wp-cell {{ $activeTasks->isNotEmpty() ? 'has-tasks' : 'is-empty' }} {{ $date->isToday() ? 'is-today' : '' }}"
                                     data-user-id="{{ $employee->id }}" data-date="{{ $dateStr }}">

                                    <div class="wp-chip-list" data-wp-chips>
                                        @foreach ($activeTasks as $index => $task)
                                            <span class="wp-chip wp-chip-{{ $task->status }} {{ $index >= $visibleLimit ? 'wp-chip-extra' : '' }}"
                                                  title="{{ $task->getLegendLabel() }}">
                                                {{ $task->legend_code ?? $task->custom_title }}
                                                @if ($task->isAdditional())
                                                    <span class="wp-chip-additional" title="Additional/urgent activity">+</span>
                                                @endif
                                                @if ($task->status === WorkPlanTask::STATUS_COMPLETED)
                                                    <span class="wp-chip-check" aria-label="Completed">✓</span>
                                                @endif
                                            </span>
                                        @endforeach
                                        @if ($activeTasks->count() > $visibleLimit)
                                            <button type="button" class="wp-chip wp-chip-more" data-wp-more-toggle
                                                    data-more-label="+{{ $activeTasks->count() - $visibleLimit }} more"
                                                    data-less-label="Show less">+{{ $activeTasks->count() - $visibleLimit }} more</button>
                                        @endif
                                    </div>

                                    @if ($isAdmin && $editingEnabled)
                                        <button type="button" class="wp-cell-picker-btn" data-wp-picker-toggle aria-expanded="false">
                                            {{ $activeTasks->isEmpty() ? '+ Add codes' : 'Edit codes' }}
                                        </button>

                                        <div class="wp-picker" data-wp-picker hidden>
                                            <input type="search" class="wp-picker-search" data-wp-search placeholder="Search codes or names…" autocomplete="off" aria-label="Search codes">
                                            <form method="POST" action="{{ route('admin.daily-work-plan.sync-day') }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $employee->id }}">
                                                <input type="hidden" name="work_date" value="{{ $dateStr }}">
                                                <div class="wp-picker-options">
                                                    @foreach ($legends as $legend)
                                                        <label class="wp-picker-option" data-wp-option data-search="{{ strtolower($legend->code.' '.$legend->name) }}">
                                                            <input type="checkbox" name="legend_codes[]" value="{{ $legend->code }}" @checked(in_array($legend->code, $selectedCodes, true))>
                                                            <span class="wp-picker-code">{{ $legend->code }}</span>
                                                            <span class="wp-picker-name">{{ $legend->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <div class="wp-picker-actions">
                                                    <button type="submit" class="wp-btn wp-btn-primary wp-btn-sm">Save codes</button>
                                                </div>
                                            </form>
                                        </div>

                                        @if ($dayTasks->isNotEmpty())
                                            <button type="button" class="wp-cell-edit-btn" data-user-id="{{ $employee->id }}" data-date="{{ $dateStr }}" data-bs-toggle="modal" data-bs-target="#editTaskModal" aria-label="Edit task details for {{ $employee->name }} on {{ $date->format('M j') }}" title="Edit task details">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach

                            <div class="wp-total">
                                <span class="wp-total-score">{{ $completedCount }} / {{ $totalCount }}</span>
                                <span class="wp-track wp-track-sm" aria-hidden="true"><span style="width: {{ $empRate }}%"></span></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="wp-key" aria-label="Chip colour key">
                <span class="wp-chip">Pending</span>
                <span class="wp-chip wp-chip-completed">Completed ✓</span>
                <span class="wp-chip wp-chip-on_hold">On hold</span>
                <span class="wp-chip wp-chip-returned_for_revision">Returned</span>
                <span class="wp-key-item"><span class="wp-chip-additional" aria-hidden="true">+</span> Additional / urgent</span>
            </div>
        @endif
    </section>

    {{-- ===== Activity legend codes (collapsible reference) ===== --}}
    <details class="wp-card wp-legend">
        <summary class="wp-card-head">
            <span class="wp-tile" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </span>
            <div class="wp-card-head-text">
                <h2>Activity Legend Codes <span class="wp-count">{{ $legends->count() }}</span></h2>
                <p>Quick reference for weekly work planning.</p>
            </div>
            <span class="wp-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></span>
        </summary>

        <div class="wp-legend-body">
            @if ($legends->isEmpty())
                <p class="wp-legend-empty">No activity legends configured yet.</p>
            @else
                <label class="wp-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span class="visually-hidden">Search codes</span>
                    <input type="search" id="wp-legend-search" placeholder="Search codes or descriptions…" autocomplete="off">
                </label>

                <div class="wp-legend-grid">
                    @foreach ($legends as $legend)
                        <div class="wp-legend-item" data-legend-item data-search="{{ strtolower($legend->code.' '.$legend->name) }}">
                            <span class="wp-legend-code">{{ $legend->code }}</span>
                            <span class="wp-legend-name">{{ $legend->name }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="wp-legend-empty" data-legend-none hidden>No codes match your search.</p>
            @endif
        </div>
    </details>

    {{-- ===== Add Task modal ===== --}}
    <div class="modal fade wp-modal" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.daily-work-plan.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTaskTitle">Add Work Plan Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="wp-form-grid">
                            <div class="wp-field">
                                <label for="add_user_id">Employee <span class="wp-req">*</span></label>
                                <select name="user_id" id="add_user_id" required>
                                    <option value="">Select employee</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }} ({{ ucfirst($employee->role) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="wp-field">
                                <label for="add_work_date">Work date <span class="wp-req">*</span></label>
                                <input type="date" name="work_date" id="add_work_date" required>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="task_code_select">Task code <span class="wp-req">*</span></label>
                                <select name="legend_code" id="task_code_select">
                                    <option value="">Select task code</option>
                                    @foreach ($legends as $legend)
                                        <option value="{{ $legend->code }}">{{ $legend->code }} - {{ $legend->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="add_custom_title">Custom title</label>
                                <input type="text" name="custom_title" id="add_custom_title" placeholder="Custom task title (if not from legend)">
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="add_description">Description</label>
                                <textarea name="description" id="add_description" rows="2" maxlength="1000" placeholder="Describe the task…"></textarea>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="add_success">Success criteria</label>
                                <input type="text" name="success_criteria" id="add_success" maxlength="500" placeholder="Measurable completion criteria">
                            </div>
                            <div class="wp-field">
                                <label for="add_client_name">Client name</label>
                                <input type="text" name="client_name" id="add_client_name" placeholder="Optional">
                            </div>
                            <div class="wp-field">
                                <label for="add_client_code">Client code</label>
                                <input type="text" name="client_code" id="add_client_code" maxlength="20" placeholder="Optional">
                            </div>
                            <label class="wp-check wp-span-2" for="add_is_additional">
                                <input type="checkbox" name="is_additional" value="1" id="add_is_additional">
                                <span>Additional / urgent activity <small>(kept separate from planned tasks)</small></span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="wp-btn wp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="wp-btn wp-btn-primary">Create task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== Edit Task modal ===== --}}
    <div class="modal fade wp-modal" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editTaskForm" method="POST" action="{{ route('admin.daily-work-plan.update', 0) }}" data-update-url="{{ route('admin.daily-work-plan.update', '__TASK__') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTaskTitle">Edit Work Plan Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="wp-form-grid">
                            <div class="wp-field wp-span-2" id="edit_task_picker_wrap" hidden>
                                <label for="edit_task_picker">This day has several tasks. Choose one to edit</label>
                                <select id="edit_task_picker"></select>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_task_code">Task code</label>
                                <select name="legend_code" id="edit_task_code">
                                    <option value="">Select task code</option>
                                    @foreach ($legends as $legend)
                                        <option value="{{ $legend->code }}">{{ $legend->code }} - {{ $legend->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_custom_title">Custom title</label>
                                <input type="text" name="custom_title" id="edit_custom_title" placeholder="Custom task title">
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_description">Description</label>
                                <textarea name="description" id="edit_description" rows="2" maxlength="1000"></textarea>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_success_criteria">Success criteria</label>
                                <input type="text" name="success_criteria" id="edit_success_criteria" maxlength="500" placeholder="Measurable completion criteria">
                            </div>
                            <div class="wp-field">
                                <label for="edit_client_name">Client name</label>
                                <input type="text" name="client_name" id="edit_client_name" placeholder="Optional">
                            </div>
                            <div class="wp-field">
                                <label for="edit_status">Status</label>
                                <select name="status" id="edit_status">
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="returned_for_revision">Returned for Revision</option>
                                </select>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_work_date">Work date</label>
                                <input type="date" name="work_date" id="edit_work_date">
                                <small class="wp-help">Changing the date carries the task over and keeps its original target date in history.</small>
                            </div>
                            <div class="wp-field wp-span-2">
                                <label for="edit_change_reason">Change reason</label>
                                <input type="text" name="change_reason" id="edit_change_reason" placeholder="Reason for change">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="wp-btn wp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="wp-btn wp-btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.wp, .wp-modal {
    --wp-ink: #1b2447;
    --wp-muted: #5b6585;
    --wp-line: #e3e7f3;
    --wp-surface: #ffffff;
    --wp-sunken: #f7f8ff;
    --wp-tint: #eef0fc;
    --wp-brand: #4338ca;
    --wp-brand-dark: #3730a3;
    --wp-ok: #0f9d58;
    color: var(--wp-ink);
}
.wp h1, .wp h2, .wp h3, .wp p { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* ---------- Header ---------- */
.wp-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.wp-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.wp-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--wp-tint); color: var(--wp-brand); }
.wp-head-icon svg { width: 30px; height: 30px; }
.wp-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.wp-head p { margin-top: 2px; color: var(--wp-muted); font-size: .95rem; }
.wp-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.wp-lock-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border: 1px solid; border-radius: 999px; font-size: .82rem; font-weight: 700; }
.wp-lock-pill.is-open { background: #e7f6ee; border-color: #c7ebd8; color: #0b5f37; }
.wp-lock-pill.is-locked { background: #fdecea; border-color: #f7cfca; color: #a5281a; }
.wp-lock-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

/* ---------- Buttons ---------- */
.wp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.wp-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--wp-brand-dark)); color: #fff; }
.wp-btn-primary:hover { background: var(--wp-brand-dark); color: #fff; }
.wp-btn-ghost { background: var(--wp-surface); border-color: var(--wp-line); color: var(--wp-ink); }
.wp-btn-ghost:hover { background: var(--wp-tint); color: var(--wp-ink); }
.wp-btn-sm { padding: 7px 12px; font-size: .8rem; }
.wp-btn:focus-visible, .wp-nav-btn:focus-visible, .wp-today-btn:focus-visible, .wp-card-head:focus-visible, .wp-cell-picker-btn:focus-visible, .wp-cell-edit-btn:focus-visible, .wp-chip-more:focus-visible, .wp-search input:focus-visible, .wp-field input:focus-visible, .wp-field select:focus-visible, .wp-field textarea:focus-visible, .wp-picker-search:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* ---------- Week nav ---------- */
.wp-weeknav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
.wp-week-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--wp-surface); border: 1px solid var(--wp-line); border-radius: 12px; font-weight: 700; font-size: .95rem; white-space: nowrap; }
.wp-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--wp-surface); border: 1px solid var(--wp-line); border-radius: 12px; color: var(--wp-ink); text-decoration: none; }
.wp-nav-btn:hover { background: var(--wp-tint); }
.wp-today-btn { padding: 10px 16px; border-radius: 12px; background: var(--wp-surface); border: 1px solid var(--wp-line); color: var(--wp-ink); font-weight: 700; font-size: .9rem; text-decoration: none; }
.wp-today-btn:hover { background: var(--wp-tint); }
.wp-today-btn.is-current { background: var(--wp-ink); border-color: var(--wp-ink); color: #fff; }

/* ---------- Stats ---------- */
.wp-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(0, 1.6fr); gap: 16px; margin-bottom: 16px; }
.wp-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: var(--wp-surface); border: 1px solid var(--wp-line); border-radius: 18px; }
.wp-stat-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: var(--wp-tint); color: var(--wp-brand); }
.wp-stat-icon svg { width: 22px; height: 22px; }
.wp-stat--ok .wp-stat-icon { background: #e7f6ee; color: var(--wp-ok); }
.wp-stat--warn .wp-stat-icon { background: #fdf1e2; color: #b45309; }
.wp-stat div { display: flex; flex-direction: column; }
.wp-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.wp-stat-label { margin-top: 4px; color: var(--wp-muted); font-size: .82rem; font-weight: 700; }
.wp-stat--wide { display: block; }
.wp-rate { display: grid; gap: 8px; width: 100%; }
.wp-rate-top { display: flex; justify-content: space-between; align-items: baseline; font-size: .85rem; color: var(--wp-muted); font-weight: 700; flex-direction: row !important; }
.wp-rate-top strong { color: var(--wp-brand); font-size: 1.3rem; font-weight: 800; }
.wp-track { display: block; height: 8px; border-radius: 999px; background: #e9ebf7; overflow: hidden; }
.wp-track > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, var(--wp-brand)); }
.wp-track-sm { height: 5px; width: 100%; max-width: 72px; }

/* ---------- Lock bar ---------- */
.wp-lockbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px 20px; margin-bottom: 16px; padding: 14px 20px; background: var(--wp-sunken); border: 1px solid var(--wp-line); border-radius: 16px; }
.wp-lockbar-info { display: flex; flex-wrap: wrap; gap: 14px 32px; }
.wp-lockbar-info > div { display: flex; flex-direction: column; gap: 2px; }
.wp-k { color: var(--wp-muted); font-size: .75rem; font-weight: 700; }
.wp-lockbar strong { font-size: .92rem; }
.wp-lockbar strong.is-ok { color: #0b7a45; }
.wp-lockbar strong.is-bad { color: #a5281a; }
.wp-lockbar small { color: var(--wp-muted); font-size: .76rem; }

/* ---------- Cards ---------- */
.wp-card { background: var(--wp-surface); border: 1px solid var(--wp-line); border-radius: 18px; margin-bottom: 20px; overflow: hidden; }
.wp-card > summary { list-style: none; cursor: pointer; }
.wp-card > summary::-webkit-details-marker { display: none; }
.wp-card-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; }
.wp-card[open] > .wp-card-head { border-bottom: 1px solid var(--wp-line); }
.wp-card-head-text { flex: 1; min-width: 0; }
.wp-card-head h2 { display: flex; align-items: center; gap: 8px; font-size: 1.05rem; font-weight: 800; }
.wp-card-head p { margin-top: 2px; color: var(--wp-muted); font-size: .85rem; }
.wp-tile { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--wp-tint); color: var(--wp-brand); }
.wp-tile svg { width: 22px; height: 22px; }
.wp-count { padding: 1px 9px; border-radius: 999px; background: var(--wp-tint); color: var(--wp-brand); font-size: .75rem; font-weight: 700; }
.wp-chevron { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center; color: var(--wp-muted); transition: transform .2s; }
.wp-chevron svg { width: 18px; height: 18px; }
.wp-card[open] .wp-chevron { transform: rotate(180deg); }

/* ---------- Grid ---------- */
.wp-scroll { overflow-x: auto; }
.wp-grid { display: grid; grid-template-columns: 220px repeat(5, minmax(150px, 1fr)) 110px; min-width: 1060px; }
.wp-row { display: contents; }
.wp-hcell { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; padding: 12px 8px; background: #e9ebfb; border-bottom: 1px solid var(--wp-line); font-size: .85rem; font-weight: 700; }
.wp-hcell-emp { align-items: flex-start; padding-left: 20px; position: sticky; left: 0; z-index: 3; }
.wp-hcell-day.is-today { background: var(--wp-brand); color: #fff; }
.wp-day-date { font-size: .75rem; font-weight: 600; opacity: .8; }
.wp-emp { display: flex; align-items: center; gap: 12px; padding: 12px 12px 12px 20px; background: var(--wp-surface); border-bottom: 1px solid var(--wp-line); border-right: 1px solid var(--wp-line); position: sticky; left: 0; z-index: 2; min-width: 0; }
.wp-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; background: var(--wp-tint); color: var(--wp-brand); font-weight: 800; font-size: .85rem; }
.wp-emp-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.wp-emp-name { font-weight: 800; font-size: .92rem; overflow-wrap: anywhere; }
.wp-emp-role { color: var(--wp-muted); font-size: .76rem; }
.wp-cell { position: relative; display: flex; flex-direction: column; align-items: stretch; gap: 6px; min-height: 64px; padding: 10px 8px; border-bottom: 1px solid var(--wp-line); border-right: 1px solid var(--wp-line); background: var(--wp-sunken); }
.wp-cell.has-tasks { background: #f1faf5; }
.wp-cell.is-today { box-shadow: inset 0 0 0 2px rgba(67, 56, 202, .35); }
.wp-total { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 10px 8px; border-bottom: 1px solid var(--wp-line); background: #f4f5fd; }
.wp-total-score { font-weight: 800; font-size: .9rem; font-variant-numeric: tabular-nums; color: var(--wp-brand); }

/* ---------- Chips ---------- */
.wp-chip-list { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 4px; }
.wp-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border: 1px solid #c7d2fe; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-size: .72rem; font-weight: 700; white-space: nowrap; }
.wp-chip-completed { background: #dcfce7; color: #166534; border-color: #86efac; }
.wp-chip-on_hold { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.wp-chip-returned_for_revision { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.wp-chip-additional { display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; border-radius: 50%; background: #f97316; color: #fff; font-size: .62rem; font-weight: 800; line-height: 1; }
.wp-chip-check { font-size: .68rem; }
.wp-chip-more { background: #fff; color: var(--wp-brand); border-color: #c7d2fe; cursor: pointer; font-family: inherit; }
.wp-chip-more:hover { background: var(--wp-tint); }
.wp-chip-extra { display: none; }
.wp-chip-list.is-expanded .wp-chip-extra { display: inline-flex; }
.wp-key { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 14px 20px; border-top: 1px solid var(--wp-line); background: var(--wp-sunken); }
.wp-key-item { display: inline-flex; align-items: center; gap: 6px; color: var(--wp-muted); font-size: .8rem; font-weight: 600; }

/* ---------- Cell controls ---------- */
.wp-cell-picker-btn { align-self: center; padding: 3px 10px; border: 1px dashed #b8bfee; border-radius: 8px; background: transparent; color: var(--wp-brand); font: inherit; font-size: .72rem; font-weight: 700; cursor: pointer; }
.wp-cell-picker-btn:hover { background: var(--wp-tint); }
.wp-cell-edit-btn { position: absolute; top: 4px; right: 4px; display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; padding: 0; border: 1px solid #c7d2fe; border-radius: 8px; background: #fff; color: var(--wp-brand); opacity: 0; cursor: pointer; transition: opacity .15s; }
.wp-cell-edit-btn svg { width: 14px; height: 14px; }
.wp-cell:hover .wp-cell-edit-btn, .wp-cell:focus-within .wp-cell-edit-btn { opacity: 1; }
@media (hover: none) { .wp-cell-edit-btn { opacity: 1; } }

/* Picker popover: fixed so the scrolling grid can't clip it; positioned by script */
.wp-picker { position: fixed; z-index: 1080; width: 280px; padding: 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 14px; box-shadow: 0 16px 36px rgba(15, 23, 42, .22); text-align: left; }
.wp-picker[hidden] { display: none; }
.wp-picker-search { width: 100%; padding: 8px 10px; border: 1px solid #cdd3ea; border-radius: 10px; font: inherit; font-size: .82rem; box-sizing: border-box; }
.wp-picker-options { display: flex; flex-direction: column; gap: 2px; max-height: 210px; margin-top: 8px; overflow-y: auto; }
.wp-picker-option { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; font-size: .8rem; cursor: pointer; }
.wp-picker-option:hover { background: var(--wp-sunken); }
.wp-picker-option[hidden] { display: none; }
.wp-picker-option input { accent-color: var(--wp-brand); }
.wp-picker-code { min-width: 28px; font-weight: 800; color: var(--wp-brand); }
.wp-picker-name { color: var(--wp-muted); }
.wp-picker-actions { margin-top: 10px; text-align: right; }

/* ---------- Legend reference ---------- */
.wp-legend-body { padding: 16px 20px 20px; }
.wp-search { position: relative; display: block; margin: 0 0 14px; max-width: 420px; }
.wp-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.wp-search input { width: 100%; min-height: 40px; padding: 8px 12px 8px 36px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; font: inherit; font-size: .9rem; color: var(--wp-ink); box-sizing: border-box; }
.wp-legend-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; }
.wp-legend-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: var(--wp-sunken); border: 1px solid var(--wp-line); border-radius: 12px; }
.wp-legend-item[hidden] { display: none; }
.wp-legend-code { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; min-width: 40px; height: 40px; padding: 0 10px; border-radius: 12px; background: #e4e6fb; color: var(--wp-brand); font-size: 1rem; font-weight: 800; box-sizing: border-box; }
.wp-legend-name { font-size: .85rem; line-height: 1.35; overflow-wrap: anywhere; }
.wp-legend-empty { color: var(--wp-muted); font-size: .88rem; text-align: center; padding: 16px; }
.wp-legend-empty[hidden] { display: none; }

/* ---------- Empty ---------- */
.wp-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 40px 20px; text-align: center; }
.wp-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--wp-muted); }
.wp-empty-icon svg { width: 24px; height: 24px; }
.wp-empty h3 { font-size: 1rem; font-weight: 800; }
.wp-empty p { color: var(--wp-muted); font-size: .88rem; }

/* ---------- Modals ---------- */
.wp-modal .modal-content { border: 1px solid var(--wp-line); border-radius: 18px; overflow: hidden; }
.wp-modal .modal-header, .wp-modal .modal-footer { border-color: var(--wp-line); padding: 16px 20px; }
.wp-modal .modal-title { font-size: 1.05rem; font-weight: 800; color: var(--wp-ink); }
.wp-modal .modal-body { padding: 18px 20px; }
.wp-modal .modal-footer { gap: 8px; }
.wp-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.wp-span-2 { grid-column: 1 / -1; }
.wp-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.wp-field[hidden] { display: none; }
.wp-field label { font-size: .82rem; font-weight: 700; color: var(--wp-ink); }
.wp-req { color: #dc2626; }
.wp-field input, .wp-field select, .wp-field textarea { width: 100%; padding: 9px 12px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; color: var(--wp-ink); font: inherit; font-size: .9rem; box-sizing: border-box; }
.wp-field textarea { resize: vertical; line-height: 1.5; }
.wp-help { color: var(--wp-muted); font-size: .76rem; }
.wp-check { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid var(--wp-line); border-radius: 12px; background: var(--wp-sunken); font-size: .88rem; font-weight: 600; cursor: pointer; }
.wp-check input { margin-top: 3px; accent-color: var(--wp-brand); }
.wp-check small { color: var(--wp-muted); font-weight: 500; }

/* ---------- Responsive ---------- */
@media (max-width: 1200px) {
    .wp-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .wp-stat--wide { grid-column: 1 / -1; }
}
@media (max-width: 800px) {
    .wp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .wp-head h1 { font-size: 1.3rem; }
}
@media (max-width: 520px) {
    .wp-form-grid { grid-template-columns: minmax(0, 1fr); }
    .wp-stats { gap: 10px; }
    .wp-stat { padding: 12px; gap: 10px; }
}
@media (prefers-reduced-motion: reduce) {
    .wp-chevron, .wp-cell-edit-btn { transition: none; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    /* ---- Legend reference search ---- */
    var legendSearch = document.getElementById('wp-legend-search');
    if (legendSearch) {
        var legendItems = Array.prototype.slice.call(document.querySelectorAll('[data-legend-item]'));
        var legendNone = document.querySelector('[data-legend-none]');
        legendSearch.addEventListener('input', function () {
            var term = legendSearch.value.trim().toLowerCase();
            var shown = 0;
            legendItems.forEach(function (item) {
                var match = term === '' || item.getAttribute('data-search').indexOf(term) !== -1;
                item.hidden = ! match;
                if (match) { shown++; }
            });
            if (legendNone) { legendNone.hidden = shown !== 0; }
        });
    }

    /* ---- Edit Task modal: load the day's tasks, let the admin pick one if there are several ---- */
    var form = document.getElementById('editTaskForm');
    var pickerWrap = document.getElementById('edit_task_picker_wrap');
    var taskPicker = document.getElementById('edit_task_picker');
    var tasksForDateUrl = @json(route('admin.daily-work-plan.tasks-for-date'));
    var loadedTasks = [];

    var statusLabels = {
        pending: 'Pending', completed: 'Completed', cancelled: 'Cancelled',
        on_hold: 'On hold', returned_for_revision: 'Returned for revision'
    };

    function fillTask(task) {
        if (! form || ! task) { return; }
        form.action = form.dataset.updateUrl.replace('__TASK__', encodeURIComponent(task.id));
        document.getElementById('edit_task_id').value = task.id;
        document.getElementById('edit_task_code').value = task.legend_code || '';
        document.getElementById('edit_custom_title').value = task.custom_title || '';
        document.getElementById('edit_description').value = task.description || '';
        document.getElementById('edit_success_criteria').value = task.success_criteria || '';
        document.getElementById('edit_status').value = task.status || 'pending';
        document.getElementById('edit_client_name').value = task.client_name || '';
        document.getElementById('edit_work_date').value = task.work_date || '';
        document.getElementById('edit_change_reason').value = '';
    }

    document.addEventListener('show.bs.modal', function (event) {
        if (! event.target || event.target.id !== 'editTaskModal') { return; }
        var btn = event.relatedTarget;
        if (! btn || ! form) { return; }

        fetch(tasksForDateUrl + '?user_id=' + encodeURIComponent(btn.dataset.userId) + '&date=' + encodeURIComponent(btn.dataset.date))
            .then(function (response) {
                if (! response.ok) { throw new Error('Unable to load tasks for this date.'); }
                return response.json();
            })
            .then(function (data) {
                loadedTasks = data.tasks || [];

                taskPicker.innerHTML = '';
                loadedTasks.forEach(function (task, index) {
                    var option = document.createElement('option');
                    option.value = String(index);
                    option.textContent = (task.legend_code || task.custom_title || 'Task') + ' — ' + (statusLabels[task.status] || task.status || 'Pending');
                    taskPicker.appendChild(option);
                });

                pickerWrap.hidden = loadedTasks.length < 2;
                if (loadedTasks.length) { fillTask(loadedTasks[0]); }
            })
            .catch(function (error) {
                console.error(error);
                window.alert('Unable to load the work plan task. Please try again.');
            });
    });

    if (taskPicker) {
        taskPicker.addEventListener('change', function () {
            fillTask(loadedTasks[parseInt(taskPicker.value, 10)]);
        });
    }

    /* ---- "+N more" chips and the code picker popover ---- */
    function closePickers(except) {
        document.querySelectorAll('[data-wp-picker]:not([hidden])').forEach(function (picker) {
            if (picker === except) { return; }
            picker.hidden = true;
            var toggle = picker.closest('.wp-cell').querySelector('[data-wp-picker-toggle]');
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
        });
    }

    function placePicker(picker, anchor) {
        var rect = anchor.getBoundingClientRect();
        var width = 280;
        var left = Math.min(Math.max(8, rect.left + rect.width / 2 - width / 2), window.innerWidth - width - 8);
        picker.style.left = left + 'px';
        picker.style.top = (rect.bottom + 6) + 'px';

        // Flip above the button when there isn't room below.
        var height = picker.offsetHeight;
        if (rect.bottom + 6 + height > window.innerHeight - 8 && rect.top - 6 - height > 8) {
            picker.style.top = (rect.top - 6 - height) + 'px';
        }
    }

    document.addEventListener('click', function (event) {
        var moreToggle = event.target.closest('[data-wp-more-toggle]');
        if (moreToggle) {
            var list = moreToggle.closest('[data-wp-chips]');
            var expanded = list.classList.toggle('is-expanded');
            moreToggle.textContent = expanded ? moreToggle.dataset.lessLabel : moreToggle.dataset.moreLabel;
            return;
        }

        var pickerToggle = event.target.closest('[data-wp-picker-toggle]');
        if (pickerToggle) {
            var cell = pickerToggle.closest('.wp-cell');
            var picker = cell.querySelector('[data-wp-picker]');
            var wasOpen = ! picker.hidden;

            closePickers(picker);
            picker.hidden = wasOpen;
            pickerToggle.setAttribute('aria-expanded', wasOpen ? 'false' : 'true');

            if (! wasOpen) {
                placePicker(picker, pickerToggle);
                var search = picker.querySelector('[data-wp-search]');
                if (search) {
                    search.value = '';
                    picker.querySelectorAll('[data-wp-option]').forEach(function (option) { option.hidden = false; });
                    search.focus();
                }
            }
            return;
        }

        if (! event.target.closest('[data-wp-picker]')) { closePickers(null); }
    });

    document.addEventListener('input', function (event) {
        if (! event.target.matches('[data-wp-search]')) { return; }
        var picker = event.target.closest('[data-wp-picker]');
        var term = event.target.value.trim().toLowerCase();
        picker.querySelectorAll('[data-wp-option]').forEach(function (option) {
            option.hidden = term !== '' && (option.dataset.search || '').indexOf(term) === -1;
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closePickers(null); }
    });

    /* The popover is fixed-position, so close it if the page or grid scrolls out from under it. */
    window.addEventListener('scroll', function (event) {
        var inside = event.target && event.target.closest && event.target.closest('[data-wp-picker]');
        if (! inside) { closePickers(null); }
    }, true);
    window.addEventListener('resize', function () { closePickers(null); });
})();
</script>
@endpush