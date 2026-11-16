@extends('layouts.dashboard')

@section('title', 'Achievement Rankings')

@php
    use Illuminate\Support\Str;

    $periodLabels = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
    ];
    $currentPeriod = request('period', 'week');

    $topPerformers = $rankings->take(3);
    $rangeLabel = $start->isSameDay($end)
        ? $start->format('F j, Y')
        : $start->format('M j, Y').' – '.$end->format('M j, Y');

    $periodUrl = fn (string $value): string => route('daily-journal.achievements', ['period' => $value]);

    // Criterion weights (from scoring configuration).
    $weights = [
        'quality' => (float) config('achievements.scoring_weights.quality', 50),
        'timeliness' => (float) config('achievements.scoring_weights.timeliness', 20),
        'responsiveness' => (float) config('achievements.scoring_weights.responsiveness', 20),
        'client_feedback' => (float) config('achievements.scoring_weights.client_feedback', 10),
    ];
    $weightTotal = array_sum($weights);

    // Each weight expressed as a share of the total, so every label stays in sync with the config.
    $shares = [];
    foreach ($weights as $key => $weight) {
        $shares[$key] = $weightTotal > 0 ? ($weight / $weightTotal) * 100 : 0;
    }

    $weightLabels = [
        'quality' => 'Accomplishment Quality',
        'timeliness' => 'Timeliness',
        'responsiveness' => 'Immediate Responsiveness',
        'client_feedback' => 'Client Feedback',
    ];

    $tableHeads = [
        'quality' => 'Quality',
        'timeliness' => 'Timeliness',
        'responsiveness' => 'Responsiveness',
        'client_feedback' => 'Client Feedback',
    ];

    // Team-level statistics per criterion (average across ranked employees that have data).
    $teamStats = [];
    foreach (array_keys($weights) as $key) {
        $values = $rankings->pluck($key.'_score')->filter(fn ($v) => $v !== null);
        $teamStats[$key] = [
            'avg' => $values->isNotEmpty() ? (float) $values->avg() : null,
            'count' => $values->count(),
        ];
    }

    $breakdowns = [
        'quality' => [
            'icon' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="m9 14 2 2 4-4"/>',
            'fields' => [
                'What it measures' => "The average assessed score of the employee's Daily Accomplishment Reports, based on evidence, completeness, accuracy, clarity, and quality.",
                'Source' => 'Daily Accomplishment / Daily Journal only. Kaizen Strategy, Admin Concerns, and Priority records are not used for this criterion.',
                'If data is missing' => 'Excluded when no assessed accomplishment reports exist in the period.',
            ],
        ],
        'timeliness' => [
            'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'fields' => [
                'What it measures' => 'Whether assigned work was completed on or before its deadline. Considers completed-before-deadline, on-deadline, late, and overdue outcomes.',
                'Sources' => 'Kaizen Strategy, Admin Concerns, and Daily Accomplishment, using the actual target deadline and the verified implementation or completion date.',
                'If data is missing' => 'A record with no target date is excluded with the reason "no deadline set". It is never scored as zero.',
            ],
        ],
        'responsiveness' => [
            'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 8 10"/>',
            'fields' => [
                'What it measures' => 'The time between assignment and the first attributable staff acknowledgment or response.',
                'Sources' => 'Kaizen Strategy and Admin Concerns only. Daily Accomplishment is not used for this criterion.',
                'If data is missing' => 'When assignment or first-response timestamps are unavailable, the item is excluded per the normalization policy. Values are never estimated or invented.',
            ],
        ],
        'client_feedback' => [
            'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'fields' => [
                'What it measures' => 'Verified client feedback attributable to the employee, based on Client Concerns linked to that staff member.',
                'Source' => 'Client Concerns only. Generic Admin Concerns or other records are not used as client feedback.',
                'If data is missing' => 'When no verifiable client feedback can be linked to the employee, this criterion is shown as N/A and excluded from the score.',
            ],
        ],
    ];

    $integrationStates = [
        ['name' => 'Integrated', 'tone' => 'ok', 'desc' => "Confirmed included in the correct employee's assessment.", 'icon' => '<polyline points="20 6 9 17 4 12"/>'],
        ['name' => 'Pending', 'tone' => 'warn', 'desc' => 'Waiting for required review or processing.', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
        ['name' => 'Excluded', 'tone' => 'muted', 'desc' => 'Not eligible, with a clear reason recorded.', 'icon' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'],
        ['name' => 'Failed', 'tone' => 'danger', 'desc' => 'Integration encountered an error; see audit trail.', 'icon' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    ];
@endphp

@section('content')
<div class="ach-rank-page">
    {{-- Page Header --}}
    <div class="ach-head page-head page-head-row">
        <div>
            <nav class="ach-crumbs" aria-label="Breadcrumb">
                <span>Daily Accomplishment</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                <span class="is-current" aria-current="page">Achievement Rankings</span>
            </nav>
            <h1>Achievement Rankings</h1>
            <p>Transparent employee performance rankings across Accomplishment Quality, Timeliness, Immediate Responsiveness, and Client Feedback.</p>
        </div>
        <div class="ach-head-side">
            <nav class="ach-periods" aria-label="Ranking period">
                @foreach ($periodLabels as $value => $label)
                    <a href="{{ $periodUrl($value) }}"
                       class="ach-period {{ $currentPeriod === $value ? 'is-active' : '' }}"
                       @if ($currentPeriod === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
                @if ($currentPeriod === 'custom')
                    <span class="ach-period is-active" aria-current="true">Custom range</span>
                @endif
            </nav>
            <div class="ach-range-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>{{ $rangeLabel }}</span>
            </div>
        </div>
    </div>

    {{-- Empty State --}}
    @if ($rankings->isEmpty())
    <section class="ach-empty" aria-label="No rankings">
        <span class="ach-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a2 2 0 0 0 0 4h3"/><path d="M17 6h3a2 2 0 0 1 0 4h-3"/></svg>
        </span>
        <h2>No assessed accomplishments for this period</h2>
        <p>
            Rankings appear after employees submit Daily Accomplishment Reports.
            Old reports without an assessment are not counted, and no rankings are invented when data is missing.
        </p>
        <div class="ach-empty-actions">
            @foreach (['week', 'month'] as $alt)
                @if ($currentPeriod !== $alt)
                    <a href="{{ $periodUrl($alt) }}" class="btn btn-outline btn-sm">Try {{ Str::lower($periodLabels[$alt]) }}</a>
                @endif
            @endforeach
        </div>
    </section>
    @else

    {{-- Summary Cards --}}
    <section class="ach-stats" aria-label="Ranking summary">
        <article class="ach-stat">
            <span class="ach-stat-label">Employees ranked</span>
            <span class="ach-stat-value">{{ $employeeCount }}</span>
            <span class="ach-stat-note">Distinct people with assessed work</span>
        </article>
        <article class="ach-stat">
            <span class="ach-stat-label">Average overall score</span>
            <span class="ach-stat-value">{{ $averageOverallScore !== null ? number_format((float) $averageOverallScore, 1) : '—' }}</span>
            <span class="ach-stat-note">Across ranked employees</span>
        </article>
        <article class="ach-stat">
            <span class="ach-stat-label">Highest score</span>
            <span class="ach-stat-value">{{ $highestScore !== null ? number_format((float) $highestScore, 1) : '—' }}</span>
            <span class="ach-stat-note">Best overall performance</span>
        </article>
        <article class="ach-stat ach-stat--pending">
            <span class="ach-stat-label">Pending integrations</span>
            <span class="ach-stat-value">{{ $pendingIntegrations }}</span>
            <span class="ach-stat-note">Evaluations not yet in Score Sheet</span>
        </article>
        <article class="ach-stat ach-stat--review">
            <span class="ach-stat-label">Awaiting admin review</span>
            <span class="ach-stat-value">{{ $awaitingReview }}</span>
            <span class="ach-stat-note">Provisional assessments pending review</span>
        </article>
        <article class="ach-stat ach-stat--criteria">
            <span class="ach-stat-label">Criteria scored</span>
            <span class="ach-stat-value">{{ $criteriaScored }}</span>
            <span class="ach-stat-note">Staff with at least one evaluated criterion</span>
        </article>
    </section>

    {{-- Top Performers --}}
    @if ($topPerformers->isNotEmpty())
    <section class="ach-podium" aria-label="Top performers">
        @foreach ($topPerformers as $performer)
            @php
                $badge = $performer['badge'] ?? 'plain';
                $levelSlug = Str::slug($performer['level']);
                $performerScore = $performer['score'] ?? $performer['overall_score'] ?? null;
                $entries = (int) ($performer['submitted_count'] ?? 0);
            @endphp
            <article class="ach-podium-card ach-podium-card--{{ $badge }}">
                <div class="ach-podium-rank">
                    <span class="ach-medal ach-medal--{{ $badge }}">#{{ $performer['rank'] }}</span>
                    <span class="ach-podium-label">{{ $badge === 'gold' ? 'Top performer' : 'Recognition' }}</span>
                </div>
                <div class="ach-podium-person">
                    <span class="ach-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($performer['user']->name, 0, 1)) }}</span>
                    <div>
                        <h2>{{ $performer['user']->name }}</h2>
                        <p>{{ ucfirst($performer['user']->role) }} &middot; {{ $entries }} assessed {{ Str::plural('entry', $entries) }}</p>
                    </div>
                </div>
                <div class="ach-podium-score">
                    <span class="ach-score">{{ $performerScore !== null ? number_format((float) $performerScore, 1) : '—' }}</span>
                    <span class="ach-level ach-level--{{ $levelSlug }}">{{ $performer['level'] }}</span>
                </div>
            </article>
        @endforeach
    </section>
    @endif

    {{-- Criterion Weights --}}
    <section class="ach-panel" aria-label="Criterion weights">
        <div class="ach-section-head">
            <h2>How scores are weighted</h2>
            <p>Each criterion contributes to the overall score using its configured weight. Criteria with no data are excluded and remaining weights are renormalized.</p>
        </div>
        <div class="ach-section-body ach-weights-grid">
            @foreach ($weights as $key => $weight)
            <div class="ach-weight-card">
                <span class="ach-weight-name">{{ $weightLabels[$key] }}</span>
                <span class="ach-weight-value">{{ number_format($shares[$key], 0) }}%</span>
                <div class="ach-weight-bar" role="presentation">
                    <div class="ach-weight-bar-fill" style="width: {{ round($shares[$key], 1) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Ranking Table --}}
    <section class="ach-card" aria-labelledby="ach-table-title">
        <div class="ach-card-head">
            <div>
                <h2 id="ach-table-title">Ranking table</h2>
                <p>
                    <span id="ach-visible-count">{{ $rankings->count() }}</span> of {{ $rankings->count() }}
                    {{ Str::plural('employee', $rankings->count()) }} shown for {{ $rangeLabel }}.
                </p>
            </div>
            <div class="ach-card-tools">
                <label class="ach-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span class="visually-hidden">Filter employees</span>
                    <input type="search" id="ach-search" placeholder="Filter by name or role" autocomplete="off">
                </label>
                <span class="ach-provisional-key">
                    <span class="ach-dot ach-dot--verified"></span> Manual verified
                    <span class="ach-dot ach-dot--partial"></span> Partial
                    <span class="ach-dot ach-dot--provisional"></span> Automatic provisional
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover ach-table" id="ach-table">
                <caption class="visually-hidden">Employee achievement rankings for {{ $rangeLabel }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Rank</th>
                        <th scope="col">Employee</th>
                        <th scope="col">Overall Score</th>
                        <th scope="col">Level</th>
                        @foreach ($tableHeads as $key => $head)
                            <th scope="col">{{ $head }}<br><span class="ach-subhead">{{ number_format($shares[$key], 0) }}%</span></th>
                        @endforeach
                        <th scope="col">Integration Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rankings as $ranking)
                        @php
                            $badge = $ranking['badge'] ?? null;
                            $levelSlug = Str::slug($ranking['level']);
                            $status = $ranking['verification_status'] ?? 'provisional';
                            $statusLabel = match ($status) {
                                'verified' => 'Manual verified',
                                'partial' => 'Partially verified',
                                default => 'Automatic provisional',
                            };
                            $dotClass = match ($status) {
                                'verified' => 'verified',
                                'partial' => 'partial',
                                default => 'provisional',
                            };
                            $overall = $ranking['overall_score'] ?? null;
                            $latestJournal = $latestJournals->get($ranking['user']->id) ?? null;
                        @endphp
                        <tr data-search="{{ Str::lower($ranking['user']->name.' '.$ranking['user']->role) }}">
                            <td>
                                <span class="ach-rank ach-rank--{{ $badge ?? 'plain' }}">
                                    @if ($badge)
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>
                                        <span class="visually-hidden">{{ ucfirst($badge) }} rank</span>
                                    @endif
                                    {{ $ranking['rank'] }}
                                </span>
                            </td>
                            <td>
                                <div class="ach-person">
                                    <span class="ach-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($ranking['user']->name, 0, 1)) }}</span>
                                    <div class="ach-person-text">
                                        <span class="ach-person-name">{{ $ranking['user']->name }}</span>
                                        <span class="ach-person-role">{{ ucfirst($ranking['user']->role) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($overall !== null)
                                    <div class="ach-meter ach-meter--lg">
                                        <span class="ach-score">{{ number_format((float) $overall, 1) }}</span>
                                        <span class="ach-meter-track" role="presentation"><span class="ach-meter-fill" style="width: {{ max(0, min(100, (float) $overall)) }}%;"></span></span>
                                    </div>
                                @else
                                    <span class="ach-na" title="Not yet evaluated">—</span>
                                @endif
                            </td>
                            <td><span class="ach-level ach-level--{{ $levelSlug }}">{{ $ranking['level'] }}</span></td>
                            @foreach (array_keys($tableHeads) as $key)
                                @php $value = $ranking[$key.'_score'] ?? null; @endphp
                                <td>
                                    @if ($value !== null)
                                        <div class="ach-meter">
                                            <span class="ach-score ach-score--sm">{{ number_format((float) $value, 1) }}</span>
                                            <span class="ach-meter-track" role="presentation"><span class="ach-meter-fill" style="width: {{ max(0, min(100, (float) $value)) }}%;"></span></span>
                                        </div>
                                    @else
                                        <span class="ach-na" title="Not yet evaluated">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <span class="ach-status ach-status--{{ $status }}">
                                    <span class="ach-dot ach-dot--{{ $dotClass }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td>
                                @if ($latestJournal)
                                    <a href="{{ route('daily-journal.assessment.score-sheet', $latestJournal) }}" class="btn btn-outline btn-sm ach-view-sheet">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17V7M12 3V7M9 20H6M18 20H15"/></svg>
                                        View Score Sheet
                                    </a>
                                @else
                                    <button type="button" class="btn btn-outline btn-sm ach-view-sheet" disabled>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17V7M12 3V7M9 20H6M18 20H15"/></svg>
                                        No assessed journal
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr id="ach-no-match" hidden>
                        <td colspan="10" class="ach-no-match">No employees match your filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Score Explanation --}}
    @php
        $calcRows = [];
        $totalPoints = 0.0;
        $availableShare = 0.0;
        foreach ($weights as $key => $weight) {
            $avg = $teamStats[$key]['avg'];
            $evaluated = $avg !== null;
            $points = $evaluated ? round($avg * ($shares[$key] / 100), 2) : null;
            if ($evaluated) {
                $totalPoints += $points;
                $availableShare += $shares[$key];
            }
            $calcRows[] = [
                'name' => $weightLabels[$key],
                'avg' => $avg,
                'share' => $shares[$key],
                'points' => $points,
                'evaluated' => $evaluated,
                'count' => $teamStats[$key]['count'],
            ];
        }
        $normalizedScore = $availableShare > 0 ? round($totalPoints / ($availableShare / 100), 1) : null;
    @endphp
    <section class="ach-panel" aria-label="Score calculation explanation">
        <div class="ach-section-head">
            <h2>How the overall score is calculated</h2>
            <p>Each criterion score is multiplied by its weight, then divided by the total available weight. The example below uses the team average for each criterion. Criteria with insufficient data are excluded, never treated as zero.</p>
        </div>
        <div class="ach-section-body ach-calc-table-wrap">
            <table class="ach-calc-table">
                <thead>
                    <tr>
                        <th scope="col">Criterion</th>
                        <th scope="col" class="text-end">Team average</th>
                        <th scope="col" class="text-end">Weight</th>
                        <th scope="col" class="text-end">Points contributed</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($calcRows as $row)
                    <tr class="{{ $row['evaluated'] ? 'ach-calc-row--evaluated' : 'ach-calc-row--unevaluated' }}">
                        <td class="ach-calc-criterion">{{ $row['name'] }}</td>
                        <td class="text-end">
                            @if ($row['evaluated'])
                                {{ number_format($row['avg'], 1) }} / 100
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($row['share'], 0) }}%</td>
                        <td class="text-end">
                            @if ($row['evaluated'])
                                {{ number_format($row['points'], 2) }}
                            @else
                                <span class="text-muted">Excluded</span>
                            @endif
                        </td>
                        <td>
                            <span class="ach-calc-status ach-calc-status--{{ $row['evaluated'] ? 'evaluated' : 'excluded' }}">
                                {{ $row['evaluated'] ? 'Evaluated ('.$row['count'].')' : 'Excluded (no data)' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                    <tr class="ach-calc-row--total">
                        <td>Total points</td>
                        <td class="text-end"></td>
                        <td class="text-end">{{ number_format($availableShare, 0) }}% available</td>
                        <td class="text-end">{{ number_format($totalPoints, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="ach-calc-row--available">
                        <td>Final score (normalized)</td>
                        <td class="text-end"></td>
                        <td class="text-end">100%</td>
                        <td class="text-end">{{ $normalizedScore !== null ? number_format($normalizedScore, 1).' / 100' : '—' }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="ach-calc-note">
            <strong>How this works:</strong> Criteria with insufficient data are excluded and their weight is removed from the total. For example, if only Accomplishment Quality has data, the quality score becomes the final score because it is the only criterion measured. Each employee is normalized on their own data, so the team figure above can differ slightly from the average overall score.
        </div>
    </section>

    {{-- Detailed Criterion Breakdown --}}
    <section class="ach-panel" aria-label="Criterion breakdown">
        <div class="ach-section-head ach-section-head--row">
            <div>
                <h2>Detailed criterion breakdown</h2>
                <p>Expand each criterion to see what it measures, which records are included or excluded, and how the result is determined.</p>
            </div>
            <div class="ach-bk-controls">
                <button type="button" class="btn btn-outline btn-sm" data-bk-all="open">Expand all</button>
                <button type="button" class="btn btn-outline btn-sm" data-bk-all="close">Collapse all</button>
            </div>
        </div>

        <div class="ach-section-body ach-breakdown-grid">
            @foreach ($breakdowns as $key => $info)
                @php $avg = $teamStats[$key]['avg']; @endphp
                <div class="ach-bk-card ach-bk-card--{{ Str::slug($key) }}">
                    <button type="button" class="ach-bk-head" data-bk-toggle aria-expanded="false" aria-controls="ach-bk-body-{{ $key }}">
                        <span class="ach-bk-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $info['icon'] !!}</svg>
                        </span>
                        <span class="ach-bk-main">
                            <span class="ach-bk-name-row">
                                <span class="ach-bk-name">{{ $weightLabels[$key] }}</span>
                                <span class="ach-bk-weight">{{ number_format($shares[$key], 0) }}% weight</span>
                            </span>
                            <span class="ach-bk-score">
                                <span class="ach-bk-points {{ $avg !== null ? 'has-value' : '' }}">{{ $avg !== null ? number_format($avg, 1) : '—' }}</span>
                                <span class="ach-bk-points-max"> / 100</span>
                                <span class="ach-bk-sample">
                                    {{ $avg !== null
                                        ? 'Team average · '.$teamStats[$key]['count'].' '.Str::plural('employee', $teamStats[$key]['count'])
                                        : 'No data this period' }}
                                </span>
                            </span>
                        </span>
                        <svg class="ach-bk-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="ach-bk-body" id="ach-bk-body-{{ $key }}" hidden>
                        <div class="ach-bk-body-inner">
                            @foreach ($info['fields'] as $label => $text)
                                <div class="ach-bk-field">
                                    <span class="ach-bk-field-label">{{ $label }}</span>
                                    <p class="ach-bk-field-value">{{ $text }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Integration Status Panel --}}
    <section class="ach-panel" aria-label="Score sheet integration">
        <div class="ach-section-head">
            <h2>Score Sheet Integration</h2>
            <p>Traceable status of how each evaluation contributes to the correct employee's Score Sheet.</p>
        </div>

        <div class="ach-section-body ach-integration-grid">
            @foreach ($integrationStates as $state)
            <div class="ach-integration-card">
                <span class="ach-integration-icon ach-integration-icon--{{ $state['tone'] }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $state['icon'] !!}</svg>
                </span>
                <div class="ach-integration-body">
                    <span class="ach-integration-name">{{ $state['name'] }}</span>
                    <span class="ach-integration-desc">{{ $state['desc'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        @if ($awaitingReview > 0 && auth()->user()?->isAdmin())
        <div class="ach-integration-actions">
            <a href="{{ route('admin.daily-journal.admin-monitor') }}" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="6" x2="12" y2="14"/><line x1="12" y1="18" x2="12" y2="14"/></svg>
                Review {{ $awaitingReview }} Pending {{ Str::plural('Assessment', $awaitingReview) }}
            </a>
        </div>
        @endif
    </section>

    {{-- Notes --}}
    <section class="ach-notes" aria-label="How rankings work">
        <div>
            <h2>How this ranking works</h2>
            <p>
                Employees are ordered by their overall achievement score, which combines four criteria using
                configurable weights: Accomplishment Quality ({{ number_format($shares['quality'], 0) }}%),
                Timeliness ({{ number_format($shares['timeliness'], 0) }}%),
                Immediate Responsiveness ({{ number_format($shares['responsiveness'], 0) }}%),
                and Client Feedback ({{ number_format($shares['client_feedback'], 0) }}%). Duplicate accomplishment text
                inside the period is counted once so copy-pasted submissions cannot inflate a position. Differences in
                role are shown so work is read in context rather than assumed to be identical.
            </p>
        </div>
        <div>
            <h2>Missing data</h2>
            <p>
                A criterion with no evaluable data is shown as &ldquo;—&rdquo; (not yet evaluated), not zero. The overall
                score is computed only from criteria that have data, so a missing source never unfairly lowers a
                staff member&rsquo;s result.
            </p>
        </div>
        <div>
            <h2>Automatic vs manual</h2>
            <p>
                An automatic provisional score comes from the rule-based fallback or an optional AI service. It is not a
                finding of misconduct and is never the sole basis for discipline. Once an admin or supervisor reviews
                the entry in manual mode, the achievement is marked manually verified and the change is recorded in its
                audit history.
            </p>
        </div>
        <div>
            <h2>Score Sheet Integration</h2>
            <p>
                Kaizen Strategy and Admin Concern evaluations are linked to each employee's Score Sheet. Only evaluations
                marked as integrated contribute weighted points; pending, excluded, or reversed items remain traceable
                but do not contribute to the score.
            </p>
        </div>
    </section>
    @endif
</div>
@endsection

@push('styles')
<style>
/* ============================================================
   Achievement Rankings
   Scoped to .ach-* so shared dashboard styles stay untouched.
   ============================================================ */

.ach-rank-page { display: flex; flex-direction: column; gap: 1.5rem; }
.ach-rank-page .visually-hidden { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.ach-na { color: #94a3b8; font-weight: 600; }

/* ---------- Page Header ---------- */
.ach-head { align-items: flex-start; gap: 1rem; }
.ach-crumbs { display: flex; align-items: center; gap: .3rem; margin-bottom: .4rem; color: var(--muted, #64748b); font-size: .75rem; font-weight: 600; letter-spacing: .02em; }
.ach-crumbs svg { width: 12px; height: 12px; flex: none; opacity: .6; }
.ach-crumbs .is-current { color: var(--navy, #1e3a5f); }
.ach-head h1 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1.55rem; font-weight: 800; letter-spacing: -.015em; line-height: 1.2; }
.ach-head p { margin: .35rem 0 0; color: var(--muted, #64748b); font-size: .9rem; }
.ach-head-side { display: flex; flex-direction: column; align-items: flex-end; gap: .6rem; flex: none; }
.ach-range-chip { display: inline-flex; align-items: center; gap: .5rem; padding: .55rem .85rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; color: var(--navy, #1e3a5f); font-size: .82rem; font-weight: 700; box-shadow: 0 4px 14px rgba(15, 47, 91, .05); }
.ach-range-chip svg { width: 16px; height: 16px; flex: none; color: #2563eb; }

.ach-periods { display: inline-flex; padding: .2rem; gap: .15rem; background: #f1f5f9; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-period { padding: .4rem .8rem; border-radius: 9px; color: var(--muted, #64748b); font-size: .78rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background .15s ease, color .15s ease; }
.ach-period:hover { color: var(--navy, #1e3a5f); background: #e8eef7; }
.ach-period.is-active { background: var(--surface, #fff); color: #1d4ed8; box-shadow: 0 2px 8px rgba(15, 47, 91, .1); }
.ach-period:focus-visible, .ach-bk-head:focus-visible, .ach-search input:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

/* ---------- Summary Cards ---------- */
.ach-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.ach-stat { display: flex; flex-direction: column; gap: .2rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 6px 18px rgba(15, 47, 91, .05); }
.ach-stat-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.ach-stat-value { color: var(--navy, #1e3a5f); font-size: 1.8rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
.ach-stat-note { color: var(--muted, #64748b); font-size: .74rem; }
.ach-stat--pending { border-left: 3px solid #d97706; }
.ach-stat--review { border-left: 3px solid #92400e; }
.ach-stat--criteria { border-left: 3px solid #2563eb; }

/* ---------- Top Performers ---------- */
.ach-podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.ach-podium-card { display: flex; flex-direction: column; gap: .75rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .06); }
.ach-podium-card--gold { border-color: #e6c66b; background: linear-gradient(180deg, #fffdf4, #fff); }
.ach-podium-card--silver { border-color: #cbd5e1; }
.ach-podium-card--bronze { border-color: #d9b08c; }
.ach-podium-rank { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.ach-podium-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.ach-medal { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 .5rem; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: .8rem; font-weight: 800; }
.ach-medal--gold { background: #fef3c7; color: #92400e; }
.ach-medal--silver { background: #e2e8f0; color: #475569; }
.ach-medal--bronze { background: #fde8d7; color: #9a3412; }
.ach-podium-person { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.ach-podium-person h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: .98rem; font-weight: 800; overflow-wrap: anywhere; }
.ach-podium-person p { margin: .1rem 0 0; color: var(--muted, #64748b); font-size: .75rem; }
.ach-podium-score { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: auto; }
.ach-score { color: var(--navy, #1e3a5f); font-size: 1.15rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.ach-score--sm { font-size: .95rem; font-weight: 700; }
.ach-podium-score .ach-score { font-size: 1.6rem; }

/* ---------- Shared section panel ---------- */
.ach-panel { display: flex; flex-direction: column; }
.ach-section-head { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px 14px 0 0; border-bottom: none; }
.ach-section-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-section-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-section-head--row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.ach-section-body { padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 14px 14px; border-top: none; }

/* ---------- Criterion Weights ---------- */
.ach-weights-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
.ach-weight-card { display: flex; flex-direction: column; gap: .5rem; padding: 1rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-weight-name { font-size: .78rem; font-weight: 700; color: var(--muted, #64748b); letter-spacing: .04em; text-transform: uppercase; }
.ach-weight-value { font-size: 1.5rem; font-weight: 800; color: var(--navy, #1e3a5f); }
.ach-weight-bar { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.ach-weight-bar-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #1d4ed8); border-radius: 999px; }

/* ---------- Ranking card & table ---------- */
.ach-card { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); overflow: hidden; }
.ach-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 1.05rem 1.25rem; border-bottom: 1px solid var(--border-subtle, #e5e7eb); }
.ach-card-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-card-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-card-tools { display: flex; align-items: center; gap: .9rem; flex-wrap: wrap; }
.ach-provisional-key { display: inline-flex; align-items: center; gap: .35rem; color: var(--muted, #64748b); font-size: .72rem; font-weight: 600; }
.ach-provisional-key .ach-dot:not(:first-child) { margin-left: .5rem; }

.ach-search { position: relative; display: inline-flex; align-items: center; margin: 0; }
.ach-search svg { position: absolute; left: .65rem; width: 14px; height: 14px; color: #94a3b8; pointer-events: none; }
.ach-search input { width: 14rem; max-width: 100%; padding: .45rem .75rem .45rem 2rem; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 10px; background: #f8fafc; color: var(--text, #1f2937); font-size: .8rem; }

.ach-table { margin: 0; min-width: 980px; }
.ach-table thead th { padding: .75rem 1.1rem; background: #f8fafc; border-bottom: 1px solid var(--border-subtle, #e5e7eb); color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
.ach-table thead th .ach-subhead { display: block; font-size: .62rem; font-weight: 600; color: #94a3b8; margin-top: .15rem; text-transform: none; letter-spacing: normal; }
.ach-table tbody td { padding: .8rem 1.1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.ach-table tbody tr:last-child td { border-bottom: none; }
.ach-no-match { padding: 2rem 1rem !important; text-align: center; color: var(--muted, #64748b); font-size: .85rem; }

.ach-meter { display: flex; flex-direction: column; gap: .3rem; min-width: 4.5rem; }
.ach-meter--lg { min-width: 5.5rem; }
.ach-meter-track { display: block; height: 5px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.ach-meter-fill { display: block; height: 100%; background: linear-gradient(90deg, #3b82f6, #1d4ed8); border-radius: 999px; }

.ach-rank { display: inline-flex; align-items: center; gap: .25rem; min-width: 2rem; height: 2rem; padding: 0 .5rem; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: .8rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.ach-rank svg { width: 14px; height: 14px; flex: none; }
.ach-rank--gold { background: #fef3c7; color: #92400e; }
.ach-rank--silver { background: #e2e8f0; color: #475569; }
.ach-rank--bronze { background: #fde8d7; color: #9a3412; }

.ach-person { display: flex; align-items: center; gap: .6rem; min-width: 0; }
.ach-avatar { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: none; border-radius: 50%; background: #eef4fd; color: #2563eb; font-size: .8rem; font-weight: 800; }
.ach-person-text { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.ach-person-name { color: var(--navy, #1e3a5f); font-weight: 700; white-space: nowrap; }
.ach-person-role { color: var(--muted, #64748b); font-size: .74rem; }

.ach-level { display: inline-flex; align-items: center; padding: .25rem .6rem; border: 1px solid transparent; border-radius: 999px; font-size: .7rem; font-weight: 700; line-height: 1.25; white-space: nowrap; }
.ach-level--outstanding { background: #ecfdf3; border-color: #bbf7d0; color: #047857; }
.ach-level--excellent { background: #eef4fd; border-color: #bfd4f5; color: #1d4ed8; }
.ach-level--very-good { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.ach-level--good { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.ach-level--needs-further-development { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }

.ach-status { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
.ach-status--verified { background: #e7f6ee; color: #0b7a45; }
.ach-status--partial { background: #fdf1e2; color: #92400e; }
.ach-status--provisional { background: #fff7ed; color: #b45309; }
.ach-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex: none; }
.ach-dot--verified { background: #0f9d58; }
.ach-dot--partial { background: #b45309; }
.ach-dot--provisional { background: #d97706; }

.ach-view-sheet { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; padding: .45rem .8rem; white-space: nowrap; }

/* ---------- Score Calculation ---------- */
.ach-calc-table-wrap { overflow-x: auto; border-radius: 0; }
.ach-calc-table { width: 100%; border-collapse: collapse; margin: 0; }
.ach-calc-table th { text-align: left; padding: .85rem 1rem; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); border-bottom: 1px solid var(--border-subtle, #e5e7eb); white-space: nowrap; }
.ach-calc-table th.text-end, .ach-calc-table td.text-end { text-align: right; font-variant-numeric: tabular-nums; }
.ach-calc-table td { padding: .9rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .88rem; color: var(--text, #1f2937); vertical-align: middle; }
.ach-calc-table tr:last-child td { border-bottom: none; }
.ach-calc-row--evaluated td { background: #f0fdf4; }
.ach-calc-row--unevaluated td { background: #f8fafc; }
.ach-calc-row--total td { font-weight: 800; background: #eef4fd; border-top: 2px solid var(--border-subtle, #e5e7eb); }
.ach-calc-row--available td { font-weight: 700; background: #f8fafc; color: #0f9d58; border-top: 1px solid var(--border-subtle, #e5e7eb); }
.ach-calc-criterion { font-weight: 700; color: var(--navy, #1e3a5f); }
.ach-calc-status { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .5rem; border-radius: 999px; font-size: .68rem; font-weight: 700; white-space: nowrap; }
.ach-calc-status--evaluated { background: #e7f6ee; color: #0b7a45; }
.ach-calc-status--excluded { background: #f1f5f9; color: #64748b; }
.ach-calc-note { margin-top: 1rem; padding: 1rem 1.25rem; background: #fffbeb; border: 1px solid #fed7aa; border-radius: 12px; color: #7c2d12; font-size: .85rem; line-height: 1.6; }
.ach-calc-note strong { color: #92400e; }
.ach-panel > .ach-calc-table-wrap + .ach-calc-note { margin-top: .75rem; }

/* ---------- Criterion Breakdown ---------- */
.ach-bk-controls { display: flex; gap: .5rem; flex-wrap: wrap; }
.ach-breakdown-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; align-items: start; }
.ach-bk-card { border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; overflow: hidden; background: var(--surface, #fff); }
.ach-bk-head { display: flex; align-items: center; gap: .9rem; width: 100%; padding: 1rem; background: #f8fafc; border: 0; text-align: left; cursor: pointer; }
.ach-bk-head:hover { background: #f1f5fb; }
.ach-bk-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: none; border-radius: 10px; background: #eef4fd; color: #2563eb; }
.ach-bk-icon svg { width: 20px; height: 20px; }
.ach-bk-main { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.ach-bk-name-row { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
.ach-bk-name { font-weight: 800; color: var(--navy, #1e3a5f); font-size: .95rem; }
.ach-bk-weight { font-size: .72rem; font-weight: 700; color: #2563eb; background: #eef4fd; padding: .15rem .5rem; border-radius: 999px; }
.ach-bk-score { margin-top: .2rem; }
.ach-bk-points { font-size: 1.25rem; font-weight: 800; color: var(--muted, #64748b); font-variant-numeric: tabular-nums; }
.ach-bk-points.has-value { color: var(--navy, #1e3a5f); }
.ach-bk-points-max { font-size: .85rem; color: var(--muted, #64748b); }
.ach-bk-sample { display: block; margin-top: .1rem; font-size: .72rem; color: var(--muted, #64748b); }
.ach-bk-chevron { width: 16px; height: 16px; flex: none; color: var(--muted, #64748b); transition: transform .2s ease; }
.ach-bk-head[aria-expanded="true"] .ach-bk-chevron { transform: rotate(180deg); }
.ach-bk-head[aria-expanded="true"] { border-bottom: 1px solid var(--border-subtle, #e5e7eb); }
.ach-bk-body { padding: 1rem; }
.ach-bk-body-inner { display: flex; flex-direction: column; gap: 1rem; }
.ach-bk-field-label { display: block; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted, #64748b); margin-bottom: .25rem; }
.ach-bk-field-value { margin: 0; color: var(--text, #1f2937); font-size: .88rem; line-height: 1.55; }

/* ---------- Integration Status ---------- */
.ach-integration-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
.ach-integration-card { display: flex; align-items: center; gap: .85rem; padding: 1rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-integration-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; flex: none; border-radius: 10px; }
.ach-integration-icon svg { width: 20px; height: 20px; }
.ach-integration-icon--ok { background: #e7f6ee; color: #0b7a45; }
.ach-integration-icon--warn { background: #fff7ed; color: #b45309; }
.ach-integration-icon--muted { background: #f1f5f9; color: #64748b; }
.ach-integration-icon--danger { background: #fef2f2; color: #b91c1c; }
.ach-integration-name { display: block; font-weight: 800; color: var(--navy, #1e3a5f); font-size: .9rem; }
.ach-integration-desc { display: block; font-size: .8rem; color: var(--muted, #64748b); margin-top: .1rem; }
.ach-integration-actions { padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-top: none; border-radius: 0 0 14px 14px; margin-top: -1px; }
.ach-integration-actions + * { }
.ach-panel:has(.ach-integration-actions) .ach-integration-grid { border-radius: 0; }

/* ---------- Notes ---------- */
.ach-notes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.ach-notes > div { padding: 1rem 1.15rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-notes h2 { margin: 0 0 .3rem; color: var(--navy, #1e3a5f); font-size: .88rem; font-weight: 700; }
.ach-notes p { margin: 0; color: var(--muted, #64748b); font-size: .8rem; line-height: 1.55; }

/* ---------- Empty State ---------- */
.ach-empty { display: flex; flex-direction: column; align-items: center; gap: .55rem; padding: 3rem 1.5rem; text-align: center; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); }
.ach-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex: none; border-radius: 14px; background: #f1f5f9; color: #64748b; }
.ach-empty-icon svg { width: 24px; height: 24px; }
.ach-empty h2 { margin: .35rem 0 0; color: var(--navy, #1e3a5f); font-size: 1.05rem; font-weight: 700; }
.ach-empty p { max-width: 34rem; margin: 0; color: var(--muted, #64748b); font-size: .87rem; line-height: 1.55; }
.ach-empty-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; margin-top: .4rem; }

/* ---------- Responsive ---------- */
@media (max-width: 1199px) {
    .ach-stats, .ach-podium, .ach-weights-grid, .ach-integration-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-breakdown-grid, .ach-notes { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 900px) {
    .ach-head { flex-direction: column; align-items: stretch; }
    .ach-head-side { align-items: flex-start; }
}
@media (max-width: 640px) {
    .ach-stats { gap: .75rem; }
    .ach-stat { padding: .85rem .9rem; }
    .ach-stat-value { font-size: 1.5rem; }
    .ach-podium { grid-template-columns: minmax(0, 1fr); }
    .ach-head h1 { font-size: 1.35rem; }
    .ach-periods { width: 100%; overflow-x: auto; }
    .ach-search, .ach-search input { width: 100%; }
    .ach-card-tools { width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .ach-bk-chevron, .ach-period { transition: none; }
}

@media print {
    .ach-periods, .ach-search, .ach-bk-controls, .ach-view-sheet, .ach-integration-actions { display: none !important; }
    .ach-bk-body[hidden] { display: block !important; }
    .ach-rank-page { gap: 1rem; }
    .ach-card, .ach-stat, .ach-podium-card { box-shadow: none; break-inside: avoid; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ---- Criterion breakdown accordion ----
    function setOpen(button, open) {
        var body = document.getElementById(button.getAttribute('aria-controls'));
        if (!body) return;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        body.hidden = !open;
    }

    document.querySelectorAll('[data-bk-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(button, button.getAttribute('aria-expanded') !== 'true');
        });
    });

    document.querySelectorAll('[data-bk-all]').forEach(function (control) {
        control.addEventListener('click', function () {
            var open = control.getAttribute('data-bk-all') === 'open';
            document.querySelectorAll('[data-bk-toggle]').forEach(function (button) {
                setOpen(button, open);
            });
        });
    });

    // ---- Ranking table filter ----
    var input = document.getElementById('ach-search');
    var table = document.getElementById('ach-table');
    if (!input || !table) return;

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
    var empty = document.getElementById('ach-no-match');
    var counter = document.getElementById('ach-visible-count');

    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();
        var visible = 0;

        rows.forEach(function (row) {
            var match = term === '' || row.getAttribute('data-search').indexOf(term) !== -1;
            row.hidden = !match;
            if (match) visible++;
        });

        if (empty) empty.hidden = visible !== 0;
        if (counter) counter.textContent = visible;
    });
})();
</script>
@endpush