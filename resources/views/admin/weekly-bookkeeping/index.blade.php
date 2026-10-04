@extends('layouts.dashboard')

@section('title', 'Weekly Bookkeeping — Egliane Accounting Services')

@section('content')
    @php
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

        {{-- Quick actions --}}
        <nav class="wk-quick" aria-label="Quick actions">
            <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}">Add Weekly Target</a>
            @if ($plans->isNotEmpty())
                <a href="{{ route('admin.weekly-bookkeeping.show', $plans->first()->id) }}">Assign Tasks</a>
            @else
                <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}">Assign Tasks</a>
            @endif
            <a href="{{ $viewUrl('pending') }}">View Pending</a>
            <a href="{{ $viewUrl('attention') }}">View Attention</a>
            <a href="{{ route('admin.weekly-bookkeeping.report', ['week_start' => $activeWeekStart]) }}">Print Report</a>
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
                                         has_attachment flag. Opening it stays on the show page. --}}
                                    <span class="wk-evi">
                                        @if ($item['has_attachment'])
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span class="wk-evi-has">Evidence</span>
                                        @else
                                            <span class="wk-evi-none">No evidence</span>
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

    {{-- ============ WEEKLY PROGRESS ============ --}}
    @if ($weekTotal > 0)
        <section class="wk-section-block" aria-labelledby="wk-progress-h">
            <div class="wk-section-head">
                <h2 id="wk-progress-h" class="wk-section-title">Weekly Progress</h2>
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

    {{-- ============ DETAILED WEEKLY REPORT ============ --}}
    <section class="wk-section-block" aria-labelledby="wk-report-h">
        <details class="card wk-report">
            <summary class="wk-report-summary">
                <h2 id="wk-report-h" class="wk-section-title">Detailed Weekly Report</h2>
                <span class="wk-section-note">Target vs actual, side by side</span>
                <span class="wk-report-toggle" aria-hidden="true"></span>
            </summary>

            <p class="wk-report-hint">A task only fills the column it belongs to; the rest stay &ldquo;&ndash;&rdquo;.</p>

            @if ($rows->isEmpty())
                <div class="empty-state">
                    <p>No targets match this selection for the selected week.</p>
                    <a href="{{ route('admin.weekly-bookkeeping.create', ['week_start' => $activeWeekStart]) }}" class="btn btn-primary btn-sm">Set Weekly Target</a>
                </div>
            @else
                <div class="table-scroll">
                    <table class="table wk-grid">
                        <thead>
                            <tr>
                                <th scope="col">Target Date</th>
                                <th scope="col">Task Type</th>
                                <th scope="col">Client Name</th>
                                <th scope="col">Assigned Staff</th>
                                <th scope="col" class="wk-col-pickup">Pick-Up</th>
                                <th scope="col" class="wk-col-record">Record</th>
                                <th scope="col" class="wk-col-return">Return</th>
                                <th scope="col" class="wk-col-billing">Billing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php($t = $row['target'])
                                <tr class="wk-row wk-row-{{ $t->effectiveStatus() }}">
                                    <td class="wk-date">
                                        <span class="wk-day">{{ $t->target_date?->format('D') ?? '—' }}</span>
                                        <span class="wk-datefull">{{ $t->target_date?->format('M j, Y') ?? 'Any day' }}</span>
                                    </td>
                                    <td class="wk-task">{{ $t->taskLabel() }}</td>
                                    <td class="wk-client">{{ $t->displayClientName() }}</td>
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
                                </tr>
                            @endforeach
                        </tbody>
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
        </details>
    </section>
@endsection

@push('styles')
    @include('admin.bookkeeping.partials.styles')
@endpush
