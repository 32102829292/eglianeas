<?php

$filePath = 'resources/views/admin/kaizen-concerns/show.blade.php';
$content = file_get_contents($filePath);

// Find the start of the Performance Evaluation section
$startMarker = '@if ($evaluation && ($isEvaluationOwner || $canAdminAssess))';
$startPos = strpos($content, $startMarker);

if ($startPos === false) {
    echo "ERROR: Could not find start marker\n";
    exit(1);
}

// Find the closing @endif for the main @if block - we need to find where the main @if ends
// The @endif for the main if should be followed by @if (!$evaluation && ...) 
$endMarker = '@if (!$evaluation && $canAdminAssess && $concern->isAdminConcern())';
$endPos = strpos($content, $endMarker);

if ($endPos === false) {
    echo "ERROR: Could not find end marker\n";
    exit(1);
}

// Get everything before and after the section
$before = substr($content, 0, $startPos);
$after = substr($content, $endPos);

$newSection = <<<'BLADE'
    {{-- Performance Evaluation --}}
    @php
        $canAdminAssess = auth()->user()->isAdmin();
        $assessmentBadge = $evaluation ? $evaluation->aiAssessmentBadge() : null;
        $aiWeighted = $evaluation ? $evaluation->aiWeightedContribution() : null;
        $evalStatus = $evaluation ? $evaluation->evaluation_status : null;
        $evalGrade = $evaluation ? $evaluation->performance_grade : null;
        $evalScore = $evaluation ? $evaluation->overall_score : null;
        // Compute a 0-100 percentage for the visual progress bar
        $scorePercent = $evalScore !== null ? round($evalScore) : 0;
        // Precompute stroke dash offset for the circular progress indicator
        $strokeDasharray = 326.7;
        $strokeDashoffset = $scorePercent !== 0 ? round(326.7 - (326.7 * $scorePercent / 100), 1) : 326.7;
        // Precompute stroke color for the progress circle
        $strokeColor = $evalScore !== null ? ($evalScore >= 75 ? '#22c55e' : ($evalScore >= 50 ? '#f59e0b' : '#ef4444')) : '#e2e8f0';
    @endphp

    @if ($evaluation && ($isEvaluationOwner || $canAdminAssess))
    <div class="eval-card" id="performance-evaluation">
        <div class="eval-card-head">
            <h2 class="eval-card-title">
                {{ $isEvaluationOwner ? 'My Performance Evaluation' : 'Performance Evaluation' }}
            </h2>
            <div class="eval-badge-group">
                @if ($assessmentBadge)
                <span class="eval-badge {{ $assessmentBadge['class'] }}">{{ $assessmentBadge['label'] }}</span>
                @endif
                @if ($evalGrade)
                <span class="eval-badge grade">{{ $evalGrade }}</span>
                @endif
            </div>
        </div>
        <div class="eval-card-body">

            {{-- Row 1: Score Summary + Score Sheet Integration --}}
            <div class="eval-row-1">
                <div class="eval-score-panel">
                    <div class="eval-score-big">
                        <div class="eval-score-circle">
                            <svg class="eval-score-circle-svg" width="120" height="120" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10"/>
                                <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $strokeColor }}" stroke-width="10" stroke-dasharray="{{ $strokeDasharray }}" stroke-dashoffset="{{ $strokeDashoffset }}" stroke-linecap="round"/>
                                <text x="60" y="66" font-family="{{ var(--font-head) }}" font-size="24" font-weight="800" text-anchor="middle" fill="var(--navy)">{{ number_format($evalScore, 1) }}</text>
                            </svg>
                            <span class="eval-score-unit">/100</span>
                        </div>
                        <div class="eval-score-meta">
                            <p class="eval-score-grade">{{ $evalGrade }}</p>
                            <p class="eval-score-status">{{ $assessmentBadge ? $assessmentBadge['label'] : ucfirst(str_replace('_', ' ', $evalStatus)) }}</p>
                            @if ($evaluation->assessment_source)
                            <p class="eval-score-source">
                                {{ \App\Models\StaffTaskEvaluation::ASSESSMENT_SOURCES[$evaluation->assessment_source] ?? $evaluation->assessment_source }}
                            </p>
                            @endif
                        </div>
                    </div>

                    <div class="eval-sheet-panel">
                        <div class="eval-sheet-head">
                            <p class="eval-sheet-label">Score Sheet Integration</p>
                            <p class="eval-sheet-period">{{ $evaluation->evaluationPeriod?->name ?? 'No evaluation period' }}</p>
                        </div>

                        @if ($evaluation->isIncludedInScoreSheet())
                        <p class="eval-sheet-note eval-sheet-note--added">
                            <span class="eval-sheet-badge badge-score-added">ADDED TO SCORE SHEET</span>
                            Added to {{ $evaluation->sourceLabel() }} #{{ $evaluation->source_id }} Score Sheet
                            on {{ $evaluation->score_sheet_added_at?->format('F j, Y') }}.
                        </p>
                        @elseif ($evaluation->evaluation_status === \App\Models\StaffTaskEvaluation::STATUS_REVERSED)
                        <p class="eval-sheet-note eval-sheet-note--reversed">
                            <span class="eval-sheet-badge badge-score-reversed">REVERSED / EXCLUDED</span>
                            Excluded from the Score Sheet.
                            @if ($evaluation->score_sheet_reversal_reason)
                                Reason: {{ $evaluation->score_sheet_reversal_reason }}
                            @endif
                        </p>
                        @elseif ($evaluation->isAwaitingAdminReview())
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-review">FOR ADMIN REVIEW</span>
                            Awaiting admin review. Use the actions below to accept, adjust, or finalize this assessment.
                        </p>
                        @elseif ($evaluation->overall_score !== null)
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-pending">PENDING INTEGRATION</span>
                            Evaluation is finalized but not yet added to the Score Sheet.
                        </p>
                        @else
                        <p class="eval-sheet-note eval-sheet-note--pending">
                            <span class="eval-sheet-badge badge-score-pending">EVALUATION PENDING</span>
                            Not yet added to the Score Sheet.
                        </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Evaluation dates --}}
            <div class="eval-dates">
                <div class="eval-date-item">
                    <p class="eval-date-label">Submitted</p>
                    <p class="eval-date-value">{{ $evaluation->submitted_at?->format('F j, Y') ?? '—' }}</p>
                </div>
                <div class="eval-date-item">
                    <p class="eval-date-label">Deadline</p>
                    <p class="eval-date-value">{{ $evaluation->deadline_at?->format('F j, Y') ?? '—' }}</p>
                </div>
                <div class="eval-date-item">
                    <p class="eval-date-label">Completed</p>
                    <p class="eval-date-value">{{ $evaluation->completed_at?->format('F j, Y') ?? '—' }}</p>
                </div>
            </div>

            {{-- Criterion breakdown table --}}
            <table class="eval-criterion-table">
                <thead>
                    <tr>
                        <th>Criterion</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">Weight</th>
                        <th class="text-end">Weighted Points</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="criterion-name">Accomplishment Quality</td>
                        <td class="text-end">{{ $evaluation->accomplishment_quality_score ? number_format($evaluation->accomplishment_quality_score, 2) : '—' }}/100</td>
                        <td class="text-end">50%</td>
                        <td class="text-end">{{ $evaluation->accomplishment_quality_score ? number_format($evaluation->accomplishment_quality_score * 0.50, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Timeliness</td>
                        <td class="text-end">{{ $evaluation->timeliness_score ? number_format($evaluation->timeliness_score, 2) : '—' }}/100</td>
                        <td class="text-end">20%</td>
                        <td class="text-end">{{ $evaluation->timeliness_score ? number_format($evaluation->timeliness_score * 0.20, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Immediate Responsiveness</td>
                        <td class="text-end">{{ $evaluation->immediate_responsiveness_score ? number_format($evaluation->immediate_responsiveness_score, 2) : '—' }}/100</td>
                        <td class="text-end">20%</td>
                        <td class="text-end">{{ $evaluation->immediate_responsiveness_score ? number_format($evaluation->immediate_responsiveness_score * 0.20, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="criterion-name">Client Feedback</td>
                        <td class="text-end na">N/A</td>
                        <td class="text-end">10%</td>
                        <td class="text-end na">N/A</td>
                    </tr>
                    <tr class="total-row">
                        <td class="criterion-name">Overall</td>
                        <td class="text-end">{{ $evalScore ? number_format($evalScore, 2) : '—' }}/100</td>
                        <td class="text-end">100%</td>
                        <td class="text-end">{{ $evalScore ? number_format($evalScore, 2) : '—' }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Assessment explanation --}}
            @if ($evaluation->assessment_explanation)
            <div class="pri-eval-note">
                <strong>Assessment:</strong> {{ $evaluation->assessment_explanation }}
            </div>
            @endif

            @if ($evaluation->adjustment_reason)
            <div class="pri-eval-note">
                <strong>Admin note:</strong> {{ $evaluation->adjustment_reason }}
            </div>
            @endif

            {{-- Admin actions --}}
            @if ($canAdminAssess)
            <div class="pri-eval-actions" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-subtle);">

                @if ($evaluation->isAwaitingAdminReview() || $evalScore === null)
                    {{-- Generate / Re-assess --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.store', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Generate AI Assessment?', message: 'This will run an AI-assisted assessment of the Admin Concern and store it as a provisional evaluation pending your review.', confirmLabel: 'Generate Assessment' });">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6M4 9h4M16 9h4"/></svg>
                            Generate AI Assessment
                        </button>
                    </form>

                @elseif ($evaluation->isProvisionalAssessment())
                    {{-- Adjust and Finalize --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.adjust', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Adjust Assessment?', message: 'Changing scores will mark this evaluation as adjusted and re-synchronize it to the Score Sheet.', confirmLabel: 'Adjust Assessment' });">
                        @csrf
                        @method('PATCH')
                        <div class="form-group d-inline" style="margin-right: 12px;">
                            <label class="form-label" style="margin-bottom: 4px;">Adjusted Score</label>
                            <input type="number" name="overall_score" class="form-control form-control-sm" step="0.01" min="0" max="100" value="{{ number_format($evalScore, 2) }}" style="width: 90px; display: inline-block;">
                        </div>
                        <div class="form-group d-inline" style="margin-right: 12px;">
                            <label class="form-label" style="margin-bottom: 4px;">Reason</label>
                            <input type="text" name="adjustment_reason" class="form-control form-control-sm" placeholder="Reason for adjustment" style="width: 200px; display: inline-block;" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">Adjust Score</button>
                    </form>

                    <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.finalize', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Finalize Assessment?', message: 'This will finalize the evaluation and add it to the staff Score Sheet. The score cannot be adjusted afterward without reversal.', confirmLabel: 'Finalize Assessment' });">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Finalize Assessment</button>
                    </form>

                @elseif ($evalStatus === \App\Models\StaffTaskEvaluation::STATUS_FINALIZED)
                    {{-- Reverse finalized --}}
                    <form method="POST" action="{{ route('admin.kaizen-concerns.evaluation.reverse', $concern) }}"
                          class="d-inline"
                          onsubmit="return egliane.confirm.form(this, { title: 'Reverse Evaluation?', message: 'This will remove the evaluation from the Score Sheet and mark it as REVERSED / EXCLUDED. The evaluation history will be kept.', confirmLabel: 'Reverse Evaluation', danger: true });">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Reverse Evaluation</button>
                    </form>
                @endif

                {{-- Always allow reverse if not reversed --}}
                @if ($evalStatus !== \App\Models\StaffTaskEvaluation::STATUS_REVERSED
                    && $evalStatus !== \App\Models\StaffTaskEvaluation::STATUS_AWAITING_REVIEW)
                    <form method="POST" action="{{ route('admin.kaizen-concerns.evaluation.reverse', $concern) }}"
                          class="d-inline"
                          style="margin-left: 12px;"
                          onsubmit="return egliane.confirm.form(this, { title: 'Reverse / Exclude from Score Sheet?', message: 'This will remove the evaluation from the Score Sheet. The evaluation history will be kept.', confirmLabel: 'Reverse', danger: true });">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Reverse</button>
                    </form>
                @endif
            </div>
            @endif

        </div>
    </div>
    @endif

    @if (!$evaluation && $canAdminAssess && $concern->isAdminConcern())
    {{-- Admin can generate an assessment even if none exists --}}
    <div class="eval-card" id="performance-evaluation">
        <div class="eval-card-head">
            <h2 class="eval-card-title">Performance Evaluation</h2>
            <span class="eval-badge pending">EVALUATION PENDING</span>
        </div>
        <div class="eval-card-body">
            <p class="muted">No performance evaluation has been generated for this Admin Concern yet.</p>
            <form method="POST" action="{{ route('admin.kaizen-concerns.assessment.store', $concern) }}"
                  onsubmit="return egliane.confirm.form(this, { title: 'Generate AI Assessment?', message: 'This will run an AI-assisted assessment of the Admin Concern based on its challenge, progress updates, and evidence.', confirmLabel: 'Generate Assessment' });">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6M4 9h4M16 9h4"/></svg>
                    Generate AI Assessment
                </button>
            </form>
        </div>
    </div>
    @endif
BLADE;

    $newContent = $before . $newSection . $after;
    
    // Write back
    file_put_contents($filePath, $newContent);
    echo "Successfully updated show.blade.php\n";
    