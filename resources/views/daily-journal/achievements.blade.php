@extends('layouts.dashboard')

@section('title', 'Achievement Rankings')

@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $periodLabels = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'custom' => 'Custom range',
    ];

    $topScore = $rankings->max('score') ?? 0;
    $topPerformers = $rankings->take(3);
    $rangeLabel = $start->isSameDay($end)
        ? $start->format('F j, Y')
        : $start->format('M j, Y').' – '.$end->format('M j, Y');

    $periodUrl = fn (string $value): string => route('daily-journal.achievements', ['period' => $value]);

    // Criterion weights (preserved from scoring configuration).
    $weights = [
        'quality' => (float) config('achievements.scoring_weights.quality', 50),
        'timeliness' => (float) config('achievements.scoring_weights.timeliness', 20),
        'responsiveness' => (float) config('achievements.scoring_weights.responsiveness', 20),
        'client_feedback' => (float) config('achievements.scoring_weights.client_feedback', 10),
    ];

    $weightLabels = [
        'quality' => 'Accomplishment Quality',
        'timeliness' => 'Timeliness',
        'responsiveness' => 'Immediate Responsiveness',
        'client_feedback' => 'Client Feedback',
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
                <span class="is-current">Achievement Rankings</span>
            </nav>
            <h1>Achievement Rankings</h1>
            <p>Transparent employee performance rankings across Accomplishment Quality, Timeliness, Immediate Responsiveness, and Client Feedback.</p>
        </div>
        <div class="ach-range-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>{{ $rangeLabel }}</span>
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
            <a href="{{ $periodUrl('week') }}" class="btn btn-outline btn-sm">Try this week</a>
            <a href="{{ $periodUrl('month') }}" class="btn btn-outline btn-sm">Try this month</a>
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
            @php $levelSlug = Str::slug($performer['level']); @endphp
            <article class="ach-podium-card ach-podium-card--{{ $performer['badge'] ?? 'plain' }}">
                <div class="ach-podium-rank">
                    <span class="ach-medal ach-medal--{{ $performer['badge'] ?? 'plain' }}">#{{ $performer['rank'] }}</span>
                    <span class="ach-podium-label">{{ $performer['badge'] === 'gold' ? 'Top performer' : 'Recognition' }}</span>
                </div>
                <div class="ach-podium-person">
                    <span class="ach-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($performer['user']->name, 0, 1)) }}</span>
                    <div>
                        <h2>{{ $performer['user']->name }}</h2>
                        <p>{{ ucfirst($performer['user']->role) }} &middot; {{ $performer['submitted_count'] }} assessed {{ Str::plural('entry', $performer['submitted_count']) }}</p>
                    </div>
                </div>
                <div class="ach-podium-score">
                    <span class="ach-score">{{ number_format((float) $performer['score'], 1) }}</span>
                    <span class="ach-level ach-level--{{ $levelSlug }}">{{ $performer['level'] }}</span>
                </div>
            </article>
        @endforeach
    </section>
    @endif

    {{-- Criterion Weights --}}
    <section class="ach-weights" aria-label="Criterion weights">
        <div class="ach-weights-head">
            <h2>How scores are weighted</h2>
            <p>Each criterion contributes to the overall score using its configured weight. Criteria with no data are excluded and remaining weights are renormalized.</p>
        </div>
        <div class="ach-weights-grid">
            @foreach ($weights as $key => $weight)
            <div class="ach-weight-card">
                <span class="ach-weight-name">{{ $weightLabels[$key] }}</span>
                <span class="ach-weight-value">{{ number_format((float) $weight, 0) }}%</span>
                <div class="ach-weight-bar">
                    <div class="ach-weight-bar-fill" style="width: {{ $weight }}%;"></div>
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
                <p>{{ $rankings->count() }} {{ Str::plural('employee', $rankings->count()) }} ranked for {{ $rangeLabel }}.</p>
            </div>
            <span class="ach-provisional-key">
                <span class="ach-dot ach-dot--verified"></span> Manual verified
                <span class="ach-dot ach-dot--provisional"></span> Automatic provisional
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover ach-table">
                <thead>
                    <tr>
                        <th scope="col">Rank</th>
                        <th scope="col">Employee</th>
                        <th scope="col">Overall Score</th>
                        <th scope="col">Level</th>
                        <th scope="col">Quality<br><span class="ach-subhead">50%</span></th>
                        <th scope="col">Timeliness<br><span class="ach-subhead">20%</span></th>
                        <th scope="col">Responsiveness<br><span class="ach-subhead">20%</span></th>
                        <th scope="col">Client Feedback<br><span class="ach-subhead">10%</span></th>
                        <th scope="col">Integration Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rankings as $ranking)
                        @php
                            $levelSlug = Str::slug($ranking['level']);
                            $statusLabel = match ($ranking['verification_status']) {
                                'verified' => 'Manual verified',
                                'partial' => 'Partially verified',
                                default => 'Automatic provisional',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="ach-rank ach-rank--{{ $ranking['badge'] ?? 'plain' }}">
                                    @if ($ranking['badge'])
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/></svg>
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
                            <td><span class="ach-score">{{ $ranking['overall_score'] !== null ? number_format((float) $ranking['overall_score'], 1) : '—' }}</span></td>
                            <td><span class="ach-level ach-level--{{ $levelSlug }}">{{ $ranking['level'] }}</span></td>
                            <td><span class="ach-score">{{ $ranking['quality_score'] !== null ? number_format((float) $ranking['quality_score'], 1) : '—' }}</span></td>
                            <td><span class="ach-score">{{ $ranking['timeliness_score'] !== null ? number_format((float) $ranking['timeliness_score'], 1) : '—' }}</span></td>
                            <td><span class="ach-score">{{ $ranking['responsiveness_score'] !== null ? number_format((float) $ranking['responsiveness_score'], 1) : '—' }}</span></td>
                            <td><span class="ach-score">{{ $ranking['client_feedback_score'] !== null ? number_format((float) $ranking['client_feedback_score'], 1) : '—' }}</span></td>
                            <td>
                                <span class="ach-status ach-status--{{ $ranking['verification_status'] }}">
                                    <span class="ach-dot ach-dot--{{ $ranking['verification_status'] === 'verified' ? 'verified' : 'provisional' }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $latestJournal = $latestJournals->get($ranking['user']->id) ?? null;
                                @endphp
                                @if ($latestJournal)
                                    <a href="{{ route('daily-journal.assessment.score-sheet', $latestJournal) }}" class="btn btn-outline btn-sm ach-view-sheet">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17V7M12 3V7M9 20H6M18 20H15"/></svg>
                                        View Score Sheet
                                    </a>
                                @else
                                    <button class="btn btn-outline btn-sm ach-view-sheet" disabled>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17V7M12 3V7M9 20H6M18 20H15"/></svg>
                                        No assessed journal
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Score Explanation: How Your Score Is Calculated --}}
    <section class="ach-calculation" aria-label="Score calculation explanation">
        <div class="ach-calc-head">
            <h2>How the overall score is calculated</h2>
            <p>Each criterion score is multiplied by its weight, then divided by the total available weight. Criteria with insufficient data are excluded, never treated as zero.</p>
        </div>

        <div class="ach-calc-table-wrap">
            <table class="ach-calc-table">
                <thead>
                    <tr>
                        <th scope="col">Criterion</th>
                        <th scope="col" class="text-end">Score</th>
                        <th scope="col" class="text-end">Weight</th>
                        <th scope="col" class="text-end">Weighted Contribution</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $calcRows = [];
                        $totalWeightedPoints = 0.0;
                        $totalAvailableWeight = 0.0;
                        $totalOriginalWeight = array_sum(array_values($weights));
                    @endphp
                    @foreach ($weights as $key => $weight)
                        @php
                            $scoreKey = $key;
                            $sampleScore = null;
                            $sampleMeta = [];
                            $sampleExplanation = '';
                            // Use the first ranking that has this criterion scored as a representative example.
                            foreach ($rankings as $r) {
                                $val = $r[$key.'_score'] ?? null;
                                if ($val !== null) {
                                    $sampleScore = $val;
                                    $sampleMeta = $r[$key.'_meta'] ?? [];
                                    $sampleExplanation = $r['criteria_explanation'] ?? '';
                                    break;
                                }
                            }
                            $isEvaluated = $sampleScore !== null;
                            $weightedPoints = $isEvaluated ? round((float) $sampleScore * $weight, 2) : null;
                            if ($isEvaluated) {
                                $totalWeightedPoints += $weightedPoints;
                                $totalAvailableWeight += $weight;
                            }
                            $calcRows[] = [
                                'key' => $key,
                                'name' => $weightLabels[$key],
                                'score' => $sampleScore,
                                'weight' => $weight,
                                'weighted_points' => $weightedPoints,
                                'is_evaluated' => $isEvaluated,
                            ];
                        @endphp
                    @endforeach
                    @foreach ($calcRows as $row)
                    <tr class="{{ $row['is_evaluated'] ? 'ach-calc-row--evaluated' : 'ach-calc-row--unevaluated' }}">
                        <td class="ach-calc-criterion">{{ $row['name'] }}</td>
                        <td class="text-end">
                            @if ($row['is_evaluated'])
                                {{ number_format((float) $row['score'], 1) }} / 100
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) $row['weight'], 0) }}%</td>
                        <td class="text-end">
                            @if ($row['is_evaluated'])
                                {{ number_format((float) $row['weighted_points'], 2) }}
                            @else
                                <span class="text-muted">Excluded</span>
                            @endif
                        </td>
                        <td>
                            <span class="ach-calc-status ach-calc-status--{{ $row['is_evaluated'] ? 'evaluated' : 'excluded' }}">
                                {{ $row['is_evaluated'] ? 'Evaluated' : 'Excluded (no data)' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                    <tr class="ach-calc-row--total">
                        <td>Total weighted points</td>
                        <td class="text-end"></td>
                        <td class="text-end">{{ number_format($totalAvailableWeight, 0) }}% available</td>
                        <td class="text-end">{{ number_format($totalWeightedPoints, 2) }}</td>
                        <td></td>
                    </tr>
                    @if ($totalAvailableWeight < $totalOriginalWeight)
                    <tr class="ach-calc-row--available">
                        <td>Final score (normalized)</td>
                        <td class="text-end"></td>
                        <td class="text-end">100%</td>
                        <td class="text-end">
                            @if ($totalAvailableWeight > 0)
                                {{ number_format(round($totalWeightedPoints / $totalAvailableWeight, 1), 1) }} / 100
                            @else
                                —
                            @endif
                        </td>
                        <td></td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="ach-calc-note">
            <strong>How this works:</strong> Criteria with insufficient data are excluded (shown as "Excluded"). Their weight is removed from the total, and the remaining weights are used to calculate the score. For example, if only Accomplishment Quality (50% weight) has data, the quality score becomes the final score because it is the only criterion being measured. An excluded criterion is never treated as zero.
        </div>
    </section>

    {{-- Detailed Criterion Breakdown --}}
    <section class="ach-breakdown" aria-label="Criterion breakdown">
        <div class="ach-breakdown-head">
            <h2>Detailed criterion breakdown</h2>
            <p>Expand each criterion to see what it measures, which records were included or excluded, and how the result was determined.</p>
        </div>

        <div class="ach-breakdown-grid">
            {{-- Accomplishment Quality --}}
            <div class="ach-breakdown-card ach-breakdown-card--quality">
                <div class="ach-breakdown-head" onclick="toggleBreakdown('quality')">
                    <div class="ach-breakdown-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="m9 14 2 2 4-4"/></svg>
                    </div>
                    <div class="ach-breakdown-main">
                        <div class="ach-breakdown-name-row">
                            <span class="ach-breakdown-name">Accomplishment Quality</span>
                            <span class="ach-breakdown-weight">50% weight</span>
                        </div>
                        <div class="ach-breakdown-score">
                            <span class="ach-breakdown-points">—</span>
                            <span class="ach-breakdown-points-max"> / 100</span>
                        </div>
                    </div>
                    <button type="button" class="ach-breakdown-toggle" data-target="quality" aria-expanded="false" aria-controls="ach-breakdown-body-quality">
                        <svg class="ach-breakdown-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="ach-breakdown-body" id="ach-breakdown-body-quality" hidden>
                    <div class="ach-breakdown-body-inner">
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">What it measures</span>
                            <p class="ach-breakdown-field-value">The average assessed score of the employee's Daily Accomplishment Reports. Based on evidence, completeness, accuracy, clarity, and quality of submitted accomplishment reports.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Source</span>
                            <p class="ach-breakdown-field-value">Daily Accomplishment / Daily Journal only. Kaizen Strategy, Admin Concerns, and Priority records are not used for this criterion.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Weighted contribution</span>
                            <p class="ach-breakdown-field-value">Score × 50% weight. Excluded if no assessed accomplishment reports exist in the period.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Timeliness --}}
            <div class="ach-breakdown-card ach-breakdown-card--timeliness">
                <div class="ach-breakdown-head" onclick="toggleBreakdown('timeliness')">
                    <div class="ach-breakdown-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="ach-breakdown-main">
                        <div class="ach-breakdown-name-row">
                            <span class="ach-breakdown-name">Timeliness</span>
                            <span class="ach-breakdown-weight">20% weight</span>
                        </div>
                        <div class="ach-breakdown-score">
                            <span class="ach-breakdown-points">—</span>
                            <span class="ach-breakdown-points-max"> / 100</span>
                        </div>
                    </div>
                    <button type="button" class="ach-breakdown-toggle" data-target="timeliness" aria-expanded="false" aria-controls="ach-breakdown-body-timeliness">
                        <svg class="ach-breakdown-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="ach-breakdown-body" id="ach-breakdown-body-timeliness" hidden>
                    <div class="ach-breakdown-body-inner">
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">What it measures</span>
                            <p class="ach-breakdown-field-value">Whether assigned work was completed on or before its deadline. Considers completed-before-deadline, on-deadline, late, and overdue outcomes.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Sources</span>
                            <p class="ach-breakdown-field-value">Kaizen Strategy, Admin Concerns, and Daily Accomplishment. Uses the actual target deadline and verified implementation/completion date.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Missing deadline</span>
                            <p class="ach-breakdown-field-value">If a record has no target date, it is excluded from timeliness with the reason "no deadline set" — not scored as zero.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Immediate Responsiveness --}}
            <div class="ach-breakdown-card ach-breakdown-card--responsiveness">
                <div class="ach-breakdown-head" onclick="toggleBreakdown('responsiveness')">
                    <div class="ach-breakdown-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 8 10"/></svg>
                    </div>
                    <div class="ach-breakdown-main">
                        <div class="ach-breakdown-name-row">
                            <span class="ach-breakdown-name">Immediate Responsiveness</span>
                            <span class="ach-breakdown-weight">20% weight</span>
                        </div>
                        <div class="ach-breakdown-score">
                            <span class="ach-breakdown-points">—</span>
                            <span class="ach-breakdown-points-max"> / 100</span>
                        </div>
                    </div>
                    <button type="button" class="ach-breakdown-toggle" data-target="responsiveness" aria-expanded="false" aria-controls="ach-breakdown-body-responsiveness">
                        <svg class="ach-breakdown-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="ach-breakdown-body" id="ach-breakdown-body-responsiveness" hidden>
                    <div class="ach-breakdown-body-inner">
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">What it measures</span>
                            <p class="ach-breakdown-field-value">The time between assignment and the first attributable staff acknowledgment or response.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Sources</span>
                            <p class="ach-breakdown-field-value">Kaizen Strategy and Admin Concerns only. Daily Accomplishment is not used for this criterion.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Missing timestamps</span>
                            <p class="ach-breakdown-field-value">If assignment or first-response timestamps are unavailable, the item is excluded per the normalization policy — never estimated or invented.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Client Feedback --}}
            <div class="ach-breakdown-card ach-breakdown-card--client-feedback">
                <div class="ach-breakdown-head" onclick="toggleBreakdown('client_feedback')">
                    <div class="ach-breakdown-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="ach-breakdown-main">
                        <div class="ach-breakdown-name-row">
                            <span class="ach-breakdown-name">Client Feedback</span>
                            <span class="ach-breakdown-weight">10% weight</span>
                        </div>
                        <div class="ach-breakdown-score">
                            <span class="ach-breakdown-points">—</span>
                            <span class="ach-breakdown-points-max"> / 100</span>
                        </div>
                    </div>
                    <button type="button" class="ach-breakdown-toggle" data-target="client_feedback" aria-expanded="false" aria-controls="ach-breakdown-body-client_feedback">
                        <svg class="ach-breakdown-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
                <div class="ach-breakdown-body" id="ach-breakdown-body-client_feedback" hidden>
                    <div class="ach-breakdown-body-inner">
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">What it measures</span>
                            <p class="ach-breakdown-field-value">Verified client feedback attributable to the employee, based on Client Concerns linked to that staff member.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">Source</span>
                            <p class="ach-breakdown-field-value">Client Concerns only. Generic Admin Concerns or other records are not used as client feedback.</p>
                        </div>
                        <div class="ach-breakdown-field">
                            <span class="ach-breakdown-field-label">No attributable feedback</span>
                            <p class="ach-breakdown-field-value">When no verifiable client feedback can be linked to the employee, this criterion is shown as N/A and excluded from the score.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Integration Status Panel --}}
    <section class="ach-integration" aria-label="Score sheet integration">
        <div class="ach-integration-head">
            <h2>Score Sheet Integration</h2>
            <p>Traceable status of how each evaluation contributes to the correct employee's Score Sheet.</p>
        </div>

        <div class="ach-integration-grid">
            <div class="ach-integration-card">
                <div class="ach-integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="ach-integration-body">
                    <span class="ach-integration-name">Integrated</span>
                    <span class="ach-integration-desc">Confirmed included in the correct employee's assessment.</span>
                </div>
            </div>
            <div class="ach-integration-card">
                <div class="ach-integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="ach-integration-body">
                    <span class="ach-integration-name">Pending</span>
                    <span class="ach-integration-desc">Waiting for required review or processing.</span>
                </div>
            </div>
            <div class="ach-integration-card">
                <div class="ach-integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="ach-integration-body">
                    <span class="ach-integration-name">Excluded</span>
                    <span class="ach-integration-desc">Not eligible, with a clear reason recorded.</span>
                </div>
            </div>
            <div class="ach-integration-card">
                <div class="ach-integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="ach-integration-body">
                    <span class="ach-integration-name">Failed</span>
                    <span class="ach-integration-desc">Integration encountered an error; see audit trail.</span>
                </div>
            </div>
        </div>

        @if ($awaitingReview > 0 && auth()->user()?->isAdmin())
        <div class="ach-integration-actions">
            <a href="{{ route('admin.daily-journal.admin-monitor') }}" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="6" x2="12" y2="14"/><line x1="12" y1="18" x2="12" y2="14"/></svg>
                Review Pending Assessments
            </a>
        </div>
        @endif
    </section>

    {{-- Notes --}}
    <section class="ach-notes" aria-label="How rankings work">
        <div class="ach-notes-grid">
            <div>
                <h2>How this ranking works</h2>
                <p>
                    Employees are ordered by their overall achievement score, which combines four criteria using
                    configurable weights: Accomplishment Quality (50%), Timeliness (20%), Immediate Responsiveness (20%),
                    and Client Feedback (10%). Duplicate accomplishment text inside the period is counted once so
                    copy-pasted submissions cannot inflate a position. Differences in role are shown so work is read in
                    context rather than assumed to be identical.
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
        </div>
    </section>
</div>
@endif
@endsection

@push('styles')
<style>
/* ============================================================
   Achievement Rankings - Premium Redesign
   Scoped to .ach-* so shared dashboard styles stay untouched.
   ============================================================ */

.ach-rank-page { display: flex; flex-direction: column; gap: 1.5rem; }

/* ---------- Page Header ---------- */
.ach-head { align-items: flex-start; gap: 1rem; }
.ach-crumbs { display: flex; align-items: center; gap: .3rem; margin-bottom: .4rem; color: var(--muted, #64748b); font-size: .75rem; font-weight: 600; letter-spacing: .02em; }
.ach-crumbs svg { width: 12px; height: 12px; flex: none; opacity: .6; }
.ach-crumbs .is-current { color: var(--navy, #1e3a5f); }
.ach-head h1 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1.55rem; font-weight: 800; letter-spacing: -.015em; line-height: 1.2; }
.ach-head p { margin: .35rem 0 0; color: var(--muted, #64748b); font-size: .9rem; }
.ach-range-chip { display: inline-flex; align-items: center; gap: .5rem; flex: none; padding: .55rem .85rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; color: var(--navy, #1e3a5f); font-size: .82rem; font-weight: 700; box-shadow: 0 4px 14px rgba(15, 47, 91, .05); }
.ach-range-chip svg { width: 16px; height: 16px; flex: none; color: #2563eb; }

/* ---------- Summary Cards ---------- */
.ach-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: .25rem; }
.ach-stat { display: flex; flex-direction: column; gap: .2rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 6px 18px rgba(15, 47, 91, .05); }
.ach-stat-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.ach-stat-value { color: var(--navy, #1e3a5f); font-size: 1.8rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
.ach-stat-note { color: var(--muted, #64748b); font-size: .74rem; }
.ach-stat--pending { border-left: 3px solid #d97706; }
.ach-stat--review { border-left: 3px solid #92400e; }
.ach-stat--criteria { border-left: 3px solid #2563eb; }

/* ---------- Top Performers ---------- */
.ach-podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: .25rem; }
.ach-podium-card { display: flex; flex-direction: column; gap: .75rem; padding: 1rem 1.1rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .06); }
.ach-podium-card--gold { border-color: #e6c66b; background: linear-gradient(180deg, #fffdf4, #fff); }
.ach-podium-card--silver { border-color: #cbd5e1; }
.ach-podium-card--bronze { border-color: #d9b08c; }
.ach-podium-rank { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.ach-podium-label { color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.ach-medal { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 .5rem; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.ach-medal--gold { background: #fef3c7; color: #92400e; }
.ach-medal--silver { background: #e2e8f0; color: #475569; }
.ach-medal--bronze { background: #fde8d7; color: #9a3412; }
.ach-podium-person { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.ach-podium-person h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: .98rem; font-weight: 800; overflow-wrap: anywhere; }
.ach-podium-person p { margin: .1rem 0 0; color: var(--muted, #64748b); font-size: .75rem; }
.ach-podium-score { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: auto; }
.ach-score { color: var(--navy, #1e3a5f); font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.ach-podium-score .ach-score { font-size: 1.6rem; }

/* ---------- Criterion Weights ---------- */
.ach-weights { margin-bottom: .25rem; }
.ach-weights-head { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px 14px 0 0; border-bottom: none; }
.ach-weights-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-weights-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-weights-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 14px 14px; border-top: none; }
.ach-weight-card { display: flex; flex-direction: column; gap: .5rem; padding: 1rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-weight-name { font-size: .78rem; font-weight: 700; color: var(--muted, #64748b); letter-spacing: .04em; text-transform: uppercase; }
.ach-weight-value { font-size: 1.5rem; font-weight: 800; color: var(--navy, #1e3a5f); }
.ach-weight-bar { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.ach-weight-bar-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #1d4ed8); border-radius: 999px; }

/* ---------- Cards & Table ---------- */
.ach-card { background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); overflow: hidden; }
.ach-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.05rem 1.25rem; border-bottom: 1px solid var(--border-subtle, #e5e7eb); }
.ach-card-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-card-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-provisional-key { display: inline-flex; align-items: center; gap: .35rem; flex: none; color: var(--muted, #64748b); font-size: .72rem; font-weight: 600; }

.ach-table { margin: 0; min-width: 820px; }
.ach-table thead th { padding: .75rem 1.1rem; background: #f8fafc; border-bottom: 1px solid var(--border-subtle, #e5e7eb); color: var(--muted, #64748b); font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
.ach-table thead th .ach-subhead { display: block; font-size: .62rem; font-weight: 600; color: #94a3b8; margin-top: .15rem; text-transform: none; letter-spacing: normal; }
.ach-table tbody td { padding: .8rem 1.1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.ach-table tbody tr:last-child td { border-bottom: none; }

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
.ach-dot--provisional { background: #d97706; }

.ach-view-sheet { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600; padding: .45rem .8rem; }

/* ---------- Score Calculation ---------- */
.ach-calculation { margin-top: .25rem; }
.ach-calc-head { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px 14px 0 0; border-bottom: none; }
.ach-calc-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-calc-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-calc-table-wrap { padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 14px 14px; border-top: none; overflow-x: auto; }
.ach-calc-table { width: 100%; border-collapse: collapse; margin: 0; }
.ach-calc-table th { text-align: left; padding: .85rem 1rem; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); border-bottom: 1px solid var(--border-subtle, #e5e7eb); white-space: nowrap; }
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
.ach-calc-note { padding: 1rem 1.25rem; background: #fffbeb; border: 1px solid #fed7aa; border-radius: 12px; margin: 0; }
.ach-calc-note strong { color: #92400e; }
.ach-calc-note { color: #7c2d12; font-size: .85rem; line-height: 1.6; }

/* ---------- Criterion Breakdown ---------- */
.ach-breakdown { margin-top: .25rem; }
.ach-breakdown-head { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px 14px 0 0; border-bottom: none; }
.ach-breakdown-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-breakdown-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-breakdown-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 14px 14px; border-top: none; }
.ach-breakdown-card { border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
.ach-breakdown-head-card { cursor: pointer; }
.ach-breakdown-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; background: #f8fafc; border-bottom: 1px solid var(--border-subtle, #e5e7eb); cursor: pointer; }
.ach-breakdown-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: none; border-radius: 10px; background: #eef4fd; color: #2563eb; }
.ach-breakdown-icon svg { width: 20px; height: 20px; flex: none; }
.ach-breakdown-main { min-width: 0; flex: 1; }
.ach-breakdown-name-row { display: flex; align-items: baseline; gap: .5rem; }
.ach-breakdown-name { font-weight: 800; color: var(--navy, #1e3a5f); font-size: .95rem; }
.ach-breakdown-weight { font-size: .75rem; font-weight: 700; color: #2563eb; background: #eef4fd; padding: .15rem .5rem; border-radius: 999px; }
.ach-breakdown-score { margin-top: .2rem; }
.ach-breakdown-points { font-size: 1.25rem; font-weight: 800; color: var(--muted, #64748b); }
.ach-breakdown-points-max { font-size: .85rem; color: var(--muted, #64748b); }
.ach-breakdown-toggle { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: none; background: transparent; border: none; color: var(--muted, #64748b); }
.ach-breakdown-toggle svg { width: 14px; height: 14px; flex: none; }
.ach-breakdown-toggle-icon { transition: transform .2s ease; }
.ach-breakdown-body { padding: 1rem; background: var(--surface, #fff); }
.ach-breakdown-body-inner { display: flex; flex-direction: column; gap: 1rem; }
.ach-breakdown-field { }
.ach-breakdown-field-label { display: block; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted, #64748b); margin-bottom: .25rem; }
.ach-breakdown-field-value { margin: 0; color: var(--text, #1f2937); font-size: .88rem; line-height: 1.55; }

/* ---------- Integration Status ---------- */
.ach-integration { margin-top: .25rem; }
.ach-integration-head { padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px 14px 0 0; border-bottom: none; }
.ach-integration-head h2 { margin: 0; color: var(--navy, #1e3a5f); font-size: 1rem; font-weight: 700; }
.ach-integration-head p { margin: .2rem 0 0; color: var(--muted, #64748b); font-size: .8rem; }
.ach-integration-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; padding: 1rem 1.25rem; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 0 0 14px 14px; border-top: none; }
.ach-integration-card { display: flex; align-items: center; gap: .85rem; padding: 1rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-integration-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; flex: none; border-radius: 10px; background: #eef4fd; color: #2563eb; font-size: 20px; }
.ach-integration-body { }
.ach-integration-name { display: block; font-weight: 800; color: var(--navy, #1e3a5f); font-size: .9rem; }
.ach-integration-desc { display: block; font-size: .8rem; color: var(--muted, #64748b); margin-top: .1rem; }
.ach-integration-actions { padding: 1rem 1.25rem; background: var(--surface, #fff); border-top: 1px solid var(--border-subtle, #e5e7eb); }

/* ---------- Notes ---------- */
.ach-notes { margin-top: .25rem; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.ach-notes > div { padding: 1rem 1.15rem; background: #f8fafc; border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 12px; }
.ach-notes h2 { margin: 0 0 .3rem; color: var(--navy, #1e3a5f); font-size: .88rem; font-weight: 700; }
.ach-notes p { margin: 0; color: var(--muted, #64748b); font-size: .8rem; line-height: 1.55; }

/* ---------- Empty State ---------- */
.ach-empty { display: flex; flex-direction: column; align-items: center; gap: .55rem; margin-bottom: 1.25rem; padding: 3rem 1.5rem; text-align: center; background: var(--surface, #fff); border: 1px solid var(--border-subtle, #e5e7eb); border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 47, 91, .05); }
.ach-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; flex: none; border-radius: 14px; background: #f1f5f9; color: #64748b; }
.ach-empty-icon svg { width: 24px; height: 24px; flex: none; }
.ach-empty h2 { margin: .35rem 0 0; color: var(--navy, #1e3a5f); font-size: 1.05rem; font-weight: 700; }
.ach-empty p { max-width: 34rem; margin: 0; color: var(--muted, #64748b); font-size: .87rem; line-height: 1.55; }
.ach-empty-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .4rem; }

/* ---------- Responsive ---------- */
@media (max-width: 1199px) {
    .ach-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-podium { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-weights-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-integration-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-breakdown-grid { grid-template-columns: minmax(0, 1fr); }
    .ach-notes { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 900px) {
    .ach-head { flex-direction: column; align-items: stretch; }
}
@media (max-width: 640px) {
    .ach-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .ach-stat { padding: .85rem .9rem; }
    .ach-stat-value { font-size: 1.5rem; }
    .ach-podium { grid-template-columns: minmax(0, 1fr); }
    .ach-weights-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-integration-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ach-table { min-width: 700px; }
    .ach-head h1 { font-size: 1.35rem; }
}
</style>
@endpush

@push('scripts')
<script>
    function toggleBreakdown(key) {
        const body = document.getElementById('ach-breakdown-body-' + key);
        const btn = document.querySelector('[data-target="' + key + '"]');
        const icon = btn ? btn.querySelector('.ach-breakdown-toggle-icon') : null;
        const isHidden = body && body.hasAttribute('hidden');

        if (isHidden) {
            body.removeAttribute('hidden');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            if (icon) icon.innerHTML = '<polyline points="6 15 12 9 18 15"/>';
        } else {
            body.setAttribute('hidden', '');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            if (icon) icon.innerHTML = '<polyline points="6 9 12 15 18 9"/>';
        }
    }
</script>
@endpush