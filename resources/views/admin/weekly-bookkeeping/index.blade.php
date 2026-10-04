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
        <h2 id="wk-summary-h" class="wk-section-title">This Week</h2>

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
                                            <span aria-hidden="true">&middot;</span>
                                            <span>{{ $item['staff_name'] !== '' ? $item['staff_name'] : 'Not Assigned' }}</span>
                                        </span>
                                    </div>

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
                                        {{ $t->assignedStaffDisplayName() !== '' ? $t->assignedStaffDisplayName() : '—' }}
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
    <style>
        /* ---------- Header + week navigator ---------- */
        .wk-head { margin-bottom: 14px; }
        .wk-head-main h1 { text-transform: uppercase; letter-spacing: .01em; }
        .wk-head-main p { font-size: var(--text-md); }

        .wk-weekbar {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            padding: 10px 12px; margin-bottom: 14px;
            background: var(--surface); border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
        }
        .wk-weeknav {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; flex: 0 0 auto;
            color: var(--navy); background: var(--surface-sunken);
            border: 1px solid var(--border-subtle); border-radius: 9px; text-decoration: none;
        }
        .wk-weeknav:hover { background: var(--sky-soft); border-color: #BFDBFE; }
        .wk-weeklabel { display: flex; flex-direction: column; line-height: 1.25; padding: 0 4px; }
        .wk-weeklabel b { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); white-space: nowrap; }
        .wk-weeklabel span { font-size: var(--text-xs); color: var(--muted-text); }
        .wk-week-today {
            padding: 5px 10px; font-size: var(--text-xs); font-weight: 700;
            color: var(--navy); background: var(--sky-soft);
            border: 1px solid #BFDBFE; border-radius: 999px; text-decoration: none;
        }
        .wk-weekbar-spacer { flex: 1 1 auto; }

        .wk-quick { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .wk-quick a {
            padding: 5px 10px; font-size: var(--text-xs); font-weight: 600;
            color: var(--muted-text); text-decoration: none; border-radius: 999px;
        }
        .wk-quick a:hover { color: var(--navy); background: var(--sky-light); }

        /* ---------- Collapsible filters ---------- */
        .wk-filters {
            margin-bottom: 16px; background: var(--surface);
            border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
        }
        .wk-filters-summary {
            display: flex; align-items: center; gap: 8px; cursor: pointer;
            padding: 10px 14px; font-size: var(--text-sm); font-weight: 700; color: var(--navy);
            list-style: none; min-height: 44px;
        }
        .wk-filters-summary::-webkit-details-marker { display: none; }
        .wk-filters-summary::marker { content: ''; }
        .wk-filters-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
            font-size: 10.5px; font-weight: 800; color: #fff; background: var(--sky-deep);
        }
        .wk-filters-active { margin-left: auto; font-size: var(--text-xs); font-weight: 600; color: var(--sky-deep); }

        .wk-filters-form {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;
            padding: 4px 14px 14px; border-top: 1px solid var(--border-subtle); padding-top: 14px;
        }
        .wk-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .wk-field > span { font-size: var(--text-xs); color: var(--muted-text); font-weight: 600; }
        .wk-field-grow { flex: 1 1 190px; }
        .wk-field select, .wk-field input {
            min-height: 36px; padding: 0 10px; font-size: var(--text-sm);
            color: var(--text); background: var(--surface);
            border: 1px solid var(--border-subtle); border-radius: 8px;
        }
        .wk-filters-actions { display: flex; align-items: center; gap: 8px; }

        /* ---------- Section blocks ---------- */
        .wk-section-block { margin-bottom: 20px; }
        .wk-section-title {
            font-family: var(--font-head); font-size: var(--text-sm); font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em; color: var(--navy); margin: 0;
        }
        .wk-section-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; flex-wrap: wrap; margin-bottom: 10px;
        }
        .wk-section-block > .wk-section-title { display: block; margin-bottom: 10px; }
        .wk-section-note { font-size: var(--text-xs); color: var(--muted-text); }

        /* ---------- Summary ---------- */
        .stat-grid.wk-summary { grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 0; }
        .stat-grid.wk-summary .stat-card { padding: 14px; }
        .stat-grid.wk-summary .stat-value { font-size: 28px; }

        /* ---------- Clear / empty state ---------- */
        .wk-clear { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; }
        .wk-clear-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; flex: 0 0 auto;
            border-radius: 50%; color: var(--success); background: var(--success-soft);
        }
        .wk-clear > div { flex: 1 1 200px; min-width: 0; }
        .wk-clear b { display: block; font-size: var(--text-base); color: var(--navy); }
        .wk-clear span { font-size: var(--text-sm); color: var(--muted-text); }

        /* ---------- Today's priorities ---------- */
        .wk-today-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .wk-today-item {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 10px 14px; background: var(--surface);
            border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);
        }
        .wk-today-main { flex: 1 1 220px; min-width: 0; }
        .wk-today-main b { display: block; font-size: var(--text-base); color: var(--navy); }
        .wk-today-main span { font-size: var(--text-xs); color: var(--muted-text); }
        .wk-today-open { font-size: var(--text-xs); font-weight: 700; color: var(--sky-deep); text-decoration: none; }
        .wk-today-open:hover { text-decoration: underline; }
        .wk-more { margin: 8px 0 0; font-size: var(--text-xs); color: var(--muted-text); }

        /* ---------- Status dots / pills / badges ---------- */
        .wk-dot { width: 9px; height: 9px; flex: 0 0 auto; border-radius: 50%; background: var(--border-subtle); }
        .wk-dot-completed { background: var(--success); }
        .wk-dot-in_progress, .wk-dot-active { background: var(--sky-deep); }
        .wk-dot-attention { background: var(--danger); }
        .wk-dot-pending { background: #C8CEDA; }

        .wk-pill {
            display: inline-flex; align-items: center; padding: 3px 9px;
            border-radius: 999px; font-size: 10.5px; font-weight: 800;
            letter-spacing: .02em; white-space: nowrap;
        }
        .wk-pill-completed { background: #DCFCE7; color: #166534; }
        .wk-pill-in_progress, .wk-pill-active { background: #DBEAFE; color: #1E40AF; }
        .wk-pill-attention { background: #FEE2E2; color: #991B1B; }
        .wk-pill-pending { background: var(--surface-sunken); color: var(--muted-text); }

        .wk-badge {
            display: inline-flex; align-items: center; padding: 3px 9px;
            border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap;
        }
        .wk-badge-attention { background: var(--danger-soft); color: #991B1B; }
        .wk-badge-ok { background: var(--success-soft); color: #166534; }

        /* ---------- Client cards ---------- */
        .wk-clients { display: flex; flex-direction: column; gap: 10px; }
        .wk-client {
            background: var(--surface); border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm); overflow: hidden;
        }
        .wk-client[open] { box-shadow: var(--shadow-sm); }
        .wk-client-head {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 12px 14px; cursor: pointer; list-style: none; min-height: 48px;
        }
        .wk-client-head::-webkit-details-marker { display: none; }
        .wk-client-head::marker { content: ''; }
        .wk-client-caret {
            width: 0; height: 0; flex: 0 0 auto;
            border-left: 6px solid var(--muted-text);
            border-top: 5px solid transparent; border-bottom: 5px solid transparent;
            transition: transform var(--transition-fast);
        }
        .wk-client[open] .wk-client-caret { transform: rotate(90deg); }
        .wk-client-name { font-family: var(--font-head); font-size: var(--text-base); color: var(--navy); }
        .wk-client-count { margin-left: auto; font-size: var(--text-xs); color: var(--muted-text); white-space: nowrap; }

        .wk-mini {
            display: block; width: 84px; height: 6px; flex: 0 0 auto;
            background: var(--surface-sunken); border-radius: 999px; overflow: hidden;
        }
        .wk-mini-bar { display: block; height: 100%; background: var(--success); border-radius: 999px; }

        .wk-tasks { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--border-subtle); }
        .wk-task {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 10px 14px; border-bottom: 1px solid var(--border-subtle);
        }
        .wk-task:last-child { border-bottom: 0; }
        .wk-task-attention { background: rgba(231, 76, 60, .035); }
        .wk-task-main { flex: 1 1 240px; min-width: 0; }
        .wk-task-main b { display: block; font-size: var(--text-sm); font-weight: 700; color: var(--text-strong); }
        .wk-task-meta { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: var(--text-xs); color: var(--muted-text); }
        .wk-task-meta .is-overdue { color: #991B1B; font-weight: 700; }
        .wk-task-actions { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; }
        .wk-task-actions .btn { min-height: 32px; }

        /* ---------- Progress ---------- */
        .wk-progress-card { padding: 16px; }
        .wk-progress-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .wk-progress-head b { font-family: var(--font-head); font-size: 24px; font-weight: 800; color: var(--navy); }
        .wk-progress-head span { margin-left: 6px; font-size: var(--text-sm); color: var(--muted-text); }
        .wk-bar { height: 9px; background: var(--surface-sunken); border-radius: 999px; overflow: hidden; }
        .wk-bar-sm { height: 6px; }
        .wk-bar-fill { display: block; height: 100%; border-radius: 999px; transition: width var(--transition); }
        .wk-bar-fill.is-ok { background: var(--success); }
        .wk-bar-fill.is-warn { background: var(--warning); }

        .wk-types {
            list-style: none; margin: 14px 0 0; padding: 0;
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px;
        }
        .wk-type-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 5px; }
        .wk-type-head span { font-size: var(--text-xs); color: var(--muted-text); }
        .wk-type-head b { font-size: var(--text-xs); color: var(--navy); }

        /* ---------- Detailed report ---------- */
        .wk-report { padding: 0; }
        .wk-report-summary {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 14px 16px; cursor: pointer; list-style: none; min-height: 52px;
        }
        .wk-report-summary::-webkit-details-marker { display: none; }
        .wk-report-summary::marker { content: ''; }
        .wk-report-toggle {
            margin-left: auto; width: 0; height: 0; flex: 0 0 auto;
            border-top: 6px solid var(--muted-text);
            border-left: 5px solid transparent; border-right: 5px solid transparent;
            transition: transform var(--transition-fast);
        }
        .wk-report[open] .wk-report-toggle { transform: rotate(180deg); }
        .wk-report > *:not(.wk-report-summary) { margin-left: 16px; margin-right: 16px; }
        .wk-report > .table-scroll { margin-left: 0; margin-right: 0; }
        .wk-report-hint { margin-top: 0; padding-top: 4px; font-size: var(--text-xs); color: var(--muted-text); }
        .wk-report .empty-state { padding: 18px 16px; }

        /* ---------- Comparison grid (unchanged semantics) ---------- */
        .wk-grid { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
        .wk-grid th, .wk-grid td { padding: 7px 10px; border-bottom: 1px solid var(--border-subtle); text-align: left; vertical-align: middle; }
        .wk-grid thead th {
            font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .03em;
            color: var(--muted-text); background: var(--surface-raised); white-space: nowrap;
        }
        .wk-row:hover { background: var(--sky-light); }
        .wk-day { display: block; font-weight: 700; }
        .wk-datefull { display: block; font-size: 10.5px; color: var(--muted-text); }
        .wk-task { font-weight: 600; white-space: nowrap; }
        .wk-client { white-space: nowrap; }
        .wk-staff { color: var(--muted-text); white-space: nowrap; }

        .cmp-none { color: #94A3B8; }
        .cmp-cell {
            display: inline-flex; flex-direction: column; gap: 1px; padding: 2px 7px;
            border-radius: 5px; text-decoration: none; line-height: 1.2; min-width: 62px;
        }
        .cmp-cell-text { font-size: var(--text-xs); font-weight: 700; }
        .cmp-cell-sub { font-size: 10px; opacity: .85; }
        .cmp-done   { background: #DCFCE7; color: #166534; }
        .cmp-late   { background: #FEF3C7; color: #92400E; }
        .cmp-active { background: #DBEAFE; color: #1E40AF; }
        .cmp-overdue{ background: #FEE2E2; color: #991B1B; }
        .cmp-todo   { background: var(--surface-raised); color: var(--muted-text); }

        .wk-side { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; padding: 16px 0; }
        .wk-side-card h3 { font-size: var(--text-sm); margin: 0 0 8px; color: var(--navy); }
        .wk-side-empty { font-size: var(--text-xs); color: var(--muted-text); margin: 0; }
        .wk-side-list { list-style: none; margin: 0; padding: 0; }
        .wk-side-list li { border-bottom: 1px solid var(--border-subtle); }
        .wk-side-list a { display: flex; align-items: center; gap: 6px; padding: 6px 0; text-decoration: none; color: inherit; font-size: var(--text-xs); }
        .wk-side-list a b { flex: 0 0 auto; }
        .wk-side-list a span { color: var(--muted-text); }
        .wk-side-status { margin-left: auto; font-style: normal; font-weight: 700; }
        .wk-side-status.is-bad  { color: #991B1B; }
        .wk-side-status.is-warn { color: #92400E; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) {
            .stat-grid.wk-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .wk-types { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .stat-grid.wk-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .stat-grid.wk-summary .stat-value { font-size: 24px; }

            .wk-weekbar { gap: 6px; padding: 10px; }
            .wk-weeklabel { order: -1; width: 100%; padding: 0; }
            .wk-weekbar-spacer { display: none; }
            .wk-quick { width: 100%; overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
            .wk-quick a { white-space: nowrap; min-height: 34px; display: inline-flex; align-items: center; }

            .wk-filters-form { flex-direction: column; align-items: stretch; gap: 12px; }
            .wk-field, .wk-field-grow { flex: 1 1 auto; width: 100%; }
            .wk-filters-actions { width: 100%; }
            .wk-filters-actions .btn { flex: 1 1 auto; min-height: 44px; }

            .wk-client-head { gap: 8px; }
            .wk-client-count { margin-left: 0; width: 100%; }
            .wk-mini { width: 100%; }

            .wk-task { align-items: flex-start; padding: 12px 14px; }
            .wk-task .wk-dot { margin-top: 6px; }
            .wk-task-actions { width: 100%; padding-left: 19px; }
            .wk-task-actions .btn { flex: 1 1 auto; min-height: 44px; }

            .wk-today-item { padding: 12px 14px; }
            .wk-today-open { width: 100%; padding: 10px 0 0; min-height: 40px; display: flex; align-items: center; }

            .wk-types { grid-template-columns: 1fr; }
            .wk-report > *:not(.wk-report-summary) { margin-left: 12px; margin-right: 12px; }
            .wk-report-summary { padding: 14px 12px; }
        }
    </style>
@endpush
