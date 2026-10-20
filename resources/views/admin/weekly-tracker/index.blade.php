@extends('layouts.dashboard')

@section('title', 'Weekly Bookkeeping — Egliane Accounting Services')

@push('styles')
<style>
    /* ---------- Header + week navigator ---------- */
    .wt-head { margin-bottom: 14px; }
    .wt-head-main h1 { text-transform: uppercase; letter-spacing: .01em; }

    .wt-weekbar {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 10px 12px; margin-bottom: 14px;
        background: var(--surface); border: 1px solid var(--border-subtle);
        border-radius: var(--radius-sm);
    }
    .wt-weeknav {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; flex: 0 0 auto;
        color: var(--navy); background: var(--surface-sunken);
        border: 1px solid var(--border-subtle); border-radius: 9px; text-decoration: none;
    }
    .wt-weeknav:hover { background: var(--sky-soft); border-color: #BFDBFE; }
    .wt-weeklabel { display: flex; flex-direction: column; line-height: 1.25; padding: 0 4px; }
    .wt-weeklabel b { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); white-space: nowrap; }
    .wt-weeklabel span { font-size: var(--text-xs); color: var(--muted-text); }
    .wt-week-today {
        padding: 5px 10px; font-size: var(--text-xs); font-weight: 700;
        color: var(--navy); background: var(--sky-soft);
        border: 1px solid #BFDBFE; border-radius: 999px; text-decoration: none;
    }
    .wt-weekbar-spacer { flex: 1 1 auto; }

    /* ---------- Summary ---------- */
    .stat-grid.wt-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 16px; }

    /* ---------- Filters ---------- */
    .wt-filters {
        margin-bottom: 16px; background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
    }
    .wt-filters-summary {
        display: flex; align-items: center; gap: 8px; cursor: pointer;
        padding: 10px 14px; font-size: var(--text-sm); font-weight: 700; color: var(--navy);
        list-style: none; min-height: 44px;
    }
    .wt-filters-summary::-webkit-details-marker { display: none; }
    .wt-filters-summary::marker { content: ''; }
    .wt-filters-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
        font-size: 10.5px; font-weight: 800; color: #fff; background: var(--sky-deep);
    }
    .wt-filters-form {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;
        padding: 14px; border-top: 1px solid var(--border-subtle);
    }
    .wt-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .wt-field > span { font-size: var(--text-xs); color: var(--muted-text); font-weight: 600; }
    .wt-field select, .wt-field input {
        min-height: 36px; padding: 0 10px; font-size: var(--text-sm);
        color: var(--text); background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: 8px;
    }
    .wt-field-grow { flex: 1 1 190px; }

    /* ---------- Add task form ---------- */
    .wt-add { padding: 16px; margin-bottom: 16px; }
    .wt-add-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .wt-add-actions { display: flex; align-items: flex-end; }
    .wt-plan-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .wt-plan-cta-main { display: flex; flex-direction: column; gap: 2px; flex: 1 1 260px; }
    .wt-plan-cta-main b { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); }
    .wt-plan-cta-main span { font-size: var(--text-sm); color: var(--muted-text); }

    /* ---------- Weekday board ---------- */
    .wt-day { margin-bottom: 12px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); }
    .wt-day-head {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 10px 14px; border-bottom: 1px solid var(--border-subtle);
        background: var(--surface-raised);
    }
    .wt-day.is-today .wt-day-head { background: var(--sky-light); }
    .wt-day-name { font-family: var(--font-head); font-size: var(--text-base); font-weight: 700; color: var(--navy); }
    .wt-day-date { font-size: var(--text-xs); color: var(--muted-text); }
    .wt-day-today { font-size: 10.5px; font-weight: 800; color: #1E40AF; background: #DBEAFE; padding: 2px 8px; border-radius: 999px; }
    .wt-day-count { margin-left: auto; font-size: var(--text-xs); color: var(--muted-text); white-space: nowrap; }

    /* Stage band inside a day (Pick-Up / Record / Return / Billing) */
    .wt-band { border-top: 1px solid var(--border-subtle); }
    .wt-band:first-of-type { border-top: 0; }
    .wt-band-head {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 7px 14px; background: var(--surface-sunken);
    }
    .wt-band-name { font-size: var(--text-xs); font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--navy); }
    .wt-band-count { margin-left: auto; font-size: 10.5px; color: var(--muted-text); white-space: nowrap; }

    .wt-table-scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }    .wt-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); min-width: 1180px; }
    .wt-table th, .wt-table td { padding: 8px 10px; border-bottom: 1px solid var(--border-subtle); text-align: left; vertical-align: top; }
    .wt-table thead th {
        font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .03em;
        color: var(--muted-text); background: var(--surface-raised); white-space: nowrap;
    }
    .wt-table tbody tr:last-child td { border-bottom: 0; }
    .wt-row:hover { background: var(--sky-light); }
    .wt-col-date { white-space: nowrap; }
    .wt-col-client { min-width: 190px; }
    .wt-col-type { white-space: nowrap; }
    .wt-col-stage { min-width: 170px; }
    .wt-col-paydate { min-width: 150px; white-space: nowrap; }
    .wt-col-actions { white-space: nowrap; text-align: right; }
    .wt-task-type { font-size: var(--text-xs); font-weight: 700; color: var(--navy); }

    .wt-cell-form { display: flex; flex-direction: column; gap: 4px; }
    .wt-cell-row { display: flex; align-items: center; gap: 6px; }
    .wt-cell-form select, .wt-cell-form input {
        min-height: 32px; padding: 0 8px; font-size: var(--text-xs);
        color: var(--text); background: var(--surface);
        border: 1px solid var(--border-subtle); border-radius: 7px; max-width: 100%;
    }
    .wt-cell-form select { min-width: 112px; }
    .wt-cell-form input[type="date"] { min-width: 128px; }
    .wt-cell-form .btn { min-height: 32px; }
    .wt-pill {
        display: inline-flex; align-items: center; padding: 2px 9px;
        border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap;
    }
    .wt-pill-done { background: #DCFCE7; color: #166534; }
    .wt-pill-open { background: #FEF3C7; color: #92400E; }
    .wt-pill-idle { background: var(--surface-sunken); color: var(--muted-text); }
    /* Bulk selection bar */
    .wt-bulk {
        display: none;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin: 0 0 12px;
        padding: 10px 14px;
        background: var(--surface);
        border: 1px solid #BFDBFE;
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-sm);
    }
    .wt-bulk.is-visible { display: flex; }
    .wt-bulk-count { font-size: var(--text-sm); font-weight: 700; color: var(--navy); }
    .wt-bulk-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-left: auto; }
    .wt-bulk-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .wt-bulk-form select, .wt-bulk-form input {
        min-height: 34px; padding: 0 10px; font-size: var(--text-sm);
        border: 1px solid var(--border-subtle); border-radius: 8px; background: var(--surface);
    }
    .wt-check { width: 16px; height: 16px; accent-color: var(--primary); }
    .wt-check-cell { width: 1%; white-space: nowrap; }
    .wt-vs-label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted-text); font-weight: 700; }
    .wt-dash { color: var(--muted-text); }
    .wt-notes { font-size: var(--text-xs); color: var(--muted-text); max-width: 220px; display: block; }

    @media (max-width: 640px) {
        .stat-grid.wt-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wt-filters-form, .wt-add-form { flex-direction: column; align-items: stretch; }
        .wt-field, .wt-field-grow { flex: 1 1 auto; width: 100%; }
        .wt-add-actions .btn { width: 100%; min-height: 44px; }
    }
</style>
@endpush

@section('content')
    @php
        $weekLabel = $weekStartCarbon->format('M j, Y').' – '.$weekEndCarbon->format('M j, Y');
        $weekUrl = fn ($start) => route('admin.weekly-tracker.index', array_filter([
            'week_start' => $start,
            'client' => $activeClient,
            'task_type' => $activeTaskType !== '' ? $activeTaskType : null,
            'target_date' => $activeDate !== '' ? $activeDate : null,
        ]));
        $activeFilterCount = ($activeClient ? 1 : 0) + ($activeTaskType !== '' ? 1 : 0) + ($activeDate !== '' ? 1 : 0);
        $percent = $stats['tasks'] > 0 ? (int) round(($stats['completed'] / $stats['tasks']) * 100) : 0;
    @endphp

    <div class="page-head page-head-row wt-head">
        <div class="wt-head-main">
            <h1>Weekly Bookkeeping</h1>
            <p>One row per client task with Target Date, Task Type and Client, and each stage shown as Actual vs Target.</p>
        </div>
    </div>

    {{-- ============ WEEK NAVIGATOR ============ --}}
    <div class="wt-weekbar">
        <a href="{{ $weekUrl($prevWeekStart) }}" class="wt-weeknav" rel="prev" aria-label="Previous week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="wt-weeklabel">
            <b>{{ $weekHeading }}</b>
            <span>{{ $weekLabel }}{{ $isCurrentWeek ? ' · Current week' : '' }}</span>
        </div>
        <a href="{{ $weekUrl($nextWeekStart) }}" class="wt-weeknav" rel="next" aria-label="Next week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        @unless ($isCurrentWeek)
            <a href="{{ $weekUrl($currentWeekStart) }}" class="wt-week-today">This Week</a>
        @endunless
        <div class="wt-weekbar-spacer"></div>
        <div class="wt-day-count">{{ $stats['tasks'] }} {{ \Illuminate\Support\Str::plural('task', $stats['tasks']) }} · {{ $stats['clients'] }} {{ \Illuminate\Support\Str::plural('client', $stats['clients']) }}</div>
    </div>

    {{-- ============ SUMMARY ============ --}}
    <div class="stat-grid wt-summary">
        <div class="stat-card">
            <div class="stat-label">Pick-Up Completed</div>
            <div class="stat-value">{{ $stats['pickup'] }}</div>
            <div class="stat-meta">of {{ $stats['tasks'] }} tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Record Completed</div>
            <div class="stat-value">{{ $stats['record'] }}</div>
            <div class="stat-meta">of {{ $stats['tasks'] }} tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Returned</div>
            <div class="stat-value">{{ $stats['return'] }}</div>
            <div class="stat-meta">of {{ $stats['tasks'] }} tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Billing Paid</div>
            <div class="stat-value">{{ $stats['billing'] }}</div>
            <div class="stat-meta">{{ $percent }}% of tasks complete</div>
        </div>
    </div>

    {{-- ============ PLAN THIS WEEK (multi-client) ============ --}}
    {{-- Work is assigned through the multi-client planner, where every client is
         picked with a checkbox (Select All / Clear Selection / a live count).
         This card links there; each selected client still gets its own set of
         independent records, so nothing is ever merged. --}}
    <div class="card wt-add wt-plan-cta">
        <div class="wt-plan-cta-main">
            <b>Plan this week</b>
            <span>Choose several clients at once with checkboxes, then pick the tasks each one needs.</span>
        </div>
        <div class="wt-add-actions">
            <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Select Clients
            </a>
        </div>
    </div>

    {{-- ============ FILTERS ============ --}}
    <details class="wt-filters"{!! $hasActiveFilters ? ' open' : '' !!}>
        <summary class="wt-filters-summary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Filters</span>
            @if ($activeFilterCount > 0)
                <span class="wt-filters-count">{{ $activeFilterCount }}</span>
            @endif
        </summary>
        <form method="GET" action="{{ route('admin.weekly-tracker.index') }}" class="wt-filters-form">
            <input type="hidden" name="week_start" value="{{ $activeWeekStart }}">
            <label class="wt-field">
                <span>Client</span>
                <select name="client" onchange="this.form.submit()">
                    <option value="">All Clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($activeClient === $client->id)>{{ $client->business_name ?: $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="wt-field">
                <span>Task type</span>
                <select name="task_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach ($taskTypes as $key => $label)
                        <option value="{{ $key }}" @selected($activeTaskType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="wt-field">
                <span>Specific date</span>
                <input type="date" name="target_date" value="{{ $activeDate }}" min="{{ $weekStartCarbon->format('Y-m-d') }}" max="{{ $weekEndCarbon->format('Y-m-d') }}">
            </label>
            <div class="wt-add-actions">
                <button type="submit" class="btn btn-outline">Apply</button>
            </div>
            @if ($hasActiveFilters)
                <div class="wt-add-actions">
                    <a href="{{ route('admin.weekly-tracker.index', ['week_start' => $activeWeekStart]) }}" class="btn btn-outline">Clear</a>
                </div>
            @endif
        </form>
    </details>

    {{-- ============ WEEKDAY BOARD ============ --}}
    @foreach ($days as $day)
        <section class="wt-day{{ $day['is_today'] ? ' is-today' : '' }}">
            <div class="wt-day-head">
                <span class="wt-day-name">{{ $day['date']->format('l') }}</span>
                <span class="wt-day-date">{{ $day['date']->format('M j, Y') }}</span>
                @if ($day['is_today'])
                    <span class="wt-day-today">Today</span>
                @endif
                <span class="wt-day-count">{{ $day['tasks']->count() }} {{ \Illuminate\Support\Str::plural('task', $day['tasks']->count()) }}</span>
            </div>

            @if ($day['tasks']->isEmpty())
                <div class="empty-state compact">
                    <p>No tasks scheduled on {{ $day['date']->format('l') }}.</p>
                </div>
            @else
                {{-- Within the day, clients are grouped by their current process
                     stage (Pick-Up, Record, Return / Collect, Billing / Payment),
                     like the bands on the reference sheet. Row = client with all
                     four stage columns plus Payment Date. Clients can be selected
                     per stage band to update them in bulk. --}}
                @foreach ($day['stage_groups'] as $group)
                    <div class="wt-band">
                        <div class="wt-band-head">
                            <span class="wt-band-name">{{ $group['label'] }}</span>
                            <span class="wt-band-count">{{ $group['rows']->count() }} {{ \Illuminate\Support\Str::plural('client', $group['rows']->count()) }}</span>
                        </div>

                        @if ($group['rows']->isEmpty())
                            <div class="empty-state compact">
                                <p>No {{ $group['label'] }} work on {{ $day['date']->format('l') }}.</p>
                            </div>
                        @else
                            <form id="{{ $group['form_id'] }}" method="POST" action="{{ route('admin.weekly-tracker.bulk-stage') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="stage" value="{{ $group['key'] }}">
                            </form>

                            <div class="wt-table-scroll">
                                <table class="wt-table">
                                    <thead>
                                        <tr>
                                            <th class="wt-col-check wt-check-cell"><input type="checkbox" class="wt-check" data-wt-select-all="{{ $group['form_id'] }}" aria-label="Select all"></th>
                                            <th class="wt-col-date">Target Date</th>
                                            <th class="wt-col-type">Task Type</th>
                                            <th class="wt-col-client">Client Name</th>
                                            @foreach ($stages as $stage)
                                                <th class="wt-col-stage">{{ $stage['label'] }}</th>
                                            @endforeach
                                            <th class="wt-col-paydate">Payment Date</th>
                                            <th class="wt-col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($group['rows'] as $row)
                                            @php
                                                $target = $row['target'];
                                                $stageKey = $row['current_stage'];
                                                $stageConfig = $stages[$stageKey] ?? null;
                                                $targetDateLabel = $target->target_date?->format('M j, Y') ?? '—';
                                            @endphp
                                            <tr class="wt-row">
                                                <td class="wt-check-cell">
                                                    <input type="checkbox" class="wt-check" name="target_ids[]" value="{{ $target->id }}" form="{{ $group['form_id'] }}" data-wt-row-check="{{ $group['form_id'] }}">
                                                </td>
                                                <td class="wt-col-date">
                                                    {{ $targetDateLabel }}
                                                    <span class="wt-notes">{{ $row['staff_name'] ?: 'Unassigned' }}</span>
                                                </td>
                                                <td class="wt-col-type">
                                                    <span class="wt-pill wt-pill-idle">{{ $target->taskLabel() }}</span>
                                                </td>
                                                <td class="wt-col-client">
                                                    <span class="wt-task-type">{{ $row['client_name'] }}</span>
                                                    @if ($row['has_notes'] || $target->notes)
                                                        <span class="wt-notes">{{ $target->notes ?: 'Has notes' }}</span>
                                                    @endif
                                                </td>

                                                {{-- All four stage columns; only the band's stage is editable here. --}}
                                                @foreach ($stages as $colKey => $colStage)
                                                    @php $col = $row['stages'][$colKey]; @endphp
                                                    @if (! $col['has'] || $col['target'] === null)
                                                        <td class="wt-col-stage"><span class="wt-dash">—</span></td>
                                                    @elseif ($colKey !== $stageKey || $stageConfig === null)
                                                        @php $cTarget = $col['target']; $cDone = $cTarget->isCompleted(); @endphp
                                                        <td class="wt-col-stage">
                                                            <div class="wt-cell-row">
                                                                <span class="wt-vs-label">Actual</span>
                                                                <span class="wt-pill {{ $cDone ? 'wt-pill-done' : 'wt-pill-open' }}">{{ $col['label'] }}</span>
                                                            </div>
                                                            <span class="wt-cell-target">Target: {{ $cTarget->target_date?->format('M j, Y') ?? '—' }}</span>
                                                        </td>
                                                    @else
                                                        @php
                                                            $current = $target->actual_status;
                                                            $done = $target->isCompleted();
                                                        @endphp
                                                        <td class="wt-col-stage">
                                                            <form method="POST" action="{{ route('admin.weekly-tracker.update-stage', $target) }}" class="wt-cell-form">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="stage" value="{{ $stageKey }}">
                                                                <div class="wt-cell-row">
                                                                    <span class="wt-vs-label">Actual</span>
                                                                    <span class="wt-pill {{ $done ? 'wt-pill-done' : 'wt-pill-open' }}">{{ $colStage['statuses'][$current] ?? ucfirst((string) $current) }}</span>
                                                                </div>
                                                                <div class="wt-cell-row">
                                                                    <select name="status">
                                                                        @foreach ($colStage['statuses'] as $value => $label)
                                                                            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                @if ($stageKey === 'payment')
                                                                    <div class="wt-cell-row">
                                                                        <select name="payment_method">
                                                                            <option value="">Payment method…</option>
                                                                            @foreach ($paymentMethods as $value => $label)
                                                                                <option value="{{ $value }}" @selected($target->payment_method === $value)>{{ $label }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                @endif
                                                                <div class="wt-cell-row">
                                                                    <input type="date" name="{{ $stageKey === 'payment' ? 'payment_date' : 'actual_date' }}" value="{{ $stageKey === 'payment' ? $target->payment_at?->format('Y-m-d') : $target->ended_at?->format('Y-m-d') }}">
                                                                    <button type="submit" class="btn btn-sm btn-outline">Save</button>
                                                                </div>
                                                                <span class="wt-cell-target">Target: {{ $targetDateLabel }}</span>
                                                            </form>
                                                        </td>
                                                    @endif
                                                @endforeach

                                                <td class="wt-col-paydate">
                                                    @if ($row['payment_date_label'])
                                                        {{ $row['payment_date_label'] }}
                                                        @if ($row['payment_label'])
                                                            <span class="wt-notes">{{ $row['payment_label'] }}</span>
                                                        @endif
                                                    @else
                                                        <span class="wt-dash">—</span>
                                                    @endif
                                                </td>

                                                <td class="wt-col-actions">
                                                    @if (auth()->user()->isAdmin())
                                                        <form method="POST" action="{{ route('admin.weekly-tracker.destroy', $target) }}" onsubmit="return confirm('Delete this tracker task?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="wt-bulk" data-wt-bulk-bar="{{ $group['form_id'] }}">
                                <span class="wt-bulk-count" data-wt-bulk-count="{{ $group['form_id'] }}">0 selected</span>
                                <div class="wt-bulk-actions">
                                    <div class="wt-bulk-form">
                                        <select name="status" form="{{ $group['form_id'] }}" required>
                                            @foreach ($stages[$group['key']]['statuses'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @if ($group['key'] === 'payment')
                                            <select name="payment_method" form="{{ $group['form_id'] }}">
                                                <option value="">Payment method…</option>
                                                @foreach ($paymentMethods as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input type="date" name="payment_date" form="{{ $group['form_id'] }}" value="{{ now()->format('Y-m-d') }}">
                                        @endif
                                        <button type="submit" form="{{ $group['form_id'] }}" class="btn btn-sm btn-primary">Update selected</button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline" data-wt-clear="{{ $group['form_id'] }}">Clear selection</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
    @endforeach

    @if ($targets->isEmpty())
        <div class="empty-state">
            <h3>No tasks this week</h3>
            <p>Add a task above or move to another week. Nothing is created automatically.</p>
        </div>
    @endif

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-wt-select-all]').forEach(function (all) {
                var formId = all.dataset.wtSelectAll;
                all.addEventListener('change', function () {
                    var checked = all.checked;
                    document.querySelectorAll('[data-wt-row-check="' + formId + '"]').forEach(function (cb) {
                        cb.checked = checked;
                    });
                    toggleBulk(formId);
                });
            });

            document.querySelectorAll('[data-wt-row-check]').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    var formId = cb.dataset.wtRowCheck;
                    var all = document.querySelector('[data-wt-select-all="' + formId + '"]');
                    var total = document.querySelectorAll('[data-wt-row-check="' + formId + '"]').length;
                    var selected = document.querySelectorAll('[data-wt-row-check="' + formId + '"]:checked').length;
                    if (all) {
                        all.checked = total > 0 && selected === total;
                    }
                    toggleBulk(formId);
                });
            });

            document.querySelectorAll('[data-wt-clear]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var formId = btn.dataset.wtClear;
                    document.querySelectorAll('[data-wt-row-check="' + formId + '"]').forEach(function (cb) {
                        cb.checked = false;
                    });
                    var all = document.querySelector('[data-wt-select-all="' + formId + '"]');
                    if (all) {
                        all.checked = false;
                    }
                    toggleBulk(formId);
                });
            });

            function toggleBulk(formId) {
                var selected = document.querySelectorAll('[data-wt-row-check="' + formId + '"]:checked').length;
                var bar = document.querySelector('[data-wt-bulk-bar="' + formId + '"]');
                var count = document.querySelector('[data-wt-bulk-count="' + formId + '"]');
                if (bar) {
                    bar.classList.toggle('is-visible', selected > 0);
                }
                if (count) {
                    count.textContent = selected + (selected === 1 ? ' selected' : ' selected');
                }
            }

            document.querySelectorAll('[data-wt-bulk-bar]').forEach(function (bar) {
                bar.classList.remove('is-visible');
            });
        });
    </script>
    @endpush
@endsection
