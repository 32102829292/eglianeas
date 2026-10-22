@extends('layouts.dashboard')

@section('title', 'Weekly Bookkeeping — Egliane Accounting Services')

@section('content')
    @php
        $prefix = 'weekly-bookkeeping';
        /*
         * The tracker is a read-and-navigate board. Every count below comes from
         * the targets already loaded by the controller for the current week and
         * filter selection, so the summary always matches the task list.
         */
        $filterQuery = array_filter([
            'staff' => $activeStaff,
            'task_type' => $activeTaskType,
            'q' => $q !== '' ? $q : null,
        ]);

        $weekUrl = fn ($start, $view = null) => route('admin.weekly-bookkeeping.index', array_merge($filterQuery, [
            'week_start' => $start,
            'view' => ($view === null || $view === 'week') ? null : $view,
        ]));

        $viewUrl = fn ($view) => route('admin.weekly-bookkeeping.index', array_filter(array_merge($filterQuery, [
            'week_start' => $activeWeekStart,
            'view' => $view === 'week' ? null : $view,
        ])));

        $weekLabel = $weekStartCarbon->format('M j, Y').' – '.$weekEndCarbon->format('M j, Y');
        $activeFilterCount = count($filterQuery) + ($activeView !== 'week' ? 1 : 0);
        $weekIsListed = $weeks->contains(fn ($w) => $w->format('Y-m-d') === $activeWeekStart);

        // Completed out of everything scheduled this week.
        $weekTotal = $summary['tasks'];
        $weekDone = $summary['completed'];
        $weekPercent = $weekTotal > 0 ? (int) round(($weekDone / $weekTotal) * 100) : 0;

        $statusOptions = [
            'week' => 'All Tasks',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'attention' => 'Needs Attention',
            'unfinished' => 'Uncollected / Unfinished',
            'unpaid' => 'Unpaid',
        ];

        /*
         * Presentation-only rollups for the plan strip and the actual-progress
         * tiles. Every figure is read from the counts the controller already put
         * in $stats / $summary; no status is re-derived from the targets here and
         * nothing is persisted.
         */
        $plan = $plans->first();

        // The plan's staff is resolved from $owners, which the controller already
        // loaded for the filter dropdown, so showing the owner costs no extra
        // query. A staff id that is no longer assignable simply shows as unknown.
        $planStaff = $plan && $plan->staff_id ? $owners->firstWhere('id', $plan->staff_id) : null;
        $planOwnerName = $planStaff?->name ?? '';
        $planOwnerRole = $planStaff ? match ($planStaff->role) {
            'supervisor' => 'Supervisor',
            'staff' => 'Staff',
            'admin' => 'Admin',
            default => ucfirst((string) $planStaff->role),
        } : '';

        // Role shown under an assigned name on a task row, from the same
        // already-loaded staff list. Items carry the staff *name* only, so the
        // lookup is by name; a name that is no longer assignable just has no
        // role line. No extra query.
        $staffRoleByName = $owners->mapWithKeys(fn ($u) => [$u->name => match ($u->role) {
            'supervisor' => 'Supervisor',
            'staff' => 'Staff',
            'admin' => 'Admin',
            default => ucfirst((string) $u->role),
        }]);

        $planStatus = match (true) {
            $weekTotal === 0 => ['Not started', 'idle'],
            $summary['attention'] > 0 => ['Needs attention', 'bad'],
            $weekPercent >= 100 => ['Completed', 'ok'],
            $weekDone > 0 => ['In progress', 'info'],
            default => ['Scheduled', 'idle'],
        };

        // Actual progress, straight from the controller's per-status counts.
        $progressTiles = [
            ['label' => 'Completed', 'value' => $stats['completed'], 'tone' => 'ok'],
            ['label' => 'On time', 'value' => $stats['onTime'], 'tone' => 'ok'],
            ['label' => 'In progress / pending', 'value' => $stats['pending'], 'tone' => 'info'],
            ['label' => 'Missed / past due', 'value' => $stats['unfinished'], 'tone' => 'bad'],
            ['label' => 'Late', 'value' => $stats['late'], 'tone' => 'warn'],
            ['label' => 'Unpaid', 'value' => $stats['unpaid'], 'tone' => 'warn'],
        ];

        /*
         * Group the sheet rows exactly like the Monthly/Quarterly Bookkeeping sheet
         * so all three modules read the same way: a band per target date (the week's
         * own scheduling date, never merged across dates), with unscheduled targets
         * last. Inside each date the rows are split by task type in the canonical
         * order (Pick-Up, Record, Return / Collect, Billing / Payment) so every
         * client of a task type sits together. The controller already sorts by date
         * then task type; grouping here only labels those runs and never reorders
         * them. Purely presentational — no data is recomputed or persisted.
         */
        $sheetGroups = $rows
            ->groupBy(fn ($row) => $row['target']->target_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(function ($group, $key) use ($taskTypes) {
                return [
                    'key' => (string) $key,
                    'date' => $key === 'unscheduled' ? null : \Illuminate\Support\Carbon::parse($key),
                    'taskGroups' => collect($taskTypes)
                        ->map(fn ($label, $typeKey) => [
                            'key' => $typeKey,
                            'label' => $label,
                            'rows' => $group->filter(fn ($row) => $row['target']->task_type === $typeKey)->values(),
                        ])
                        ->filter(fn ($taskGroup) => $taskGroup['rows']->isNotEmpty())
                        ->values(),
                    'total' => $group->count(),
                    'done' => $group->filter(fn ($row) => $row['target']->isCompleted())->count(),
                ];
            })
            ->sortBy(fn ($group) => $group['key'] === 'unscheduled' ? '9999-12-31' : $group['key'])
            ->values();
    @endphp

    {{-- ============ PAGE HEADER ============ --}}
    <div class="page-head page-head-row wk-head">
        <div class="wk-head-main">
            <h1>Weekly Bookkeeping</h1>
            <p>Track this week&rsquo;s bookkeeping work.</p>
        </div>

        <div class="page-head-actions">
            <a href="{{ route('admin.weekly-bookkeeping.report', ['week_start' => $activeWeekStart]) }}" class="btn btn-outline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Report
            </a>
            <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Set Weekly Target
            </a>
        </div>
    </div>

    {{-- ============ WEEK NAVIGATOR ============ --}}
    <div class="wk-weekbar">
        <a href="{{ $weekUrl($prevWeekStart) }}" class="wk-weeknav" rel="prev" aria-label="Previous week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
        </a>

        <div class="wk-weeklabel">
            <b>{{ $weekLabel }}</b>
            <span>
                @if ($isCurrentWeek)
                    Current week
                @else
                    Week of {{ $weekStartCarbon->format('M j') }}
                @endif
            </span>
        </div>

        <a href="{{ $weekUrl($nextWeekStart) }}" class="wk-weeknav" rel="next" aria-label="Next week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
        </a>

        @unless ($isCurrentWeek)
            <a href="{{ $weekUrl($currentWeekStart) }}" class="wk-week-today">This Week</a>
        @endunless

        <div class="wk-weekbar-spacer"></div>

        {{-- Quick actions. "Set Weekly Target" and "Print Report" already sit in
             the page header above, so only the navigation shortcuts live here. --}}
        <nav class="wk-quick" aria-label="Quick actions">
            @if ($plans->isNotEmpty())
                <a href="{{ route('admin.weekly-bookkeeping.show', $plans->first()->id) }}">Assign Tasks</a>
            @else
                <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}">Assign Tasks</a>
            @endif
            <a href="{{ $viewUrl('pending') }}">View Pending</a>
            <a href="{{ $viewUrl('attention') }}">View Attention</a>
        </nav>
    </div>

    {{-- ============ WEEKLY TARGET / PLAN ============ --}}
    <section class="wk-plan" aria-label="Weekly target">
        <span class="wk-plan-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </span>

        <div class="wk-plan-main">
            <b>Weekly Target</b>
            <span>{{ $weekLabel }}</span>
        </div>

        <div class="wk-plan-facts">
            <div class="wk-fact">
                <span class="wk-fact-label">Status</span>
                <span class="wk-fact-value">{{ $planStatus[0] }}</span>
                @if ($plans->isNotEmpty())
                    <span class="wk-fact-sub">{{ $plans->count() }} {{ $plans->count() === 1 ? 'plan' : 'plans' }} saved</span>
                @else
                    <span class="wk-fact-sub">No plan yet</span>
                @endif
            </div>
            <div class="wk-fact">
                <span class="wk-fact-label">Owner</span>
                <span class="wk-fact-value">{{ $planOwnerName !== '' ? $planOwnerName : 'Not assigned' }}</span>
                <span class="wk-fact-sub">{{ $planOwnerRole !== '' ? $planOwnerRole : 'No owner set' }}</span>
            </div>
        </div>
    </section>

    {{-- ============ NEXT STEP HINT ============ --}}
    {{-- One plain-language line telling the reader what to do next, computed
         only from counts the controller already sent. Never blocks anything. --}}
    @if ($plans->isEmpty())
        <p class="wk-nextstep">
            <b>Next step:</b> no target is set for this week yet.
            <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}">Set this week&rsquo;s target</a>
            to assign work to your team.
        </p>
    @elseif ($summary['attention'] > 0)
        <p class="wk-nextstep is-warn">
            <b>Next step:</b> {{ $summary['attention'] }} {{ \Illuminate\Support\Str::plural('task', $summary['attention']) }} {{ $summary['attention'] === 1 ? 'needs' : 'need' }} attention
            (overdue, unassigned or unpaid) &mdash; see <a href="#wk-todo-h">What Needs to Be Done</a> below.
        </p>
    @elseif ($summary['pending'] > 0)
        <p class="wk-nextstep">
            <b>Next step:</b> {{ $summary['pending'] }} {{ \Illuminate\Support\Str::plural('task', $summary['pending']) }} still open this week &mdash;
            start or complete {{ $summary['pending'] === 1 ? 'it' : 'them' }} from the task list below.
        </p>
    @else
        <p class="wk-nextstep is-ok">
            <b>All done:</b> every task scheduled for this week is complete.
        </p>
    @endif

    {{-- ============ FILTERS (collapsible) ============ --}}
    <details class="wk-filters"{!! $hasActiveFilters ? ' open' : '' !!}>
        <summary class="wk-filters-summary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Filters</span>
            @if ($activeFilterCount > 0)
                <span class="wk-filters-count">{{ $activeFilterCount }}</span>
            @endif
            @if ($hasActiveFilters)
                <span class="wk-filters-active">Filtered view</span>
            @endif
        </summary>

        <form method="GET" action="{{ route('admin.weekly-bookkeeping.index') }}" class="wk-filters-form">
            <input type="hidden" name="week_start" value="{{ $activeWeekStart }}">

            <label class="wk-field">
                <span>Week</span>
                <select name="week_start" onchange="this.form.submit()">
                    @foreach ($weeks as $weekOption)
                        <option value="{{ $weekOption->format('Y-m-d') }}" @selected($weekOption->format('Y-m-d') === $activeWeekStart)>
                            {{ $weekOption->format('M j, Y') }} – {{ $weekOption->copy()->endOfWeek()->format('M j, Y') }}
                        </option>
                    @endforeach
                    @unless ($weekIsListed)
                        <option value="{{ $activeWeekStart }}" selected>
                            {{ $weekStartCarbon->format('M j, Y') }} – {{ $weekEndCarbon->format('M j, Y') }}
                        </option>
                    @endunless
                </select>
            </label>

            @if ($seesAll)
                <label class="wk-field">
                    <span>Staff</span>
                    <select name="staff" onchange="this.form.submit()">
                        <option value="">All Staff</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected($activeStaff === $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="wk-field">
                <span>Status</span>
                <select name="view" onchange="this.form.submit()">
                    @foreach ($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($activeView === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="wk-field">
                <span>Task</span>
                <select name="task_type" onchange="this.form.submit()">
                    <option value="">All Tasks</option>
                    @foreach ($taskTypes as $key => $label)
                        <option value="{{ $key }}" @selected($activeTaskType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="wk-field wk-field-grow">
                <span>Client</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search client name&hellip;">
            </label>

            <div class="wk-filters-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                @if ($hasActiveFilters)
                    <a href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $activeWeekStart]) }}" class="btn btn-ghost btn-sm">Clear Filters</a>
                @endif
            </div>
        </form>
    </details>

    {{-- ============ WEEKLY BOOKKEEPING SHEET (main content) ============ --}}
    {{-- The comparison spreadsheet is the primary view. It matches the workbook
         columns — Target Date, Task Type, Client Name, the four stages and the
         Payment Date — with a dash wherever a column does not apply. --}}
    <section class="wk-section-block" aria-labelledby="wk-report-h">
        <div class="wk-section-head">
            <h2 id="wk-report-h" class="wk-section-title">Weekly Bookkeeping Sheet</h2>
            <span class="wk-section-note">{{ $weekDone }} of {{ $weekTotal }} complete</span>
        </div>

        <p class="wk-report-hint">One row per client task. A task only fills the column it belongs to; the rest stay &ldquo;&ndash;&rdquo;. Use the stage links to open a task&rsquo;s plan.</p>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <p>{{ $hasActiveFilters ? 'No targets match this selection for the selected week.' : 'No targets set for this week.' }}</p>
                @unless ($hasActiveFilters)
                    <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}" class="btn btn-primary btn-sm">Set Weekly Target</a>
                @else
                    <a href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $activeWeekStart]) }}" class="btn btn-outline btn-sm">Clear Filters</a>
                @endunless
            </div>
        @else
            <div class="table-scroll">
                <table class="table wk-grid wk-grid-dense">
                    <thead>
                        <tr>
                            <th scope="col" class="wk-col-date">Target Date</th>
                            <th scope="col" class="wk-col-task">Task Type</th>
                            <th scope="col" class="wk-col-client">Client Name</th>
                            <th scope="col" class="wk-col-staff">Assigned Staff</th>
                            <th scope="col" class="wk-col-stage wk-col-pickup">Pick-Up</th>
                            <th scope="col" class="wk-col-stage wk-col-record">Record</th>
                            <th scope="col" class="wk-col-stage wk-col-return">Return</th>
                            <th scope="col" class="wk-col-stage wk-col-billing">Billing</th>
                            <th scope="col" class="wk-col-paymentdate">Payment Date</th>
                            <th scope="col" class="wk-col-actions">Actions</th>
                        </tr>
                    </thead>
                    @foreach ($sheetGroups as $periodGroup)
                        <tbody class="wk-dayband">
                            <tr class="wk-dayhead">
                                <td colspan="10">
                                    <span class="wk-dayhead-name">
                                        @if ($periodGroup['date'])
                                            {{ $periodGroup['date']->format('l') }} {{ $periodGroup['date']->format('m/d/Y') }}
                                        @else
                                            Unscheduled
                                        @endif
                                    </span>
                                    <span class="wk-dayhead-count">{{ $periodGroup['done'] }}/{{ $periodGroup['total'] }} done</span>
                                </td>
                            </tr>
                            @foreach ($periodGroup['taskGroups'] as $taskGroup)
                                <tr class="wk-taskhead">
                                    <td colspan="10">
                                        <span class="wk-taskhead-name">{{ $taskGroup['label'] }}</span>
                                        <span class="wk-taskhead-count">{{ $taskGroup['rows']->count() }} {{ \Illuminate\Support\Str::plural('client', $taskGroup['rows']->count()) }}</span>
                                    </td>
                                </tr>
                                @foreach ($taskGroup['rows'] as $row)
                                    @php($t = $row['target'])
                                    <tr class="wk-row wk-row-{{ $t->effectiveStatus() }}">
                                        <td class="wk-date">
                                            <span class="wk-day">{{ $t->target_date?->format('D') ?? '—' }}</span>
                                            <span class="wk-datefull">{{ $t->target_date?->format('M j, Y') ?? 'Any day' }}</span>
                                        </td>
                                        <td class="wk-task">{{ $t->taskLabel() }}</td>
                                        <td class="wk-client" title="{{ $t->displayClientName() }}">{{ $t->displayClientName() }}</td>
                                        <td class="wk-staff">
                                            <span class="wk-person">
                                                <span class="wk-person-name">{{ $t->assignedStaffDisplayName() !== '' ? $t->assignedStaffDisplayName() : 'Unassigned' }}</span>
                                                <span class="wk-person-sub {{ $t->assignedStaffDisplayName() === '' ? 'is-empty' : '' }}">
                                                    {{ $t->assignedStaffDisplayName() === ''
                                                        ? 'No staff assigned'
                                                        : ($staffRoleByName[$t->assignedStaffDisplayName()] ?? 'Assigned') }}
                                                </span>
                                            </span>
                                        </td>
                                        <td>@include('admin.weekly-bookkeeping.partials.cell', ['target' => $row['cells']['pickup']])</td>
                                        <td>@include('admin.weekly-bookkeeping.partials.cell', ['target' => $row['cells']['record']])</td>
                                        <td>@include('admin.weekly-bookkeeping.partials.cell', ['target' => $row['cells']['return']])</td>
                                        <td>@include('admin.weekly-bookkeeping.partials.cell', ['target' => $row['cells']['payment']])</td>
                                        <td class="wk-paydate">
                                            @if ($t->task_type === 'payment' && $t->payment_at)
                                                <span class="wk-day">{{ $t->payment_at->format('M j, Y') }}</span>
                                                @if ($t->paymentLabel())
                                                    <span class="wk-person-sub">{{ $t->paymentLabel() }}</span>
                                                @endif
                                            @else
                                                <span class="cmp-none" aria-label="Not applicable">&ndash;</span>
                                            @endif
                                        </td>
                                        <td class="wk-col-actions">
                                            <button type="button" class="btn btn-ghost btn-sm wk-open" data-bs-toggle="modal" data-bs-target="#bkTaskModal" data-bk-task-id="{{ $t->id }}" data-bk-booking-id="{{ $t->weekly_bookkeeping_id }}">Open</button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            {{-- Workbook side columns: work not finished, and billing not paid. --}}
            @if (in_array($activeView, ['week', 'unfinished', 'unpaid'], true))
                <div class="wk-side">
                    <div class="wk-side-card">
                        <h3>Uncollected / Unfinished</h3>
                        @if ($unfinishedRows->isEmpty())
                            <p class="wk-side-empty">Nothing outstanding this week.</p>
                        @else
                            <ul class="wk-side-list">
                                @foreach ($unfinishedRows as $sideRow)
                                    <li>
                                        <a href="{{ route('admin.weekly-bookkeeping.show', $sideRow['target']->weekly_bookkeeping_id) }}#target-{{ $sideRow['target']->id }}">
                                            <b>{{ $sideRow['target']->displayClientName() }}</b>
                                            <span>{{ $sideRow['target']->taskLabel() }}</span>
                                            <em class="wk-side-status is-bad">{{ $sideRow['target']->effectiveStatusLabel() }}</em>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="wk-side-card">
                        <h3>Unpaid</h3>
                        @if ($unpaidRows->isEmpty())
                            <p class="wk-side-empty">No unpaid billing this week.</p>
                        @else
                            <ul class="wk-side-list">
                                @foreach ($unpaidRows as $sideRow)
                                    <li>
                                        <a href="{{ route('admin.weekly-bookkeeping.show', $sideRow['target']->weekly_bookkeeping_id) }}#target-{{ $sideRow['target']->id }}">
                                            <b>{{ $sideRow['target']->displayClientName() }}</b>
                                            <span>{{ $sideRow['target']->taskLabel() }}</span>
                                            <em class="wk-side-status is-warn">Unpaid</em>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </section>

    {{-- ============ AT A GLANCE (collapsible summary) ============ --}}
    {{-- Secondary detail. The sheet above is the main view; this collapsed block
         keeps the period's counts, priorities and progress one click away. --}}
    <details class="wk-glance">
        <summary class="wk-glance-summary">
            <h2 class="wk-section-title">Week at a Glance</h2>
            <span class="wk-section-note">{{ $weekDone }} of {{ $weekTotal }} complete{{ $summary['attention'] > 0 ? ' · '.$summary['attention'].' need attention' : '' }}</span>
            <span class="wk-report-toggle" aria-hidden="true"></span>
        </summary>
        <div class="wk-glance-body">

            {{-- ============ THIS WEEK ============ --}}
            <section class="wk-section-block" aria-labelledby="wk-summary-h">
                <div class="wk-section-head">
                    <h2 id="wk-summary-h" class="wk-section-title">This Week</h2>
                    <span class="wk-section-note">{{ $weekDone }} of {{ $weekTotal }} complete</span>
                </div>

                <div class="stat-grid wk-summary">
                    <div class="stat-card">
                        <span class="stat-label">Clients</span>
                        <b class="stat-value">{{ $summary['clients'] }}</b>
                        <span class="stat-meta">With scheduled work</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Tasks</span>
                        <b class="stat-value">{{ $summary['tasks'] }}</b>
                        <span class="stat-meta">Scheduled this week</span>
                    </div>
                    <div class="stat-card {{ $summary['completed'] > 0 ? 'stat-ok' : '' }}">
                        <span class="stat-label">Completed</span>
                        <b class="stat-value">{{ $summary['completed'] }}</b>
                        <span class="stat-meta">{{ $weekPercent }}% of this week</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pending</span>
                        <b class="stat-value">{{ $summary['pending'] }}</b>
                        <span class="stat-meta">Not finished yet</span>
                    </div>
                    <div class="stat-card {{ $summary['attention'] > 0 ? 'stat-danger' : '' }}">
                        <span class="stat-label">Attention</span>
                        <b class="stat-value">{{ $summary['attention'] }}</b>
                        <span class="stat-meta">Overdue, unpaid or unassigned</span>
                    </div>
                </div>

                {{-- Completion read-out: the headline percentage, stated in text as well as bar length. --}}
                <div class="card wk-done">
                    <div class="wk-done-figure">
                        <b>{{ $weekPercent }}%</b>
                        <span>complete</span>
                    </div>
                    <div class="wk-bar wk-done-track" role="img" aria-label="{{ $weekPercent }} percent of this week's tasks completed">
                        <span class="wk-bar-fill {{ $summary['attention'] > 0 ? 'is-warn' : 'is-ok' }}" style="width: {{ $weekPercent }}%"></span>
                    </div>
                </div>

                {{-- Actual progress: the per-status counts the controller already computed.
                     Labelled in text so the breakdown never relies on colour alone. --}}
                <div class="wk-tiles">
                    @foreach ($progressTiles as $tile)
                        <div class="wk-tile is-{{ $tile['tone'] }}">
                            <span class="wk-tile-dot" aria-hidden="true"></span>
                            <span class="wk-tile-text">
                                <b>{{ $tile['value'] }}</b>
                                <span>{{ $tile['label'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ TODAY'S PRIORITIES ============ --}}
            <section class="wk-section-block" aria-labelledby="wk-today-h">
                <div class="wk-section-head">
                    <h2 id="wk-today-h" class="wk-section-title">Today&rsquo;s Priorities</h2>
                    <span class="wk-section-note">{{ now()->format('D, M j') }}</span>
                </div>

                @if ($todayTargets->isEmpty())
                    <div class="card wk-clear">
                        <div class="wk-clear-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <b>Nothing requires attention today.</b>
                            <span>{{ $summary['pending'] > 0 ? $summary['pending'].' task'.($summary['pending'] === 1 ? '' : 's').' still scheduled this week.' : 'No open tasks for this week yet.' }}</span>
                        </div>
                    </div>
                @else
                    <ul class="wk-today-list">
                        @foreach ($todayItems->take(5) as $item)
                            <li class="wk-today-item">
                                <span class="wk-dot wk-dot-{{ $item['status'] }}" aria-hidden="true"></span>
                                <div class="wk-today-main">
                                    <b>{{ $item['client_name'] }}</b>
                                    <span>{{ $item['task_label'] }} &middot; {{ $item['target_date']?->format('D, M j') ?? 'No date' }}</span>
                                </div>
                                <span class="wk-pill wk-pill-{{ $item['status'] }}">{{ $item['status_label'] }}</span>
                                <a href="{{ $item['show_url'] }}" class="wk-today-open">{{ $item['can_manage'] ? 'Open' : 'View' }}</a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($todayTargets->count() > 5)
                        <p class="wk-more">+ {{ $todayTargets->count() - 5 }} more in the task list below.</p>
                    @endif
                @endif
            </section>

            {{-- ============ WHAT NEEDS TO BE DONE ============ --}}
            <section class="wk-section-block" aria-labelledby="wk-todo-h">
                <div class="wk-section-head">
                    <h2 id="wk-todo-h" class="wk-section-title">What Needs to Be Done</h2>
                    <span class="wk-section-note">{{ $clientGroups->count() }} {{ $clientGroups->count() === 1 ? 'client' : 'clients' }}</span>
                </div>

                @if ($clientGroups->isEmpty())
                    <div class="card wk-clear">
                        <div class="wk-clear-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div>
                            <b>{{ $hasActiveFilters ? 'No tasks match these filters.' : 'No targets set for this week.' }}</b>
                            <span>{{ $hasActiveFilters ? 'Try clearing the filters to see the whole week.' : 'Add a weekly target to start tracking this week&rsquo;s work.' }}</span>
                        </div>
                        @unless ($hasActiveFilters)
                            <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}" class="btn btn-primary btn-sm">Set Weekly Target</a>
                        @else
                            <a href="{{ route('admin.weekly-bookkeeping.index', ['week_start' => $activeWeekStart]) }}" class="btn btn-outline btn-sm">Clear Filters</a>
                        @endunless
                    </div>
                @else
                    <div class="wk-clients">
                        @foreach ($clientGroups as $group)
                            <details class="wk-client"{!! ($group['attention'] > 0 || $loop->index < 3) ? ' open' : '' !!}>
                                <summary class="wk-client-head">
                                    <span class="wk-client-caret" aria-hidden="true"></span>
                                    <b class="wk-client-name">{{ $group['name'] }}</b>

                                    @if ($group['attention'] > 0)
                                        <span class="wk-badge wk-badge-attention">{{ $group['attention'] }} need attention</span>
                                    @endif

                                    <span class="wk-client-count">{{ $group['completed'] }}/{{ $group['total'] }} done</span>

                                    <span class="wk-mini" role="img" aria-label="{{ $group['completed'] }} of {{ $group['total'] }} tasks completed">
                                        <span class="wk-mini-bar" style="width: {{ $group['total'] > 0 ? (int) round(($group['completed'] / $group['total']) * 100) : 0 }}%"></span>
                                    </span>
                                </summary>

                                <ul class="wk-tasks">
                                    @foreach ($group['items'] as $item)
                                        <li class="wk-task wk-task-{{ $item['status'] }}">
                                            <span class="wk-dot wk-dot-{{ $item['status'] }}" aria-hidden="true"></span>

                                            <div class="wk-task-main">
                                                <b>{{ $item['task_label'] }}</b>
                                                <span class="wk-task-meta">
                                                    <span class="{{ $item['is_overdue'] ? 'is-overdue' : '' }}">{{ $item['due_label'] }}</span>
                                                </span>

                                                {{-- Balance and remarks stay inline so the tracker keeps its
                                                     column count; the detail lives on the plan page. --}}
                                                @if (! empty($item['balance_summary']))
                                                    <span class="wk-badge wk-badge-balance" title="{{ $item['balance_note'] ?? 'Payment balance' }}">
                                                        {{ $item['balance_summary'] }}
                                                    </span>
                                                @endif

                                                @if (! empty($item['has_remarks']))
                                                    <span class="wk-badge wk-badge-remark" title="Remarks left on this task">Remarks</span>
                                                @endif
                                            </div>

                                            <div class="wk-task-staff">
                                                <span class="wk-person-name">{{ $item['staff_name'] !== '' ? $item['staff_name'] : 'Not Assigned' }}</span>
                                                <span class="wk-person-sub {{ $item['staff_name'] === '' ? 'is-empty' : '' }}">
                                                    {{ $item['staff_name'] === ''
                                                        ? 'No staff assigned'
                                                        : ($staffRoleByName[$item['staff_name']] ?? 'Assigned') }}
                                                </span>
                                            </div>

                                            {{-- Evidence: presence only, taken from the item's existing
                                                 has_attachment flag. The paperclip links straight to
                                                 that task's row on the show page, where the existing
                                                 View/Download buttons already live. --}}
                                            <span class="wk-evi">
                                                @if ($item['has_attachment'])
                                                    <a class="wk-evi-has" href="{{ $item['show_url'] }}#target-{{ $item['id'] }}" title="Open the evidence for this task">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                        <span aria-hidden="true">&#128206;</span> Evidence
                                                    </a>
                                                @else
                                                    <span class="wk-evi-none"><span aria-hidden="true">&#128206;</span> No evidence</span>
                                                @endif
                                            </span>

                                            <span class="wk-pill wk-pill-{{ $item['status'] }}">{{ $item['status_label'] }}</span>

                                            <div class="wk-task-actions">
                                                @if ($item['can_start'])
                                                    <form method="POST" action="{{ route('admin.weekly-bookkeeping.start-target', [$item['bookkeeping_id'], $item['id']]) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-sm">Start</button>
                                                    </form>
                                                @endif
                                                <a href="{{ $item['show_url'] }}" class="btn btn-ghost btn-sm">
                                                    {{ $item['can_manage'] ? 'Open' : 'View' }}
                                                </a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ============ PROGRESS ============ --}}
            @if ($weekTotal > 0)
                <section class="wk-section-block" aria-labelledby="wk-progress-h">
                    <div class="wk-section-head">
                        <h2 id="wk-progress-h" class="wk-section-title">Week Progress</h2>
                        <span class="wk-section-note">{{ $weekDone }} of {{ $weekTotal }} complete</span>
                    </div>

                    <div class="card wk-progress-card">
                        <div class="wk-progress-top">
                            <div class="wk-progress-head">
                                <b>{{ $weekPercent }}%</b>
                                <span>of this week&rsquo;s tasks completed</span>
                            </div>
                            @if ($summary['attention'] > 0)
                                <span class="wk-badge wk-badge-attention">{{ $summary['attention'] }} needing attention</span>
                            @else
                                <span class="wk-badge wk-badge-ok">On track</span>
                            @endif
                        </div>

                        <div class="wk-bar" role="img" aria-label="{{ $weekPercent }} percent of tasks completed">
                            <span class="wk-bar-fill {{ $summary['attention'] > 0 ? 'is-warn' : 'is-ok' }}" style="width: {{ $weekPercent }}%"></span>
                        </div>

                        <ul class="wk-types">
                            @foreach ($typeProgress as $type)
                                <li>
                                    <div class="wk-type-head">
                                        <span>{{ $type['label'] }}</span>
                                        <b>{{ $type['completed'] }}/{{ $type['total'] }}</b>
                                    </div>
                                    <div class="wk-bar wk-bar-sm" role="img" aria-label="{{ $type['label'] }}: {{ $type['completed'] }} of {{ $type['total'] }} complete">
                                        <span class="wk-bar-fill {{ $type['attention'] > 0 ? 'is-warn' : 'is-ok' }}" style="width: {{ $type['percent'] }}%"></span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

        </div>
    </details>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('bkTaskModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const body = document.getElementById('bkTaskModalBody');
    const title = document.getElementById('bkTaskModalTitle');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const taskId = btn.getAttribute('data-bk-task-id');
        const bookingId = btn.getAttribute('data-bk-booking-id');
        const prefix = '{{ $prefix }}';
        title.textContent = 'Task Details';
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small text-muted">Loading task details...</div></div>';
        fetch('/admin/' + prefix + '/' + bookingId + '/task/' + taskId, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(r => {
                if(!r.ok){
                    throw new Error('Request failed: ' + r.status);
                }
                return r.json();
            })
.then(data => {
                const t = data.target || {};
                const routes = data.routes || {};
                const badge = t.is_completed ? 'bk-badge bk-badge-completed' : (t.is_in_progress ? 'bk-badge bk-badge-inprogress' : 'bk-badge bk-badge-pending');
                let html = '';
                html += '<div class="bk-modal-section">';
                html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Task Overview</div><span class="'+badge+'">'+(t.status_label||t.status||'—')+'</span></div>';
                html += '  <div class="bk-modal-section-body"><div class="bk-modal-grid">';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Client</span><span class="bk-modal-value">'+(t.client_name||'—')+'</span></div>';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Task Type</span><span class="bk-modal-value">'+(t.task_label||t.task_type||'—')+'</span></div>';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Assigned Staff</span><span class="bk-modal-value">'+(t.assigned_staff||'Unassigned')+'</span></div>';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Target Date</span><span class="bk-modal-value">'+(t.target_date_label||'—')+(t.date_auto?' <span class="text-muted">(Automatic)</span>':' <span class="text-muted">(Manually adjusted)</span>')+'</span></div>';
                html += '  </div></div>';
                html += '</div>';

                html += '<div class="bk-modal-section">';
                html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Actual Progress</div></div>';
                html += '  <div class="bk-modal-section-body"><div class="bk-modal-grid">';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Started</span><span class="bk-modal-value">'+(t.started_at||'—')+'</span></div>';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Completed</span><span class="bk-modal-value">'+(t.ended_at||'—')+'</span></div>';
                html += '    <div class="bk-modal-field"><span class="bk-modal-label">Performed By</span><span class="bk-modal-value">'+(t.performed_by||'—')+(t.performed_by_role? ' ('+t.performed_by_role+')':'')+'</span></div>';
                html += '  </div>'+(t.duration? '<div class="small text-muted mt-2">Duration: '+t.duration+(t.timing_label?' ('+t.timing_label+')':'')+'</div>':'')+'</div>';
                html += '</div>';

                html += '<div class="bk-modal-section">';
                html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Target Date & Remarks</div></div>';
                html += '  <div class="bk-modal-section-body">';
                html += '    <div class="mb-2"><span class="bk-modal-label">Remarks / Notes</span><div class="mt-1">'+(t.notes ? t.notes.replace(/\n/g,'<br>') : '<span class="text-muted">No remarks</span>')+'</div></div>';
                html += '    '+(t.can_manage && routes.update_target ? '<form method="POST" action="'+routes.update_target+'" class="row g-2 align-items-end">@csrf @method("PATCH")<div class="col-md-4"><label class="form-label small mb-1">Target Date</label><input type="date" name="target_date" class="form-control form-control-sm" value="'+(t.target_date||'')+'"></div><div class="col-md-4"><label class="form-label small mb-1">Explanation for date change <span class="text-danger">*</span></label><input type="text" name="explanation" class="form-control form-control-sm" placeholder="Required if changing date" required></div><div class="col-md-4"><label class="form-label small mb-1">Remarks/Notes</label><input type="text" name="notes" class="form-control form-control-sm" placeholder="Add/update notes" value="'+(t.notes? String(t.notes).replace(/"/g,'"'):'')+'"></div><div class="col-12"><button class="btn btn-sm btn-outline-secondary">Update Target Date & Remarks</button></div></form>' : '');
                html += '  </div>';
                html += '</div>';

                html += '<div class="bk-modal-section">';
                html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Evidence</div></div>';
                html += '  <div class="bk-modal-section-body">';
                if(data.evidence && data.evidence.has){
                    html += '    <div class="d-flex flex-wrap align-items-center gap-2"><span class="bk-badge bk-badge-inprogress">Evidence Submitted</span><a class="btn btn-sm btn-outline-primary" href="'+data.evidence.view_url+'" target="_blank" rel="noopener">View</a><a class="btn btn-sm btn-outline-secondary" href="'+data.evidence.download_url+'" download>Download</a>'+(data.evidence.name? '<span class="small text-muted">'+data.evidence.name+'</span>':'')+'</div>';
                } else {
                    html += '    <span class="text-muted">No evidence submitted yet</span>';
                }
                if(t.can_manage && routes.upload){
                    html += '    <form method="POST" action="'+routes.upload+'" enctype="multipart/form-data" class="row g-2 align-items-end mt-2">@csrf<div class="col-md-6"><input type="file" name="evidence" class="form-control form-control-sm" required></div><div class="col-auto"><button class="btn btn-sm btn-outline-primary">Upload Evidence</button></div></form>';
                }
                html += '  </div>';
                html += '</div>';

                if(t.is_payment){
                    html += '<div class="bk-modal-section">';
                    html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Payment Details</div></div>';
                    html += '  <div class="bk-modal-section-body"><div class="bk-modal-grid">';
                    html += '    <div class="bk-modal-field"><span class="bk-modal-label">Payment Status</span><span class="bk-modal-value">'+(t.payment_label||'—')+'</span></div>';
                    html += '    <div class="bk-modal-field"><span class="bk-modal-label">Payment Date</span><span class="bk-modal-value">'+(t.payment_at_label||'—')+'</span></div>';
                    html += '    <div class="bk-modal-field"><span class="bk-modal-label">Payment Method</span><span class="bk-modal-value">'+(t.payment_method||'—')+'</span></div>';
                    html += '    <div class="bk-modal-field"><span class="bk-modal-label">Payment Detail</span><span class="bk-modal-value">'+(t.payment_detail||'—')+'</span></div>';
                    html += '  </div></div>';
                    html += '</div>';
                }

                html += '<div class="bk-modal-section">';
                html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Task History</div></div>';
                html += '  <div class="bk-modal-section-body">';
                if(data.history && data.history.length){
                    html += '    <div class="bk-timeline">';
                    data.history.forEach(function(h){
                        html += '      <div class="bk-timeline-item"><div class="bk-timeline-dot"></div><div class="bk-timeline-content"><div class="bk-timeline-title">'+(h.action||'Activity')+'</div><div>'+ (h.description||'') +'</div><div class="bk-timeline-meta">'+(h.user_name? h.user_name+' • ':'')+(h.created_at||'')+'</div></div></div>';
                    });
                    html += '    </div>';
                } else {
                    html += '    <span class="text-muted">No history available</span>';
                }
                html += '  </div>';
                html += '</div>';

                if(t.can_manage){
                    html += '<div class="bk-modal-section">';
                    html += '  <div class="bk-modal-section-header"><div class="bk-modal-section-title">Task Operations</div></div>';
                    html += '  <div class="bk-modal-section-body d-flex flex-wrap gap-2">';
                    if(routes.start){ html += '<form method="POST" action="'+routes.start+'">@csrf<button class="btn btn-sm btn-primary">Start Task</button></form>'; }
                    if(routes.complete){ html += '<form method="POST" action="'+routes.complete+'">@csrf<button class="btn btn-sm btn-success">Mark Complete</button></form>'; }
                    html += '  </div>';
                    html += '</div>';
                }
                body.innerHTML = html;
            })
            .catch(() => { body.innerHTML = '<div class="text-center text-danger py-4">Failed to load task details.<br><button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">Reload Page</button></div>'; });
    });
});
</script>
@endpush

<div class="modal fade" id="bkTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bkTaskModalTitle">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bkTaskModalBody" style="max-height: calc(100vh - 200px); overflow-y: auto;">
            <div class="text-center py-4">Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
</div>
</div>

@push('styles')
    @include('admin.bookkeeping.partials.styles')
    {{-- Task Details Modal styles matching Monthly/Quarterly Bookkeeping --}}
    <style>
        #bkTaskModal .modal-content { border-radius: 12px; overflow: hidden; }
        #bkTaskModal .modal-header { background: linear-gradient(90deg, #0f1f3d 0%, #1a2f5c 100%); color: #fff; border-bottom: none; }
        #bkTaskModal .modal-header .btn-close { filter: invert(1) grayscale(100%); }
        #bkTaskModal .modal-title { font-weight: 700; letter-spacing: 0.2px; }
        #bkTaskModal .modal-body { background: #f8fafc; padding: 1rem 1.25rem; }
        #bkTaskModal .modal-footer { background: #f8fafc; border-top: 1px solid #e5e7eb; }
        .bk-modal-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 2px rgba(15,31,61,0.02); margin-bottom: 1rem; overflow: hidden; }
        .bk-modal-section-header { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.9rem; background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb; }
        .bk-modal-section-title { font-weight: 700; color: #0f1f3d; font-size: 0.95rem; }
        .bk-modal-section-body { padding: 0.8rem 0.9rem; }
        .bk-modal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.6rem 0.9rem; }
        .bk-modal-field { display: flex; flex-direction: column; gap: 0.25rem; }
        .bk-modal-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
        .bk-modal-value { font-weight: 600; color: #111827; }
        .bk-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .bk-badge-pending { background: #f3f4f6; color: #374151; }
        .bk-badge-inprogress { background: #e8f4ff; color: #1d4ed8; }
        .bk-badge-completed { background: #ecfdf5; color: #047857; }
        .bk-badge-overdue { background: #fef2f2; color: #b91c1c; }
        .bk-timeline { display: flex; flex-direction: column; gap: 0.6rem; max-height: 220px; overflow-y: auto; }
        .bk-timeline-item { display: grid; grid-template-columns: 10px 1fr; gap: 0.7rem; align-items: start; }
        .bk-timeline-dot { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; margin-top: 0.35rem; }
        .bk-timeline-content { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.5rem 0.7rem; }
        .bk-timeline-title { font-weight: 600; color: #111827; }
        .bk-timeline-meta { font-size: 0.8rem; color: #6b7280; }
        @media (max-width: 768px) {
            #bkTaskModal .modal-dialog { margin: 0.5rem; }
            #bkTaskModal .modal-body { padding: 0.75rem; }
        }
    </style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* Guards the tracker's inline action forms (Start, etc.) against a double
       click posting the same action twice. Filters and links are unaffected. */
    document.querySelectorAll('form.d-inline').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]:not([disabled])');
            if (button) {
                button.disabled = true;
            }
        });
    });
})();
</script>
@endpush
