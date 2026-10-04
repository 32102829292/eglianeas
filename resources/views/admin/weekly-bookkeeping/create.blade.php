@extends('layouts.dashboard')

@section('title', 'Set Weekly Target — Weekly Bookkeeping — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Set Weekly Target</h1>
            <p>
                Select as many clients as you will handle this week, choose the task(s) for each, and assign a target day.
            </p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.weekly-bookkeeping.index') }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.weekly-bookkeeping.create') }}" class="week-picker">
        <label class="week-picker-label" for="week_start">Week</label>
        <select name="week_start" id="week_start" onchange="this.form.submit()">
            @foreach ($weeks as $key => $label)
                <option value="{{ $key }}" @selected($activeWeekStart === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="week-picker-range">{{ \Illuminate\Support\Carbon::parse($activeWeekStart)->format('F j, Y') }} – {{ \Illuminate\Support\Carbon::parse($activeWeekEnd)->format('F j, Y') }}</span>
    </form>

    {{-- Staff-centered assignment: name the staff member once, give the batch one
         target date and one task type, then tick every client that work covers.
         Writes the same target rows the client matrix below writes, so both stay
         in step and the week is still the organising unit. Built from the same
         .card / .client-row / .table classes as the rest of this screen. --}}
    <form method="POST" action="{{ route('admin.weekly-bookkeeping.bulk-assign') }}" id="assignForm" class="card">
        @csrf
        <input type="hidden" name="week_start" value="{{ $activeWeekStart }}">

        <div class="card-head">
            <h2 class="card-title">Assign Bookkeeping Tasks</h2>
        </div>
        <p class="form-hint">Assign a staff member, target date, task, and multiple clients.</p>

        <div class="form-grid three">
            <div>
                <label class="form-label" for="assignStaff">Staff Member</label>
                <select name="assigned_staff_id" id="assignStaff" class="form-control" required>
                    <option value="">Select staff member</option>
                    @foreach ($assignableStaff as $staffMember)
                        <option value="{{ $staffMember->id }}">{{ $staffMember->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="assignTargetDate">Target Date</label>
                <input type="date" name="target_date" id="assignTargetDate" class="form-control"
                       min="{{ $activeWeekStart }}" max="{{ $activeWeekEnd }}" required>
                <p class="form-hint">
                    Within this week:
                    {{ \Illuminate\Support\Carbon::parse($activeWeekStart)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($activeWeekEnd)->format('M j, Y') }}
                </p>
            </div>

            <div>
                <label class="form-label" for="assignTaskType">Task Type</label>
                <select name="task_type" id="assignTaskType" class="form-control" required>
                    @foreach ($taskTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="assign-clients">
            <div class="target-card-head">
                <h3 class="card-title">Select Clients</h3>
                <span class="selected-count-wrap">
                    <b id="assignSelectedCount" class="selected-count">0</b>
                    <span class="selected-count-label">clients selected</span>
                </span>
            </div>

            <div class="client-selector-tools">
                <input type="search" id="assignClientSearch" class="form-control" placeholder="Search clients&hellip;" aria-label="Search clients">
                <button type="button" class="btn btn-outline btn-sm" id="assignSelectAll">Select All</button>
                <button type="button" class="btn btn-outline btn-sm" id="assignClearSelection">Clear</button>
            </div>

            <div class="client-list" id="assignClientRows">
                @forelse ($clients as $client)
                    @php
                        $assignedTasks = $existingByClient->get($client->id, collect())->keys()->implode(',');
                    @endphp
                    <div class="client-row" data-name="{{ strtolower($client->name) }}" data-business="{{ strtolower($client->business_name ?? '') }}">
                        <label class="client-pick">
                            <input type="checkbox" class="assign-client-check"
                                   name="client_ids[]" value="{{ $client->id }}"
                                   data-assigned="{{ $assignedTasks }}"
                                   aria-label="Select {{ $client->name }}">
                        </label>

                        <div class="client-id">
                            @if ($client->profile_image_path)
                                <img src="{{ $client->photoUrl() }}" alt="" class="client-photo-sm">
                            @else
                                <span class="avatar">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                            @endif
                            <div class="client-id-text">
                                <span class="client-name">{{ $client->business_name ?: $client->name }}</span>
                                @if ($client->business_name)
                                    <span class="client-sub">{{ $client->name }}</span>
                                @endif
                                <span class="form-hint assign-existing" hidden></span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="client-empty">No clients are available yet.</div>
                @endforelse

                <div class="client-empty" id="assignClientEmpty" hidden>No clients match your search.</div>
            </div>
        </div>

        {{-- Compact confirmation of what will be written, mirroring the planner's
             own summary rows so the two read the same way. --}}
        <div class="staff-assign-panel assign-summary">
            <div class="summary-row">
                <span>Staff</span>
                <b id="assignSumStaff">—</b>
            </div>
            <div class="summary-row">
                <span>Target Date</span>
                <b id="assignSumDate">—</b>
            </div>
            <div class="summary-row">
                <span>Task</span>
                <b id="assignSumTask">—</b>
            </div>
            <div class="summary-row">
                <span>Clients</span>
                <b id="assignSumClients">0 selected</b>
            </div>
        </div>

        <div class="form-actions">
            @error('assigned_staff_id')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('task_type')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('target_date')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('client_ids')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('client_ids.*')
                <div class="form-error">{{ $message }}</div>
            @enderror

            <button type="submit" id="assignSubmitBtn" class="btn btn-primary" disabled>
                Add Assignment
            </button>
        </div>
    </form>

    {{-- What is already assigned this week, grouped the way the form above
         submits it: one staff member, one date, one task, many clients. --}}
    @php
        $assignRoster = $existingByClient
            ->map(fn ($byTask, $clientId) => $byTask->flatten()->map(fn ($target) => [
                'staff' => $target->assignedStaffDisplayName(),
                'date' => $target->target_date,
                'task' => $target->task_type,
                'status' => $target->effectiveStatus(),
                'status_label' => $target->effectiveStatusLabel(),
                'client' => $clients->firstWhere('id', $clientId)?->business_name
                    ?: $clients->firstWhere('id', $clientId)?->name
                    ?: 'Client #'.$clientId,
            ]))
            ->flatten(1)
            ->groupBy(fn ($row) => $row['staff'].'|'.($row['date']?->format('Y-m-d') ?? '').'|'.$row['task'])
            ->map(fn ($group) => [
                'staff' => $group->first()['staff'],
                'date' => $group->first()['date'],
                'task' => $group->first()['task'],
                'task_label' => $taskTypes[$group->first()['task']] ?? $group->first()['task'],
                'clients' => $group->pluck('client')->unique()->values(),
                'statuses' => $group->pluck('status')->unique()->values(),
                'status_label' => $group->pluck('status_label')->unique()->values(),
            ])
            ->sortBy(fn ($row) => ($row['date']?->format('Y-m-d') ?? '9999').$row['staff'])
            ->values();

        $assignPillClass = fn (string $status) => match ($status) {
            'completed', 'on_time', 'returned', 'paid' => 'completed',
            'in_progress' => 'in_progress',
            'unreturned', 'unpaid', 'missed' => 'attention',
            default => 'pending',
        };
    @endphp

    @if ($assignRoster->isNotEmpty())
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Current Assignments</h2>
                <span class="form-hint">{{ $assignRoster->count() }} batch{{ $assignRoster->count() === 1 ? '' : 'es' }} this week</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Target Date</th>
                            <th>Task</th>
                            <th>Clients</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignRoster as $rosterRow)
                            <tr>
                                <td class="fw-semibold">{{ $rosterRow['staff'] }}</td>
                                <td>{{ $rosterRow['date']?->format('M j, Y') ?? 'No date' }}</td>
                                <td>{{ $rosterRow['task_label'] }}</td>
                                <td>{{ $rosterRow['clients']->join(', ') }}</td>
                                <td>
                                    @if ($rosterRow['statuses']->count() === 1)
                                        <span class="wk-pill wk-pill-{{ $assignPillClass($rosterRow['statuses']->first()) }}">
                                            {{ $rosterRow['status_label']->first() }}
                                        </span>
                                    @else
                                        {{ $rosterRow['status_label']->join(', ') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- The client matrix is the original client-centered planner. It posts the
         same targets and stays on the page, but folded away by default so the
         staff-centered panel above is the one assignment section in view. --}}
    <details class="target-adv">
        <summary class="target-adv-head">
            <span class="target-adv-caret" aria-hidden="true"></span>
            <span class="target-adv-name">Client-by-client planner</span>
            <span class="target-adv-count">Per-client target dates, tasks and staff</span>
        </summary>

    <form method="POST" action="{{ route('admin.weekly-bookkeeping.store') }}" id="targetForm">
        @csrf
        <input type="hidden" name="week_start" value="{{ $activeWeekStart }}">

        <div class="target-layout">
            <div class="card target-client-card">
                <div class="card-head target-card-head">
                    <h2 class="card-title">Select Clients</h2>
                    <div class="target-card-head-tools">
                        <label class="master-pick" for="selectAllToggle">
                            <input type="checkbox" id="selectAllToggle">
                            <span>All</span>
                        </label>
                        <span class="selected-count-wrap">
                            <b id="selectedCount" class="selected-count">0</b>
                            <span class="selected-count-label">selected</span>
                        </span>
                    </div>
                </div>

                <div class="client-selector-tools">
                    <input type="search" id="clientSearch" class="form-control" placeholder="Search client&hellip;" aria-label="Search client">
                    <select id="clientFilter" class="form-control" aria-label="Filter clients">
                        <option value="all">All clients</option>
                        <option value="selected">Selected only</option>
                        @if ($plan)
                            <option value="targeted">Already targeted this week</option>
                        @endif
                    </select>
                    <button type="button" class="btn btn-outline btn-sm" id="selectAllVisible">Select all visible</button>
                    <button type="button" class="btn btn-outline btn-sm" id="clearSelection">Clear selection</button>
                </div>

                <div class="client-list" id="clientRows">
                    @forelse ($clients as $client)
                        @php
                            $clientExisting = $existingByClient->get($client->id, collect());
                        @endphp
                        <div class="client-row" data-name="{{ strtolower($client->name) }}" data-business="{{ strtolower($client->business_name ?? '') }}">
                            <label class="client-pick">
                                <input type="checkbox" class="client-check"
                                       name="clients[]" value="{{ $client->id }}"
                                       data-client-id="{{ $client->id }}"
                                       aria-label="Select {{ $client->name }}"
                                       @checked($clientExisting->isNotEmpty())>
                            </label>

                            <div class="client-id">
                                @if ($client->profile_image_path)
                                    <img src="{{ $client->photoUrl() }}" alt="" class="client-photo-sm">
                                @else
                                    <span class="avatar">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                                @endif
                                <div class="client-id-text">
                                    <span class="client-name">{{ $client->business_name ?: $client->name }}</span>
                                    @if ($client->business_name)
                                        <span class="client-sub">{{ $client->name }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="client-target">
                                <span class="field-label">Target Date</span>
                                <input type="date" class="target-date"
                                       name="target_date[{{ $client->id }}]"
                                       min="{{ $activeWeekStart }}" max="{{ $activeWeekEnd }}"
                                       value="{{ $clientExisting->flatten()->first()?->target_date?->format('Y-m-d') ?? '' }}"
                                       aria-label="Target date for {{ $client->name }}">
                                {{-- One date here seeds the whole schedule. On the plan page this
                                     date becomes Pick-Up, and Record, Return and Payment follow one
                                     day apart unless someone edits them by hand. --}}
                                <small class="schedule-seed-hint">
                                    Seeds the schedule: Record +1, Return +2, Payment +3 days. Adjust any stage on the plan page.
                                </small>
                            </div>

                            <div class="client-tasks">
                                <span class="field-label">Target Tasks</span>
                                <div class="task-chips">
                                    @foreach ($taskTypes as $key => $label)
                                        @php
                                            $existingTask = $clientExisting->get($key)?->first();
                                            $checked = $existingTask !== null;
                                        @endphp
                                        {{-- Each task carries its own assignee, matching the
                                             workbook where a client can have different staff
                                             for Pick-Up, Record, Return and Billing. --}}
                                        <span class="task-unit" data-task-unit="{{ $key }}">
                                            <label class="task-chip">
                                                <input type="checkbox" class="task-check-input"
                                                       name="tasks[{{ $client->id }}][]" value="{{ $key }}"
                                                       data-client-id="{{ $client->id }}"
                                                       @checked($checked)>
                                                <span>{{ $label }}</span>
                                            </label>
                                            <select class="task-staff"
                                                    name="assignee[{{ $client->id }}][{{ $key }}]"
                                                    aria-label="Assigned staff for {{ $label }} — {{ $client->name }}"
                                                    @disabled(! $checked)>
                                                <option value="">—</option>
                                                @foreach ($assignableStaff as $staffMember)
                                                    <option value="{{ $staffMember->id }}"
                                                            @selected($existingTask && $existingTask->assigned_staff_id === $staffMember->id)>
                                                        {{ $staffMember->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="client-empty">No clients are available yet.</div>
                    @endforelse

                    <div class="client-empty" id="clientEmpty" hidden>No clients match your search or filter.</div>
                </div>
            </div>

            <aside class="card target-summary-card">
                <div class="card-head target-card-head">
                    <h2 class="card-title">Summary</h2>
                </div>
                <div class="target-summary">
                    <div class="summary-row">
                        <span>Week</span>
                        <b>{{ \Illuminate\Support\Carbon::parse($activeWeekStart)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($activeWeekEnd)->format('M j, Y') }}</b>
                    </div>
                    <div class="summary-row">
                        <span>Staff / Supervisor</span>
                        <b>{{ auth()->user()->name }}</b>
                    </div>
                    <div class="summary-row">
                        <span>Selected clients</span>
                        <b id="summaryClients">0</b>
                    </div>
                    <div class="summary-row">
                        <span>Selected tasks</span>
                        <b id="summaryTasks">0</b>
                    </div>
                    <div class="summary-note">
                        Targets lock once actual work starts, so you can keep adjusting them until then.
                    </div>
                    <button type="submit" id="saveTargetsBtn" class="btn btn-primary target-save" disabled>
                        Save Weekly Targets
                    </button>
                    @error('clients')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                    @error('week_start')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                    @error('tasks')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </aside>
        </div>
    </form>
    </details>
@endsection

@push('styles')
<style>
/* Week picker */
.week-picker {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.week-picker-label {
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted-text);
}
.week-picker select {
    padding: 7px 10px;
    font-size: var(--text-md);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    min-width: 190px;
}
.week-picker-range { font-size: var(--text-sm); color: var(--muted-text); }

/* Folded client matrix. Same disclosure the tracker already uses for its client
   cards: white surface, subtle border, rotating caret. */
.target-adv {
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    overflow: hidden;
}
.target-adv[open] { box-shadow: var(--shadow-sm); }
.target-adv-head {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 12px 14px;
    min-height: 48px;
    cursor: pointer;
    list-style: none;
}
.target-adv-head::-webkit-details-marker { display: none; }
.target-adv-head::marker { content: ''; }
.target-adv-caret {
    width: 0;
    height: 0;
    flex: 0 0 auto;
    border-left: 6px solid var(--muted-text);
    border-top: 5px solid transparent;
    border-bottom: 5px solid transparent;
    transition: transform var(--transition-fast);
}
.target-adv[open] .target-adv-caret { transform: rotate(90deg); }
.target-adv-name {
    font-family: var(--font-head);
    font-size: var(--text-base);
    color: var(--navy);
}
.target-adv-count {
    margin-left: auto;
    font-size: var(--text-xs);
    color: var(--muted-text);
    white-space: nowrap;
}
.target-adv[open] .target-adv-head { border-bottom: 1px solid var(--border-subtle); }
.target-adv .target-layout { padding: 14px; }

/* Layout: client list gets the width, summary stays narrow */
.target-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 272px;
    gap: 14px;
    align-items: start;
}
@media (max-width: 980px) {
    .target-layout { grid-template-columns: 1fr; }
}

.target-client-card { padding: 0; overflow: hidden; }

.target-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-subtle);
    margin: 0;
}
.target-card-head .card-title { font-size: var(--text-lg); margin: 0; }
.target-card-head-tools { display: inline-flex; align-items: center; gap: 14px; }

.master-pick {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: var(--text-sm);
    color: var(--muted-text);
    cursor: pointer;
    user-select: none;
}
.master-pick input {
    width: 14px;
    height: 14px;
    margin: 0;
    accent-color: var(--sky-deep);
    cursor: pointer;
}

.selected-count-wrap { display: inline-flex; align-items: baseline; gap: 5px; }
.selected-count { font-size: 17px; font-weight: 700; color: var(--sky-deep); }
.selected-count-label { font-size: var(--text-sm); color: var(--muted-text); }

/* Toolbar */
.client-selector-tools {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    padding: 9px 14px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-sunken);
}
.client-selector-tools .form-control {
    max-width: 230px;
    height: 32px;
    font-size: var(--text-md);
    border-color: var(--border-subtle);
    border-radius: 7px;
    background: var(--surface);
}
.client-selector-tools .btn { height: 32px; font-size: var(--text-md); border-radius: 7px; }

/* Compact client rows */
.client-list { display: flex; flex-direction: column; }

.client-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 14px;
    border-bottom: 1px solid var(--border-light);
    background: var(--surface);
    transition: background var(--transition-fast);
}
.client-row:last-of-type { border-bottom: 0; }
.client-row:hover { background: var(--surface-raised); }
.client-row.is-selected { background: var(--sky-light); }
.client-row.is-selected:hover { background: #E1EFFA; }
.client-row.no-task { box-shadow: inset 2px 0 0 var(--warning); }

.client-pick { flex: 0 0 auto; display: inline-flex; cursor: pointer; }
.client-pick input {
    width: 15px;
    height: 15px;
    margin: 0;
    accent-color: var(--sky-deep);
    cursor: pointer;
}

.client-id {
    flex: 1 1 210px;
    min-width: 165px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.client-id .client-photo-sm,
.client-id .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
    background: var(--sky-soft);
    color: var(--sky-deep);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.client-id-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.client-name {
    font-size: var(--text-md);
    font-weight: 600;
    color: var(--navy);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.client-sub {
    font-size: var(--text-xs);
    color: var(--muted-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.client-target {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 7px;
}
.client-tasks {
    flex: 1 1 320px;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.field-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted-text);
    white-space: nowrap;
}

.target-date {
    width: 132px;
    height: 30px;
    padding: 4px 8px;
    font-size: var(--text-sm);
    color: var(--text);
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: 6px;
}

.schedule-seed-hint {
    display: block;
    margin-top: 3px;
    max-width: 190px;
    font-size: 11px;
    line-height: 1.35;
    color: var(--muted);
}
.target-date:focus,
.client-selector-tools .form-control:focus {
    outline: none;
    border-color: var(--sky-deep);
    box-shadow: 0 0 0 3px var(--focus-ring-color);
}
.target-date:disabled {
    background: var(--surface-sunken);
    color: var(--muted-text);
    cursor: not-allowed;
}

/* Compact task chips */
.task-chips { display: flex; flex-wrap: wrap; gap: 4px; min-width: 0; }

/* Chip + its own staff selector stay together as one unit */
.task-unit {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 3px 2px 0;
    border: 1px solid transparent;
    border-radius: 7px;
}
.task-unit:has(input:checked) { background: var(--sky-soft); border-color: #BFDBFE; }

.task-staff {
    min-height: 21px;
    max-width: 108px;
    padding: 0 4px;
    font-size: 10.5px;
    line-height: 1.2;
    color: var(--navy);
    background: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: 4px;
    cursor: pointer;
}
.task-staff:disabled { opacity: .4; cursor: not-allowed; background: transparent; }
.task-staff:not(:disabled):focus { outline: 2px solid var(--sky); outline-offset: 1px; }

.task-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 25px;
    padding: 3px 8px;
    font-size: var(--text-xs);
    line-height: 1.25;
    color: var(--muted-text);
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
}
.task-chip:hover { background: var(--surface-raised); border-color: #CBD5E1; }
.task-chip input {
    width: 12px;
    height: 12px;
    margin: 0;
    accent-color: var(--sky-deep);
    cursor: pointer;
}
.task-chip:has(input:checked) {
    background: var(--sky-soft);
    border-color: #A8D8F5;
    color: #1C6FA8;
    font-weight: 600;
}
.task-chip:has(input:disabled) { opacity: .5; cursor: not-allowed; }

.client-empty {
    padding: 18px 14px;
    text-align: center;
    font-size: var(--text-md);
    color: var(--muted-text);
}

/* Summary */
.target-summary-card { position: sticky; top: 14px; padding: 0; }
.target-summary-card .target-card-head { border-bottom: 1px solid var(--border-subtle); }
.target-summary { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px 14px; }
.summary-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid var(--border-light);
    font-size: var(--text-md);
}
.summary-row:last-of-type { border-bottom: 0; }
.summary-row span { color: var(--muted-text); }
.summary-row b { color: var(--navy); text-align: right; }
.summary-note {
    margin: 10px 0 12px;
    padding: 8px 10px;
    font-size: var(--text-xs);
    line-height: 1.45;
    color: var(--muted-text);
    background: var(--surface-sunken);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
}
.target-save {
    width: 100%;
    min-height: 42px;
    font-size: var(--text-base);
    font-weight: 600;
    border-radius: 9px;
}
.target-summary .form-error { margin-top: 8px; font-size: var(--text-sm); color: var(--danger); }

/* ---- Staff-centered assignment -------------------------------------
   The panel above reuses .card, .form-grid, .client-row, .client-selector-tools,
   .summary-row, .form-actions and .table wholesale, so only the white client box
   and the vertical rhythm between the stacked pieces are declared here. */
.assign-clients {
    margin-top: 16px;
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    overflow: hidden;
}
.assign-summary { margin-top: 14px; }
.assign-summary .summary-row:last-of-type { border-bottom: 0; }
.assign-existing { font-style: normal; }

/* Mobile: client list -> target date -> target tasks */
@media (max-width: 980px) {
    .target-summary-card { position: static; }
    .client-row {
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 7px 10px;
        padding: 10px 12px;
    }
    .client-pick { padding-top: 3px; }
    .client-id { flex: 1 1 100%; }
    .client-target,
    .client-tasks { flex: 1 1 100%; align-items: flex-start; }
    .client-target .field-label,
    .client-tasks .field-label { width: 78px; padding-top: 6px; }
    .target-date { flex: 1 1 auto; width: auto; }
    .task-chips { flex: 1 1 auto; }
    .client-selector-tools .form-control { max-width: none; flex: 1 1 150px; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---- Staff-centered assignment -------------------------------------
       Owns its own form. All it adds on top of the planner's usual behaviour is
       the live confirmation strip and the note that a client already holds the
       chosen task, since one client + one task type per week is the existing
       rule and a repeat save moves that row instead of adding another. */
    var assignForm = document.getElementById('assignForm');

    if (assignForm) {
        var assignRows = Array.prototype.slice.call(assignForm.querySelectorAll('.client-row'));
        var assignChecks = Array.prototype.slice.call(assignForm.querySelectorAll('.assign-client-check'));
        var assignSearch = document.getElementById('assignClientSearch');
        var assignSelectAll = document.getElementById('assignSelectAll');
        var assignClear = document.getElementById('assignClearSelection');
        var assignCount = document.getElementById('assignSelectedCount');
        var assignSubmit = document.getElementById('assignSubmitBtn');
        var assignStaff = document.getElementById('assignStaff');
        var assignDate = document.getElementById('assignTargetDate');
        var assignTaskType = document.getElementById('assignTaskType');
        var assignEmpty = document.getElementById('assignClientEmpty');
        var assignSumStaff = document.getElementById('assignSumStaff');
        var assignSumDate = document.getElementById('assignSumDate');
        var assignSumTask = document.getElementById('assignSumTask');
        var assignSumClients = document.getElementById('assignSumClients');

        var ASSIGN_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];

        function assignSelectedCount() {
            return assignChecks.filter(function (check) { return check.checked; }).length;
        }

        /* Reads straight off the selected option so the summary can never drift
           from what the form will actually post. */
        function assignOptionText(field) {
            if (!field || field.selectedIndex < 0) {
                return '';
            }

            var option = field.options[field.selectedIndex];
            return option.value === '' ? '' : option.textContent.trim();
        }

        function assignFormatDate(iso) {
            var parts = String(iso).split('-');

            if (parts.length !== 3) {
                return iso;
            }

            return Number(parts[2]) + ' ' + ASSIGN_MONTHS[Number(parts[1]) - 1] + ', ' + parts[0];
        }

        function refreshAssignSummary() {
            var count = assignSelectedCount();

            assignCount.textContent = String(count);
            assignSumStaff.textContent = assignOptionText(assignStaff) || '—';
            assignSumDate.textContent = assignDate.value ? assignFormatDate(assignDate.value) : '—';
            assignSumTask.textContent = assignOptionText(assignTaskType) || '—';
            assignSumClients.textContent = count + ' selected';

            assignSubmit.disabled = count === 0
                || assignStaff.value === ''
                || assignDate.value === '';
        }

        function refreshAssignRows() {
            assignChecks.forEach(function (check) {
                check.closest('.client-row').classList.toggle('is-selected', check.checked);
            });

            refreshAssignSummary();
        }

        function refreshExistingHints() {
            var task = assignTaskType ? assignTaskType.value : '';

            assignChecks.forEach(function (check) {
                var note = check.closest('.client-row').querySelector('.assign-existing');
                var assigned = (check.getAttribute('data-assigned') || '').split(',').filter(Boolean);

                if (task && assigned.indexOf(task) !== -1) {
                    note.textContent = 'Already has this task — saving will update it';
                    note.hidden = false;
                } else {
                    note.hidden = true;
                }
            });
        }

        function applyAssignFilter() {
            var needle = (assignSearch.value || '').trim().toLowerCase();
            var visible = 0;

            assignRows.forEach(function (row) {
                var haystack = (row.getAttribute('data-name') || '') + ' ' + (row.getAttribute('data-business') || '');
                var matches = needle === '' || haystack.indexOf(needle) !== -1;

                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visible++;
                }
            });

            assignEmpty.hidden = visible !== 0;
        }

        assignSearch.addEventListener('input', applyAssignFilter);

        assignSelectAll.addEventListener('click', function () {
            assignRows.forEach(function (row) {
                if (row.style.display !== 'none') {
                    row.querySelector('.assign-client-check').checked = true;
                }
            });
            refreshAssignRows();
        });

        assignClear.addEventListener('click', function () {
            assignChecks.forEach(function (check) { check.checked = false; });
            refreshAssignRows();
        });

        assignChecks.forEach(function (check) {
            check.addEventListener('change', refreshAssignRows);
        });

        [assignStaff, assignDate, assignTaskType].forEach(function (field) {
            field.addEventListener('change', refreshAssignSummary);
            field.addEventListener('input', refreshAssignSummary);
        });

        if (assignTaskType) {
            assignTaskType.addEventListener('change', refreshExistingHints);
        }

        /* Submitting an empty selection would only bounce off the server, so the
           button also guards itself for keyboards that submit on Enter. */
        assignForm.addEventListener('submit', function (event) {
            if (assignSelectedCount() === 0) {
                event.preventDefault();
            }
        });

        refreshExistingHints();
        refreshAssignRows();
    }
})();
</script>

<script>
(function () {
    'use strict';

    /* Scoped to the matrix list: the assignment card above reuses .client-row
       for its own client picker, so an unscoped query would mix the two. */
    var rows = Array.prototype.slice.call(document.querySelectorAll('#clientRows .client-row'));
    var form = document.getElementById('targetForm');
    var searchInput = document.getElementById('clientSearch');
    var filterSelect = document.getElementById('clientFilter');
    var selectAllToggle = document.getElementById('selectAllToggle');
    var emptyNotice = document.getElementById('clientEmpty');

    function isVisible(row) {
        return row.style.display !== 'none';
    }

    function selectedCount() {
        return rows.filter(function (row) {
            return row.querySelector('.client-check').checked;
        }).length;
    }

    function taskCount() {
        return rows.reduce(function (acc, row) {
            return acc + row.querySelectorAll('.task-check-input:checked').length;
        }, 0);
    }

    function updateSummary() {
        document.getElementById('selectedCount').textContent = selectedCount();
        document.getElementById('summaryClients').textContent = selectedCount();
        document.getElementById('summaryTasks').textContent = taskCount();
        document.getElementById('saveTargetsBtn').disabled = selectedCount() === 0;
    }

    function visibleRows() {
        return rows.filter(isVisible);
    }

    function applyFilters() {
        var term = searchInput.value.trim().toLowerCase();
        var filter = filterSelect.value;

        rows.forEach(function (row) {
            var name = row.getAttribute('data-name');
            var business = row.getAttribute('data-business');
            var checked = row.querySelector('.client-check').checked;
            var matchesSearch = term === '' || name.indexOf(term) !== -1 || business.indexOf(term) !== -1;
            var matchesFilter = filter === 'all' || (filter === 'selected' && checked);
            if (filter === 'targeted') {
                matchesFilter = row.querySelectorAll('.task-check-input:checked').length > 0;
            }
            row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });

        if (emptyNotice) {
            emptyNotice.hidden = visibleRows().length > 0;
        }

        updateSelectAllToggle();
    }

    function updateSelectAllToggle() {
        var visible = visibleRows();
        var checkedVisible = visible.filter(function (row) {
            return row.querySelector('.client-check').checked;
        });
        selectAllToggle.checked = visible.length > 0 && checkedVisible.length === visible.length;
        selectAllToggle.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visible.length;
    }

    // Per-client task/date/staff enablement
    function syncRow(row) {
        var check = row.querySelector('.client-check');
        var date = row.querySelector('.target-date');
        var taskInputs = Array.prototype.slice.call(row.querySelectorAll('.task-check-input'));

        taskInputs.forEach(function (input) {
            input.disabled = !check.checked;
            if (!check.checked) input.checked = false;
        });
        date.disabled = !check.checked;
        if (!check.checked) date.value = '';

        // A staff selector is only meaningful for a task that is actually
        // targeted this week, so it enables with its own task chip.
        Array.prototype.slice.call(row.querySelectorAll('.task-unit')).forEach(function (unit) {
            var input = unit.querySelector('.task-check-input');
            var staff = unit.querySelector('.task-staff');
            if (!staff) return;
            staff.disabled = !input.checked || input.disabled;
            if (staff.disabled) staff.value = '';
        });

        var anyTask = taskInputs.some(function (input) { return input.checked; });
        row.classList.toggle('is-selected', check.checked);
        row.classList.toggle('no-task', check.checked && !anyTask);

        updateSummary();
        updateSelectAllToggle();
    }

    rows.forEach(function (row) {
        var check = row.querySelector('.client-check');
        check.addEventListener('change', function () { syncRow(row); });
        Array.prototype.slice.call(row.querySelectorAll('.task-check-input')).forEach(function (input) {
            input.addEventListener('change', function () { syncRow(row); });
        });
    });

    selectAllToggle.addEventListener('change', function () {
        var check = this.checked;
        visibleRows().forEach(function (row) {
            row.querySelector('.client-check').checked = check;
            syncRow(row);
        });
    });

    document.getElementById('selectAllVisible').addEventListener('click', function () {
        visibleRows().forEach(function (row) {
            row.querySelector('.client-check').checked = true;
            syncRow(row);
        });
    });

    document.getElementById('clearSelection').addEventListener('click', function () {
        rows.forEach(function (row) {
            row.querySelector('.client-check').checked = false;
            syncRow(row);
        });
    });

    searchInput.addEventListener('input', applyFilters);
    filterSelect.addEventListener('change', applyFilters);

    // Prevent submitting a client with no task selected.
    form.addEventListener('submit', function (e) {
        var anyInvalid = rows.some(function (row) {
            var check = row.querySelector('.client-check');
            if (!check.checked) return false;
            return row.querySelectorAll('.task-check-input:checked').length === 0;
        });
        if (anyInvalid) {
            e.preventDefault();
            alert('Each selected client needs at least one target task.');
        }
    });

    rows.forEach(function (row) { syncRow(row); });
    applyFilters();
    updateSummary();
})();
</script>
@endpush
