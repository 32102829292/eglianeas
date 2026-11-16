@extends('layouts.dashboard')

@section('title', 'Weekly Performance Summary')

@section('content')
@php
    use Illuminate\Support\Str;

    $weekLabel     = $weekStart->format('M j') . ' — ' . $weekEnd->format('M j, Y');
    $isCurrentWeek = $weekStart->format('o-W') === now()->format('o-W');
    $canEvaluate   = auth()->user()->isAdminOrSupervisor();
    $isAdmin       = auth()->user()->isAdmin();

    // Best score first; ties fall back to name so the order is stable.
    $ranked = collect($scores)
        ->sortBy([
            fn ($a, $b) => (float) ($b['overall_score'] ?? 0) <=> (float) ($a['overall_score'] ?? 0),
            fn ($a, $b) => strcmp((string) ($a['user']?->name ?? ''), (string) ($b['user']?->name ?? '')),
        ])
        ->values();

    $count            = $ranked->count();
    $teamAverage      = $count > 0 ? $ranked->avg(fn ($s) => (float) ($s['overall_score'] ?? 0)) : 0;
    $pendingEval      = $ranked->filter(fn ($s) => ! ($s['problem_evaluated'] ?? false))->count();
    $completedTotal   = $ranked->sum(fn ($s) => (int) ($s['completed_tasks'] ?? 0));
    $eligibleTotal    = $ranked->sum(fn ($s) => (int) ($s['eligible_planned_tasks'] ?? 0));
    $teamCompletion   = $eligibleTotal > 0 ? (int) round($completedTotal / $eligibleTotal * 100) : 0;
    $top              = $ranked->first();
    $hasAnyScore      = $ranked->contains(fn ($s) => (float) ($s['overall_score'] ?? 0) > 0);

    // Colour only, no invented grade labels: the page doesn't define bands, so these are just visual cues.
    $toneFor = static fn (float $value): string => match (true) {
        $value >= 90 => '#0f9d58',
        $value >= 75 => '#4338ca',
        $value >= 60 => '#b45309',
        default      => '#c0392b',
    };
@endphp

<div class="wpp">
    {{-- ===== Header ===== --}}
    <header class="wpp-head">
        <div class="wpp-head-main">
            <span class="wpp-head-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </span>
            <div>
                <h1>Weekly Performance</h1>
                <p>Planned completion is worth 90 points. Problem-solving is evaluated by an Admin or Supervisor for up to 10 points.</p>
            </div>
        </div>
        @if ($isAdmin)
            <div class="wpp-head-actions">
                <a href="{{ route('admin.daily-work-plan.incomplete', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wpp-btn wpp-btn-ghost">Incomplete Tasks</a>
                <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="wpp-btn wpp-btn-ghost">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Work Plan Grid
                </a>
            </div>
        @endif
    </header>

    {{-- ===== Flash + errors ===== --}}
    @if (session('status'))
        <div class="wpp-alert wpp-alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="wpp-alert wpp-alert-danger" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Week navigation ===== --}}
    <div class="wpp-weeknav">
        <a href="{{ route('admin.daily-work-plan.performance', ['week_start' => $previousWeek->format('Y-m-d')]) }}" class="wpp-nav-btn" rel="prev" aria-label="Previous week" title="Previous week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="wpp-week-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>{{ $weekLabel }}</span>
        </div>
        <a href="{{ route('admin.daily-work-plan.performance', ['week_start' => $nextWeek->format('Y-m-d')]) }}" class="wpp-nav-btn" rel="next" aria-label="Next week" title="Next week">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="{{ route('admin.daily-work-plan.performance') }}" class="wpp-today-btn {{ $isCurrentWeek ? 'is-current' : '' }}" @if ($isCurrentWeek) aria-current="date" @endif>This week</a>
    </div>

    @if ($count === 0)
        <div class="wpp-empty">
            <span class="wpp-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>
            <h3>No employees found</h3>
            <p>No staff or supervisor accounts found.</p>
        </div>
    @else
        {{-- ===== Team summary ===== --}}
        <section class="wpp-stats" aria-label="Team summary">
            <div class="wpp-stat">
                <span class="wpp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                <div><span class="wpp-stat-value">{{ number_format($teamAverage, 1) }}</span><span class="wpp-stat-label">Team average / 100</span></div>
            </div>
            <div class="wpp-stat wpp-stat--ok">
                <span class="wpp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                <div><span class="wpp-stat-value">{{ $teamCompletion }}%</span><span class="wpp-stat-label">{{ $completedTotal }} of {{ $eligibleTotal }} tasks done</span></div>
            </div>
            <div class="wpp-stat wpp-stat--warn">
                <span class="wpp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
                <div><span class="wpp-stat-value">{{ $pendingEval }}</span><span class="wpp-stat-label">Awaiting evaluation</span></div>
            </div>
            <div class="wpp-stat">
                <span class="wpp-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/></svg></span>
                <div>
                    <span class="wpp-stat-value wpp-stat-value--sm">{{ $hasAnyScore ? ($top['user']?->name ?? '—') : '—' }}</span>
                    <span class="wpp-stat-label">{{ $hasAnyScore ? 'Top score ('.number_format((float) ($top['overall_score'] ?? 0), 1).')' : 'Top performer' }}</span>
                </div>
            </div>
        </section>

        {{-- ===== Toolbar ===== --}}
        <div class="wpp-toolbar">
            <label class="wpp-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span class="visually-hidden">Search employees</span>
                <input type="search" placeholder="Search employee" data-perf-search autocomplete="off">
            </label>

            <div class="wpp-pills" role="group" aria-label="Filter by evaluation">
                <button type="button" class="is-active" data-eval-filter="all">All <small>{{ $count }}</small></button>
                <button type="button" data-eval-filter="pending">Needs evaluation <small>{{ $pendingEval }}</small></button>
                <button type="button" data-eval-filter="done">Evaluated <small>{{ $count - $pendingEval }}</small></button>
            </div>

            <label class="wpp-sort">
                <span>Sort</span>
                <select data-perf-sort>
                    <option value="score">Highest score</option>
                    <option value="name">Name A–Z</option>
                    <option value="pending">Needs evaluation first</option>
                </select>
            </label>
        </div>

        <p class="wpp-count" aria-live="polite" data-perf-count>{{ $count }} {{ Str::plural('employee', $count) }}</p>

        {{-- ===== Employee cards ===== --}}
        <div class="wpp-grid" data-perf-grid>
            @foreach ($ranked as $index => $score)
                @php
                    $name         = $score['user']?->name ?? 'Employee #'.$score['user_id'];
                    $role         = ucfirst($score['user']?->role ?? 'staff');
                    $overall      = (float) ($score['overall_score'] ?? 0);
                    $planned      = (float) ($score['planned_score'] ?? 0);
                    $problem      = (int) ($score['problem_score'] ?? 0);
                    $evaluated    = (bool) ($score['problem_evaluated'] ?? false);
                    $plannedPct   = min(100, max(0, $planned / 90 * 100));
                    $problemPct   = min(100, max(0, $problem * 10));
                    $tone         = $toneFor($overall);
                @endphp
                <article class="wpp-card"
                         data-card
                         data-name="{{ Str::lower($name) }}"
                         data-score="{{ $overall }}"
                         data-evaluated="{{ $evaluated ? 1 : 0 }}"
                         data-rank="{{ $index }}">
                    <header class="wpp-card-head">
                        <span class="wpp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                        <div class="wpp-who">
                            <h2>{{ $name }}</h2>
                            <p>{{ $role }}@if ($hasAnyScore && $overall > 0) &middot; <span class="wpp-rank">Rank #{{ $index + 1 }}</span>@endif</p>
                        </div>
                        <div class="wpp-ring" style="--pct: {{ round($overall, 1) }}; --tone: {{ $tone }};" role="img" aria-label="Overall score {{ number_format($overall, 1) }} out of 100">
                            <div class="wpp-ring-inner">
                                <strong>{{ number_format($overall, 1) }}</strong>
                                <small>/ 100</small>
                            </div>
                        </div>
                    </header>

                    <div class="wpp-card-body">
                        {{-- Planned completion --}}
                        <div class="wpp-metric">
                            <div class="wpp-metric-top">
                                <span>Planned completion</span>
                                <strong>{{ number_format($planned, 1) }} <small>/ 90</small></strong>
                            </div>
                            <div class="wpp-track" role="progressbar" aria-valuemin="0" aria-valuemax="90" aria-valuenow="{{ round($planned, 1) }}"><span style="width: {{ $plannedPct }}%"></span></div>
                            <p class="wpp-metric-note">
                                {{ $score['completed_tasks'] }} of {{ $score['eligible_planned_tasks'] }} eligible planned tasks completed
                                @if (($score['cancelled_tasks'] ?? 0) > 0)
                                    &middot; {{ $score['cancelled_tasks'] }} cancelled excluded
                                @endif
                            </p>
                        </div>

                        {{-- Problem solving --}}
                        <div class="wpp-metric">
                            <div class="wpp-metric-top">
                                <span>
                                    Problem-solving
                                    <span class="wpp-chip {{ $evaluated ? 'wpp-chip--ok' : 'wpp-chip--warn' }}">{{ $evaluated ? 'Evaluated' : 'Not evaluated' }}</span>
                                </span>
                                <strong>{{ $problem }} <small>/ 10</small></strong>
                            </div>
                            <div class="wpp-track wpp-track--ok" role="progressbar" aria-valuemin="0" aria-valuemax="10" aria-valuenow="{{ $problem }}"><span style="width: {{ $problemPct }}%"></span></div>
                            <p class="wpp-metric-note">
                                @if ($evaluated)
                                    Evaluated by an Admin or Supervisor
                                @else
                                    Not evaluated &mdash; 0 points (never awarded automatically)
                                @endif
                            </p>
                            @if ($evaluated && ! empty($score['problem_notes']))
                                <blockquote class="wpp-notes">{{ $score['problem_notes'] }}</blockquote>
                            @endif
                        </div>

                        {{-- Task counts --}}
                        <div class="wpp-tiles">
                            <div class="wpp-tile"><strong>{{ $score['planned_tasks'] }}</strong><span>Eligible</span></div>
                            <div class="wpp-tile wpp-tile--ok"><strong>{{ $score['completed_tasks'] }}</strong><span>Completed</span></div>
                            <div class="wpp-tile wpp-tile--danger"><strong>{{ $score['incomplete_tasks'] }}</strong><span>Incomplete</span></div>
                            <div class="wpp-tile"><strong>{{ $score['problems_resolved'] }}/{{ $score['problems_total'] }}</strong><span>Problems</span></div>
                        </div>

                        <p class="wpp-extra">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span>
                                Additional / urgent activities: <strong>{{ $score['additional_tasks'] }}</strong>
                                @if ($score['additional_rating_average'] !== null)
                                    (average rating {{ $score['additional_rating_average'] }}/10 across {{ $score['additional_rated_tasks'] }} rated)
                                @endif
                                &middot; kept separate from the planned score
                            </span>
                        </p>

                        {{-- Evaluation form --}}
                        @if ($canEvaluate)
                            <form method="POST" action="{{ route('admin.daily-work-plan.problem-evaluation') }}" class="wpp-eval">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $score['user_id'] }}">
                                <input type="hidden" name="week_start" value="{{ $weekStart->format('Y-m-d') }}">
                                <p class="wpp-eval-title">{{ $evaluated ? 'Update evaluation' : 'Evaluate problem-solving' }}</p>
                                <div class="wpp-eval-grid">
                                    <div class="wpp-field">
                                        <label for="score_{{ $score['user_id'] }}">Score</label>
                                        <select name="problem_solving_score" id="score_{{ $score['user_id'] }}" required>
                                            <option value="">Select</option>
                                            @foreach ([10, 7, 5, 0] as $value)
                                                <option value="{{ $value }}" @selected($evaluated && $problem === $value)>{{ $value }} / 10</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="wpp-field wpp-field--grow">
                                        <label for="notes_{{ $score['user_id'] }}">Notes</label>
                                        <input type="text" name="notes" id="notes_{{ $score['user_id'] }}" maxlength="2000" value="{{ $score['problem_notes'] }}" placeholder="Evaluation basis (optional)">
                                    </div>
                                    <button type="submit" class="wpp-btn wpp-btn-primary">Save evaluation</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="wpp-empty wpp-empty--search" data-no-match hidden>
            <span class="wpp-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <h3>No employees match</h3>
            <p>Try a different search or clear the filter.</p>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.wpp {
    --wpp-ink: #1b2447;
    --wpp-muted: #5b6585;
    --wpp-line: #e3e7f3;
    --wpp-surface: #ffffff;
    --wpp-sunken: #f7f8ff;
    --wpp-tint: #eef0fc;
    --wpp-brand: #4338ca;
    --wpp-brand-dark: #3730a3;
    --wpp-ok: #0f9d58;
    color: var(--wpp-ink);
}
.wpp h1, .wpp h2, .wpp h3, .wpp p, .wpp ul, .wpp blockquote { margin: 0; }
.visually-hidden { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* Header */
.wpp-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.wpp-head-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
.wpp-head-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 56px; width: 56px; height: 56px; border-radius: 16px; background: var(--wpp-tint); color: var(--wpp-brand); }
.wpp-head-icon svg { width: 30px; height: 30px; }
.wpp-head h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
.wpp-head p { margin-top: 2px; color: var(--wpp-muted); font-size: .95rem; max-width: 52rem; }
.wpp-head-actions { display: flex; flex-wrap: wrap; gap: 8px; }

/* Buttons */
.wpp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-weight: 700; font-size: .88rem; line-height: 1.2; cursor: pointer; text-decoration: none; white-space: nowrap; }
.wpp-btn svg { width: 16px; height: 16px; flex: none; }
.wpp-btn-primary { background: linear-gradient(180deg, #4f46e5, var(--wpp-brand-dark)); color: #fff; }
.wpp-btn-primary:hover { background: var(--wpp-brand-dark); }
.wpp-btn-ghost { background: var(--wpp-surface); border-color: var(--wpp-line); color: var(--wpp-ink); }
.wpp-btn-ghost:hover { background: var(--wpp-tint); color: var(--wpp-ink); }
.wpp-btn:focus-visible, .wpp-nav-btn:focus-visible, .wpp-today-btn:focus-visible, .wpp-pills button:focus-visible, .wpp-search input:focus-visible, .wpp-sort select:focus-visible, .wpp-field input:focus-visible, .wpp-field select:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 2px; }

/* Alerts */
.wpp-alert { margin-bottom: 16px; padding: 12px 16px; border: 1px solid; border-radius: 12px; font-size: .9rem; }
.wpp-alert-success { background: #e7f6ee; border-color: #b6e3cb; color: #0b5f37; }
.wpp-alert-danger { background: #fdecea; border-color: #f1b5ae; color: #a5281a; }
.wpp-alert ul { margin-top: 6px; padding-left: 18px; }

/* Week nav */
.wpp-weeknav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
.wpp-week-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--wpp-surface); border: 1px solid var(--wpp-line); border-radius: 12px; font-weight: 700; font-size: .95rem; white-space: nowrap; }
.wpp-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--wpp-surface); border: 1px solid var(--wpp-line); border-radius: 12px; color: var(--wpp-ink); text-decoration: none; }
.wpp-nav-btn:hover { background: var(--wpp-tint); }
.wpp-today-btn { padding: 10px 16px; border-radius: 12px; background: var(--wpp-surface); border: 1px solid var(--wpp-line); color: var(--wpp-ink); font-weight: 700; font-size: .9rem; text-decoration: none; }
.wpp-today-btn:hover { background: var(--wpp-tint); }
.wpp-today-btn.is-current { background: var(--wpp-ink); border-color: var(--wpp-ink); color: #fff; }

/* Stats */
.wpp-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
.wpp-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: var(--wpp-surface); border: 1px solid var(--wpp-line); border-radius: 18px; min-width: 0; }
.wpp-stat-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: var(--wpp-tint); color: var(--wpp-brand); }
.wpp-stat-icon svg { width: 22px; height: 22px; }
.wpp-stat--ok .wpp-stat-icon { background: #e7f6ee; color: var(--wpp-ok); }
.wpp-stat--warn .wpp-stat-icon { background: #fdf1e2; color: #b45309; }
.wpp-stat div { display: flex; flex-direction: column; min-width: 0; }
.wpp-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.wpp-stat-value--sm { font-size: 1.1rem; line-height: 1.25; overflow-wrap: anywhere; }
.wpp-stat-label { margin-top: 4px; color: var(--wpp-muted); font-size: .82rem; font-weight: 700; }

/* Toolbar */
.wpp-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-bottom: 8px; }
.wpp-search { position: relative; flex: 1 1 240px; max-width: 360px; display: block; margin: 0; }
.wpp-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.wpp-search input { width: 100%; min-height: 42px; padding: 8px 12px 8px 36px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; font: inherit; font-size: .9rem; color: var(--wpp-ink); box-sizing: border-box; }
.wpp-pills { display: inline-flex; flex-wrap: wrap; padding: 3px; border: 1px solid var(--wpp-line); border-radius: 999px; background: #f1f2fb; }
.wpp-pills button { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 0; border-radius: 999px; background: transparent; color: var(--wpp-muted); font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.wpp-pills button small { padding: 0 7px; border-radius: 999px; background: rgba(67,56,202,.1); color: var(--wpp-brand); font-size: .72rem; }
.wpp-pills button.is-active { background: #fff; color: var(--wpp-brand); box-shadow: 0 1px 2px rgba(15,23,42,.12); }
.wpp-sort { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 0 auto; color: var(--wpp-muted); font-size: .82rem; font-weight: 700; }
.wpp-sort select { min-height: 42px; padding: 8px 12px; border: 1px solid #cdd3ea; border-radius: 12px; background: #fff; color: var(--wpp-ink); font: inherit; font-size: .88rem; }
.wpp-count { margin: 4px 2px 14px; color: var(--wpp-muted); font-size: .85rem; }

/* Cards */
.wpp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: start; }
.wpp-card { background: var(--wpp-surface); border: 1px solid var(--wpp-line); border-radius: 18px; overflow: hidden; min-width: 0; }
.wpp-card[hidden] { display: none; }
.wpp-card-head { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--wpp-line); }
.wpp-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 50%; background: var(--wpp-tint); color: var(--wpp-brand); font-weight: 800; }
.wpp-who { flex: 1; min-width: 0; }
.wpp-who h2 { font-size: 1.05rem; font-weight: 800; overflow-wrap: anywhere; }
.wpp-who p { margin-top: 2px; color: var(--wpp-muted); font-size: .82rem; }
.wpp-rank { font-weight: 700; color: var(--wpp-brand); }
.wpp-ring { --pct: 0; --tone: var(--wpp-brand); display: grid; place-items: center; flex: 0 0 76px; width: 76px; height: 76px; border-radius: 50%; background: conic-gradient(var(--tone) calc(var(--pct) * 1%), #e9ebf7 0); }
.wpp-ring-inner { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 60px; height: 60px; border-radius: 50%; background: var(--wpp-surface); line-height: 1.1; }
.wpp-ring-inner strong { font-size: .98rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.wpp-ring-inner small { color: var(--wpp-muted); font-size: .66rem; font-weight: 600; }
.wpp-card-body { display: flex; flex-direction: column; gap: 18px; padding: 18px 20px 20px; }

.wpp-metric { display: grid; gap: 7px; }
.wpp-metric-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: .88rem; font-weight: 700; }
.wpp-metric-top > span { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wpp-metric-top strong { font-size: 1rem; font-weight: 800; font-variant-numeric: tabular-nums; white-space: nowrap; }
.wpp-metric-top small { color: var(--wpp-muted); font-size: .75rem; font-weight: 600; }
.wpp-track { display: block; height: 8px; border-radius: 999px; background: #e9ebf7; overflow: hidden; }
.wpp-track > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, var(--wpp-brand)); }
.wpp-track--ok > span { background: linear-gradient(90deg, #34d399, var(--wpp-ok)); }
.wpp-metric-note { color: var(--wpp-muted); font-size: .8rem; }
.wpp-notes { padding: 8px 12px; border-left: 3px solid #b8bfee; border-radius: 0 10px 10px 0; background: var(--wpp-sunken); color: var(--wpp-muted); font-size: .82rem; line-height: 1.5; overflow-wrap: anywhere; }
.wpp-chip { display: inline-flex; padding: 2px 9px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.wpp-chip--ok { background: #e7f6ee; color: #0b7a45; }
.wpp-chip--warn { background: #fdf1e2; color: #92400e; }

.wpp-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.wpp-tile { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 10px 6px; border: 1px solid var(--wpp-line); border-radius: 12px; background: var(--wpp-sunken); text-align: center; }
.wpp-tile strong { font-size: 1.1rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.wpp-tile span { color: var(--wpp-muted); font-size: .74rem; font-weight: 600; }
.wpp-tile--ok strong { color: #0b7a45; }
.wpp-tile--danger strong { color: #a5281a; }
.wpp-extra { display: flex; align-items: flex-start; gap: 8px; color: var(--wpp-muted); font-size: .8rem; line-height: 1.5; }
.wpp-extra svg { flex: none; margin-top: 3px; color: #f59e0b; }

/* Evaluation form */
.wpp-eval { padding: 14px; border: 1px solid var(--wpp-line); border-radius: 14px; background: var(--wpp-sunken); }
.wpp-eval-title { margin-bottom: 10px; font-size: .85rem; font-weight: 800; }
.wpp-eval-grid { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
.wpp-field { display: flex; flex-direction: column; gap: 5px; min-width: 120px; }
.wpp-field--grow { flex: 1 1 180px; }
.wpp-field label { font-size: .78rem; font-weight: 700; }
.wpp-field input, .wpp-field select { width: 100%; min-height: 40px; padding: 8px 11px; border: 1px solid #cdd3ea; border-radius: 10px; background: #fff; color: var(--wpp-ink); font: inherit; font-size: .88rem; box-sizing: border-box; }

/* Empty */
.wpp-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 44px 20px; text-align: center; border: 1px dashed #c9cff0; border-radius: 18px; background: var(--wpp-sunken); }
.wpp-empty[hidden] { display: none; }
.wpp-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: #e7e9f7; color: var(--wpp-muted); }
.wpp-empty-icon svg { width: 24px; height: 24px; }
.wpp-empty h3 { font-size: 1rem; font-weight: 800; }
.wpp-empty p { color: var(--wpp-muted); font-size: .88rem; }

@media (max-width: 1200px) { .wpp-grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 1000px) { .wpp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) {
    .wpp-head h1 { font-size: 1.3rem; }
    .wpp-search { max-width: none; flex-basis: 100%; }
    .wpp-sort { margin-left: 0; width: 100%; }
    .wpp-sort select { flex: 1; }
    .wpp-stats { gap: 10px; }
    .wpp-stat { padding: 12px; gap: 10px; }
    .wpp-card-head, .wpp-card-body { padding-left: 16px; padding-right: 16px; }
    .wpp-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .wpp-eval-grid .wpp-btn { width: 100%; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var grid = document.querySelector('[data-perf-grid]');
    if (! grid) { return; }

    var search = document.querySelector('[data-perf-search]');
    var sort = document.querySelector('[data-perf-sort]');
    var pills = Array.prototype.slice.call(document.querySelectorAll('[data-eval-filter]'));
    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-card]'));
    var noMatch = document.querySelector('[data-no-match]');
    var count = document.querySelector('[data-perf-count]');
    var originalCount = count ? count.textContent : '';
    var filter = 'all';

    function reorder() {
        var mode = sort ? sort.value : 'score';
        var sorted = cards.slice().sort(function (a, b) {
            if (mode === 'name') {
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            }
            if (mode === 'pending') {
                var diff = parseInt(a.getAttribute('data-evaluated'), 10) - parseInt(b.getAttribute('data-evaluated'), 10);
                if (diff !== 0) { return diff; }
            }
            return parseFloat(b.getAttribute('data-score')) - parseFloat(a.getAttribute('data-score'));
        });
        sorted.forEach(function (card) { grid.appendChild(card); });
    }

    function apply() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var shown = 0;

        cards.forEach(function (card) {
            var matchesText = term === '' || card.getAttribute('data-name').indexOf(term) !== -1;
            var evaluated = card.getAttribute('data-evaluated') === '1';
            var matchesFilter = filter === 'all' || (filter === 'done' ? evaluated : ! evaluated);
            var visible = matchesText && matchesFilter;
            card.hidden = ! visible;
            if (visible) { shown++; }
        });

        if (noMatch) { noMatch.hidden = shown !== 0; }
        if (count) {
            count.textContent = (term === '' && filter === 'all')
                ? originalCount
                : 'Showing ' + shown + ' of ' + cards.length + ' employees';
        }
    }

    if (search) { search.addEventListener('input', apply); }
    if (sort) { sort.addEventListener('change', reorder); }
    pills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            filter = pill.getAttribute('data-eval-filter');
            pills.forEach(function (p) { p.classList.toggle('is-active', p === pill); });
            apply();
        });
    });
})();
</script>
@endpush