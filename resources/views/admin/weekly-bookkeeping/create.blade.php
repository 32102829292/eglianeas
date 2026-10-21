@extends('layouts.dashboard')

@section('title', 'Set Weekly Target — Weekly Bookkeeping — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Set Weekly Target</h1>
            <p>
                Assign bookkeeping tasks to a staff member for this week: pick a staff member, a target
                date and a task, then tick every client that work covers.
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

    {{-- Staff-centered assignment: the same workflow as Set Monthly Target. Name
         one staff member, give the batch one target date and one task type, then
         tick every client that work covers. Each selected client gets its own
         target row inside this week, so every client keeps an independent
         schedule. --}}
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
                    Within this week: {{ \Illuminate\Support\Carbon::parse($activeWeekStart)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($activeWeekEnd)->format('M j, Y') }}
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
            <div class="bk-t-card-head">
                <h3 class="card-title">Select Clients</h3>
                <span class="bk-t-selected-wrap">
                    <b id="assignSelectedCount" class="bk-t-selected">0</b>
                    <span class="bk-t-selected-label">clients selected</span>
                </span>
            </div>

            <div class="bk-t-tools">
                <input type="search" id="assignClientSearch" class="form-control" placeholder="Search clients&hellip;" aria-label="Search clients">
                <button type="button" class="btn btn-outline btn-sm" id="assignSelectAll">Select All</button>
                <button type="button" class="btn btn-outline btn-sm" id="assignClearSelection">Clear</button>
            </div>

            <div class="bk-t-list" id="assignClientRows">
                @forelse ($clients as $client)
                    @php
                        $assignedTasks = $existingByClient->get($client->id, collect())->keys()->implode(',');
                    @endphp
                    <div class="bk-t-row" data-name="{{ strtolower($client->name) }}" data-business="{{ strtolower($client->business_name ?? '') }}">
                        <label class="bk-t-pick">
                            <input type="checkbox" class="assign-client-check"
                                   name="client_ids[]" value="{{ $client->id }}"
                                   data-assigned="{{ $assignedTasks }}"
                                   aria-label="Select {{ $client->name }}">
                        </label>

                        <div class="bk-t-id">
                            @if ($client->profile_image_path)
                                <img src="{{ $client->photoUrl() }}" alt="" class="bk-t-photo">
                            @else
                                <span class="bk-t-avatar">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                            @endif
                            <div class="bk-t-id-text">
                                <span class="bk-t-id-name">{{ $client->business_name ?: $client->name }}</span>
                                @if ($client->business_name)
                                    <span class="bk-t-id-sub">{{ $client->name }}</span>
                                @endif
                                <span class="form-hint assign-existing" hidden></span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bk-t-empty">No clients are available yet.</div>
                @endforelse

                <div class="bk-t-empty" id="assignClientEmpty" hidden>No clients match your search.</div>
            </div>
        </div>

        {{-- Compact confirmation of what will be written, mirroring the planner's
             own summary rows so the two read the same way. --}}
        <div class="staff-assign-panel assign-summary">
            <div class="bk-t-summary-row">
                <span>Staff</span>
                <b id="assignSumStaff">—</b>
            </div>
            <div class="bk-t-summary-row">
                <span>Target Date</span>
                <b id="assignSumDate">—</b>
            </div>
            <div class="bk-t-summary-row">
                <span>Task</span>
                <b id="assignSumTask">—</b>
            </div>
            <div class="bk-t-summary-row">
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

    {{-- What is already assigned this week, grouped by staff member, target
         date and task type. --}}
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
                <span class="form-hint">{{ $assignRoster->count() }} group{{ $assignRoster->count() === 1 ? '' : 's' }} this week</span>
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

/* ---- Staff-centered assignment -------------------------------------
   Same interaction model as Set Monthly Target: one staff member, one
   target date, one task type, many clients. Reuses the shared .card,
   .form-grid, .form-control, .form-actions and .table classes, so only the
   client box, its compact rows and the confirmation strip are declared here. */
.assign-clients {
    margin-top: 16px;
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    overflow: hidden;
}
.assign-summary { margin-top: 14px; }
.assign-summary .bk-t-summary-row:last-of-type { border-bottom: 0; }
.assign-existing { font-style: normal; }

.bk-t-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-subtle);
    margin: 0;
}
.bk-t-card-head .card-title { font-size: var(--text-lg); margin: 0; }

.bk-t-selected-wrap { display: inline-flex; align-items: baseline; gap: 5px; }
.bk-t-selected { font-size: 17px; font-weight: 700; color: var(--sky-deep); }
.bk-t-selected-label { font-size: var(--text-sm); color: var(--muted-text); }

.bk-t-tools {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    padding: 9px 14px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-sunken);
}
.bk-t-tools .form-control {
    max-width: 230px;
    height: 32px;
    font-size: var(--text-md);
    border-color: var(--border-subtle);
    border-radius: 7px;
    background: var(--surface);
}
.bk-t-tools .btn { height: 32px; font-size: var(--text-md); border-radius: 7px; }

.bk-t-list { display: flex; flex-direction: column; }

.bk-t-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 14px;
    border-bottom: 1px solid var(--border-light);
    background: var(--surface);
    transition: background var(--transition-fast);
}
.bk-t-row:last-of-type { border-bottom: 0; }
.bk-t-row:hover { background: var(--surface-raised); }
.bk-t-row.is-selected { background: var(--sky-light); }
.bk-t-row.is-selected:hover { background: #E1EFFA; }

.bk-t-pick { flex: 0 0 auto; display: inline-flex; cursor: pointer; }
.bk-t-pick input {
    width: 15px;
    height: 15px;
    margin: 0;
    accent-color: var(--sky-deep);
    cursor: pointer;
}

.bk-t-id {
    flex: 1 1 210px;
    min-width: 165px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.bk-t-id .bk-t-photo,
.bk-t-id .bk-t-avatar {
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
.bk-t-id-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.bk-t-id-name {
    font-size: var(--text-md);
    font-weight: 600;
    color: var(--navy);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bk-t-id-sub {
    font-size: var(--text-xs);
    color: var(--muted-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bk-t-empty {
    padding: 18px 14px;
    text-align: center;
    font-size: var(--text-md);
    color: var(--muted-text);
}

/* Confirmation strip shown above the Save button. */
.staff-assign-panel { display: flex; flex-direction: column; gap: 2px; }
.bk-t-summary-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid var(--border-light);
    font-size: var(--text-md);
}
.bk-t-summary-row span { color: var(--muted-text); }
.bk-t-summary-row b { color: var(--navy); text-align: right; }

@media (max-width: 980px) {
    .bk-t-row {
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 7px 10px;
        padding: 10px 12px;
    }
    .bk-t-tools .form-control { max-width: none; flex: 1 1 150px; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---- Staff-centered assignment -------------------------------------
       Same behaviour as Set Monthly Target's panel: own form, its own client
       picker, a live confirmation strip, and the note that a client already
       holds the chosen task, since one client + one task type per week is the
       existing rule and a repeat save moves that row instead of adding one. */
    var assignForm = document.getElementById('assignForm');

    if (assignForm) {
        var assignRows = Array.prototype.slice.call(assignForm.querySelectorAll('.bk-t-row'));
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
                check.closest('.bk-t-row').classList.toggle('is-selected', check.checked);
            });

            refreshAssignSummary();
        }

        function refreshExistingHints() {
            var task = assignTaskType ? assignTaskType.value : '';

            assignChecks.forEach(function (check) {
                var note = check.closest('.bk-t-row').querySelector('.assign-existing');
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
@endpush
