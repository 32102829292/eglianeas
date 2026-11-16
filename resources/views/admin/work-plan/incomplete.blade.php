@extends('layouts.dashboard')

@section('title', 'Incomplete Tasks')

@section('content')
@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $weekLabel = $weekStart->format('M j') . ' — ' . $weekEnd->format('M j, Y');
    $isCurrentWeek = $weekStart->format('o-W') === now()->format('o-W');
    $today = Carbon::today();

    $grouped = $tasks->groupBy(fn ($task) => $task->work_date->toDateString())->sortKeys();

    // A day is overdue only once it is strictly before today; today itself is "due today".
    $bucketFor = static function (Carbon $day) use ($today): string {
        if ($day->lt($today)) {
            return 'overdue';
        }

        return $day->isSameDay($today) ? 'today' : 'upcoming';
    };

    $total    = $tasks->count();
    $overdue  = $tasks->filter(fn ($t) => $t->work_date->lt($today))->count();
    $dueToday = $tasks->filter(fn ($t) => $t->work_date->isSameDay($today))->count();
    $upcoming = $total - $overdue - $dueToday;
    $openProblemsTotal = $tasks->sum(fn ($t) => $t->problems->where('status', \App\Models\WorkPlanTaskProblem::STATUS_OPEN)->count());

    // Who has the most outstanding work, for the quick overview strip.
    $byEmployee = $tasks->groupBy(fn ($t) => $t->user?->name ?? 'Unassigned')
        ->map->count()
        ->sortDesc();

    $statusTone = static fn (?string $status): string => match ($status) {
        'returned_for_revision' => 'danger',
        'on_hold'               => 'warn',
        default                 => 'info',
    };

    $bucketLabels = ['overdue' => 'Overdue', 'today' => 'Due today', 'upcoming' => 'Upcoming'];
@endphp

<div class="wpi">
    {{-- ===== Header ===== --}}
    <header class="wpi-head">
        <div class="wpi-head-main">
            <span class="wpi-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <div>
                <h1>Incomplete Tasks</h1>
                <p>Pending and returned tasks that still need action.</p>
            </div>
        </div>
        <div class="wpi-head-actions">
            <a href="{{ route('admin.daily-work-plan.performance', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wpi-btn wpi-btn-ghost">Weekly Performance</a>
            <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wpi-btn wpi-btn-ghost">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Work Plan Grid
            </a>
        </div>
    </header>

    {{-- ===== Week navigation ===== --}}
    <div class="wpi-weeknav">
        <a href="{{ route('admin.daily-work-plan.incomplete', ['week_start' => $previousWeek->format('Y-m-d')]) }}" class="wpi-nav-btn" rel="prev" aria-label="Previous week" title="Previous week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="wpi-week-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>{{ $weekLabel }}</span>
        </div>
        <a href="{{ route('admin.daily-work-plan.incomplete', ['week_start' => $nextWeek->format('Y-m-d')]) }}" class="wpi-nav-btn" rel="next" aria-label="Next week" title="Next week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="{{ route('admin.daily-work-plan.incomplete') }}" class="wpi-today-btn {{ $isCurrentWeek ? 'is-current' : '' }}" @if ($isCurrentWeek) aria-current="date" @endif>This week</a>
    </div>

    @if ($total === 0)
        <div class="wpi-clear">
            <span class="wpi-clear-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <h3>All caught up</h3>
            <p>No incomplete tasks for this week.</p>
        </div>
    @else
        {{-- ===== Summary ===== --}}
        <section class="wpi-stats" aria-label="Incomplete task summary">
            <div class="wpi-stat">
                <span class="wpi-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                <div><span class="wpi-stat-value">{{ $total }}</span><span class="wpi-stat-label">Incomplete</span></div>
            </div>
            <div class="wpi-stat wpi-stat--danger">
                <span class="wpi-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <div><span class="wpi-stat-value">{{ $overdue }}</span><span class="wpi-stat-label">Overdue</span></div>
            </div>
            <div class="wpi-stat wpi-stat--warn">
                <span class="wpi-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <div><span class="wpi-stat-value">{{ $dueToday + $upcoming }}</span><span class="wpi-stat-label">Due today or later</span></div>
            </div>
            <div class="wpi-stat {{ $openProblemsTotal > 0 ? 'wpi-stat--danger' : '' }}">
                <span class="wpi-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
                <div><span class="wpi-stat-value">{{ $openProblemsTotal }}</span><span class="wpi-stat-label">Open problems</span></div>
            </div>
        </section>

        {{-- ===== Toolbar ===== --}}
        <div class="wpi-toolbar">
            <label class="wpi-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span class="visually-hidden">Search tasks</span>
                <input type="search" placeholder="Search employee, code or task" data-task-search autocomplete="off">
            </label>

            <div class="wpi-pills" role="group" aria-label="Filter by timing">
                <button type="button" class="is-active" data-bucket-filter="all">All <small>{{ $total }}</small></button>
                @if ($overdue > 0)<button type="button" data-bucket-filter="overdue">Overdue <small>{{ $overdue }}</small></button>@endif
                @if ($dueToday > 0)<button type="button" data-bucket-filter="today">Today <small>{{ $dueToday }}</small></button>@endif
                @if ($upcoming > 0)<button type="button" data-bucket-filter="upcoming">Upcoming <small>{{ $upcoming }}</small></button>@endif
            </div>
        </div>

        @if ($byEmployee->count() > 1)
            <div class="wpi-people" aria-label="Incomplete tasks by employee">
                <span class="wpi-people-label">By employee</span>
                @foreach ($byEmployee as $name => $count)
                    <button type="button" class="wpi-person" data-person="{{ Str::lower($name) }}">
                        <span class="wpi-person-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                        {{ $name }} <small>{{ $count }}</small>
                    </button>
                @endforeach
            </div>
        @endif

        <p class="wpi-count" aria-live="polite" data-task-count>{{ $total }} incomplete {{ Str::plural('task', $total) }} this week.</p>

        {{-- ===== Day cards ===== --}}
        @foreach ($grouped as $date => $dayTasks)
            @php
                $day = Carbon::parse($date);
                $bucket = $bucketFor($day);
            @endphp
            <section class="wpi-card wpi-card--{{ $bucket }}" data-day data-bucket="{{ $bucket }}" aria-label="{{ $day->format('l, M j') }}">
                <header class="wpi-card-head">
                    <div class="wpi-card-title">
                        <span class="wpi-tile wpi-tile--{{ $bucket }}" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </span>
                        <div>
                            <h2>{{ $day->format('l, M j, Y') }}</h2>
                            <p>{{ $dayTasks->count() }} {{ Str::plural('task', $dayTasks->count()) }}</p>
                        </div>
                    </div>
                    <span class="wpi-chip wpi-chip--{{ $bucket === 'overdue' ? 'danger' : ($bucket === 'today' ? 'warn' : 'info') }}">{{ $bucketLabels[$bucket] }}</span>
                </header>

                <div class="wpi-table-wrap">
                    <table class="wpi-table">
                        <thead>
                            <tr>
                                <th scope="col">Employee</th>
                                <th scope="col">Code</th>
                                <th scope="col">Task</th>
                                <th scope="col">Status</th>
                                <th scope="col">Problems</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dayTasks as $task)
                                @php
                                    $employeeName = $task->user?->name;
                                    $taskTitle = $task->custom_title ?: $task->getLegendLabel();
                                    $openProblems = $task->problems->where('status', \App\Models\WorkPlanTaskProblem::STATUS_OPEN)->count();
                                @endphp
                                <tr data-row
                                    data-person="{{ Str::lower($employeeName ?? 'unassigned') }}"
                                    data-search="{{ Str::lower(($employeeName ?? '').' '.($task->legend_code ?? '').' '.$taskTitle) }}">
                                    <td>
                                        @if ($employeeName)
                                            <span class="wpi-emp">
                                                <span class="wpi-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($employeeName, 0, 1)) }}</span>
                                                <span>{{ $employeeName }}</span>
                                            </span>
                                        @else
                                            <span class="wpi-none">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($task->legend_code)
                                            <span class="wpi-code">{{ $task->legend_code }}</span>
                                        @else
                                            <span class="wpi-none">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="wpi-task">{{ $taskTitle }}</div>
                                        @if ($task->returned_reason)
                                            <div class="wpi-returned">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
                                                Returned: {{ $task->returned_reason }}
                                            </div>
                                        @endif
                                    </td>
                                    <td><span class="wpi-chip wpi-chip--{{ $statusTone($task->status) }}">{{ $task->statusLabel() }}</span></td>
                                    <td>
                                        @if ($openProblems > 0)
                                            <span class="wpi-chip wpi-chip--danger">{{ $openProblems }} open</span>
                                        @else
                                            <span class="wpi-none">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div class="wpi-clear wpi-clear--search" data-no-match hidden>
            <span class="wpi-clear-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <h3>No tasks match</h3>
            <p>Try a different search or clear the filters.</p>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.wpi {
    --wpi-ink: #1b2447;
    --wpi-muted: #5b6585;
    --wpi-line: #e3e7f3;
    --wpi-surface: #ffffff;
    --wpi-sunken: #f7f8ff;
    --wpi-tint: #eef0fc;
    --wpi-brand: #4338ca;
    --wpi-brand-dark: #3730a3;
    color: var(--wpi-ink);
}
.wpi h1, .wpi h2, .wpi h3, .wpi p { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* Header */
.wpi-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.wpi-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.wpi-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: #fdf1e2; color: #b45309; }
.wpi-head-icon svg { width: 30px; height: 30px; }
.wpi-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.wpi-head p { margin-top: 2px; color: var(--wpi-muted); font-size: .95rem; }
.wpi-head-actions { display: flex; flex-wrap: wrap; gap: 8px; }

/* Buttons */
.wpi-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.wpi-btn svg { width: 16px; height: 16px; flex: none; }
.wpi-btn-ghost { background: var(--wpi-surface); border-color: var(--wpi-line); color: var(--wpi-ink); }
.wpi-btn-ghost:hover { background: var(--wpi-tint); color: var(--wpi-ink); }
.wpi-btn:focus-visible, .wpi-nav-btn:focus-visible, .wpi-today-btn:focus-visible, .wpi-pills button:focus-visible, .wpi-person:focus-visible, .wpi-search input:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* Week nav */
.wpi-weeknav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
.wpi-week-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--wpi-surface); border: 1px solid var(--wpi-line); border-radius: 12px; font-weight: 700; font-size: .95rem; white-space: nowrap; }
.wpi-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--wpi-surface); border: 1px solid var(--wpi-line); border-radius: 12px; color: var(--wpi-ink); text-decoration: none; }
.wpi-nav-btn:hover { background: var(--wpi-tint); }
.wpi-today-btn { padding: 10px 16px; border-radius: 12px; background: var(--wpi-surface); border: 1px solid var(--wpi-line); color: var(--wpi-ink); font-weight: 700; font-size: .9rem; text-decoration: none; }
.wpi-today-btn:hover { background: var(--wpi-tint); }
.wpi-today-btn.is-current { background: var(--wpi-ink); border-color: var(--wpi-ink); color: #fff; }

/* Stats */
.wpi-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
.wpi-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: var(--wpi-surface); border: 1px solid var(--wpi-line); border-radius: 18px; }
.wpi-stat-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: var(--wpi-tint); color: var(--wpi-brand); }
.wpi-stat-icon svg { width: 22px; height: 22px; }
.wpi-stat--danger .wpi-stat-icon { background: #fdecea; color: #c0392b; }
.wpi-stat--warn .wpi-stat-icon { background: #fdf1e2; color: #b45309; }
.wpi-stat div { display: flex; flex-direction: column; }
.wpi-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.wpi-stat-label { margin-top: 4px; color: var(--wpi-muted); font-size: .82rem; font-weight: 700; }

/* Toolbar */
.wpi-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-bottom: 12px; }
.wpi-search { position: relative; flex: 1 1 280px; max-width: 420px; display: block; margin: 0; }
.wpi-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.wpi-search input { width: 100%; min-height: 42px; padding: 8px 12px 8px 36px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; font: inherit; font-size: .9rem; color: var(--wpi-ink); box-sizing: border-box; }
.wpi-pills { display: inline-flex; flex-wrap: wrap; padding: 3px; border: 1px solid var(--wpi-line); border-radius: 999px; background: #f1f2fb; }
.wpi-pills button { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 0; border-radius: 999px; background: transparent; color: var(--wpi-muted); font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.wpi-pills button small { padding: 0 7px; border-radius: 999px; background: rgba(67, 56, 202, .1); color: var(--wpi-brand); font-size: .72rem; }
.wpi-pills button.is-active { background: #fff; color: var(--wpi-brand); box-shadow: 0 1px 2px rgba(15,23,42,.12); }

.wpi-people { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; }
.wpi-people-label { color: var(--wpi-muted); font-size: .78rem; font-weight: 700; margin-right: 2px; }
.wpi-person { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px 5px 6px; border: 1px solid var(--wpi-line); border-radius: 999px; background: var(--wpi-surface); color: var(--wpi-ink); font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.wpi-person:hover { background: var(--wpi-tint); }
.wpi-person.is-active { background: var(--wpi-brand); border-color: var(--wpi-brand); color: #fff; }
.wpi-person small { padding: 0 7px; border-radius: 999px; background: var(--wpi-tint); color: var(--wpi-brand); font-size: .72rem; }
.wpi-person.is-active small { background: rgba(255,255,255,.25); color: #fff; }
.wpi-person-avatar { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--wpi-tint); color: var(--wpi-brand); font-size: .7rem; font-weight: 800; }
.wpi-count { margin: 4px 2px 14px; color: var(--wpi-muted); font-size: .85rem; }

/* Day cards */
.wpi-card { background: var(--wpi-surface); border: 1px solid var(--wpi-line); border-radius: 18px; margin-bottom: 16px; overflow: hidden; }
.wpi-card[hidden] { display: none; }
.wpi-card--overdue { border-left: 3px solid #dc4c3f; }
.wpi-card--today { border-left: 3px solid #f59e0b; }
.wpi-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--wpi-line); }
.wpi-card-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.wpi-card-title h2 { font-size: 1.02rem; font-weight: 800; }
.wpi-card-title p { margin-top: 2px; color: var(--wpi-muted); font-size: .82rem; }
.wpi-tile { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--wpi-tint); color: var(--wpi-brand); }
.wpi-tile svg { width: 22px; height: 22px; }
.wpi-tile--overdue { background: #fdecea; color: #c0392b; }
.wpi-tile--today { background: #fdf1e2; color: #b45309; }

/* Table */
.wpi-table-wrap { overflow-x: auto; }
.wpi-table { width: 100%; min-width: 680px; border-collapse: collapse; }
.wpi-table thead th { padding: 11px 20px; background: #e9ebfb; text-align: left; font-size: .82rem; font-weight: 700; white-space: nowrap; }
.wpi-table tbody td { padding: 13px 20px; border-top: 1px solid var(--wpi-line); vertical-align: middle; }
.wpi-table tbody tr:first-child td { border-top: 0; }
.wpi-table tbody tr:hover td { background: var(--wpi-sunken); }
.wpi-table tbody tr[hidden] { display: none; }
.wpi-emp { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; }
.wpi-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; background: var(--wpi-tint); color: var(--wpi-brand); font-size: .78rem; font-weight: 800; }
.wpi-code { display: inline-flex; align-items: center; justify-content: center; min-width: 38px; padding: 4px 10px; border-radius: 999px; background: #e4e6fb; color: var(--wpi-brand); font-weight: 800; font-size: .85rem; }
.wpi-task { font-weight: 600; overflow-wrap: anywhere; }
.wpi-returned { display: flex; align-items: flex-start; gap: 6px; margin-top: 4px; color: #a5281a; font-size: .8rem; }
.wpi-returned svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
.wpi-none { color: #94a3b8; }

.wpi-chip { display: inline-flex; align-items: center; padding: 4px 11px; border: 1px solid transparent; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
.wpi-chip--info { background: #eaf1ff; border-color: #d5e3f8; color: #1d4ed8; }
.wpi-chip--warn { background: #fdf1e2; border-color: #f6ddb9; color: #92400e; }
.wpi-chip--danger { background: #fdecea; border-color: #f7cfca; color: #a5281a; }

/* Empty / all clear */
.wpi-clear { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 44px 20px; text-align: center; border: 1px dashed #b6e3cb; border-radius: 18px; background: #f1faf5; }
.wpi-clear--search { border-color: #c9cff0; background: var(--wpi-sunken); }
.wpi-clear[hidden] { display: none; }
.wpi-clear-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: 50%; background: #d6f1e3; color: #0b7a45; }
.wpi-clear--search .wpi-clear-icon { background: #e7e9f7; color: var(--wpi-muted); }
.wpi-clear-icon svg { width: 26px; height: 26px; }
.wpi-clear h3 { font-size: 1.05rem; font-weight: 800; }
.wpi-clear p { color: var(--wpi-muted); font-size: .9rem; }

@media (max-width: 1000px) { .wpi-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) {
    .wpi-head h1 { font-size: 1.3rem; }
    .wpi-search { max-width: none; flex-basis: 100%; }
    .wpi-stats { gap: 10px; }
    .wpi-stat { padding: 12px; gap: 10px; }
    .wpi-card-head { padding: 14px 16px; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var search = document.querySelector('[data-task-search]');
    if (! search) { return; }

    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));
    var days = Array.prototype.slice.call(document.querySelectorAll('[data-day]'));
    var bucketButtons = Array.prototype.slice.call(document.querySelectorAll('[data-bucket-filter]'));
    var personButtons = Array.prototype.slice.call(document.querySelectorAll('[data-person]')).filter(function (el) { return el.tagName === 'BUTTON'; });
    var noMatch = document.querySelector('[data-no-match]');
    var count = document.querySelector('[data-task-count]');
    var originalCount = count ? count.textContent : '';
    var bucket = 'all';
    var person = '';

    function apply() {
        var term = search.value.trim().toLowerCase();
        var shown = 0;

        days.forEach(function (day) {
            var dayMatches = bucket === 'all' || day.getAttribute('data-bucket') === bucket;
            var visibleRows = 0;

            Array.prototype.forEach.call(day.querySelectorAll('[data-row]'), function (row) {
                var matchesText = term === '' || row.getAttribute('data-search').indexOf(term) !== -1;
                var matchesPerson = person === '' || row.getAttribute('data-person') === person;
                var visible = dayMatches && matchesText && matchesPerson;
                row.hidden = ! visible;
                if (visible) { visibleRows++; }
            });

            day.hidden = visibleRows === 0;
            shown += visibleRows;
        });

        if (noMatch) { noMatch.hidden = shown !== 0; }
        if (count) {
            count.textContent = (term === '' && bucket === 'all' && person === '')
                ? originalCount
                : 'Showing ' + shown + ' of ' + rows.length + ' tasks.';
        }
    }

    search.addEventListener('input', apply);

    bucketButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            bucket = button.getAttribute('data-bucket-filter');
            bucketButtons.forEach(function (b) { b.classList.toggle('is-active', b === button); });
            apply();
        });
    });

    personButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-person');
            person = person === value ? '' : value;
            personButtons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-person') === person); });
            apply();
        });
    });
})();
</script>
@endpush