@extends('layouts.dashboard')

@section('title', 'Score Sheet — ' . $journal->user->name)

@section('content')
@php
    $assessmentLevel = $journal->achievement_level ?? null;
    $assessmentLevelSlug = $assessmentLevel ? \Illuminate\Support\Str::slug($assessmentLevel) : null;
    $assessmentLevelColor = match ($assessmentLevel) {
        'Outstanding' => '#196F3D',
        'Excellent' => '#1D4ED8',
        'Very Good' => '#4338CA',
        'Good' => '#475569',
        'Needs Further Development' => '#92400E',
        default => '#6B7280',
    };

    $isManual = $journal->assessment_source === 'reviewer';
    $assessmentType = $isManual ? 'Manual Reassessment' : 'Automatic Assessment';
    $hasManualOverride = $journal->hasManualOverride();
    $originalAiScore = $journal->originalAiScore();
    $finalScore = $journal->finalScore();
    $manualAdjustment = $journal->manualAdjustment();

    $criteriaList = [
        'quality' => [
            'name' => 'Accomplishment Quality',
            'key' => 'quality',
            'max' => 100,
            'explanation' => $criteria['quality_explanation'] ?? 'Not yet evaluated.',
            'meta' => $criteria['quality_meta'] ?? [],
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="m9 14 2 2 4-4"/></svg>',
            'color' => '#2563eb',
        ],
        'timeliness' => [
            'name' => 'Timeliness',
            'key' => 'timeliness',
            'max' => 100,
            'explanation' => $criteria['timeliness_explanation'] ?? 'Not yet evaluated.',
            'meta' => $criteria['timeliness_meta'] ?? [],
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'color' => '#7c3aed',
        ],
        'responsiveness' => [
            'name' => 'Immediate Responsiveness',
            'key' => 'responsiveness',
            'max' => 100,
            'explanation' => $criteria['responsiveness_explanation'] ?? 'Not yet evaluated.',
            'meta' => $criteria['responsiveness_meta'] ?? [],
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 8 10"/></svg>',
            'color' => '#059669',
        ],
        'client_feedback' => [
            'name' => 'Client Feedback',
            'key' => 'client_feedback',
            'max' => 100,
            'explanation' => $criteria['client_feedback_explanation'] ?? 'Not yet evaluated.',
            'meta' => $criteria['client_feedback_meta'] ?? [],
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" preserveAspectRatio="xMidYMid meet"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'color' => '#0891b2',
        ],
    ];

    $weightLabels = [
        'quality' => 'Accomplishment Quality',
        'timeliness' => 'Timeliness',
        'responsiveness' => 'Immediate Responsiveness',
        'client_feedback' => 'Client Feedback',
    ];

    $evidenceCount = $journal->evidenceCount();
    $submittedAt = $journal->submitted_at;
    $assessedAt = $journal->assessed_at;
    $reviewedAt = $journal->reviewed_at;

    $percentComplete = $finalScore !== null ? round((float) $finalScore, 1) : 0;
@endphp

<!-- Page Header -->
<header class="eas-score-head">
    <div class="eas-score-head-content">
        <div class="eas-score-head-label">
            <span class="eas-score-eyebrow">AI Score Sheet</span>
        </div>
        <div class="eas-score-head-title">
            <h1>{{ $journal->user->name }}</h1>
            <div class="eas-score-head-meta">
                <span class="eas-score-head-role">{{ ucfirst($journal->user->role) }}</span>
                <span class="eas-score-divider"></span>
                <span class="eas-score-period">{{ $journal->required_date->format('F j, Y') }}</span>
            </div>
        </div>
    </div>
    <div class="eas-score-head-actions">
        <a href="{{ route('daily-journal.show', $journal) }}" class="btn btn-outline btn-sm eas-score-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Report
        </a>
    </div>
</header>

<!-- Assessment Information Panel -->
<section class="eas-score-info" aria-label="Assessment information">
    <div class="eas-score-info-panel">
        <div class="eas-score-info-row">
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Staff member</span>
                <span class="eas-score-info-value">{{ $journal->user->name }}</span>
            </div>
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Assessment period</span>
                <span class="eas-score-info-value">{{ $journal->required_date->format('F j, Y') }}</span>
            </div>
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Submitted</span>
                <span class="eas-score-info-value">{{ $submittedAt ? $submittedAt->format('M j, Y g:i A') : 'Not submitted' }}</span>
            </div>
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Assessed</span>
                <span class="eas-score-info-value">{{ $assessedAt ? $assessedAt->format('M j, Y g:i A') : 'Not assessed' }}</span>
            </div>
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Type</span>
                <span class="eas-score-info-value eas-score-badge eas-score-badge--{{ $isManual ? 'manual' : 'automatic' }}">{{ $assessmentType }}</span>
            </div>
            <div class="eas-score-info-item">
                <span class="eas-score-info-label">Status</span>
                <span class="eas-score-info-value eas-score-badge eas-score-badge--{{ $journal->isAssessmentVerified() ? 'verified' : 'provisional' }}">{{ $journal->assessmentStatusLabel() }}</span>
            </div>
        </div>
    </div>
</section>

<!-- Score Overview Section -->
<section class="eas-score-card eas-score-card--summary" aria-labelledby="eas-score-summary-title">
    <div class="eas-score-card-head">
        <div>
            <h2 id="eas-score-summary-title">Overall Score</h2>
            <p class="eas-score-card-sub">
                {{ $finalScore !== null ? number_format((float) $finalScore, 1) : 0 }} / 100
                @if ($assessmentLevel)
                    &nbsp;•&nbsp; {{ $assessmentLevel }}
                @else
                    &nbsp;•&nbsp; Not yet assessed
                @endif
            </p>
        </div>
    </div>

    <div class="eas-score-overall">
        <!-- Circular Score Indicator -->
        <div class="eas-score-circle-wrapper">
            <div class="eas-score-circle">
                <svg class="eas-score-circle-svg" viewBox="0 0 36 36" width="96" height="96">
                    <circle class="eas-score-circle-track" cx="18" cy="18" r="15.9155" stroke="#E5E7EB" stroke-width="3" fill="none"/>
                    @if ($finalScore !== null)
                    <circle class="eas-score-circle-progress-ring" cx="18" cy="18" r="15.9155"
                        stroke="{{ $finalScore >= 70 ? '#10b981' : ($finalScore >= 40 ? '#f59e0b' : '#ef4444') }}"
                        stroke-width="3" fill="none"
                        stroke-dasharray="100"
                        stroke-dashoffset="{{ 100 - (float) $finalScore }}"
                        stroke-linecap="butt"
                        transform="rotate(-90 18 18)"
                        style="transition: stroke-dashoffset 0.8s ease;"></circle>
                    @endif
                </svg>
                <div class="eas-score-circle-text">
                    @if ($finalScore !== null)
                        <span class="eas-score-circle-value">{{ number_format((float) $finalScore, 1) }}</span>
                        <span class="eas-score-circle-label">score</span>
                    @else
                        <span class="eas-score-circle-value">—</span>
                        <span class="eas-score-circle-label">no score</span>
                    @endif
                </div>
            </div>
            @if ($assessmentLevel)
            <div class="eas-score-level-badge">
                <span class="eas-score-level" style="background: {{ $assessmentLevelColor }}22; color: {{ $assessmentLevelColor }}; border: 1px solid {{ $assessmentLevelColor }}55;">
                    {{ $assessmentLevel }}
                </span>
            </div>
            @endif
        </div>

        <!-- Score Details -->
        <div class="eas-score-details">
            <div class="eas-score-detail">
                <span class="eas-score-detail-label">Original AI score</span>
                <span class="eas-score-detail-value">{{ $originalAiScore !== null ? number_format((float) $originalAiScore, 1) : '—' }}</span>
            </div>
            @if ($hasManualOverride)
            <div class="eas-score-detail eas-score-detail--adjustment">
                <span class="eas-score-detail-label">Manual adjustment</span>
                <span class="eas-score-detail-value eas-score-adjustment eas-score-adjustment--{{ $manualAdjustment > 0 ? 'positive' : 'negative' }}">
                    {{ $manualAdjustment > 0 ? '+' : '' }}{{ number_format((float) $manualAdjustment, 1) }}
                </span>
                <span class="eas-score-detail-note">by {{ $journal->reviewer?->name ?? 'Admin' }}</span>
            </div>
            @endif
            <div class="eas-score-detail eas-score-detail--main">
                <span class="eas-score-detail-label">Final score</span>
                <span class="eas-score-detail-value eas-score-detail-value--main">{{ $finalScore !== null ? number_format((float) $finalScore, 1) : '—' }}</span>
            </div>
            <div class="eas-score-detail">
                <span class="eas-score-detail-label">Assessment type</span>
                <span class="eas-score-detail-value">{{ $assessmentType }}</span>
            </div>
        </div>
    </div>

    <!-- Score Calculation Breakdown -->
    <div class="eas-score-weights-section">
        <span class="eas-score-weights-label">Score calculation</span>
        <div class="eas-score-weight-chips">
            @if ($combined['weights_used'] !== [])
                @foreach ($combined['weights_used'] as $criterion => $weight)
                    <span class="eas-score-weight-chip">
                        <span class="eas-score-chip-name">{{ $weightLabels[$criterion] }}</span>
                        <span class="eas-score-chip-value">{{ number_format((float) $weight, 0) }}%</span>
                    </span>
                @endforeach
                @if (count($combined['weights_used']) < 4)
                    <span class="eas-score-weight-chip eas-score-weight-chip--muted">
                        <span class="eas-score-chip-name">Missing criteria</span>
                        <span class="eas-score-chip-value">excluded</span>
                    </span>
                @endif
            @else
                <span class="eas-score-weight-chip eas-score-weight-chip--muted">
                    <span class="eas-score-chip-name">No assessable data</span>
                    <span class="eas-score-chip-value">—</span>
                </span>
            @endif
        </div>
        <span class="eas-score-weights-note">Criteria with insufficient data are excluded and remaining weights are renormalized.</span>
    </div>
</section>

<!-- Why You Received This Score -->
<section class="eas-score-card eas-score-card--why" aria-labelledby="eas-score-why-title">
    <div class="eas-score-card-head">
        <div>
            <h2 id="eas-score-why-title">Why did I receive this score?</h2>
            <p class="eas-score-card-sub">A plain-language summary of your performance across all criteria.</p>
        </div>
    </div>

    <div class="eas-score-why-body">
        @if ($finalScore === null)
        <div class="eas-score-why-empty">
            <svg class="eas-score-why-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div class="eas-score-why-empty-text">
                <strong>No assessment data available</strong>
                <p>No assessment data is available for this reporting period. The score sheet will be populated once an automatic or manual assessment is completed.</p>
            </div>
        </div>
        @else
        <!-- Positive Contributions -->
        <div class="eas-score-why-section">
            <div class="eas-score-why-section-header">
                <svg class="eas-score-why-section-icon eas-score-why-section-icon--positive" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <h3 class="eas-score-why-subhead">Positive contributions</h3>
            </div>
            <ul class="eas-score-why-list">
                @php
                    $contribs = [];
                    $tMeta = $criteria['timeliness_meta'] ?? [];
                    $rMeta = $criteria['responsiveness_meta'] ?? [];
                    if ((int) ($tMeta['on_time'] ?? 0) > 0) { $contribs[] = (int) ($tMeta['on_time'] ?? 0) . ' task(s) completed on time'; }
                    if ((int) ($tMeta['early'] ?? 0) > 0) { $contribs[] = (int) ($tMeta['early'] ?? 0) . ' task(s) completed early'; }
                    if ((int) ($rMeta['prompt'] ?? 0) > 0) { $contribs[] = (int) ($rMeta['prompt'] ?? 0) . ' prompt response(s)'; }
                    if ($evidenceCount > 0) { $contribs[] = $evidenceCount . ' evidence file(s) attached'; }
                    if (empty($contribs)) { $contribs[] = 'No specific positive contributions identified'; }
                @endphp
                @foreach ($contribs as $contrib)
                    <li>
                        <svg class="eas-score-why-list-icon eas-score-why-list-icon--positive" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>{{ $contrib }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Deductions and Reasons -->
        <div class="eas-score-why-section">
            <div class="eas-score-why-section-header">
                <svg class="eas-score-why-section-icon eas-score-why-section-icon--negative" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <h3 class="eas-score-why-subhead">Deductions and reasons</h3>
            </div>
            <ul class="eas-score-why-list">
                @foreach ($blockers as $blocker)
                    <li>
                        <svg class="eas-score-why-list-icon eas-score-why-list-icon--negative" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>{{ $blocker }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Criteria Not Yet Evaluated -->
        <div class="eas-score-why-section">
            <div class="eas-score-why-section-header">
                <svg class="eas-score-why-section-icon eas-score-why-section-icon--muted" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <h3 class="eas-score-why-subhead">Criteria not yet evaluated</h3>
            </div>
            <ul class="eas-score-why-list">
                @php
                    $notEvaluated = [];
                    foreach ($criteriaList as $c) {
                        $key = $c['key'];
                        $score = $criteria[$key] ?? null;
                        if ($score === null) {
                            $notEvaluated[$key] = $criteria[$key . '_explanation'] ?? 'No data available.';
                        }
                    }
                @endphp
                @if (count($notEvaluated) > 0)
                    @foreach ($notEvaluated as $key => $reason)
                    <li>
                        <svg class="eas-score-why-list-icon eas-score-why-list-icon--muted" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong>
                            <span class="eas-score-why-reason">{{ $reason }}</span>
                        </div>
                    </li>
                    @endforeach
                @else
                    <li>
                        <svg class="eas-score-why-list-icon eas-score-why-list-icon--positive" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>All criteria have been evaluated.</span>
                    </li>
                @endif
            </ul>
        </div>

        <!-- Areas for Improvement -->
        <div class="eas-score-why-section">
            <div class="eas-score-why-section-header">
                <svg class="eas-score-why-section-icon eas-score-why-section-icon--warning" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                <h3 class="eas-score-why-subhead">Areas for improvement</h3>
            </div>
            <ul class="eas-score-why-list">
                @foreach ($improvements as $improvement)
                    <li>
                        <svg class="eas-score-why-list-icon eas-score-why-list-icon--warning" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        <span>{{ $improvement }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</section>

<!-- Criteria Breakdown -->
<section class="eas-score-card" aria-labelledby="eas-score-criteria-title">
    <div class="eas-score-card-head">
        <div>
            <h2 id="eas-score-criteria-title">Criteria Breakdown</h2>
            <p class="eas-score-card-sub">Each criterion is scored out of 100. Expand a card to see its detailed breakdown and evidence.</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm eas-score-toggle-all" onclick="toggleAllCriteria()">
            <span id="eas-toggleAllText">Collapse all</span>
        </button>
    </div>

    <div class="eas-score-criteria-list">
        @foreach ($criteriaList as $criterion)
            <?php
                $scoreKey = $criterion['key'];
                $score = $criteria[$scoreKey] ?? null;
                $meta = $criteria[$scoreKey . '_meta'] ?? [];
                $isEvaluated = $score !== null;
                $manualCriterion = $journal->criterionScores()[$scoreKey] ?? null;
                $deduction = $isEvaluated ? (float) $criterion['max'] - (float) $score : null;
                $percent = $isEvaluated ? round((float) $score, 1) : 0;
                $weight = number_format((float) ($weights[$scoreKey] ?? 0), 0);
            ?>
            <div class="eas-score-criterion eas-score-criterion--collapsed" id="eas-criterion-{{ $scoreKey }}" data-key="{{ $scoreKey }}">
                <div class="eas-score-criterion-head" onclick="toggleCriterion('{{ $scoreKey }}')">
                    <div class="eas-score-criterion-row">
                        <div class="eas-score-criterion-icon">
                            {!! $criterion['icon'] !!}
                        </div>
                        <div class="eas-score-criterion-main">
                            <div class="eas-score-criterion-name-row">
                                <span class="eas-score-criterion-name">{{ $criterion['name'] }}</span>
                                <span class="eas-score-criterion-weight">{{ $weight }}% weight</span>
                            </div>
                            <div class="eas-score-criterion-score">
                                @if ($isEvaluated)
                                    <span class="eas-score-points">{{ number_format((float) $score, 1) }}</span>
                                    <span class="eas-score-points-max"> / {{ $criterion['max'] }}</span>
                                    @if ($deduction > 0)
                                        <span class="eas-score-points-deduction">−{{ number_format((float) $deduction, 1) }}</span>
                                    @endif
                                @else
                                    <span class="eas-score-points eas-score-points--unevaluated">—</span>
                                    <span class="eas-score-points-max"> / {{ $criterion['max'] }}</span>
                                @endif
                            </div>
                        </div>
                        <button type="button" class="eas-score-criterion-toggle" data-target="criterion-{{ $scoreKey }}" aria-expanded="false" aria-controls="eas-criterion-body-{{ $scoreKey }}">
                            <svg class="eas-score-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                    </div>
                </div>

                <div class="eas-score-criterion-progress">
                    <div class="eas-score-progress-track">
                        @if ($isEvaluated)
                        <div class="eas-score-progress-fill" style="width: {{ $percent }}%; background: {{ $finalScore >= 70 ? '#10b981' : ($finalScore >= 40 ? '#f59e0b' : '#ef4444') }};"></div>
                        @else
                        <div class="eas-score-progress-fill eas-score-progress-fill--empty"></div>
                        @endif
                    </div>
                    <span class="eas-score-progress-label">{{ $isEvaluated ? number_format((float) $score, 1) . ' / 100' : 'Not evaluated' }}</span>
                </div>

                <div class="eas-score-criterion-status">
                    <span class="eas-score-status eas-score-status--{{ $isEvaluated ? 'evaluated' : 'unevaluated' }}">
                        {{ $isEvaluated ? 'Evaluated' : 'Not yet evaluated' }}
                    </span>
                </div>

                <div class="eas-score-criterion-body" id="eas-criterion-body-{{ $scoreKey }}" hidden>
                    <div class="eas-score-criterion-body-inner">
                        <div class="eas-score-field">
                            <span class="eas-score-field-label">Reason</span>
                            <p class="eas-score-field-value">{{ $criteria[$scoreKey . '_explanation'] ?? 'Not yet evaluated.' }}</p>
                        </div>

                        @if ($isEvaluated)
                        <div class="eas-score-field">
                            <span class="eas-score-field-label">Evidence used</span>
                            <p class="eas-score-field-value">{{ $criterionEvidence[$scoreKey] ?? 'No evidence data available.' }}</p>
                        </div>
                        @else
                        <div class="eas-score-field">
                            <span class="eas-score-field-label">Missing requirements</span>
                            <p class="eas-score-field-value eas-score-field-value--muted">{{ $criteria[$scoreKey . '_explanation'] ?? 'No evaluable data available for this criterion in the reporting period.' }}</p>
                        </div>
                        @endif

                        @if ($manualCriterion)
                            <div class="eas-score-field eas-score-field--manual">
                                <span class="eas-score-field-label">Manual adjustment</span>
                                <p class="eas-score-field-value">Adjusted to {{ number_format((float) $manualCriterion['score'], 1) }} / {{ $manualCriterion['max'] }}. Reason: {{ $manualCriterion['reason'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<!-- Assessment History -->
@if ($journal->assessmentRevisions->isNotEmpty())
<section class="eas-score-card" aria-labelledby="eas-score-history-title">
    <div class="eas-score-card-head">
        <div>
            <h2 id="eas-score-history-title">Assessment History</h2>
            <p class="eas-score-card-sub">Every assessment change is recorded and never deleted.</p>
        </div>
    </div>
    <div class="eas-score-history-list">
        @foreach ($journal->assessmentRevisions as $revision)
            @php
                $actionLabel = match ($revision->action) {
                    'manually_reassessed' => 'Admin reassessment',
                    'reassessed' => 'Automatic reassessment',
                    'corrected' => 'Manual review',
                    default => ucfirst((string) $revision->action),
                };
                $actionClass = $revision->action === 'manually_reassessed' ? 'eas-score-history-action--manual' : 'eas-score-history-action--auto';
            @endphp
            <div class="eas-score-history-item">
                <div class="eas-score-history-head">
                    <span class="eas-score-history-action {{ $actionClass }}">{{ $actionLabel }}</span>
                    <span class="eas-score-history-date">{{ $revision->created_at->format('M j, Y g:i A') }}</span>
                </div>
                <div class="eas-score-history-body">
                    <p>
                        <span class="eas-score-history-reviewer">{{ $revision->reviewer?->name ?? 'Unknown reviewer' }}</span>
                        changed the score from
                        <span class="eas-score-history-score eas-score-history-score--prev">{{ $revision->previous_score ?? 'none' }}</span>
                        to
                        <span class="eas-score-history-score eas-score-history-score--next">{{ $revision->new_score ?? 'none' }}</span>.
                        @if ($revision->reason)
                            <span class="eas-score-history-reason">Reason: {{ $revision->reason }}</span>
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<!-- Admin Reassessment Panel -->
@if ($canReview && $journal->hasAssessment())
<div class="eas-score-reassess">
    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('eas-reassess-panel').hidden = false;">
        Manually reassess score (Admin)
    </button>

    <div id="eas-reassess-panel" hidden class="eas-score-reassess-panel">
        <form method="POST" action="{{ route('daily-journal.assessment.manual-reassess', $journal) }}">
            @csrf
            <h3 class="eas-score-reassess-title">Manual Reassessment</h3>
            <p>Adjust individual criterion scores. A mandatory reason is required for every change.</p>
            <div class="eas-score-reassess-grid">
                @foreach ($criteriaList as $criterion)
                    <?php
                        $scoreKey = $criterion['key'];
                        $currentScore = $journal->criterionScores()[$scoreKey]['score'] ?? ($criteria[$scoreKey] ?? null);
                        $currentReason = $journal->criterionScores()[$scoreKey]['reason'] ?? '';
                    ?>
                    <div class="eas-score-reassess-field">
                        <label>{{ $criterion['name'] }}</label>
                        <input type="number" name="criteria[{{ $scoreKey }}][score]" min="0" max="100" step="0.1" value="{{ $currentScore !== null ? number_format((float) $currentScore, 1) : '' }}" required>
                        <input type="hidden" name="criteria[{{ $scoreKey }}][max]" value="100">
                        <input type="text" name="criteria[{{ $scoreKey }}][reason]" placeholder="Reason for this score" value="{{ old('criteria.'.$scoreKey.'.reason', $currentReason) }}">
                    </div>
                @endforeach
            </div>
            <div class="eas-score-reassess-field eas-score-reassess-field--wide">
                <label>Reason for reassessment (required)</label>
                <textarea name="reason" rows="3" required placeholder="Explain why the scores are being adjusted."></textarea>
            </div>
            <div class="eas-score-reassess-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('eas-reassess-panel').hidden = true;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save manual reassessment</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
    function toggleCriterion(key) {
        const card = document.getElementById('eas-criterion-' + key);
        if (!card) return;
        const body = card.querySelector('.eas-score-criterion-body');
        const btn = card.querySelector('.eas-score-criterion-toggle');
        const icon = btn.querySelector('.eas-score-toggle-icon');
        const isCollapsed = card.classList.contains('eas-score-criterion--collapsed');

        if (isCollapsed) {
            card.classList.remove('eas-score-criterion--collapsed');
            body.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            icon.innerHTML = '<polyline points="6 15 12 9 18 15"/>';
        } else {
            card.classList.add('eas-score-criterion--collapsed');
            body.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            icon.innerHTML = '<polyline points="6 9 12 15 18 9"/>';
        }
    }

    function toggleAllCriteria() {
        const cards = document.querySelectorAll('.eas-score-criterion');
        const anyCollapsed = Array.from(cards).some(c => c.classList.contains('eas-score-criterion--collapsed'));
        const toggleBtn = document.querySelector('.eas-score-toggle-all');
        const text = document.getElementById('eas-toggleAllText');

        cards.forEach(card => {
            const body = card.querySelector('.eas-score-criterion-body');
            const toggleBtn = card.querySelector('.eas-score-criterion-toggle');
            const icon = toggleBtn.querySelector('.eas-score-toggle-icon');

            if (anyCollapsed) {
                card.classList.remove('eas-score-criterion--collapsed');
                body.hidden = false;
                toggleBtn.setAttribute('aria-expanded', 'true');
                icon.innerHTML = '<polyline points="6 15 12 9 18 15"/>';
            } else {
                card.classList.add('eas-score-criterion--collapsed');
                body.hidden = true;
                toggleBtn.setAttribute('aria-expanded', 'false');
                icon.innerHTML = '<polyline points="6 9 12 15 18 9"/>';
            }
        });

        if (toggleBtn && text) {
            text.textContent = anyCollapsed ? 'Collapse all' : 'Expand all';
        }
    }
</script>

@endsection

@push('styles')
<style>
/* ============================================================
   EAS Score Sheet - Premium Professional Design
   Consistent with EAS design system
   ============================================================ */

/* ---------- Page Header ---------- */
.eas-score-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-score-head-content {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    flex-wrap: wrap;
}

.eas-score-head-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.eas-score-eyebrow {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-divider {
    width: 1px;
    height: 12px;
    background: var(--border-subtle);
    flex: 0 0 1px;
}

.eas-score-period {
    font-size: 13px;
    color: var(--muted-text);
    font-weight: 500;
}

.eas-score-head-title h1 {
    margin: 0 0 6px;
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.01em;
}

.eas-score-head-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.eas-score-head-role {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted-text);
}

.eas-score-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.eas-score-head-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.eas-score-head-actions .btn svg {
    width: 16px;
    height: 16px;
    flex: none;
}

/* ---------- Assessment Information Panel ---------- */
.eas-score-info {
    margin-bottom: 24px;
}

.eas-score-info-panel {
    background: var(--surface-raised);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
}

.eas-score-info-row {
    display: flex;
    align-items: stretch;
    gap: 0;
    flex-wrap: wrap;
}

.eas-score-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 16px;
    min-width: 0;
    border-right: 1px solid var(--border-subtle);
}

.eas-score-info-item:last-child {
    border-right: none;
}

.eas-score-info-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--navy);
    overflow-wrap: anywhere;
}

.eas-score-info-item .eas-score-badge {
    font-size: 11px;
    font-weight: 600;
}

.eas-score-badge--automatic {
    background: #EAF1FC;
    color: #1D4ED8;
    border-color: rgba(29,78,216,.18);
}

.eas-score-badge--manual {
    background: #E6F7EE;
    color: #196F3D;
    border-color: rgba(25,111,61,.18);
}

.eas-score-badge--verified {
    background: #E6F7EE;
    color: #196F3D;
    border-color: rgba(25,111,61,.18);
}

.eas-score-badge--provisional {
    background: #FFF3E0;
    color: #92400E;
    border-color: rgba(146,64,14,.18);
}

/* ---------- Cards ---------- */
.eas-score-card {
    margin-bottom: 24px;
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}

.eas-score-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-raised);
}

.eas-score-card-head h2 {
    margin: 0;
    font-family: var(--font-head);
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-card-head p {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--muted-text);
}

/* ---------- Overall Score Section ---------- */
.eas-score-overall {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 24px;
    flex-wrap: wrap;
}

.eas-score-circle-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}

.eas-score-circle {
    position: relative;
    width: 96px;
    height: 96px;
    flex: none;
}

.eas-score-circle-svg {
    width: 100%;
    height: 100%;
    display: block;
}

.eas-score-circle-track {
    fill: none;
    stroke: #E5E7EB;
}

.eas-score-circle-progress-ring {
    fill: none;
    stroke-linecap: butt;
}

.eas-score-circle-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    line-height: 1.1;
}

.eas-score-circle-value {
    display: block;
    font-size: 32px;
    font-weight: 800;
    color: var(--navy);
    letter-spacing: -0.02em;
}

.eas-score-circle-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-level-badge {
    text-align: center;
}

.eas-score-level {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

/* Score Details */
.eas-score-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.eas-score-detail {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 8px 0;
}

.eas-score-detail-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
    min-width: 130px;
}

.eas-score-detail-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-detail-value--main {
    font-size: 18px;
}

.eas-score-detail-note {
    font-size: 10px;
    color: var(--muted-text);
    margin-top: 2px;
    display: block;
}

.eas-score-detail--adjustment .eas-score-detail-note {
    font-size: 10px;
}

.eas-score-adjustment--positive {
    color: #27AE60;
}

.eas-score-adjustment--negative {
    color: #ef4444;
}

/* Weights / Calculation Breakdown */
.eas-score-weights-section {
    margin-top: 24px;
    padding: 20px 24px 0;
    border-top: 1px solid var(--border-subtle);
}

.eas-score-weights-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--muted-text);
    display: block;
    margin-bottom: 10px;
}

.eas-score-weight-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.eas-score-weight-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--surface);
    color: var(--navy);
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--border-subtle);
    white-space: nowrap;
}

.eas-score-weight-chip--muted {
    opacity: 0.6;
}

.eas-score-weight-chip-name {
    font-size: 11.5px;
}

.eas-score-weight-chip-value {
    font-size: 12px;
    font-weight: 700;
}

.eas-score-weights-note {
    font-size: 10px;
    color: var(--muted-text);
    margin-top: 8px;
    display: block;
    font-style: italic;
}

/* ---------- Why Section ---------- */
.eas-score-why-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.eas-score-why-section {
    margin-bottom: 4px;
}

.eas-score-why-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.eas-score-why-section-icon {
    width: 20px;
    height: 20px;
    flex: none;
}

.eas-score-why-section-icon--positive { color: #10b981; }
.eas-score-why-section-icon--negative { color: #ef4444; }
.eas-score-why-section-icon--muted { color: #94a3b8; }
.eas-score-why-section-icon--warning { color: #f59e0b; }

.eas-score-why-subhead {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--navy);
}

.eas-score-why-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.eas-score-why-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: var(--text);
    line-height: 1.5;
    padding: 6px 0;
}

.eas-score-why-list-icon {
    width: 18px;
    height: 18px;
    flex: none;
}

.eas-score-why-list-icon--positive { color: #10b981; }
.eas-score-why-list-icon--negative { color: #ef4444; }
.eas-score-why-list-icon--muted { color: #94a3b8; }
.eas-score-why-list-icon--warning { color: #f59e0b; }

.eas-score-why-reason {
    font-size: 13px;
    color: var(--muted-text);
    font-style: italic;
}

.eas-score-why-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 48px 32px;
    color: var(--muted-text);
    background: var(--surface-raised);
    border-radius: var(--radius-card);
}

.eas-score-why-empty-icon {
    width: 56px;
    height: 56px;
    flex: none;
    margin-bottom: 16px;
    opacity: 0.4;
}

.eas-score-why-empty-text {
    max-width: 400px;
}

.eas-score-why-empty-text strong {
    display: block;
    font-size: 16px;
    color: var(--navy);
    margin-bottom: 6px;
}

.eas-score-why-empty-text p {
    margin: 0;
    font-size: 14px;
    color: var(--muted-text);
}

/* ---------- Criteria Breakdown ---------- */
.eas-score-criteria-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
}

.eas-score-criterion {
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-card);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--surface);
}

.eas-score-criterion--collapsed .eas-score-criterion-body {
    display: none;
}

.eas-score-criterion-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-raised);
    cursor: pointer;
    transition: background 0.2s ease;
}

.eas-score-criterion-head:hover {
    background: #F8FAFC;
}

.eas-score-criterion-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.eas-score-criterion-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #F1F5F9;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
    color: var(--navy);
}

.eas-score-criterion-icon path,
.eas-score-criterion-icon line,
.eas-score-criterion-icon polyline,
.eas-score-criterion-icon circle {
    stroke: currentColor;
    fill: none;
}

.eas-score-criterion-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.eas-score-criterion-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.eas-score-criterion-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-criterion-weight {
    font-size: 11px;
    font-weight: 600;
    color: var(--muted-text);
}

.eas-score-criterion-score {
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex: none;
    min-width: 90px;
    justify-content: flex-end;
}

.eas-score-points {
    font-size: 20px;
    font-weight: 800;
    color: var(--navy);
    line-height: 1;
}

.eas-score-points--unevaluated {
    color: var(--muted-text);
}

.eas-score-points-max {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted-text);
}

.eas-score-points-deduction {
    font-size: 16px;
    font-weight: 700;
    color: #ef4444;
    margin-left: 6px;
}

.eas-score-criterion-toggle {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    background: var(--surface);
    color: var(--muted-text);
    cursor: pointer;
    padding: 0;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
}

.eas-score-criterion-toggle:hover {
    border-color: #2563eb;
    color: #2563eb;
}

.eas-score-criterion-toggle[aria-expanded="true"] {
    background: #EAF1FC;
    color: #2563eb;
    border-color: #2563eb;
}

.eas-score-toggle-icon {
    width: 16px;
    height: 16px;
    flex: none;
}

.eas-score-criterion-progress {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border-subtle);
    background: #FAFBFC;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.eas-score-progress-track {
    flex: 1;
    height: 6px;
    background: #E5E7EB;
    border-radius: 3px;
    overflow: hidden;
}

.eas-score-progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.eas-score-progress-fill--empty {
    background: #E5E7EB;
    width: 100%;
    opacity: 0.4;
}

.eas-score-progress-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--navy);
    white-space: nowrap;
    min-width: 70px;
    text-align: right;
}

.eas-score-criterion-status {
    padding: 8px 16px;
    border-bottom: 1px solid var(--border-subtle);
}

.eas-score-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.eas-score-status--evaluated {
    background: #E6F7EE;
    color: #196F3D;
}

.eas-score-status--unevaluated {
    background: #F1F5F9;
    color: #6B7280;
}

.eas-score-criterion-body {
    padding: 20px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    background: var(--surface);
}

.eas-score-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.eas-score-field-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-field-value {
    font-size: 14px;
    color: var(--text);
    line-height: 1.55;
}

.eas-score-field-value--muted {
    color: var(--muted-text);
    font-style: italic;
}

.eas-score-field--manual {
    border-top: 1px solid var(--border-subtle);
    padding-top: 16px;
    margin-top: 16px;
    background: #FEF3C7;
    border-radius: 8px;
}

/* ---------- Assessment History ---------- */
.eas-score-history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 24px;
}

.eas-score-history-item {
    padding: 12px 14px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
}

.eas-score-history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.eas-score-history-action {
    font-size: 13px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-history-action--manual {
    color: #196F3D;
}

.eas-score-history-action--auto {
    color: #1D4ED8;
}

.eas-score-history-date {
    font-size: 11px;
    color: var(--muted-text);
}

.eas-score-history-body p {
    margin: 0;
    font-size: 13px;
    line-height: 1.55;
    color: var(--muted-text);
}

.eas-score-history-reviewer {
    font-weight: 700;
    color: var(--navy);
}

.eas-score-history-score {
    font-weight: 700;
}

.eas-score-history-score--prev {
    color: #6B7280;
}

.eas-score-history-score--next {
    color: var(--navy);
}

.eas-score-history-reason {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    font-style: italic;
    color: var(--muted-text);
}

/* ---------- Reassessment ---------- */
.eas-score-reassess {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border-subtle);
}

.eas-score-reassess-panel {
    margin-top: 12px;
    padding: 20px;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
}

.eas-score-reassess-panel h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 700;
    color: var(--navy);
}

.eas-score-reassess-panel p {
    margin: 0 0 16px;
    font-size: 14px;
    color: var(--muted-text);
}

.eas-score-reassess-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.eas-score-reassess-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.eas-score-reassess-field--wide {
    grid-column: 1 / -1;
}

.eas-score-reassess-field label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}

.eas-score-reassess-field input,
.eas-score-reassess-field select,
.eas-score-reassess-field textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-subtle);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text);
    font: inherit;
    font-size: 14px;
}

.eas-score-reassess-field textarea {
    resize: vertical;
    line-height: 1.5;
}

.eas-score-reassess-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .eas-score-info-row {
        flex-wrap: wrap;
    }

    .eas-score-info-item {
        min-width: 50%;
    }

    .eas-score-overall {
        flex-direction: column;
        align-items: stretch;
        gap: 20px;
    }

    .eas-score-details {
        flex-direction: column;
        gap: 8px;
    }

    .eas-score-detail {
        flex: none;
        min-width: 0;
    }
}

@media (max-width: 640px) {
    .eas-score-head {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .eas-score-head-content {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .eas-score-head h1 {
        font-size: 22px;
    }

    .eas-score-head-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .eas-score-circle {
        width: 80px;
        height: 80px;
    }

    .eas-score-overall {
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }

    .eas-score-details {
        flex-direction: column;
        gap: 8px;
        align-items: center;
        text-align: center;
    }

    .eas-score-detail-label {
        min-width: auto;
    }

    .eas-score-criterion-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .eas-score-criterion-score {
        justify-content: flex-start;
        min-width: auto;
        width: 100%;
    }

    .eas-score-criterion-toggle {
        align-self: flex-start;
    }

    .eas-score-criterion-progress {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .eas-score-progress-track {
        width: 100%;
    }

    .eas-score-progress-label {
        min-width: auto;
    }
}
</style>
@endpush