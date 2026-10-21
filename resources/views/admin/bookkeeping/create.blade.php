@extends('layouts.dashboard')

@section('title', 'Set '.$config['noun_title'].' — '.$config['title'].' — Egliane Accounting Services')

@section('content')
    @php
        $prefix = $config['route_prefix'];
        $startField = $config['start_field'];
        $periodStart = $period->startDate();
        $periodEnd = $period->endDate();
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>Set {{ $config['noun_title'] }}</h1>
            <p>
                Tick the clients you will handle this {{ strtolower($config['unit']) }}, set their tasks and target days,
                then save. Newly selected clients start with Pick-Up, Return and Billing ticked.
            </p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route($prefix.'.index', [$config['query_key'] => $activePeriodKey]) }}" class="btn btn-outline btn-sm">Back to tracker</a>
        </div>
    </div>

    {{-- Switching period reloads the form for that period, showing what is already planned. --}}
    <form method="GET" action="{{ route($prefix.'.create') }}" class="bk-t-picker">
        <label class="bk-t-picker-label" for="{{ $config['query_key'] }}">{{ $config['unit'] }}</label>
        <select name="{{ $config['query_key'] }}" id="{{ $config['query_key'] }}" onchange="this.form.submit()">
            @foreach ($periodOptions as $key => $label)
                <option value="{{ $key }}" @selected($activePeriodKey === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="bk-t-picker-range">{{ $period->rangeLabel() }}</span>
    </form>

    {{-- Staff-centered assignment: name the staff member once, give the batch one
         target date and one task type, then tick every client that work covers.
         Writes the same target rows the client matrix below writes, so both stay
in step and the month/quarter is still the organising unit. Built from the
     same .card / .bk-t-row / .bk-t-tools classes as the rest of this screen. --}}
    <form method="POST" action="{{ route($prefix.'.bulk-assign') }}" id="assignForm" class="card">
        @csrf
        <input type="hidden" name="{{ $startField }}" value="{{ $periodStart }}">

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
                       min="{{ $periodStart }}" max="{{ $periodEnd }}" required>
                <p class="form-hint">
                    Within this {{ strtolower($config['unit']) }}: {{ $period->shortRangeLabel() }}
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

    {{-- What is already assigned this period, grouped the way the form above
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
                <span class="form-hint">{{ $assignRoster->count() }} group{{ $assignRoster->count() === 1 ? '' : 's' }} this {{ strtolower($config['unit']) }}</span>
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
    /* Period picker */
    .bk-t-picker {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .bk-t-picker-label {
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--muted-text);
    }
    .bk-t-picker select {
        padding: 7px 10px;
        font-size: var(--text-md);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        background: var(--surface);
        color: var(--text);
        min-width: 190px;
    }
    .bk-t-picker-range { font-size: var(--text-sm); color: var(--muted-text); }

    /* Planner styles removed - client-by-client planner no longer used */

    /* Layout: client list gets the width, summary stays narrow */
    .bk-t-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 272px;
        gap: 14px;
        align-items: start;
    }
    @media (max-width: 980px) {
        .bk-t-layout { grid-template-columns: 1fr; }
    }

    .bk-t-client-card { padding: 0; overflow: hidden; }

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
    .bk-t-card-head-tools { display: inline-flex; align-items: center; gap: 14px; }

    .bk-t-master-pick {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: var(--text-sm);
        color: var(--muted-text);
        cursor: pointer;
        user-select: none;
    }
    .bk-t-master-pick input {
        width: 14px;
        height: 14px;
        margin: 0;
        accent-color: var(--sky-deep);
        cursor: pointer;
    }

    .bk-t-selected-wrap { display: inline-flex; align-items: baseline; gap: 5px; }
    .bk-t-selected { font-size: 17px; font-weight: 700; color: var(--sky-deep); }
    .bk-t-selected-label { font-size: var(--text-sm); color: var(--muted-text); }

    /* Toolbar */
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

    /* Compact client rows */
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
    .bk-t-row.no-task { box-shadow: inset 2px 0 0 var(--warning); }

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

    .bk-t-target {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .bk-t-tasks {
        flex: 1 1 320px;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bk-t-field-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--muted-text);
        white-space: nowrap;
    }

    .bk-t-date {
        width: 132px;
        height: 30px;
        padding: 4px 8px;
        font-size: var(--text-sm);
        color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        border-radius: 6px;
    }
    .bk-t-date:focus,
    .bk-t-tools .form-control:focus {
        outline: none;
        border-color: var(--sky-deep);
        box-shadow: 0 0 0 3px var(--focus-ring-color);
    }
    .bk-t-date:disabled {
        background: var(--surface-sunken);
        color: var(--muted-text);
        cursor: not-allowed;
    }

    /* Compact task chips */
    .bk-t-chips { display: flex; flex-wrap: wrap; gap: 4px; min-width: 0; }

    /* Chip + its own staff selector stay together as one unit */
    .bk-t-unit {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 3px 2px 0;
        border: 1px solid transparent;
        border-radius: 7px;
    }
    .bk-t-unit:has(input:checked) { background: var(--sky-soft); border-color: #BFDBFE; }

    .bk-t-staff {
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
    .bk-t-staff:disabled { opacity: .4; cursor: not-allowed; background: transparent; }
    .bk-t-staff:not(:disabled):focus { outline: 2px solid var(--sky); outline-offset: 1px; }

    .bk-t-chip {
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
    .bk-t-chip:hover { background: var(--surface-raised); border-color: #CBD5E1; }
    .bk-t-chip input {
        width: 12px;
        height: 12px;
        margin: 0;
        accent-color: var(--sky-deep);
        cursor: pointer;
    }
    .bk-t-chip:has(input:checked) {
        background: var(--sky-soft);
        border-color: #A8D8F5;
        color: #1C6FA8;
        font-weight: 600;
    }
    .bk-t-chip:has(input:disabled) { opacity: .5; cursor: not-allowed; }

    .bk-t-empty {
        padding: 18px 14px;
        text-align: center;
        font-size: var(--text-md);
        color: var(--muted-text);
    }

    /* Summary */
    .bk-t-summary-card { position: sticky; top: 14px; padding: 0; }
    .bk-t-summary-card .bk-t-card-head { border-bottom: 1px solid var(--border-subtle); }
    .bk-t-summary { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px 14px; }
    .bk-t-summary-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 0;
        border-bottom: 1px solid var(--border-light);
        font-size: var(--text-md);
    }
    .bk-t-summary-row:last-of-type { border-bottom: 0; }
    .bk-t-summary-row span { color: var(--muted-text); }
    .bk-t-summary-row b { color: var(--navy); text-align: right; }
    .bk-t-removals {
        margin: 8px 0 0;
        font-size: var(--text-xs);
        line-height: 1.45;
        color: var(--warning);
    }
    .bk-t-note {
        margin: 10px 0 12px;
        padding: 8px 10px;
        font-size: var(--text-xs);
        line-height: 1.45;
        color: var(--muted-text);
        background: var(--surface-sunken);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
    }
    .bk-t-save {
        width: 100%;
        min-height: 42px;
        font-size: var(--text-base);
        font-weight: 600;
        border-radius: 9px;
    }
    .bk-t-summary .form-error { margin-top: 8px; font-size: var(--text-sm); color: var(--danger); }

    /* ---- Staff-centered assignment -------------------------------------
       The panel reuses .card, .form-grid, .bk-t-row, .bk-t-tools,
       .bk-t-summary-row, .form-actions and .table wholesale, so only the white
       client box and the vertical rhythm between its stacked pieces are
       declared here. Same box the client matrix's own card uses. */
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


    /* Mobile: client list -> target date -> target tasks */
    @media (max-width: 980px) {
        .bk-t-summary-card { position: static; }
        .bk-t-row {
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 7px 10px;
            padding: 10px 12px;
        }
        .bk-t-pick { padding-top: 3px; }
        .bk-t-id { flex: 1 1 100%; }
        .bk-t-target,
        .bk-t-tasks { flex: 1 1 100%; align-items: flex-start; }
        .bk-t-target .bk-t-field-label,
        .bk-t-tasks .bk-t-field-label { width: 78px; padding-top: 6px; }
        .bk-t-date { flex: 1 1 auto; width: auto; }
        .bk-t-chips { flex: 1 1 auto; }
        .bk-t-tools .form-control { max-width: none; flex: 1 1 150px; }
    }

    .schedule-seed-hint {
        display: block;
        margin-top: 3px;
        max-width: 190px;
        font-size: 11px;
        line-height: 1.35;
        color: var(--muted);
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
       chosen task, since one client + one task type per period is the existing
       rule and a repeat save moves that row instead of adding another. */
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

<script>
(function () {
    'use strict';

    /* Scoped to the matrix list: the assignment card above reuses .bk-t-row for
       its own client picker, so an unscoped query would mix the two. */
    var rows = Array.prototype.slice.call(document.querySelectorAll('#clientRows .bk-t-row'));
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
            return row.querySelector('.bk-t-client-check').checked;
        }).length;
    }

    /* Task types a client starts with when newly ticked. Record is deliberately
       left out: it is the one stage staff usually schedule by hand. */
    var DEFAULT_TASKS = ['pickup', 'return', 'payment'];

    /* The saved task set rendered for this client at load, used to restore the
       exact chips on re-tick instead of re-applying the defaults. */
    function existingTasks(row) {
        return (row.getAttribute('data-existing') || '').split(',').filter(Boolean);
    }

    /* { pairs, saved, removals } — pairs is every ticked client×task, saved is
       how many of those already exist for this period, removals is how many
       existing targets are currently unticked and will be deleted on save. */
    function plannedCounts() {
        var pairs = 0;
        var saved = 0;
        var removals = 0;

        rows.forEach(function (row) {
            var existing = existingTasks(row);
            var checkedKeys = Array.prototype.map.call(
                row.querySelectorAll('.bk-t-task-check:checked'),
                function (input) { return input.value; }
            );

            pairs += checkedKeys.length;
            saved += checkedKeys.filter(function (key) { return existing.indexOf(key) !== -1; }).length;
            removals += existing.filter(function (key) { return checkedKeys.indexOf(key) === -1; }).length;
        });

        return { pairs: pairs, saved: saved, removals: removals };
    }

    function updateSummary() {
        var clients = selectedCount();
        var counts = plannedCounts();
        var removalNote = document.getElementById('summaryRemovals');

        document.getElementById('selectedCount').textContent = clients;
        document.getElementById('summaryClients').textContent = clients;
        document.getElementById('summaryTasks').textContent = counts.pairs;
        document.getElementById('summarySaved').textContent = counts.saved;
        document.getElementById('saveTargetsBtn').disabled = clients === 0;

        if (removalNote) {
            removalNote.hidden = counts.removals === 0;
            removalNote.textContent = counts.removals + ' saved target' + (counts.removals === 1 ? '' : 's')
                + ' will be removed when you save unless still ticked.';
        }
    }

    /* Newly ticking a client preloads the usual period: Pick-Up, Return and
       Billing. A client that already holds saved targets keeps them exactly as
       rendered — re-ticking restores that saved set, never the defaults, so an
       existing assignment is never reset by a selection change. */
    function applyTaskDefaults(row) {
        var existing = existingTasks(row);
        var chips = Array.prototype.slice.call(row.querySelectorAll('.bk-t-task-check'));

        chips.forEach(function (chip) {
            if (existing.length) {
                chip.checked = existing.indexOf(chip.value) !== -1;
            } else if (DEFAULT_TASKS.indexOf(chip.value) !== -1) {
                chip.checked = true;
            }
        });

        syncRow(row);
    }

    function setClientChecked(row, checked) {
        var check = row.querySelector('.bk-t-client-check');

        if (check.checked !== checked) {
            check.checked = checked;
        }

        syncRow(row);

        if (checked) {
            applyTaskDefaults(row);
        }
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
            var checked = row.querySelector('.bk-t-client-check').checked;
            var matchesSearch = term === '' || name.indexOf(term) !== -1 || business.indexOf(term) !== -1;
            var matchesFilter = filter === 'all' || (filter === 'selected' && checked);
            if (filter === 'targeted') {
                matchesFilter = row.querySelectorAll('.bk-t-task-check:checked').length > 0;
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
            return row.querySelector('.bk-t-client-check').checked;
        });
        selectAllToggle.checked = visible.length > 0 && checkedVisible.length === visible.length;
        selectAllToggle.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visible.length;
    }

    /* Pick-Up drives the shared default: picking the Pick-Up staff member fills
       Return and Billing for that client unless either was set by hand. Record
       stays independent, and each client's selectors only ever touch their own
       row, so one client's change never reaches another. */
    function bindStaffAutoFill(row) {
        Array.prototype.slice.call(row.querySelectorAll('.bk-t-staff')).forEach(function (staffSel) {
            if (staffSel.dataset.autoBound) return;
            staffSel.dataset.autoBound = '1';
            staffSel.addEventListener('change', function () {
                var unit = staffSel.closest('.bk-t-unit');
                var taskKey = unit ? unit.getAttribute('data-task-unit') : null;
                if (!taskKey) return;
                var key = taskKey.toLowerCase();
                if (key === 'pickup') {
                    var val = staffSel.value;
                    if (!val) return;
                    var returnSel = row.querySelector('.bk-t-unit[data-task-unit="return"] .bk-t-staff');
                    var paySel = row.querySelector('.bk-t-unit[data-task-unit="payment"] .bk-t-staff');
                    if (returnSel && !returnSel.disabled && (!returnSel.dataset.userChanged || returnSel.dataset.userChanged === 'auto')) {
                        returnSel.value = val;
                        returnSel.dataset.userChanged = 'auto';
                    }
                    if (paySel && !paySel.disabled && (!paySel.dataset.userChanged || paySel.dataset.userChanged === 'auto')) {
                        paySel.value = val;
                        paySel.dataset.userChanged = 'auto';
                    }
                } else if (key === 'return' || key === 'payment') {
                    staffSel.dataset.userChanged = 'manual';
                }
            });
        });
    }

    // Per-client task/date/staff enablement
    function syncRow(row) {
        var check = row.querySelector('.bk-t-client-check');
        var date = row.querySelector('.bk-t-date');
        var taskInputs = Array.prototype.slice.call(row.querySelectorAll('.bk-t-task-check'));

        taskInputs.forEach(function (input) {
            input.disabled = !check.checked;
            if (!check.checked) input.checked = false;
        });
        date.disabled = !check.checked;
        if (!check.checked) date.value = '';

        // A staff selector is only meaningful for a task that is actually
        // targeted in this period, so it enables with its own task chip. The
        // chosen name is kept on re-tick so an existing assignment is never
        // silently blanked by unticking and re-ticking the client.
        Array.prototype.slice.call(row.querySelectorAll('.bk-t-unit')).forEach(function (unit) {
            var input = unit.querySelector('.bk-t-task-check');
            var staff = unit.querySelector('.bk-t-staff');
            if (!staff) return;
            staff.disabled = !input.checked || input.disabled;
        });

        bindStaffAutoFill(row);

        var anyTask = taskInputs.some(function (input) { return input.checked; });
        row.classList.toggle('is-selected', check.checked);
        row.classList.toggle('no-task', check.checked && !anyTask);

        updateSummary();
        updateSelectAllToggle();
    }

    rows.forEach(function (row) {
        var check = row.querySelector('.bk-t-client-check');
        check.addEventListener('change', function () { setClientChecked(row, check.checked); });
        Array.prototype.slice.call(row.querySelectorAll('.bk-t-task-check')).forEach(function (input) {
            input.addEventListener('change', function () { syncRow(row); });
        });
    });

    selectAllToggle.addEventListener('change', function () {
        var checked = this.checked;
        visibleRows().forEach(function (row) {
            setClientChecked(row, checked);
        });
    });

    document.getElementById('selectAllVisible').addEventListener('click', function () {
        visibleRows().forEach(function (row) {
            setClientChecked(row, true);
        });
    });

    document.getElementById('clearSelection').addEventListener('click', function () {
        rows.forEach(function (row) {
            row.querySelector('.bk-t-client-check').checked = false;
            syncRow(row);
        });
    });

    searchInput.addEventListener('input', applyFilters);
    filterSelect.addEventListener('change', applyFilters);

    // Prevent submitting a client with no task selected. The message is shown
    // inline next to the Save button instead of a blocking browser alert.
    var formError = document.getElementById('targetFormError');

    form.addEventListener('change', function () {
        if (formError) {
            formError.hidden = true;
        }
    });

    form.addEventListener('submit', function (e) {
        var firstInvalid = null;

        rows.some(function (row) {
            var check = row.querySelector('.bk-t-client-check');
            if (!check.checked) return false;
            if (row.querySelectorAll('.bk-t-task-check:checked').length === 0) {
                firstInvalid = row;
                return true;
            }
            return false;
        });

        if (firstInvalid) {
            e.preventDefault();

            if (formError) {
                var nameEl = firstInvalid.querySelector('.bk-t-id-name');
                var name = nameEl ? nameEl.textContent.trim() : '';
                formError.textContent = name
                    ? 'Each selected client needs at least one target task. Tick a task for ' + name + ', or untick the client.'
                    : 'Each selected client needs at least one target task.';
                formError.hidden = false;
                formError.scrollIntoView({ block: 'nearest' });
            }

            return;
        }

        if (formError) {
            formError.hidden = true;
        }

        var saveBtn = document.getElementById('saveTargetsBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
    });

    rows.forEach(function (row) { syncRow(row); });
    applyFilters();
    updateSummary();
})();
</script>
@endpush
