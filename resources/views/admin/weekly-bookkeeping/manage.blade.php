@extends('layouts.dashboard')

@section('title', 'Edit Weekly Target — Weekly Bookkeeping — Egliane Accounting Services')

@section('content')
@php
    $minDate = $bookkeeping->week_start->format('Y-m-d');
    $maxDate = $bookkeeping->week_end->format('Y-m-d');

    // Pending tasks can be edited; started or completed ones are locked.
    $editableCount = $targets->filter(fn ($t) => $t->isPending())->count();
    $lockedCount = $targets->count() - $editableCount;
@endphp

<div class="page-head page-head-row">
    <div>
        <h1>Edit Weekly Target</h1>
        <p>
            {{ $bookkeeping->week_start->format('M j, Y') }} – {{ $bookkeeping->week_end->format('M j, Y') }}.
            Change a date or staff member, tick Remove to delete a task, or add new tasks at the bottom. Nothing is saved until you press Save changes.
        </p>
    </div>
    <div>
        <a class="btn btn-outline" href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $minDate]) }}">Back to tracker</a>
    </div>
</div>

<div class="wt-edit">
    @if (session('status'))
        <div class="wt-alert" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="wt-alert" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.weekly-bookkeeping.update-plan', $bookkeeping) }}" id="wt-form">
        @csrf
        @method('PUT')

        <div class="wt-section">
            Current tasks ({{ $targets->count() }})
            @if ($targets->isNotEmpty())
                <span class="wt-section-sub">{{ $editableCount }} editable · {{ $lockedCount }} locked</span>
            @endif
        </div>
        <p class="wt-note">Tasks that have already been started or completed are locked so their history stays accurate.</p>

        @if ($targets->isNotEmpty())
            {{-- Quick tools for a long list. They only change the fields on this
                 page; nothing is saved until Save changes is pressed. --}}
            <div class="wt-tools">
                <label class="wt-tool">
                    <span>Find</span>
                    <input type="search" id="wt-filter" placeholder="Client or task…" autocomplete="off">
                </label>
                <label class="wt-tool">
                    <span>Assign shown tasks to</span>
                    <select id="wt-bulk-staff">
                        <option value="">Choose staff…</option>
                        <option value="__none__">Unassigned</option>
                        @foreach ($assignableStaff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-outline" id="wt-bulk-apply">Apply</button>
                <span class="wt-tools-note" id="wt-tools-note" aria-live="polite"></span>
            </div>
        @endif

        <div class="wt-table-wrap wt-table-tall">
            <table>
                <caption class="wt-sr">Tasks planned for this week</caption>
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col">Task</th>
                        <th scope="col">Staff</th>
                        <th scope="col">Target date</th>
                        <th scope="col">Status</th>
                        <th scope="col">Remove</th>
                    </tr>
                </thead>
                <tbody id="wt-current-rows">
                    @forelse ($targets as $t)
                        @php
                            $removeOld = old("targets.{$t->id}.remove");
                            $searchText = \Illuminate\Support\Str::lower($t->displayClientName().' '.$t->taskLabel());
                        @endphp
                        @if ($t->isPending())
                            <tr data-row
                                data-client-id="{{ $t->client_id }}"
                                data-task-type="{{ $t->task_type }}"
                                data-search="{{ $searchText }}"
                                class="{{ $removeOld ? 'wt-removing' : '' }}">
                                <td>{{ $t->displayClientName() }}</td>
                                <td>{{ $t->taskLabel() }}</td>
                                <td>
                                    <select name="targets[{{ $t->id }}][assigned_staff_id]" aria-label="Staff for {{ $t->displayClientName() }} {{ $t->taskLabel() }}">
                                        <option value="">Unassigned</option>
                                        @foreach ($assignableStaff as $staff)
                                            <option value="{{ $staff->id }}" @selected((int) old("targets.{$t->id}.assigned_staff_id", $t->assigned_staff_id) === (int) $staff->id)>
                                                {{ $staff->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="wt-date-field">
                                        <input type="date"
                                               name="targets[{{ $t->id }}][target_date]"
                                               value="{{ old("targets.{$t->id}.target_date", $t->target_date?->format('Y-m-d')) }}"
                                               min="{{ $minDate }}" max="{{ $maxDate }}"
                                               aria-label="Target date for {{ $t->displayClientName() }} {{ $t->taskLabel() }}">
                                        <small class="wt-dow" data-dow>{{ $t->target_date?->format('D') }}</small>
                                    </div>
                                </td>
                                <td>Pending</td>
                                <td>
                                    <input type="checkbox"
                                           name="targets[{{ $t->id }}][remove]"
                                           value="1"
                                           data-remove
                                           @checked($removeOld)
                                           aria-label="Remove {{ $t->displayClientName() }} {{ $t->taskLabel() }}">
                                </td>
                            </tr>
                        @else
                            <tr class="wt-locked"
                                data-client-id="{{ $t->client_id }}"
                                data-task-type="{{ $t->task_type }}"
                                data-search="{{ $searchText }}">
                                <td>{{ $t->displayClientName() }}</td>
                                <td>{{ $t->taskLabel() }}</td>
                                <td>{{ $t->assignedStaffDisplayName() ?: 'Unassigned' }}</td>
                                <td>{{ $t->target_date?->format('M j, Y') ?? 'Any day' }}</td>
                                <td>{{ $t->effectiveStatusLabel() }} (locked)</td>
                                <td>—</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6">No tasks in this week yet. Add some below.</td></tr>
                    @endforelse
                    <tr id="wt-no-match" class="wt-hidden"><td colspan="6">No tasks match your search.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="wt-section">Add tasks</div>
        <p class="wt-note">Each client can have one task of each type per week. Leave a row empty to ignore it.</p>

        <div class="wt-table-wrap">
            <table>
                <caption class="wt-sr">New tasks to add to this week</caption>
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col">Task</th>
                        <th scope="col">Staff</th>
                        <th scope="col">Target date</th>
                        <th scope="col"><span class="wt-sr">Row actions</span></th>
                    </tr>
                </thead>
                <tbody id="new-rows"></tbody>
            </table>
        </div>

        {{-- Stays at the bottom of the screen so Save is always in reach on a long list. --}}
        <div class="wt-actions">
            <span class="wt-summary" id="wt-summary" aria-live="polite">No changes yet</span>
            <div class="wt-actions-buttons">
                <button type="button" class="btn btn-outline" id="add-row">+ Add another task</button>
                <a class="btn btn-outline" href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $minDate]) }}">Cancel</a>
                <button type="submit" class="btn btn-primary" id="wt-save">Save changes</button>
            </div>
        </div>
    </form>
</div>

<template id="new-row-template">
    <tr data-new-row>
        <td>
            <select name="new[__INDEX__][client_id]" data-new-client aria-label="Client for new task">
                <option value="">Select client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->business_name ?: $client->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="new[__INDEX__][task_type]" data-new-task aria-label="Task type for new task">
                <option value="">Select task</option>
                @foreach ($taskTypes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="wt-inline-error" data-row-error aria-live="polite"></div>
        </td>
        <td>
            <select name="new[__INDEX__][assigned_staff_id]" aria-label="Staff for new task">
                <option value="">Select staff</option>
                @foreach ($assignableStaff as $staff)
                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <div class="wt-date-field">
                <input type="date" name="new[__INDEX__][target_date]" min="{{ $minDate }}" max="{{ $maxDate }}" aria-label="Target date for new task">
                <small class="wt-dow" data-dow></small>
            </div>
        </td>
        <td><button type="button" class="btn btn-outline" data-drop-row>Remove row</button></td>
    </tr>
</template>
@endsection

@push('styles')
<style>
    .wt-edit table { width: 100%; border-collapse: collapse; }
    .wt-edit th, .wt-edit td { font-size: 14px; padding: 6px 10px; border-bottom: 1px solid rgba(128,128,128,.25); text-align: left; vertical-align: middle; }
    .wt-edit th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
    /* Compact, lighter input boxes (staff, date, search) in place of the heavy browser defaults. */
    .wt-edit select, .wt-edit input[type="date"], .wt-edit input[type="search"] {
        width: 100%; min-width: 150px; height: 34px; box-sizing: border-box;
        padding: 0 10px; font: inherit; font-size: 14px; color: inherit;
        background-color: transparent;
        border: 1px solid rgba(128,128,128,.4); border-radius: 6px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .wt-edit select:hover, .wt-edit input[type="date"]:hover, .wt-edit input[type="search"]:hover { border-color: rgba(128,128,128,.7); }
    .wt-edit select:focus, .wt-edit input[type="date"]:focus, .wt-edit input[type="search"]:focus {
        outline: none; border-color: var(--primary, #6366f1); box-shadow: 0 0 0 3px rgba(99, 102, 241, .18);
    }
    .wt-edit select:user-invalid, .wt-edit input[type="date"]:user-invalid { border-color: #b91c1c; }
    /* A changed row also marks the boxes that were edited. */
    .wt-edit .wt-changed select, .wt-edit .wt-changed input[type="date"] { border-color: var(--primary, #6366f1); }
    .wt-edit .wt-table-tall td select { max-width: 230px; }
    .wt-edit input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary, #6366f1); }
    @media (prefers-reduced-motion: reduce) {
        .wt-edit select, .wt-edit input[type="date"], .wt-edit input[type="search"] { transition: none; }
    }
    .wt-edit .wt-locked { opacity: .6; }
    .wt-edit .wt-removing { opacity: .45; text-decoration: line-through; }
    .wt-edit .wt-changed { background: rgba(99, 102, 241, .08); }
    .wt-edit .wt-hidden { display: none; }
    .wt-edit .wt-section { margin: 24px 0 8px; font-weight: 600; }
    .wt-edit .wt-section-sub { margin-left: 8px; font-size: 13px; font-weight: 400; opacity: .7; }
    .wt-edit .wt-note { font-size: 13px; opacity: .75; margin: 4px 0 12px; }
    .wt-edit .wt-alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; border: 1px solid rgba(128,128,128,.4); }
    .wt-edit .wt-table-wrap { overflow-x: auto; }
    .wt-edit .wt-date-field { display: flex; align-items: center; gap: 8px; }
    .wt-edit .wt-date-field input[type="date"] { flex: 1 1 auto; }
    .wt-edit .wt-dow { flex: 0 0 auto; min-width: 2.2em; font-size: 12px; opacity: .65; }
    .wt-edit .wt-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    /* A long list scrolls inside its own box so the column headings stay visible. */
    .wt-edit .wt-table-tall { max-height: 62vh; overflow: auto; }
    /* Solid background so rows scrolling underneath never show through the headings. */
    .wt-edit .wt-table-tall thead th {
        position: sticky; top: 0; z-index: 1;
        background-color: Canvas;
        background-image: linear-gradient(var(--surface, transparent), var(--surface, transparent));
        box-shadow: 0 1px 0 rgba(128,128,128,.3);
    }
    /* Leave room on the right so the floating assistant button never covers the Remove tick. */
    .wt-edit .wt-table-tall th:last-child, .wt-edit .wt-table-tall td:last-child { padding-right: 72px; }

    .wt-edit .wt-tools { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin: 0 0 12px; }
    .wt-edit .wt-tool { display: flex; flex-direction: column; gap: 4px; font-size: 12px; min-width: 180px; }
    .wt-edit .wt-tool span { opacity: .75; text-transform: uppercase; letter-spacing: .03em; }
    .wt-edit .wt-tools-note { font-size: 13px; opacity: .8; }
    /* Same height for the search box, the staff picker and Apply so the row lines up. */
    .wt-edit .wt-tools .btn { height: 34px; box-sizing: border-box; }

    .wt-edit .wt-inline-error { margin-top: 4px; font-size: 12px; color: #b91c1c; }
    .wt-edit .wt-inline-error:empty { display: none; }

    /* Save stays in reach at the bottom of the screen. */
    .wt-edit .wt-actions {
        position: sticky; bottom: 0; z-index: 2;
        display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        margin-top: 16px; padding: 12px 0;
        background-color: Canvas;
        background-image: linear-gradient(var(--surface, transparent), var(--surface, transparent));
        border-top: 1px solid rgba(128,128,128,.3);
    }
    .wt-edit .wt-actions-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
    .wt-edit .wt-summary { font-size: 13px; opacity: .85; }
    .wt-edit .wt-summary.is-dirty { opacity: 1; font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        'use strict';

        var form = document.getElementById('wt-form');
        var currentBody = document.getElementById('wt-current-rows');
        var newBody = document.getElementById('new-rows');
        var template = document.getElementById('new-row-template');
        var summary = document.getElementById('wt-summary');
        var saveBtn = document.getElementById('wt-save');
        var filterInput = document.getElementById('wt-filter');
        var bulkStaff = document.getElementById('wt-bulk-staff');
        var bulkApply = document.getElementById('wt-bulk-apply');
        var toolsNote = document.getElementById('wt-tools-note');
        var noMatchRow = document.getElementById('wt-no-match');

        var index = 0;
        var changeCount = 0;
        var submitting = false;

        // New rows the person had filled in before a validation error, so a
        // failed save does not wipe them.
        var restoredRows = @json(array_values(old('new', [])));

        /* ---------- helpers ---------- */

        function weekday(value) {
            var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
            if (!p) { return ''; }
            return new Date(+p[1], +p[2] - 1, +p[3]).toLocaleDateString('en-US', { weekday: 'short' });
        }

        function updateWeekday(input) {
            var cell = input.closest('td');
            var out = cell ? cell.querySelector('[data-dow]') : null;
            if (out) { out.textContent = weekday(input.value); }
        }

        // Compares a select with the option the server rendered as selected.
        function selectChanged(select) {
            var original = 0;
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].defaultSelected) { original = i; break; }
            }
            return select.selectedIndex !== original;
        }

        function addRow() {
            var holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index++));
            var row = holder.firstElementChild;
            newBody.appendChild(row);
            return row;
        }

        /* ---------- validation helpers for the new rows ---------- */

        // Client and task are needed as soon as any part of a new row is filled in.
        function syncRequired(row) {
            var fields = row.querySelectorAll('select, input');
            var any = Array.prototype.some.call(fields, function (el) { return el.value !== ''; });
            row.querySelector('[data-new-client]').required = any;
            row.querySelector('[data-new-task]').required = any;
        }

        // One task of each type per client per week: flag a new row that repeats
        // an existing task (unless that one is being removed) or an earlier new row.
        function checkDuplicates() {
            var taken = {};

            currentBody.querySelectorAll('tr[data-client-id]').forEach(function (row) {
                var box = row.querySelector('[data-remove]');
                if (box && box.checked) { return; }
                taken[row.getAttribute('data-client-id') + ':' + row.getAttribute('data-task-type')] = true;
            });

            newBody.querySelectorAll('tr').forEach(function (row) {
                var client = row.querySelector('[data-new-client]');
                var task = row.querySelector('[data-new-task]');
                var out = row.querySelector('[data-row-error]');
                var message = '';

                if (client.value && task.value) {
                    var key = client.value + ':' + task.value;
                    if (taken[key]) {
                        message = 'This client already has a ' + task.options[task.selectedIndex].text + ' task this week.';
                    } else {
                        taken[key] = true;
                    }
                }

                task.setCustomValidity(message);
                out.textContent = message;
            });
        }

        /* ---------- change tracking ---------- */

        function refresh() {
            var removing = 0;
            var edited = 0;

            currentBody.querySelectorAll('tr[data-row]').forEach(function (row) {
                var box = row.querySelector('[data-remove]');
                var staff = row.querySelector('select');
                var date = row.querySelector('input[type="date"]');
                var isRemoving = box && box.checked;
                var isEdited = !isRemoving && ((staff && selectChanged(staff)) || (date && date.value !== date.defaultValue));

                row.classList.toggle('wt-removing', !!isRemoving);
                row.classList.toggle('wt-changed', !!isEdited);

                if (isRemoving) { removing++; }
                if (isEdited) { edited++; }
            });

            var added = 0;
            newBody.querySelectorAll('tr').forEach(function (row) {
                syncRequired(row);
                var fields = row.querySelectorAll('select, input');
                var any = Array.prototype.some.call(fields, function (el) { return el.value !== ''; });
                if (any) { added++; }
            });

            checkDuplicates();

            var parts = [];
            if (edited) { parts.push(edited + (edited === 1 ? ' task edited' : ' tasks edited')); }
            if (removing) { parts.push(removing + ' to remove'); }
            if (added) { parts.push(added + (added === 1 ? ' new task' : ' new tasks')); }

            changeCount = edited + removing + added;
            summary.textContent = parts.length ? parts.join(' · ') + ' — not saved yet' : 'No changes yet';
            summary.classList.toggle('is-dirty', parts.length > 0);
        }

        /* ---------- find and bulk assign ---------- */

        function applyFilter() {
            if (!filterInput) { return; }
            var q = filterInput.value.trim().toLowerCase();
            var shown = 0;

            currentBody.querySelectorAll('tr[data-search]').forEach(function (row) {
                var match = q === '' || row.getAttribute('data-search').indexOf(q) !== -1;
                row.classList.toggle('wt-hidden', !match);
                if (match) { shown++; }
            });

            noMatchRow.classList.toggle('wt-hidden', q === '' || shown !== 0);
            toolsNote.textContent = q === '' ? '' : shown + ' shown';
        }

        if (filterInput) {
            filterInput.addEventListener('input', applyFilter);
        }

        if (bulkApply) {
            bulkApply.addEventListener('click', function () {
                if (bulkStaff.value === '') {
                    toolsNote.textContent = 'Choose a staff member first.';
                    return;
                }

                var value = bulkStaff.value === '__none__' ? '' : bulkStaff.value;
                var updated = 0;

                currentBody.querySelectorAll('tr[data-row]:not(.wt-hidden)').forEach(function (row) {
                    var box = row.querySelector('[data-remove]');
                    var select = row.querySelector('select');
                    if ((box && box.checked) || !select || select.value === value) { return; }
                    select.value = value;
                    updated++;
                });

                refresh();
                toolsNote.textContent = updated
                    ? 'Updated ' + updated + (updated === 1 ? ' task' : ' tasks') + '. Press Save changes to keep it.'
                    : 'Nothing to change.';
            });
        }

        /* ---------- events ---------- */

        document.getElementById('add-row').addEventListener('click', function () {
            var row = addRow();
            refresh();
            row.querySelector('[data-new-client]').focus();
        });

        newBody.addEventListener('click', function (event) {
            var button = event.target.closest('[data-drop-row]');
            if (button) {
                button.closest('tr').remove();
                refresh();
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target.matches('input[type="date"]')) {
                updateWeekday(event.target);
            }
            refresh();
        });

        form.addEventListener('input', function (event) {
            if (event.target.matches('input[type="date"]')) {
                updateWeekday(event.target);
            }
            if (event.target !== filterInput) {
                refresh();
            }
        });

        form.addEventListener('submit', function (event) {
            var removing = currentBody.querySelectorAll('[data-remove]:checked').length;

            if (removing > 0) {
                var label = removing === 1 ? 'this task' : removing + ' tasks';
                if (!window.confirm('Remove ' + label + ' from this week’s target?')) {
                    event.preventDefault();
                    return;
                }
            }

            submitting = true;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
        });

        // Warn before leaving with edits that were not saved.
        window.addEventListener('beforeunload', function (event) {
            if (submitting || changeCount === 0) { return; }
            event.preventDefault();
            event.returnValue = '';
        });

        // The Back button can restore the page with Save still disabled.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                submitting = false;
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save changes';
            }
        });

        /* ---------- start up ---------- */

        form.querySelectorAll('input[type="date"]').forEach(updateWeekday);

        if (restoredRows.length) {
            restoredRows.forEach(function (saved) {
                var row = addRow();
                row.querySelector('[name$="[client_id]"]').value = saved.client_id || '';
                row.querySelector('[name$="[task_type]"]').value = saved.task_type || '';
                row.querySelector('[name$="[assigned_staff_id]"]').value = saved.assigned_staff_id || '';
                var date = row.querySelector('[name$="[target_date]"]');
                date.value = saved.target_date || '';
                updateWeekday(date);
            });
        } else {
            addRow();
        }

        refresh();
    })();
</script>
@endpush