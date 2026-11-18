@extends('layouts.dashboard')

@section('title', 'Score Sheet — ' . $journal->user->name)

@section('content')
@php
    use Illuminate\Support\Str;

    /* ------------------------------------------------------------------
     | Core values
     ------------------------------------------------------------------ */
    $assessmentLevel = $journal->achievement_level ?? null;
    $levelColor = match ($assessmentLevel) {
        'Outstanding' => '#34d399',
        'Excellent' => '#60a5fa',
        'Very Good' => '#a5b4fc',
        'Good' => '#cbd5e1',
        'Needs Further Development' => '#fbbf24',
        default => '#cbd5e1',
    };

    $isManual = $journal->assessment_source === 'reviewer';
    $assessmentType = $isManual ? 'Manual Reassessment' : 'Automatic Assessment';
    $hasManualOverride = $journal->hasManualOverride();
    $originalAiScore = $journal->originalAiScore();
    $finalScore = $journal->finalScore();
    $manualAdjustment = $journal->manualAdjustment();
    $evidenceCount = $journal->evidenceCount();
    $submittedAt = $journal->submitted_at;
    $assessedAt = $journal->assessed_at;
    $criterionScores = $journal->criterionScores();
    $criterionEvidence = $criterionEvidence ?? [];
    $weightsUsed = $combined['weights_used'] ?? [];

    $tone = fn ($s) => $s === null ? '#cbd5e1' : ((float) $s >= 70 ? '#10b981' : ((float) $s >= 40 ? '#f59e0b' : '#ef4444'));
    $fmt = fn ($v, int $d = 1) => $v !== null ? number_format((float) $v, $d) : '—';
    $clamp = fn ($v) => max(0, min(100, (float) $v));

    // The two parts of the score: tasks (90) and problems & solutions (10).
    $configWeights = [
        'target_completion' => (float) ($weights['target_completion'] ?? 90),
        'problems_solutions' => (float) ($weights['problems_solutions'] ?? 10),
    ];
    $configWeightTotal = array_sum($configWeights);
    $weightShare = fn (string $key) => $configWeightTotal > 0 ? ($configWeights[$key] / $configWeightTotal) * 100 : 0;

    $criteriaList = [
        'target_completion' => ['name' => 'Task Completion', 'short' => 'Tasks', 'color' => '#2563eb',
            'icon' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="m9 14 2 2 4-4"/>'],
        'problems_solutions' => ['name' => 'Problems & Solutions', 'short' => 'Problems & Solutions', 'color' => '#7c3aed',
            'icon' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/>'],
    ];

    /* ------------------------------------------------------------------
     | Effective weights, contribution to the final score, insights
     ------------------------------------------------------------------ */
    $effective = [];
    $totalEffective = 0.0;
    foreach ($criteriaList as $k => $c) {
        if (($criteria[$k] ?? null) !== null) {
            $effective[$k] = (float) ($weightsUsed[$k] ?? $configWeights[$k]);
            $totalEffective += $effective[$k];
        }
    }
    $contribution = [];
    $potentialGain = [];
    foreach ($effective as $k => $w) {
        $contribution[$k] = $totalEffective > 0 ? ((float) $criteria[$k] * $w) / $totalEffective : 0;
        $potentialGain[$k] = $totalEffective > 0 ? ((100 - (float) $criteria[$k]) * $w) / $totalEffective : 0;
    }
    $evaluatedCount = count($effective);

    $strongestKey = null;
    $opportunityKey = null;
    if ($evaluatedCount > 0) {
        $byScore = collect(array_keys($effective))->sortByDesc(fn ($k) => (float) $criteria[$k])->values();
        $strongestKey = $byScore->first();
        if ($evaluatedCount > 1) {
            $opportunityKey = collect($potentialGain)->sortDesc()->keys()->first();
            if ($opportunityKey === $strongestKey) {
                $opportunityKey = $byScore->last();
            }
        }
    }

    /* Findings (what went well). */
    $positiveFindings = [];
    if ((int) $breakdown['assigned_completed'] > 0) {
        $positiveFindings[] = ['title' => 'Assigned tasks completed', 'detail' => (int) $breakdown['assigned_completed'] . ' of ' . (int) $breakdown['assigned_total'] . ' assigned task(s) were completed.', 'note' => 'Each completed task earns ' . number_format((float) $breakdown['assigned_points_each'], 1) . ' points.'];
    }
    if ((int) $breakdown['urgent_count'] > 0) {
        $positiveFindings[] = ['title' => 'Urgent tasks handled', 'detail' => (int) $breakdown['urgent_count'] . ' urgent task(s) outside Goals for the Day were added to the report.', 'note' => 'Each urgent task earns ' . number_format((float) $breakdown['urgent_points_each'], 1) . ' points, which is 80% of an assigned task.'];
    }
    if ($breakdown['problems_filled']) {
        $positiveFindings[] = ['title' => 'Problems described', 'detail' => 'Problems Encountered was filled in.', 'note' => 'This earns 5 of the 10 problems and solutions points.'];
    }
    if ($breakdown['solutions_filled']) {
        $positiveFindings[] = ['title' => 'Solutions suggested', 'detail' => 'Suggested Solutions was filled in.', 'note' => 'This earns 5 of the 10 problems and solutions points.'];
    }
    if ($evidenceCount > 0) {
        $positiveFindings[] = ['title' => 'Supporting evidence attached', 'detail' => $evidenceCount . ' evidence file(s) attached to the report.', 'note' => 'Evidence helps verify the work that was completed.'];
    }
    if (empty($positiveFindings)) {
        $positiveFindings[] = ['title' => 'No specific positive contributions identified', 'detail' => 'The available data does not contain specific positive findings for this day.', 'note' => 'This may be because no tasks were completed and the report sections were left empty.'];
    }
    $excludedCriteria = isset($calculationCriteria) ? collect($calculationCriteria)->filter(fn ($c) => $c['status'] !== 'Evaluated')->values() : collect();

    $tasksIncluded = $scoreSheetTasks->filter(fn ($e) => $e->isIncludedInScoreSheet())->count();
    $hasHistory = $journal->assessmentRevisions->isNotEmpty();
    $canReassess = $canReview && $journal->hasAssessment();

    /* Section navigation. */
    $nav = [['es-overview', 'Overview']];
    if ($finalScore !== null) {
        $nav[] = ['es-calculation', 'Calculation'];
        $nav[] = ['es-findings', 'Findings'];
    }
    $nav[] = ['es-tasks', 'Tasks'];
    $nav[] = ['es-criteria', 'Criteria'];
    $nav[] = ['es-sources', 'Sources'];
    if ($hasHistory) { $nav[] = ['es-history', 'History']; }
    if ($canReassess) { $nav[] = ['es-reassess', 'Reassess']; }

    $copyText = $journal->user->name . ' — Score Sheet, ' . $journal->required_date->format('F j, Y') . "\n"
        . 'Final score: ' . $fmt($finalScore) . ' / 100' . ($assessmentLevel ? ' (' . $assessmentLevel . ')' : '') . "\n"
        . collect($criteriaList)->map(fn ($c, $k) => $c['name'] . ': ' . (($criteria[$k] ?? null) !== null ? number_format((float) $criteria[$k], 1) . ' / 100' : 'not evaluated'))->implode("\n");
@endphp

<div class="es">

{{-- ===================== HERO ===================== --}}
<header class="es-hero">
    <div class="es-hero-bg" aria-hidden="true"></div>
    <div class="es-hero-top">
        <span class="es-eyebrow">Score Sheet</span>
        <div class="es-hero-actions">
            <button type="button" class="es-btn es-btn--ghost" id="es-copy" data-copy="{{ $copyText }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span id="es-copy-label">Copy summary</span>
            </button>
            <button type="button" class="es-btn es-btn--ghost" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print
            </button>
            <a href="{{ route('daily-journal.show', $journal) }}" class="es-btn es-btn--solid">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Report
            </a>
        </div>
    </div>

    <div class="es-hero-main">
        <div class="es-hero-person">
            <span class="es-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($journal->user->name, 0, 1)) }}</span>
            <div>
                <h1>{{ $journal->user->name }}</h1>
                <p class="es-hero-meta">
                    {{ ucfirst($journal->user->role) }}
                    <span class="es-dot" aria-hidden="true"></span>
                    {{ $journal->required_date->format('F j, Y') }}
                </p>
                <div class="es-hero-badges">
                    <span class="es-pill es-pill--{{ $isManual ? 'manual' : 'auto' }}">{{ $assessmentType }}</span>
                    <span class="es-pill es-pill--{{ $journal->isAssessmentVerified() ? 'verified' : 'provisional' }}">{{ $journal->assessmentStatusLabel() }}</span>
                    @if ($assessmentLevel)
                        <span class="es-pill es-pill--level" style="--lv: {{ $levelColor }};">{{ $assessmentLevel }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="es-hero-score">
            <div class="es-gauge" role="img" aria-label="Final score {{ $fmt($finalScore) }} out of 100">
                <svg viewBox="0 0 36 36" aria-hidden="true">
                    <circle cx="18" cy="18" r="15.9155" class="es-gauge-track"/>
                    @if ($finalScore !== null)
                    <circle cx="18" cy="18" r="15.9155" class="es-gauge-ring" stroke="{{ $tone($finalScore) }}"
                            stroke-dasharray="100" stroke-dashoffset="{{ round(100 - $clamp($finalScore), 2) }}" transform="rotate(-90 18 18)"/>
                    @endif
                </svg>
                <div class="es-gauge-text">
                    @if ($finalScore !== null)
                        <span class="es-gauge-value" data-countup="{{ round((float) $finalScore, 1) }}" data-decimals="1">{{ $fmt($finalScore) }}</span>
                        <span class="es-gauge-label">out of 100</span>
                    @else
                        <span class="es-gauge-value">—</span>
                        <span class="es-gauge-label">no score yet</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <dl class="es-kpis">
        <div class="es-kpi">
            <dt>Assigned tasks done</dt>
            <dd>{{ $breakdown['assigned_completed'] }} <small>/ {{ $breakdown['assigned_total'] }}</small></dd>
        </div>
        <div class="es-kpi">
            <dt>Urgent tasks</dt>
            <dd>{{ $breakdown['urgent_count'] }}</dd>
        </div>
        <div class="es-kpi">
            <dt>Task points</dt>
            <dd>{{ $fmt($breakdown['task_points']) }} <small>/ 90</small></dd>
        </div>
        <div class="es-kpi">
            <dt>Problems &amp; solutions</dt>
            <dd>{{ $fmt($breakdown['problems_points']) }} <small>/ 10</small></dd>
        </div>
        <div class="es-kpi">
            <dt>Original auto score</dt>
            <dd>{{ $fmt($originalAiScore) }}
                @if ($hasManualOverride)
                    <small class="es-kpi-delta es-kpi-delta--{{ $manualAdjustment > 0 ? 'up' : 'down' }}">{{ $manualAdjustment > 0 ? '+' : '' }}{{ number_format((float) $manualAdjustment, 1) }} manual</small>
                @endif
            </dd>
        </div>
        <div class="es-kpi">
            <dt>Submitted</dt>
            <dd class="es-kpi-text">{{ $submittedAt ? $submittedAt->format('M j, g:i A') : 'Not submitted' }}</dd>
        </div>
    </dl>
</header>

{{-- ===================== SECTION NAV ===================== --}}
<nav class="es-nav" aria-label="Score sheet sections">
    @foreach ($nav as [$anchor, $label])
        <a href="#{{ $anchor }}" class="es-nav-link" data-spy="{{ $anchor }}">{{ $label }}</a>
    @endforeach
</nav>

{{-- ===================== OVERVIEW ===================== --}}
<section class="es-section" id="es-overview" aria-labelledby="es-overview-title">
    <div class="es-section-head">
        <h2 id="es-overview-title">Overview</h2>
        <p>Where the score comes from, and what moves it most.</p>
    </div>

    @if ($finalScore === null)
        <div class="es-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <strong>No assessment data available</strong>
            <p>This report has not been assessed yet. The score sheet will be populated once an automatic or manual assessment is completed.</p>
        </div>
    @else
    <div class="es-overview-grid">
        {{-- The two parts --}}
        <article class="es-card">
            <h3 class="es-card-title">The two parts of the score</h3>
            <ul class="es-parts">
                @foreach ($criteriaList as $k => $c)
                    @php $s = $criteria[$k] ?? null; @endphp
                    <li class="es-part {{ $s === null ? 'is-empty' : '' }}" style="--c: {{ $c['color'] }};">
                        <div class="es-part-top">
                            <span class="es-part-name">{{ $c['name'] }}</span>
                            <span class="es-part-weight">{{ number_format($configWeights[$k], 0) }} points</span>
                        </div>
                        <div class="es-part-bar" role="presentation"><span style="width: {{ $s !== null ? $clamp($s) : 0 }}%;"></span></div>
                        <div class="es-part-foot">
                            @if ($s !== null)
                                <strong>{{ number_format($contribution[$k], 1) }}</strong> of {{ number_format($configWeights[$k], 0) }} points earned
                            @else
                                Not evaluated for this day
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="es-card-foot">A part with no data is excluded and the remaining weight is renormalized, never counted as zero.</p>
        </article>

        {{-- Build-up --}}
        <article class="es-card es-card--build">
            <h3 class="es-card-title">How the final score is built</h3>
            <div class="es-stack" role="img" aria-label="Stacked bar showing each part's contribution to the final score">
                @foreach ($contribution as $k => $pts)
                    <span class="es-stack-seg" style="width: {{ round($pts, 2) }}%; background: {{ $criteriaList[$k]['color'] }};" title="{{ $criteriaList[$k]['name'] }}: {{ number_format($pts, 1) }} pts"></span>
                @endforeach
            </div>
            <div class="es-stack-scale"><span>0</span><span>Final {{ $fmt($finalScore) }}</span><span>100</span></div>

            <ul class="es-build-list">
                @foreach ($criteriaList as $k => $c)
                    @php $s = $criteria[$k] ?? null; @endphp
                    <li class="es-build-row {{ $s === null ? 'is-empty' : '' }}">
                        <span class="es-swatch" style="background: {{ $c['color'] }};"></span>
                        <span class="es-build-name">{{ $c['name'] }}<small>{{ number_format($weightShare($k), 0) }}% weight</small></span>
                        <span class="es-build-bar" role="presentation"><span style="width: {{ $s !== null ? $clamp($s) : 0 }}%; background: {{ $c['color'] }};"></span></span>
                        <span class="es-build-score">{{ $s !== null ? $fmt($s) : '—' }}</span>
                        <span class="es-build-pts">{{ $s !== null ? '+' . number_format($contribution[$k], 1) . ' pts' : 'Excluded' }}</span>
                    </li>
                @endforeach
            </ul>
        </article>
    </div>

    {{-- Insights --}}
    <div class="es-insights">
        @if ($strongestKey)
        <article class="es-insight es-insight--good">
            <span class="es-insight-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
            <div>
                <span class="es-insight-label">Strongest part</span>
                <strong>{{ $criteriaList[$strongestKey]['name'] }}</strong>
                <p>{{ $fmt($criteria[$strongestKey]) }} / 100, contributing {{ number_format($contribution[$strongestKey], 1) }} points to the final score.</p>
            </div>
        </article>
        @endif
        @if ($opportunityKey)
        <article class="es-insight es-insight--warn">
            <span class="es-insight-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg></span>
            <div>
                <span class="es-insight-label">Biggest opportunity</span>
                <strong>{{ $criteriaList[$opportunityKey]['name'] }}</strong>
                <p>At {{ $fmt($criteria[$opportunityKey]) }} / 100. Raising it to 100 would add up to {{ number_format($potentialGain[$opportunityKey], 1) }} points to the final score.</p>
            </div>
        </article>
        @endif
        <article class="es-insight es-insight--info">
            <span class="es-insight-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
            <div>
                <span class="es-insight-label">What this means</span>
                <strong>{{ $assessmentLevel ?? 'Not yet assessed' }}</strong>
                <p>{{ $scoreMeaning }}</p>
            </div>
        </article>
    </div>
    <p class="es-method">{{ $assessmentMethodLabel }}. {{ $assessmentMethodDetail }}</p>
    @endif
</section>

@if ($finalScore !== null)
{{-- ===================== CALCULATION ===================== --}}
<section class="es-section" id="es-calculation" aria-labelledby="es-calc-title">
    <div class="es-section-head">
        <h2 id="es-calc-title">How the score was calculated</h2>
        <p>Each part is scored out of 100 and multiplied by its weight (90% tasks, 10% problems and solutions).</p>
    </div>
    <div class="es-card es-card--flush">
        <div class="es-table-wrap">
            <table class="es-table">
                <thead>
                    <tr>
                        <th scope="col">Part</th>
                        <th scope="col" class="num">Score</th>
                        <th scope="col" class="num">Weight</th>
                        <th scope="col" class="num">Weighted points</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($calculationCriteria as $criterion)
                    @php $rowEvaluated = $criterion['status'] === 'Evaluated'; @endphp
                    <tr class="{{ $rowEvaluated ? 'is-evaluated' : 'is-excluded' }}">
                        <th scope="row">{{ $criterion['name'] }}</th>
                        <td class="num">{{ $criterion['score'] !== null ? number_format((float) $criterion['score'], 1) . ' / 100' : '—' }}</td>
                        <td class="num">{{ number_format((float) $criterion['weight'], 0) }}%</td>
                        <td class="num">{{ $criterion['weighted_points'] !== null ? number_format((float) $criterion['weighted_points'], 2) : '—' }}</td>
                        <td><span class="es-tag es-tag--{{ $rowEvaluated ? 'ok' : 'muted' }}">{{ $rowEvaluated ? 'Evaluated' : 'Not evaluated' }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="es-tr-total">
                        <th scope="row">Total</th>
                        <td></td>
                        <td class="num">{{ number_format($totalOriginalWeight, 0) }}% original</td>
                        <td class="num">{{ number_format($totalWeightedPoints, 2) }}</td>
                        <td></td>
                    </tr>
                    @if ($totalAvailableWeight < $totalOriginalWeight)
                    <tr class="es-tr-available">
                        <th scope="row">Available after exclusions</th>
                        <td></td>
                        <td class="num">{{ number_format($totalAvailableWeight, 0) }}%</td>
                        <td class="num">{{ number_format($totalWeightedPoints, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if ($normalizedScore !== null)
                    <tr class="es-tr-final">
                        <th scope="row">Final score (normalized)</th>
                        <td class="num">{{ number_format($normalizedScore, 1) }} / 100</td>
                        <td class="num">100%</td>
                        <td class="num">{{ number_format($normalizedScore, 1) }}</td>
                        <td></td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
        <div class="es-callout">
            <strong>How this works:</strong> the 90 task points are divided equally among the tasks assigned for the day, and a task earns its share once it is completed. Each urgent task that is not in Goals for the Day adds 80% of one assigned task's share. Problems Encountered and Suggested Solutions are worth 5 points each when filled in.
            @if (!empty($normalizationExplanation))
                <span class="es-callout-extra">{{ $normalizationExplanation }}</span>
            @endif
        </div>
    </div>
</section>

{{-- ===================== FINDINGS ===================== --}}
<section class="es-section" id="es-findings" aria-labelledby="es-findings-title">
    <div class="es-section-head">
        <h2 id="es-findings-title">Why did I receive this score?</h2>
        <p>A plain-language breakdown of what helped, what held the score back, and what to do next.</p>
    </div>

    <div class="es-findings-grid">
        <article class="es-finding-card es-finding-card--good">
            <h3><span class="es-finding-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>What went well</h3>
            <ul>
                @foreach ($positiveFindings as $f)
                <li>
                    <strong>{{ $f['title'] }}</strong>
                    <p>{{ $f['detail'] }}</p>
                    <small>{{ $f['note'] }}</small>
                </li>
                @endforeach
            </ul>
        </article>

        <article class="es-finding-card es-finding-card--bad">
            <h3><span class="es-finding-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></span>What affected the score</h3>
            <ul>
                @forelse ($blockers as $blocker)
                <li><strong>Assessment finding</strong><p>{{ $blocker }}</p></li>
                @empty
                <li><p>Nothing negatively affected this score.</p></li>
                @endforelse
            </ul>
        </article>

        <article class="es-finding-card es-finding-card--muted">
            <h3><span class="es-finding-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>Parts not evaluated</h3>
            <ul>
                @forelse ($excludedCriteria as $criterion)
                <li>
                    <strong>{{ $criterion['name'] }} <span class="es-tag es-tag--muted">{{ number_format((float) $criterion['weight'], 0) }}% excluded</span></strong>
                    <p>{{ $criterion['explanation'] }}</p>
                    <small>N/A means there was no data to measure. It is not counted as zero.</small>
                </li>
                @empty
                <li><p>Both parts have been evaluated.</p></li>
                @endforelse
            </ul>
        </article>

        <article class="es-finding-card es-finding-card--next">
            <h3><span class="es-finding-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>How to improve</h3>
            <ul>
                @forelse ($improvements as $improvement)
                <li><strong>Recommended action</strong><p>{{ $improvement }}</p></li>
                @empty
                <li><p>No specific improvements were recommended. Keep up the current approach.</p></li>
                @endforelse
            </ul>
        </article>
    </div>
</section>
@endif

{{-- ===================== TASKS BEHIND THE SCORE ===================== --}}
<section class="es-section" id="es-tasks" aria-labelledby="es-tasks-title">
    <div class="es-section-head">
        <h2 id="es-tasks-title">Tasks behind the score</h2>
        <p>
            The day's assigned tasks share 90 points
            @if ($breakdown['assigned_total'] > 0)
                ({{ number_format((float) $breakdown['assigned_points_each'], 1) }} points each).
            @else
                (none were assigned for this date).
            @endif
            Urgent tasks outside Goals for the Day are worth 80% of an assigned task.
        </p>
    </div>

    <div class="es-card es-card--flush">
        @if ($assignedTasks->isEmpty() && $urgentTasks->isEmpty())
            <div class="es-empty es-empty--compact"><p>No assigned or urgent tasks for this date.</p></div>
        @else
        <div class="es-table-wrap">
            <table class="es-table">
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Task</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assignedTasks as $task)
                        @php $done = $task->isCompleted(); @endphp
                        <tr class="{{ $done ? 'is-evaluated' : 'is-excluded' }}">
                            <th scope="row">Assigned <span class="es-tag es-tag--muted">{{ $task->legend_code ?? '·' }}</span></th>
                            <td>{{ $task->getLegendLabel() }}</td>
                            <td><span class="es-tag es-tag--{{ $done ? 'ok' : 'muted' }}">{{ $done ? 'Completed' : 'Not completed' }}</span></td>
                            <td class="num">{{ $done ? '+' . number_format((float) $breakdown['assigned_points_each'], 1) : '0.0' }}</td>
                        </tr>
                    @endforeach
                    @foreach ($urgentTasks as $urgent)
                        <tr class="is-evaluated">
                            <th scope="row">Urgent</th>
                            <td>
                                {{ $urgent['description'] }}
                                @if (! empty($urgent['reason']))
                                    <small class="es-cell-note">Reason: {{ $urgent['reason'] }}</small>
                                @endif
                            </td>
                            <td><span class="es-tag es-tag--ok">Listed</span></td>
                            <td class="num">+{{ number_format((float) $breakdown['urgent_points_each'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="es-tr-total">
                        <th scope="row" colspan="3">Task points (capped at 90)</th>
                        <td class="num">{{ number_format((float) $breakdown['task_points'], 1) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</section>

{{-- ===================== CRITERIA ===================== --}}
<section class="es-section" id="es-criteria" aria-labelledby="es-criteria-title">
    <div class="es-section-head es-section-head--row">
        <div>
            <h2 id="es-criteria-title">Breakdown by part</h2>
            <p>Each part is scored out of 100. Open a card to see the reasoning and evidence.</p>
        </div>
        <button type="button" class="es-btn es-btn--outline" id="es-toggle-all">Expand all</button>
    </div>

    <div class="es-criteria">
        @foreach ($criteriaList as $scoreKey => $criterion)
            @php
                $score = $criteria[$scoreKey] ?? null;
                $isEvaluated = $score !== null;
                $manualCriterion = $criterionScores[$scoreKey] ?? null;
                $criterionWeight = $weightsUsed[$scoreKey] ?? $configWeights[$scoreKey];
                $explanation = $criteria[$scoreKey . '_explanation'] ?? 'Not yet evaluated.';
            @endphp
            <article class="es-crit" id="es-criterion-{{ $scoreKey }}" style="--c: {{ $criterion['color'] }};">
                <button type="button" class="es-crit-head" data-crit-toggle aria-expanded="false" aria-controls="es-crit-body-{{ $scoreKey }}">
                    <span class="es-crit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $criterion['icon'] !!}</svg></span>
                    <span class="es-crit-main">
                        <span class="es-crit-name">{{ $criterion['name'] }}</span>
                        <span class="es-crit-meta">{{ number_format((float) $criterionWeight, 0) }}% weight
                            <span class="es-tag es-tag--{{ $isEvaluated ? 'ok' : 'muted' }}">{{ $isEvaluated ? 'Evaluated' : 'Not evaluated' }}</span>
                        </span>
                    </span>
                    <span class="es-crit-score">
                        <strong>{{ $isEvaluated ? $fmt($score) : '—' }}</strong><small>/ 100</small>
                    </span>
                    <svg class="es-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="es-crit-track" role="presentation"><span style="width: {{ $isEvaluated ? $clamp($score) : 0 }}%; background: {{ $isEvaluated ? $tone($score) : '#cbd5e1' }};"></span></div>

                <div class="es-crit-body" id="es-crit-body-{{ $scoreKey }}" hidden>
                    <div class="es-field">
                        <span class="es-field-label">{{ $isEvaluated ? 'Reason' : 'Why it is not evaluated' }}</span>
                        <p class="{{ $isEvaluated ? '' : 'is-muted' }}">{{ $explanation }}</p>
                    </div>
                    @if ($isEvaluated)
                    <div class="es-field">
                        <span class="es-field-label">Evidence used</span>
                        <p>{{ $criterionEvidence[$scoreKey] ?? 'No evidence data available.' }}</p>
                    </div>
                    @if (($potentialGain[$scoreKey] ?? 0) > 0.05)
                    <div class="es-field">
                        <span class="es-field-label">Room to grow</span>
                        <p>Raising this part to 100 would add up to <strong>{{ number_format($potentialGain[$scoreKey], 1) }}</strong> points to the final score.</p>
                    </div>
                    @endif
                    @endif
                    @if ($manualCriterion)
                    <div class="es-field es-field--manual">
                        <span class="es-field-label">Manual adjustment</span>
                        <p>Adjusted to {{ number_format((float) $manualCriterion['score'], 1) }} / {{ $manualCriterion['max'] }}. Reason: {{ $manualCriterion['reason'] }}</p>
                    </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- ===================== SOURCES ===================== --}}
<section class="es-section" id="es-sources" aria-labelledby="es-sources-title">
    <div class="es-section-head es-section-head--row">
        <div>
            <h2 id="es-sources-title">Kaizen Strategy &amp; Admin Concern sources</h2>
            <p>Only evaluations marked <strong>ADDED TO SCORE SHEET</strong> contribute weighted points. Reversed or pending items stay visible so every score is traceable.</p>
        </div>
        @if ($scoreSheetTasks->isNotEmpty())
        <div class="es-chips">
            <span class="es-chip">Included <strong>{{ $tasksIncluded }} / {{ $scoreSheetTasks->count() }}</strong></span>
            <span class="es-chip">Weighted points <strong>{{ number_format((float) $scoreSheetTasksTotal, 2) }}</strong></span>
        </div>
        @endif
    </div>

    @if ($scoreSheetTasks->isEmpty())
        <div class="es-empty es-empty--compact"><p>No Kaizen Strategy or Admin Concern evaluations are linked to this Score Sheet period yet.</p></div>
    @else
    <div class="es-card es-card--flush">
        <ul class="es-tasks">
            @foreach ($scoreSheetTasks as $taskEvaluation)
                @php
                    $taskBadge = $taskEvaluation->integrationBadge();
                    $taskScore = $taskEvaluation->overall_score;
                @endphp
                <li class="es-task">
                    <div class="es-task-main">
                        <div class="es-task-title-row">
                            @if ($taskEvaluation->taskReferenceUrl())
                                <a href="{{ $taskEvaluation->taskReferenceUrl() }}" class="es-task-title">{{ $taskEvaluation->taskTitle() }}</a>
                            @else
                                <span class="es-task-title">{{ $taskEvaluation->taskTitle() }}</span>
                            @endif
                            <span class="badge {{ $taskBadge['class'] }}">{{ $taskBadge['label'] }}</span>
                        </div>
                        <div class="es-task-meta">
                            <span>{{ $taskEvaluation->sourceLabel() }} #{{ $taskEvaluation->source_id }}</span>
                            <span>{{ $taskEvaluation->evaluationPeriod?->name ?? 'No evaluation period' }}</span>
                            @if ($taskEvaluation->isIncludedInScoreSheet())
                                <span>{{ $taskEvaluation->criterionLabel() }} · {{ number_format((float) $taskEvaluation->score_sheet_weighted_points, 2) }} weighted points</span>
                                <span>Added {{ $taskEvaluation->score_sheet_added_at?->format('M j, Y') }}</span>
                            @elseif ($taskEvaluation->score_sheet_reversed_at)
                                <span>Excluded {{ $taskEvaluation->score_sheet_reversed_at->format('M j, Y') }}</span>
                            @elseif ($taskScore === null)
                                <span>Score not calculated yet</span>
                            @else
                                <span>Not linked to the Score Sheet</span>
                            @endif
                        </div>
                        @if ($taskEvaluation->score_sheet_reversal_reason)
                            <p class="es-task-note">Reason: {{ $taskEvaluation->score_sheet_reversal_reason }}</p>
                        @endif
                    </div>
                    <div class="es-task-score" style="--t: {{ $tone($taskScore) }};">
                        <strong>{{ $fmt($taskScore) }}</strong><small>/ 100</small>
                        <span class="es-task-bar" role="presentation"><span style="width: {{ $taskScore !== null ? $clamp($taskScore) : 0 }}%;"></span></span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
    @endif
</section>

{{-- ===================== HISTORY ===================== --}}
@if ($hasHistory)
<section class="es-section" id="es-history" aria-labelledby="es-history-title">
    <div class="es-section-head">
        <h2 id="es-history-title">Assessment history</h2>
        <p>Every assessment change is recorded and never deleted.</p>
    </div>
    <ol class="es-timeline">
        @foreach ($journal->assessmentRevisions as $revision)
            @php
                $actionLabel = match ($revision->action) {
                    'manually_reassessed' => 'Admin reassessment',
                    'reassessed' => 'Automatic reassessment',
                    'corrected' => 'Manual review',
                    default => Str::headline((string) $revision->action),
                };
                $manualAction = $revision->action === 'manually_reassessed';
                $prev = is_numeric($revision->previous_score) ? (float) $revision->previous_score : null;
                $next = is_numeric($revision->new_score) ? (float) $revision->new_score : null;
                $delta = ($prev !== null && $next !== null) ? $next - $prev : null;
            @endphp
            <li class="es-tl-item es-tl-item--{{ $manualAction ? 'manual' : 'auto' }}">
                <span class="es-tl-dot" aria-hidden="true"></span>
                <div class="es-tl-card">
                    <div class="es-tl-head">
                        <strong>{{ $actionLabel }}</strong>
                        <time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('M j, Y g:i A') }}</time>
                    </div>
                    <p>
                        <span class="es-tl-who">{{ $revision->reviewer?->name ?? 'Unknown reviewer' }}</span>
                        changed the score from
                        <span class="es-tl-score">{{ $prev !== null ? number_format($prev, 1) : 'none' }}</span>
                        to
                        <span class="es-tl-score es-tl-score--next">{{ $next !== null ? number_format($next, 1) : 'none' }}</span>
                        @if ($delta !== null && $delta != 0)
                            <span class="es-tl-delta es-tl-delta--{{ $delta > 0 ? 'up' : 'down' }}">{{ $delta > 0 ? '▲ +' : '▼ ' }}{{ number_format($delta, 1) }}</span>
                        @endif
                    </p>
                    @if ($revision->reason)
                        <p class="es-tl-reason">Reason: {{ $revision->reason }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>
@endif

{{-- ===================== REASSESS ===================== --}}
@if ($canReassess)
<section class="es-section" id="es-reassess" aria-labelledby="es-reassess-title">
    <div class="es-section-head es-section-head--row">
        <div>
            <h2 id="es-reassess-title">Manual reassessment</h2>
            <p>Admin only. Adjust the two part scores (0–100). A reason is mandatory for every change and is saved to the history.</p>
        </div>
        <button type="button" class="es-btn es-btn--outline" id="es-reassess-open" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="es-reassess-panel">Manually reassess score</button>
    </div>

    <div id="es-reassess-panel" class="es-card es-reassess" @unless ($errors->any()) hidden @endunless>
        <form method="POST" action="{{ route('daily-journal.assessment.manual-reassess', $journal) }}">
            @csrf

            @if ($errors->any())
            <div class="es-alert" role="alert">
                <strong>Please fix the following:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
            @endif

            <div class="es-projection" aria-live="polite">
                <div><span class="es-field-label">Current final score</span><strong id="es-proj-current" data-current="{{ $finalScore !== null ? round((float) $finalScore, 2) : '' }}">{{ $fmt($finalScore) }}</strong></div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                <div><span class="es-field-label">Projected (estimate)</span><strong id="es-proj-value">—</strong><span id="es-proj-delta" class="es-proj-delta"></span></div>
            </div>

            <div class="es-reassess-grid">
                @foreach ($criteriaList as $scoreKey => $criterion)
                    @php
                        $currentScore = $criterionScores[$scoreKey]['score'] ?? ($criteria[$scoreKey] ?? null);
                        $currentReason = $criterionScores[$scoreKey]['reason'] ?? '';
                        $w = (float) ($weightsUsed[$scoreKey] ?? $configWeights[$scoreKey]);
                    @endphp
                    <div class="es-input-group" style="--c: {{ $criterion['color'] }};">
                        <label for="es-score-{{ $scoreKey }}"><span class="es-swatch" style="background: {{ $criterion['color'] }};"></span>{{ $criterion['name'] }}<small>{{ number_format($w, 0) }}% weight</small></label>
                        <input type="number" id="es-score-{{ $scoreKey }}" name="criteria[{{ $scoreKey }}][score]" min="0" max="100" step="0.1" data-weight="{{ $w }}"
                               value="{{ old('criteria.'.$scoreKey.'.score', $currentScore !== null ? number_format((float) $currentScore, 1, '.', '') : '') }}" required>
                        <input type="hidden" name="criteria[{{ $scoreKey }}][max]" value="100">
                        <input type="text" name="criteria[{{ $scoreKey }}][reason]" aria-label="Reason for {{ $criterion['name'] }} score" placeholder="Reason for this score"
                               value="{{ old('criteria.'.$scoreKey.'.reason', $currentReason) }}">
                    </div>
                @endforeach
            </div>

            <div class="es-input-group es-input-group--wide">
                <label for="es-reassess-reason">Reason for reassessment (required)</label>
                <textarea id="es-reassess-reason" name="reason" rows="3" required minlength="10" placeholder="Explain why the scores are being adjusted.">{{ old('reason') }}</textarea>
            </div>

            <div class="es-form-actions">
                <button type="button" class="es-btn es-btn--outline" id="es-reassess-cancel">Cancel</button>
                <button type="submit" class="es-btn es-btn--primary">Save manual reassessment</button>
            </div>
        </form>
    </div>
</section>
@endif

</div>
@endsection

@push('scripts')
<script>
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Count-up on the headline score ----
    document.querySelectorAll('[data-countup]').forEach(function (el) {
        if (reduce) return;
        var target = parseFloat(el.getAttribute('data-countup'));
        var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
        if (isNaN(target)) return;
        var start = null, duration = 1100;
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toFixed(decimals);
            if (p < 1) requestAnimationFrame(step);
        }
        el.textContent = (0).toFixed(decimals);
        requestAnimationFrame(step);
    });

    // ---- Copy summary ----
    var copyBtn = document.getElementById('es-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var label = document.getElementById('es-copy-label');
            var text = copyBtn.getAttribute('data-copy') || '';
            var done = function (ok) {
                label.textContent = ok ? 'Copied' : 'Copy failed';
                setTimeout(function () { label.textContent = 'Copy summary'; }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
            } else {
                done(false);
            }
        });
    }

    // ---- Section nav scroll-spy ----
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-spy]'));
    if (links.length && 'IntersectionObserver' in window) {
        var map = {};
        links.forEach(function (l) { map[l.getAttribute('data-spy')] = l; });
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    links.forEach(function (l) { l.classList.remove('is-active'); });
                    var link = map[entry.target.id];
                    if (link) link.classList.add('is-active');
                }
            });
        }, { rootMargin: '-25% 0px -65% 0px' });
        Object.keys(map).forEach(function (id) {
            var section = document.getElementById(id);
            if (section) observer.observe(section);
        });
    }

    // ---- Criteria accordion ----
    var cards = Array.prototype.slice.call(document.querySelectorAll('.es-crit'));
    var toggleAll = document.getElementById('es-toggle-all');

    function setCard(card, open) {
        var btn = card.querySelector('[data-crit-toggle]');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        card.querySelector('.es-crit-body').hidden = !open;
        card.classList.toggle('is-open', open);
    }
    function allOpen() { return cards.length > 0 && cards.every(function (c) { return c.classList.contains('is-open'); }); }
    function syncToggleAll() { if (toggleAll) toggleAll.textContent = allOpen() ? 'Collapse all' : 'Expand all'; }

    cards.forEach(function (card) {
        card.querySelector('[data-crit-toggle]').addEventListener('click', function () {
            setCard(card, !card.classList.contains('is-open'));
            syncToggleAll();
        });
    });
    if (toggleAll) toggleAll.addEventListener('click', function () {
        var open = !allOpen();
        cards.forEach(function (c) { setCard(c, open); });
        syncToggleAll();
    });
    syncToggleAll();

    // ---- Reassessment panel + live projection ----
    var panel = document.getElementById('es-reassess-panel');
    var openBtn = document.getElementById('es-reassess-open');
    var cancelBtn = document.getElementById('es-reassess-cancel');
    if (panel) {
        var inputs = Array.prototype.slice.call(panel.querySelectorAll('input[data-weight]'));
        var projValue = document.getElementById('es-proj-value');
        var projDelta = document.getElementById('es-proj-delta');
        var current = parseFloat(document.getElementById('es-proj-current').getAttribute('data-current'));

        function project() {
            var sum = 0, weight = 0;
            inputs.forEach(function (input) {
                var v = parseFloat(input.value);
                var w = parseFloat(input.getAttribute('data-weight'));
                if (!isNaN(v) && !isNaN(w)) { sum += v * w; weight += w; }
            });
            if (weight === 0) { projValue.textContent = '—'; projDelta.textContent = ''; return; }
            var result = sum / weight;
            projValue.textContent = result.toFixed(1);
            if (!isNaN(current)) {
                var d = result - current;
                projDelta.textContent = (d > 0 ? '+' : '') + d.toFixed(1);
                projDelta.className = 'es-proj-delta ' + (d > 0.04 ? 'is-up' : (d < -0.04 ? 'is-down' : ''));
            }
        }
        inputs.forEach(function (i) { i.addEventListener('input', project); });
        project();

        function setPanel(open) {
            panel.hidden = !open;
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                panel.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
                if (inputs[0]) inputs[0].focus({ preventScroll: true });
            }
        }
        openBtn.addEventListener('click', function () { setPanel(panel.hidden); });
        cancelBtn.addEventListener('click', function () {
            panel.querySelector('form').reset();
            project();
            setPanel(false);
            openBtn.focus();
        });
    }
})();
</script>
@endpush

@push('styles')
<style>
/* ============================================================
   EAS Score Sheet — scoped to .es so shared dashboard styles stay untouched.
   ============================================================ */
.es {
    --es-navy: var(--navy, #12305c);
    --es-text: var(--text, #1f2937);
    --es-muted: var(--muted-text, #64748b);
    --es-border: var(--border-subtle, #e5e7eb);
    --es-surface: var(--surface, #ffffff);
    --es-raised: var(--surface-raised, #f8fafc);
    --es-radius: var(--radius-card, 16px);
    --es-shadow: 0 8px 28px rgba(15, 47, 91, .07);
    --es-blue: #2563eb;
    display: flex; flex-direction: column; gap: 28px; color: var(--es-text);
}
.es [hidden] { display: none !important; }
.es *, .es *::before, .es *::after { box-sizing: border-box; }
.es h1, .es h2, .es h3, .es p, .es ul, .es ol, .es dl, .es dd { margin: 0; padding: 0; }
.es ul, .es ol { list-style: none; }
.es svg { flex: none; }

/* ---------- Buttons ---------- */
.es-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; border: 1px solid transparent; font: inherit; font-size: 13px; font-weight: 700; line-height: 1.2; text-decoration: none; cursor: pointer; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
.es-btn svg { width: 15px; height: 15px; }
.es-btn:hover { transform: translateY(-1px); }
.es-btn:focus-visible, .es-nav-link:focus-visible, .es-crit-head:focus-visible, .es-task-title:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; }
.es-btn--ghost { background: rgba(255,255,255,.1); color: #fff; border-color: rgba(255,255,255,.22); }
.es-btn--ghost:hover { background: rgba(255,255,255,.18); }
.es-btn--solid { background: #fff; color: var(--es-navy); }
.es-btn--solid:hover { background: #eef4fd; }
.es-btn--outline { background: var(--es-surface); color: var(--es-navy); border-color: var(--es-border); }
.es-btn--outline:hover { border-color: var(--es-blue); color: var(--es-blue); }
.es-btn--primary { background: var(--es-blue); color: #fff; }
.es-btn--primary:hover { background: #1d4ed8; }

/* ---------- Hero ---------- */
.es-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 22px 28px 26px; color: #fff; background: linear-gradient(135deg, #0b1f3f 0%, #12305c 55%, #1d4ed8 130%); box-shadow: 0 18px 44px rgba(15, 47, 91, .28); }
.es-hero-bg { position: absolute; inset: 0; pointer-events: none; background: radial-gradient(520px 260px at 92% -10%, rgba(96,165,250,.35), transparent 70%), radial-gradient(380px 220px at 0% 110%, rgba(52,211,153,.16), transparent 70%); }
.es-hero > * { position: relative; }
.es-hero-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.es-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #bfdbfe; }
.es-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.es-hero-main { display: flex; align-items: center; justify-content: space-between; gap: 28px; flex-wrap: wrap; margin-top: 18px; }
.es-hero-person { display: flex; align-items: center; gap: 18px; min-width: 0; }
.es-avatar { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 18px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); font-size: 26px; font-weight: 800; flex: none; }
.es .es-hero h1 { font-family: var(--font-head, inherit); font-size: 30px; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; overflow-wrap: anywhere; color: #fff; text-shadow: 0 1px 12px rgba(0, 0, 0, .25); }
.es .es-hero .es-hero-meta, .es .es-hero .es-kpi dd { color: #fff; }
.es .es-hero .es-hero-meta { color: #cbd5e1; }
.es .es-hero .es-kpi dt, .es .es-hero .es-eyebrow, .es .es-hero .es-gauge-label { color: #bfdbfe; }
.es-hero-meta { display: flex; align-items: center; gap: 10px; margin-top: 4px; font-size: 14px; color: #cbd5e1; font-weight: 500; flex-wrap: wrap; }
.es-dot { width: 4px; height: 4px; border-radius: 50%; background: #94a3b8; }
.es-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.es-pill { display: inline-flex; align-items: center; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 700; border: 1px solid rgba(255,255,255,.22); background: rgba(255,255,255,.1); white-space: nowrap; }
.es-pill--verified { background: rgba(52,211,153,.18); border-color: rgba(52,211,153,.45); color: #a7f3d0; }
.es-pill--provisional { background: rgba(251,191,36,.16); border-color: rgba(251,191,36,.45); color: #fde68a; }
.es-pill--manual { background: rgba(96,165,250,.18); border-color: rgba(96,165,250,.45); color: #bfdbfe; }
.es-pill--level { background: color-mix(in srgb, var(--lv) 20%, transparent); border-color: color-mix(in srgb, var(--lv) 60%, transparent); color: var(--lv); }

.es-gauge { position: relative; width: 168px; height: 168px; }
.es-gauge svg { width: 100%; height: 100%; display: block; }
.es-gauge-track { fill: none; stroke: rgba(255,255,255,.16); stroke-width: 3; }
.es-gauge-ring { fill: none; stroke-width: 3; stroke-linecap: round; animation: es-gauge 1.2s cubic-bezier(.22,.8,.3,1) both; filter: drop-shadow(0 0 3px rgba(255,255,255,.25)); }
@keyframes es-gauge { from { stroke-dashoffset: 100; } }
.es-gauge-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1; }
.es-gauge-value { font-size: 44px; font-weight: 800; letter-spacing: -.03em; font-variant-numeric: tabular-nums; }
.es-gauge-label { margin-top: 2px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #bfdbfe; }

.es-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-top: 22px; }
.es-kpi { padding: 12px 14px; border-radius: 14px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); min-width: 0; }
.es-kpi dt { font-size: 10.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #bfdbfe; }
.es-kpi dd { margin-top: 4px; font-size: 22px; font-weight: 800; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
.es-kpi dd small { font-size: 12px; font-weight: 600; color: #cbd5e1; }
.es-kpi-text { font-size: 14px !important; font-weight: 700 !important; line-height: 1.35; }
.es-kpi-delta { display: block; margin-top: 2px; }
.es-kpi-delta--up { color: #6ee7b7 !important; }
.es-kpi-delta--down { color: #fca5a5 !important; }

/* ---------- Sticky section nav ---------- */
.es-nav { position: sticky; top: 8px; z-index: 20; display: flex; gap: 4px; padding: 5px; overflow-x: auto; scrollbar-width: none; background: color-mix(in srgb, var(--es-surface) 88%, transparent); backdrop-filter: blur(10px); border: 1px solid var(--es-border); border-radius: 14px; box-shadow: 0 6px 20px rgba(15, 47, 91, .08); margin-top: -8px; }
.es-nav::-webkit-scrollbar { display: none; }
.es-nav-link { padding: 7px 14px; border-radius: 10px; color: var(--es-muted); font-size: 13px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background .15s ease, color .15s ease; }
.es-nav-link:hover { color: var(--es-navy); background: #eef2f7; }
.es-nav-link.is-active { background: var(--es-navy); color: #fff; }

/* ---------- Sections ---------- */
.es-section { display: flex; flex-direction: column; gap: 16px; scroll-margin-top: 76px; }
.es-section-head h2 { font-family: var(--font-head, inherit); font-size: 20px; font-weight: 800; letter-spacing: -.01em; color: var(--es-navy); }
.es-section-head p { margin-top: 4px; font-size: 13.5px; color: var(--es-muted); max-width: 70ch; }
.es-section-head--row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

.es-card { background: var(--es-surface); border: 1px solid var(--es-border); border-radius: var(--es-radius); box-shadow: var(--es-shadow); padding: 20px; }
.es-card--flush { padding: 0; overflow: hidden; }
.es-card-title { font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--es-navy); margin-bottom: 14px; }
.es-card-foot { margin-top: 10px; font-size: 12px; color: var(--es-muted); text-align: center; }
.es-tag { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.es-tag--ok { background: #dcfce7; color: #166534; }
.es-tag--muted { background: #f1f5f9; color: #64748b; }

/* ---------- Overview ---------- */
.es-overview-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 16px; }
.es-parts { display: flex; flex-direction: column; gap: 18px; }
.es-part.is-empty { opacity: .6; }
.es-part-top { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.es-part-name { font-size: 15px; font-weight: 800; color: var(--es-navy); }
.es-part-weight { font-size: 12px; font-weight: 700; color: var(--es-muted); }
.es-part-bar { height: 12px; margin-top: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.es-part-bar span { display: block; height: 100%; border-radius: 999px; background: var(--c); transition: width .8s ease; }
.es-part-foot { margin-top: 6px; font-size: 12.5px; color: var(--es-muted); }
.es-part-foot strong { color: var(--es-navy); font-size: 15px; }

.es-stack { display: flex; height: 18px; border-radius: 999px; overflow: hidden; background: #e2e8f0; }
.es-stack-seg { height: 100%; border-right: 2px solid var(--es-surface); transition: width .8s ease; }
.es-stack-seg:last-child { border-right: 0; }
.es-stack-scale { display: flex; justify-content: space-between; margin-top: 6px; font-size: 11px; font-weight: 700; color: var(--es-muted); }
.es-stack-scale span:nth-child(2) { color: var(--es-navy); }
.es-build-list { display: flex; flex-direction: column; margin-top: 12px; }
.es-build-row { display: grid; grid-template-columns: 12px minmax(0, 1.5fr) minmax(0, 1.2fr) 48px 74px; align-items: center; gap: 12px; padding: 11px 0; border-top: 1px solid var(--es-border); }
.es-build-row.is-empty { opacity: .6; }
.es-swatch { display: inline-block; width: 10px; height: 10px; border-radius: 3px; flex: none; }
.es-build-name { font-size: 13.5px; font-weight: 700; color: var(--es-navy); line-height: 1.25; }
.es-build-name small { display: block; font-size: 11px; font-weight: 600; color: var(--es-muted); }
.es-build-bar { height: 7px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.es-build-bar span { display: block; height: 100%; border-radius: 999px; transition: width .8s ease; }
.es-build-score { text-align: right; font-size: 14px; font-weight: 800; font-variant-numeric: tabular-nums; }
.es-build-pts { text-align: right; font-size: 12px; font-weight: 700; color: var(--es-blue); font-variant-numeric: tabular-nums; }
.es-build-row.is-empty .es-build-pts { color: var(--es-muted); }

.es-insights { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.es-insight { display: flex; gap: 14px; padding: 16px 18px; border-radius: var(--es-radius); border: 1px solid var(--es-border); background: var(--es-surface); box-shadow: var(--es-shadow); }
.es-insight-icon { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 12px; flex: none; }
.es-insight-icon svg { width: 20px; height: 20px; }
.es-insight-label { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--es-muted); }
.es-insight strong { display: block; margin-top: 2px; font-size: 15px; color: var(--es-navy); }
.es-insight p { margin-top: 4px; font-size: 13px; line-height: 1.5; color: var(--es-text); }
.es-insight--good { background: linear-gradient(180deg, #f0fdf4, #fff); border-color: #bbf7d0; }
.es-insight--good .es-insight-icon { background: #dcfce7; color: #15803d; }
.es-insight--warn { background: linear-gradient(180deg, #fffbeb, #fff); border-color: #fde68a; }
.es-insight--warn .es-insight-icon { background: #fef3c7; color: #b45309; }
.es-insight--info { background: linear-gradient(180deg, #eff6ff, #fff); border-color: #bfdbfe; }
.es-insight--info .es-insight-icon { background: #dbeafe; color: #1d4ed8; }
.es-method { font-size: 12.5px; color: var(--es-muted); font-style: italic; }

.es-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 44px 28px; text-align: center; background: var(--es-raised); border: 1px dashed var(--es-border); border-radius: var(--es-radius); color: var(--es-muted); }
.es-empty svg { width: 52px; height: 52px; opacity: .4; }
.es-empty strong { font-size: 16px; color: var(--es-navy); }
.es-empty p { max-width: 420px; font-size: 14px; }
.es-empty--compact { padding: 24px; }

/* ---------- Tables ---------- */
.es-table-wrap { overflow-x: auto; }
.es-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 13.5px; }
.es-table thead th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--es-muted); background: var(--es-raised); border-bottom: 1px solid var(--es-border); white-space: nowrap; }
.es-table th.num, .es-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.es-table tbody th, .es-table tfoot th { text-align: left; font-weight: 700; color: var(--es-navy); }
.es-table td, .es-table tbody th, .es-table tfoot th { padding: 13px 16px; border-bottom: 1px solid var(--es-border); }
.es-table tr.is-evaluated { background: #f7fef9; }
.es-table tr.is-excluded { background: var(--es-raised); color: var(--es-muted); }
.es-cell-note { display: block; margin-top: 2px; font-size: 12px; color: var(--es-muted); }
.es-tr-total th, .es-tr-total td { background: #eef4fd; font-weight: 800; border-top: 2px solid var(--es-border); }
.es-tr-available th, .es-tr-available td { background: #fef9e7; font-weight: 700; }
.es-tr-final th, .es-tr-final td { background: #dbeafe; font-weight: 800; border-top: 2px solid var(--es-blue); border-bottom: 0; }
.es-callout { margin: 16px; padding: 14px 16px; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 13px; line-height: 1.6; }
.es-callout-extra { display: block; margin-top: 6px; font-style: italic; color: #1e40af; }

/* ---------- Findings ---------- */
.es-findings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.es-finding-card { border-radius: var(--es-radius); border: 1px solid var(--es-border); background: var(--es-surface); box-shadow: var(--es-shadow); overflow: hidden; border-top: 4px solid var(--fc); }
.es-finding-card--good { --fc: #10b981; --fb: #ecfdf5; --fi: #047857; }
.es-finding-card--bad { --fc: #ef4444; --fb: #fef2f2; --fi: #b91c1c; }
.es-finding-card--muted { --fc: #94a3b8; --fb: #f1f5f9; --fi: #475569; }
.es-finding-card--next { --fc: #f59e0b; --fb: #fffbeb; --fi: #b45309; }
.es-finding-card h3 { display: flex; align-items: center; gap: 10px; padding: 14px 18px; font-size: 14.5px; font-weight: 800; color: var(--es-navy); }
.es-finding-icon { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: var(--fb); color: var(--fi); }
.es-finding-icon svg { width: 15px; height: 15px; }
.es-finding-card ul { display: flex; flex-direction: column; gap: 8px; padding: 0 18px 18px; }
.es-finding-card li { padding: 11px 13px; border-radius: 10px; background: var(--fb); }
.es-finding-card li strong { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 13.5px; color: var(--es-navy); }
.es-finding-card li p { margin-top: 3px; font-size: 13px; line-height: 1.5; }
.es-finding-card li small { display: block; margin-top: 4px; font-size: 12px; font-style: italic; color: var(--es-muted); }

/* ---------- Criteria ---------- */
.es-criteria { display: flex; flex-direction: column; gap: 10px; }
.es-crit { background: var(--es-surface); border: 1px solid var(--es-border); border-left: 4px solid var(--c); border-radius: var(--es-radius); box-shadow: var(--es-shadow); overflow: hidden; }
.es-crit-head { display: flex; align-items: center; gap: 14px; width: 100%; padding: 14px 18px; border: 0; background: transparent; font: inherit; text-align: left; cursor: pointer; transition: background .15s ease; }
.es-crit-head:hover { background: var(--es-raised); }
.es-crit-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; background: color-mix(in srgb, var(--c) 12%, #fff); color: var(--c); flex: none; }
.es-crit-icon svg { width: 22px; height: 22px; }
.es-crit-main { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.es-crit-name { font-size: 15px; font-weight: 800; color: var(--es-navy); }
.es-crit-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; font-weight: 600; color: var(--es-muted); }
.es-crit-score { display: inline-flex; align-items: baseline; gap: 4px; flex: none; }
.es-crit-score strong { font-size: 24px; font-weight: 800; color: var(--es-navy); font-variant-numeric: tabular-nums; }
.es-crit-score small { font-size: 12px; font-weight: 600; color: var(--es-muted); }
.es-chevron { width: 18px; height: 18px; color: var(--es-muted); transition: transform .2s ease; }
.es-crit.is-open .es-chevron { transform: rotate(180deg); color: var(--c); }
.es-crit-track { height: 5px; background: #eef2f7; }
.es-crit-track span { display: block; height: 100%; transition: width .8s ease; }
.es-crit-body { display: grid; gap: 16px; padding: 18px 20px 20px; border-top: 1px solid var(--es-border); }
.es-field { display: flex; flex-direction: column; gap: 5px; }
.es-field-label { font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--es-muted); }
.es-field p { font-size: 14px; line-height: 1.6; }
.es-field p.is-muted { color: var(--es-muted); font-style: italic; }
.es-field--manual { padding: 12px 14px; border-radius: 10px; background: #fef3c7; }

/* ---------- Kaizen / Admin Concern sources ---------- */
.es-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.es-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--es-border); background: var(--es-surface); font-size: 12px; font-weight: 600; color: var(--es-muted); }
.es-chip strong { color: var(--es-navy); font-variant-numeric: tabular-nums; }
.es-tasks { display: flex; flex-direction: column; }
.es-task { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 16px 20px; border-bottom: 1px solid var(--es-border); }
.es-task:last-child { border-bottom: 0; }
.es-task:hover { background: var(--es-raised); }
.es-task-main { min-width: 0; flex: 1; }
.es-task-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.es-task-title { font-size: 14.5px; font-weight: 700; color: var(--es-navy); text-decoration: none; overflow-wrap: anywhere; }
a.es-task-title:hover { color: var(--es-blue); text-decoration: underline; }
.es-task-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 5px; font-size: 12px; color: var(--es-muted); }
.es-task-note { margin-top: 6px; font-size: 12px; font-style: italic; color: #92400e; }
.es-task-score { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: flex-end; gap: 4px; width: 96px; flex: none; }
.es-task-score strong { font-size: 22px; font-weight: 800; color: var(--es-navy); font-variant-numeric: tabular-nums; }
.es-task-score small { font-size: 12px; color: var(--es-muted); font-weight: 600; }
.es-task-bar { flex-basis: 100%; height: 5px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.es-task-bar span { display: block; height: 100%; background: var(--t); border-radius: 999px; }

/* ---------- Timeline ---------- */
.es-timeline { position: relative; display: flex; flex-direction: column; gap: 14px; padding-left: 28px; }
.es-timeline::before { content: ""; position: absolute; left: 8px; top: 8px; bottom: 8px; width: 2px; background: var(--es-border); }
.es-tl-item { position: relative; }
.es-tl-dot { position: absolute; left: -28px; top: 14px; width: 18px; height: 18px; border-radius: 50%; background: var(--es-surface); border: 4px solid #3b82f6; }
.es-tl-item--manual .es-tl-dot { border-color: #10b981; }
.es-tl-card { padding: 14px 16px; background: var(--es-surface); border: 1px solid var(--es-border); border-radius: 14px; box-shadow: var(--es-shadow); }
.es-tl-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.es-tl-head strong { font-size: 14px; color: var(--es-navy); }
.es-tl-head time { font-size: 12px; color: var(--es-muted); }
.es-tl-card p { margin-top: 6px; font-size: 13.5px; line-height: 1.6; color: var(--es-muted); }
.es-tl-who { font-weight: 800; color: var(--es-navy); }
.es-tl-score { font-weight: 800; font-variant-numeric: tabular-nums; color: #64748b; }
.es-tl-score--next { color: var(--es-navy); }
.es-tl-delta { margin-left: 4px; font-weight: 800; font-size: 12.5px; }
.es-tl-delta--up { color: #15803d; }
.es-tl-delta--down { color: #b91c1c; }
.es-tl-card .es-tl-reason { font-size: 12.5px; font-style: italic; }

/* ---------- Reassessment ---------- */
.es-reassess { display: block; }
.es-alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px; }
.es-alert ul { margin-top: 6px; padding-left: 18px; list-style: disc; }
.es-projection { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-bottom: 18px; padding: 14px 18px; border-radius: 14px; background: linear-gradient(135deg, #eff6ff, #f8fafc); border: 1px solid #bfdbfe; }
.es-projection > div { display: flex; flex-direction: column; gap: 2px; }
.es-projection strong { font-size: 28px; font-weight: 800; color: var(--es-navy); font-variant-numeric: tabular-nums; line-height: 1.1; }
.es-projection svg { width: 22px; height: 22px; color: var(--es-muted); }
.es-proj-delta { font-size: 13px; font-weight: 800; color: var(--es-muted); }
.es-proj-delta.is-up { color: #15803d; }
.es-proj-delta.is-down { color: #b91c1c; }
.es-reassess-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
.es-input-group { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding: 14px; border-radius: 12px; background: var(--es-raised); border: 1px solid var(--es-border); border-left: 4px solid var(--c, var(--es-border)); }
.es-input-group--wide { border-left-color: var(--es-border); }
.es-input-group label { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--es-navy); }
.es-input-group label small { margin-left: auto; font-size: 11px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--es-muted); }
.es-input-group input, .es-input-group textarea { width: 100%; padding: 9px 12px; border: 1px solid var(--es-border); border-radius: 10px; background: var(--es-surface); color: var(--es-text); font: inherit; font-size: 14px; }
.es-input-group input[type="number"] { font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; }
.es-input-group input:focus-visible, .es-input-group textarea:focus-visible { outline: 2px solid var(--es-blue); outline-offset: 1px; }
.es-input-group textarea { resize: vertical; line-height: 1.5; }
.es-form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }

/* ---------- Responsive ---------- */
@media (max-width: 1100px) {
    .es-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .es-overview-grid, .es-findings-grid { grid-template-columns: minmax(0, 1fr); }
    .es-insights { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 720px) {
    .es-hero { padding: 18px 16px 20px; border-radius: 18px; }
    .es-hero h1 { font-size: 24px; }
    .es-hero-main { justify-content: center; }
    .es-hero-person { width: 100%; }
    .es-gauge { width: 140px; height: 140px; margin: 0 auto; }
    .es-gauge-value { font-size: 36px; }
    .es-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .es-build-row { grid-template-columns: 12px minmax(0, 1fr) 44px 70px; }
    .es-build-bar { display: none; }
    .es-crit-head { flex-wrap: wrap; padding: 12px 14px; }
    .es-task { flex-direction: column; align-items: flex-start; }
    .es-task-score { width: 100%; justify-content: flex-start; }
    .es-reassess-grid { grid-template-columns: minmax(0, 1fr); }
    .es-section-head--row { align-items: flex-start; }
}

@media (prefers-reduced-motion: reduce) {
    .es *, .es *::before, .es *::after { animation: none !important; transition: none !important; }
}

@media print {
    .es { gap: 16px; }
    .es-nav, .es-hero-actions, #es-toggle-all, #es-reassess, .es-btn { display: none !important; }
    .es-hero { background: #12305c !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
    .es-crit-body[hidden] { display: grid !important; }
    .es-card, .es-insight, .es-crit, .es-finding-card, .es-tl-card { box-shadow: none; break-inside: avoid; }
    .es-gauge-ring { animation: none; }
}
</style>
@endpush