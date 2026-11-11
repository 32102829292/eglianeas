<?php

namespace App\Services;

use App\Models\KaizenConcern;
use App\Models\StaffContributionAudit;
use App\Models\StaffContributionRecord;
use App\Models\StaffEvaluationAudit;
use App\Models\StaffEvaluationPeriod;
use App\Models\StaffScoringConfig;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Computes per-task performance scores for Kaizen submissions and Admin Concern
 * completions.
 *
 * Four criteria, each on a 0-100 scale:
 *   1. Accomplishment Quality (weight: 50%)
 *   2. Timeliness (weight: 20%)
 *   3. Immediate Responsiveness (weight: 20%)
 *   4. Client Feedback (weight: 10%) — always null for Kaizen/Admin Concern
 *
 * Overall score = (quality × 0.50) + (timeliness × 0.20) + (responsiveness × 0.20)
 *   (client_feedback is excluded since it's not applicable to these modules)
 *
 * Duplicate prevention: evaluation records are keyed by (source_type, source_id).
 * A single qualifying event must not award points multiple times.
 */
class StaffScoringService
{
    public const CRITERION_QUALITY = 'quality';
    public const CRITERION_TIMELINESS = 'timeliness';
    public const CRITERION_RESPONSIVENESS = 'responsiveness';
    public const CRITERION_CLIENT_FEEDBACK = 'client_feedback';

    public const WEIGHT_QUALITY = 0.50;
    public const WEIGHT_TIMELINESS = 0.20;
    public const WEIGHT_RESPONSIVENESS = 0.20;
    public const WEIGHT_CLIENT_FEEDBACK = 0.10;

    public const RESPONSE_PROMPT_HOURS = 24;

    public const SOURCE_KAIZEN_CONCERN = 'kaizen_concern';
    public const SOURCE_ADMIN_CONCERN = 'admin_concern';

    /**
     * The criterion each source primarily contributes to in the official
     * four-criterion Score Sheet. The weight is the existing configured weight
     * for that criterion, so the integration badge never invents a second
     * scoring formula.
     */
    public const SCORE_SHEET_CRITERIA = [
        self::SOURCE_KAIZEN_CONCERN => self::CRITERION_QUALITY,
        self::SOURCE_ADMIN_CONCERN => self::CRITERION_QUALITY,
    ];

    public const SCORE_SHEET_CRITERION_WEIGHTS = [
        self::CRITERION_QUALITY => self::WEIGHT_QUALITY,
        self::CRITERION_TIMELINESS => self::WEIGHT_TIMELINESS,
        self::CRITERION_RESPONSIVENESS => self::WEIGHT_RESPONSIVENESS,
        self::CRITERION_CLIENT_FEEDBACK => self::WEIGHT_CLIENT_FEEDBACK,
    ];

    public const ACTIVITY_KAIZEN_SUBMITTED = 'kaizen_submitted';
    public const ACTIVITY_KAIZEN_IMPLEMENTED = 'kaizen_implemented';
    public const ACTIVITY_ADMIN_CONCERN_COMPLETED = 'admin_concern_completed';

    public const CONTRIBUTION_KAIZEN_SUBMITTED = 2;
    public const CONTRIBUTION_KAIZEN_IMPLEMENTED = 3;
    public const CONTRIBUTION_ADMIN_CONCERN_COMPLETED = 3;

    /**
     * Evaluate a Kaizen concern submission. Called when a staff member submits
     * an Employee Suggestion.
     *
     * Idempotent: if an evaluation already exists for this source, returns it
     * without creating a duplicate.
     */
    public function evaluateKaizenSubmission(KaizenConcern $concern): StaffTaskEvaluation
    {
        $existing = StaffTaskEvaluation::forSource(self::SOURCE_KAIZEN_CONCERN, $concern->id)->first();

        if ($existing) {
            return $existing;
        }

        $staff = $concern->creator;

        // --- Quality: based on completeness of the submission ---
        $quality = $this->scoreKaizenQuality($concern);

        // --- Timeliness: submission vs target_date ---
        $timeliness = $this->scoreKaizenTimeliness($concern);

        // --- Responsiveness: creation to first action (assignment) ---
        // For a new submission, there's no action yet, so this is provisional.
        $responsiveness = $this->scoreKaizenResponsiveness($concern);

        // --- Client feedback: not applicable ---
        $clientFeedback = null;

        // --- Combine ---
        $weights = [
            self::CRITERION_QUALITY => self::WEIGHT_QUALITY,
            self::CRITERION_TIMELINESS => self::WEIGHT_TIMELINESS,
            self::CRITERION_RESPONSIVENESS => self::WEIGHT_RESPONSIVENESS,
        ];

        $criteria = [
            self::CRITERION_QUALITY => $quality['score'],
            self::CRITERION_TIMELINESS => $timeliness['score'],
            self::CRITERION_RESPONSIVENESS => $responsiveness['score'],
        ];

        $combined = $this->combineScores($criteria, $weights);

        $evaluation = StaffTaskEvaluation::create([
            'staff_id' => $staff->id,
            'source_type' => self::SOURCE_KAIZEN_CONCERN,
            'source_id' => $concern->id,
            'evaluation_period_id' => $this->currentPeriod()->id ?? null,
            'accomplishment_quality_score' => $quality['score'],
            'timeliness_score' => $timeliness['score'],
            'immediate_responsiveness_score' => $responsiveness['score'],
            'client_feedback_score' => $clientFeedback,
            'overall_score' => $combined['overall_score'],
            'performance_grade' => $combined['overall_score'] !== null
                ? $this->gradeForScore($combined['overall_score'])
                : null,
            'evaluation_status' => StaffTaskEvaluation::STATUS_PROVISIONAL,
            'submitted_at' => $concern->created_at,
            'deadline_at' => $concern->target_date,
            'adjustment_reason' => null,
        ]);

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'created',
            'previous_values' => null,
            'new_values' => $evaluation->only([
                'accomplishment_quality_score', 'timeliness_score',
                'immediate_responsiveness_score', 'overall_score',
                'performance_grade', 'evaluation_status',
            ]),
            'adjustment_reason' => 'Auto-created on Kaizen submission.',
            'performed_by' => auth()->id(),
        ]);

        // --- Contribution points for submission ---
        $this->awardContribution(
            $staff->id,
            self::ACTIVITY_KAIZEN_SUBMITTED,
            self::SOURCE_KAIZEN_CONCERN,
            $concern->id,
            self::CONTRIBUTION_KAIZEN_SUBMITTED,
            'Kaizen submission: "'.$concern->challenge.'"',
        );

        return $evaluation;
    }

    /**
     * Evaluate a Kaizen concern implementation. Called when an admin confirms
     * a suggestion as implemented.
     *
     * Updates the existing evaluation with implementation data and awards
     * implementation contribution points.
     */
    public function evaluateKaizenImplementation(KaizenConcern $concern): StaffTaskEvaluation
    {
        $evaluation = StaffTaskEvaluation::forSource(self::SOURCE_KAIZEN_CONCERN, $concern->id)->first();

        if (! $evaluation) {
            // No submission evaluation exists; create one now from implementation data.
            return $this->evaluateKaizenSubmission($concern);
        }

        $previous = $evaluation->only([
            'accomplishment_quality_score',
            'timeliness_score',
            'immediate_responsiveness_score',
            'overall_score',
            'performance_grade',
            'evaluation_status',
        ]);

        // --- Quality: now that it's implemented, factor in the outcome ---
        $quality = $this->scoreKaizenQuality($concern, $concern->isImplemented());

        // --- Timeliness: submission to implementation vs target_date ---
        $timeliness = $this->scoreKaizenTimeliness($concern, $concern->isImplemented());

        // --- Responsiveness: creation to implementation ---
        $responsiveness = $this->scoreKaizenResponsiveness($concern, $concern->isImplemented());

        $weights = [
            self::CRITERION_QUALITY => self::WEIGHT_QUALITY,
            self::CRITERION_TIMELINESS => self::WEIGHT_TIMELINESS,
            self::CRITERION_RESPONSIVENESS => self::WEIGHT_RESPONSIVENESS,
        ];

        $criteria = [
            self::CRITERION_QUALITY => $quality['score'],
            self::CRITERION_TIMELINESS => $timeliness['score'],
            self::CRITERION_RESPONSIVENESS => $responsiveness['score'],
        ];

        $combined = $this->combineScores($criteria, $weights);

        $evaluation->update([
            'accomplishment_quality_score' => $quality['score'],
            'timeliness_score' => $timeliness['score'],
            'immediate_responsiveness_score' => $responsiveness['score'],
            'overall_score' => $combined['overall_score'],
            'performance_grade' => $combined['overall_score'] !== null
                ? $this->gradeForScore($combined['overall_score'])
                : null,
            'evaluation_status' => StaffTaskEvaluation::STATUS_FINALIZED,
            'completed_at' => $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null,
            'finalized_at' => now(),
            'evaluated_by' => auth()->id(),
            'adjustment_reason' => 'Finalised on implementation confirmation.',
        ]);

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'adjusted',
            'previous_values' => $previous,
            'new_values' => $evaluation->only([
                'accomplishment_quality_score', 'timeliness_score',
                'immediate_responsiveness_score', 'overall_score',
                'performance_grade', 'evaluation_status',
            ]),
            'adjustment_reason' => 'Score updated on implementation confirmation.',
            'performed_by' => auth()->id(),
        ]);

        $this->synchroniseScoreSheet(
            $evaluation,
            auth()->id(),
            'Included in the staff Score Sheet after implementation confirmation.'
        );

        // --- Contribution points for implementation ---
        $this->awardContribution(
            $concern->creator->id,
            self::ACTIVITY_KAIZEN_IMPLEMENTED,
            self::SOURCE_KAIZEN_CONCERN,
            $concern->id,
            self::CONTRIBUTION_KAIZEN_IMPLEMENTED,
            'Kaizen implementation: "'.$concern->challenge.'"',
        );

        return $evaluation;
    }

    /**
     * Create a pending evaluation when an Admin Concern is assigned to a staff
     * member. No scores are computed yet; the evaluation is finalized when the
     * concern is completed.
     *
     * Idempotent: if an evaluation already exists for this source, returns it.
     */
    public function evaluateAdminConcernCreated(KaizenConcern $concern): StaffTaskEvaluation
    {
        $existing = StaffTaskEvaluation::forSource(self::SOURCE_ADMIN_CONCERN, $concern->id)->first();

        if ($existing) {
            return $existing;
        }

        $staff = $concern->assignedStaff ?? $concern->creator;

        $evaluation = StaffTaskEvaluation::create([
            'staff_id' => $staff->id,
            'source_type' => self::SOURCE_ADMIN_CONCERN,
            'source_id' => $concern->id,
            'evaluation_period_id' => $this->currentPeriod()->id ?? null,
            'accomplishment_quality_score' => null,
            'timeliness_score' => null,
            'immediate_responsiveness_score' => null,
            'client_feedback_score' => null,
            'overall_score' => null,
            'performance_grade' => null,
            'evaluation_status' => StaffTaskEvaluation::STATUS_PENDING,
            'submitted_at' => $concern->created_at,
            'acknowledged_at' => null,
            'deadline_at' => $concern->target_date,
            'completed_at' => null,
            'finalized_at' => null,
            'evaluated_by' => null,
            'adjustment_reason' => null,
        ]);

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'created',
            'previous_values' => null,
            'new_values' => $evaluation->only([
                'accomplishment_quality_score', 'timeliness_score',
                'immediate_responsiveness_score', 'overall_score',
                'performance_grade', 'evaluation_status',
            ]),
            'adjustment_reason' => 'Auto-created when Admin Concern was assigned.',
            'performed_by' => auth()->id(),
        ]);

        return $evaluation;
    }

    /**
     * Evaluate an Admin Concern completion. Called when an admin verifies that
     * a staff member has completed an assigned Admin Concern.
     */
    public function evaluateAdminConcernCompletion(KaizenConcern $concern): StaffTaskEvaluation
    {
        $evaluation = StaffTaskEvaluation::forSource(self::SOURCE_ADMIN_CONCERN, $concern->id)->first();

        if (!$evaluation) {
            $staff = $concern->assignedStaff ?? $concern->creator;

            $evaluation = StaffTaskEvaluation::create([
                'staff_id' => $staff->id,
                'source_type' => self::SOURCE_ADMIN_CONCERN,
                'source_id' => $concern->id,
                'evaluation_period_id' => $this->currentPeriod()->id ?? null,
                'accomplishment_quality_score' => null,
                'timeliness_score' => null,
                'immediate_responsiveness_score' => null,
                'client_feedback_score' => null,
                'overall_score' => null,
                'performance_grade' => null,
                'evaluation_status' => StaffTaskEvaluation::STATUS_PENDING,
                'submitted_at' => $concern->created_at,
                'acknowledged_at' => null,
                'deadline_at' => $concern->target_date,
                'completed_at' => null,
                'finalized_at' => null,
                'evaluated_by' => null,
                'adjustment_reason' => null,
            ]);

            StaffEvaluationAudit::create([
                'evaluation_id' => $evaluation->id,
                'action' => 'created',
                'previous_values' => null,
                'new_values' => $evaluation->only([
                    'accomplishment_quality_score', 'timeliness_score',
                    'immediate_responsiveness_score', 'overall_score',
                    'performance_grade', 'evaluation_status',
                ]),
                'adjustment_reason' => 'Auto-created when Admin Concern was assigned.',
                'performed_by' => auth()->id(),
            ]);
        } else {
            $staff = $concern->assignedStaff ?? $concern->creator;
        }

        $quality = $this->scoreAdminConcernQuality($concern);

        // --- Timeliness: assignment to completion vs target_date ---
        $timeliness = $this->scoreAdminConcernTimeliness($concern);

        // --- Responsiveness: assignment to first action/completion ---
        $responsiveness = $this->scoreAdminConcernResponsiveness($concern);

        // --- Client feedback: not applicable ---
        $clientFeedback = null;

        $weights = [
            self::CRITERION_QUALITY => self::WEIGHT_QUALITY,
            self::CRITERION_TIMELINESS => self::WEIGHT_TIMELINESS,
            self::CRITERION_RESPONSIVENESS => self::WEIGHT_RESPONSIVENESS,
        ];

        $criteria = [
            self::CRITERION_QUALITY => $quality['score'],
            self::CRITERION_TIMELINESS => $timeliness['score'],
            self::CRITERION_RESPONSIVENESS => $responsiveness['score'],
        ];

        $combined = $this->combineScores($criteria, $weights);

        $evaluation->update([
            'staff_id' => $staff->id,
            'accomplishment_quality_score' => $quality['score'],
            'timeliness_score' => $timeliness['score'],
            'immediate_responsiveness_score' => $responsiveness['score'],
            'client_feedback_score' => $clientFeedback,
            'overall_score' => $combined['overall_score'],
            'performance_grade' => $combined['overall_score'] !== null
                ? $this->gradeForScore($combined['overall_score'])
                : null,
            'evaluation_status' => StaffTaskEvaluation::STATUS_FINALIZED,
            'submitted_at' => $concern->created_at,
            'acknowledged_at' => $concern->created_at,
            'deadline_at' => $concern->target_date,
            'completed_at' => $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null,
            'finalized_at' => now(),
            'evaluated_by' => auth()->id(),
            'adjustment_reason' => 'Auto-evaluated on Admin Concern completion.',
        ]);

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'updated',
            'previous_values' => [],
            'new_values' => $evaluation->only([
                'accomplishment_quality_score', 'timeliness_score',
                'immediate_responsiveness_score', 'overall_score',
                'performance_grade', 'evaluation_status',
            ]),
            'adjustment_reason' => 'Auto-evaluated on Admin Concern completion.',
            'performed_by' => auth()->id(),
        ]);

        $this->synchroniseScoreSheet(
            $evaluation,
            auth()->id(),
            'Included in the staff Score Sheet after verified Admin Concern completion.'
        );

        // --- Contribution points for completion ---
        $this->awardContribution(
            $staff->id,
            self::ACTIVITY_ADMIN_CONCERN_COMPLETED,
            self::SOURCE_ADMIN_CONCERN,
            $concern->id,
            self::CONTRIBUTION_ADMIN_CONCERN_COMPLETED,
            'Admin Concern completion: "'.$concern->challenge.'"',
        );

        return $evaluation;
    }

    /**
     * Adjust an existing evaluation (admin override). Records an audit trail.
     */
    public function adjustEvaluation(
        StaffTaskEvaluation $evaluation,
        array $scores,
        string $reason,
        ?int $performedBy = null
    ): StaffTaskEvaluation {
        $previous = $evaluation->only([
            'accomplishment_quality_score',
            'timeliness_score',
            'immediate_responsiveness_score',
            'client_feedback_score',
            'overall_score',
            'performance_grade',
            'evaluation_status',
        ]);

        $evaluation->fill($scores);

        if (isset($scores['overall_score'])) {
            $evaluation->performance_grade = $this->gradeForScore($scores['overall_score']);
        }

        if ($scores['evaluation_status'] ?? null) {
            $evaluation->evaluation_status = $scores['evaluation_status'];
        } elseif (isset($scores['overall_score'])) {
            // An adjusted score is revisions of an evaluation that may already
            // be on the Score Sheet, so flag it for the "REVISED" badge.
            $evaluation->evaluation_status = StaffTaskEvaluation::STATUS_ADJUSTED;
        }

        if (isset($scores['overall_score'])) {
            $evaluation->finalized_at = now();
            $evaluation->evaluated_by = $performedBy ?? auth()->id();
        }

        $evaluation->adjustment_reason = $reason;

        if ($evaluation->evaluation_status === StaffTaskEvaluation::STATUS_REVERSED) {
            $this->markScoreSheetExcluded($evaluation, $reason, $performedBy);
        }

        $evaluation->save();

        if ($evaluation->evaluation_status !== StaffTaskEvaluation::STATUS_REVERSED) {
            $this->synchroniseScoreSheet($evaluation, $performedBy, $reason);
        }

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'adjusted',
            'previous_values' => $previous,
            'new_values' => $evaluation->only([
                'accomplishment_quality_score', 'timeliness_score',
                'immediate_responsiveness_score', 'client_feedback_score',
                'overall_score', 'performance_grade', 'evaluation_status',
                'score_sheet_included', 'score_sheet_added_at',
                'score_sheet_weighted_points', 'score_sheet_criterion',
            ]),
            'adjustment_reason' => $reason,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);

        return $evaluation;
    }

    /**
     * Reverse/invalidate an evaluation and remove it from the Score Sheet while
     * keeping the audit history intact. The badge immediately reads
     * REVERSED / EXCLUDED because the evaluation status is the authoritative
     * source of truth.
     */
    public function reverseEvaluation(
        StaffTaskEvaluation $evaluation,
        string $reason,
        ?int $performedBy = null
    ): StaffTaskEvaluation {
        $previous = $evaluation->only([
            'overall_score',
            'performance_grade',
            'evaluation_status',
            'score_sheet_included',
            'score_sheet_added_at',
            'score_sheet_weighted_points',
            'score_sheet_criterion',
        ]);

        $evaluation->evaluation_status = StaffTaskEvaluation::STATUS_REVERSED;
        $evaluation->adjustment_reason = $reason;
        $this->markScoreSheetExcluded($evaluation, $reason, $performedBy);
        $evaluation->save();

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'reversed',
            'previous_values' => $previous,
            'new_values' => $evaluation->only([
                'overall_score', 'performance_grade', 'evaluation_status',
                'score_sheet_included', 'score_sheet_added_at',
                'score_sheet_weighted_points', 'score_sheet_criterion',
                'score_sheet_reversed_at', 'score_sheet_reversal_reason',
            ]),
            'adjustment_reason' => $reason,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);

        return $evaluation;
    }

    /**
     * Move an Admin Concern evaluation to the staff member who now owns the
     * task. When the assignment is cleared the evaluation is excluded instead
     * of being left against the wrong person.
     */
    public function reassignEvaluation(
        StaffTaskEvaluation $evaluation,
        ?int $staffId,
        ?int $performedBy = null,
        string $reason = 'Admin Concern assignment changed.'
    ): StaffTaskEvaluation {
        if ($staffId === null) {
            return $this->reverseEvaluation($evaluation, $reason.' No staff member is assigned.', $performedBy);
        }

        if ((int) $evaluation->staff_id === (int) $staffId) {
            return $evaluation;
        }

        $previousStaff = $evaluation->staff_id;
        $evaluation->staff_id = $staffId;
        $evaluation->save();

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'reassigned',
            'previous_values' => ['staff_id' => $previousStaff],
            'new_values' => ['staff_id' => $staffId],
            'adjustment_reason' => $reason,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);

        if ($evaluation->evaluation_status !== StaffTaskEvaluation::STATUS_REVERSED) {
            $this->synchroniseScoreSheet($evaluation, $performedBy, $reason);
        }

        return $evaluation;
    }

    /**
     * Link a valid evaluation to the staff Score Sheet and snapshot the points
     * it contributes. This is the only method that can produce the
     * "ADDED TO SCORE SHEET" badge.
     */
    public function synchroniseScoreSheet(
        StaffTaskEvaluation $evaluation,
        ?int $performedBy = null,
        ?string $reason = null
    ): StaffTaskEvaluation {
        if ($evaluation->evaluation_status === StaffTaskEvaluation::STATUS_REVERSED) {
            return $evaluation;
        }

        // A badge must never claim integration without a staff member, an
        // evaluation period, and an actual score.
        if ($evaluation->staff_id === null
            || $evaluation->evaluation_period_id === null
            || $evaluation->overall_score === null) {
            return $evaluation;
        }

        $criterion = self::SCORE_SHEET_CRITERIA[$evaluation->source_type] ?? self::CRITERION_QUALITY;
        $weight = self::SCORE_SHEET_CRITERION_WEIGHTS[$criterion] ?? self::WEIGHT_QUALITY;

        $evaluation->score_sheet_included = true;
        $evaluation->score_sheet_added_at ??= now();
        $evaluation->score_sheet_criterion = $criterion;
        $evaluation->score_sheet_weighted_points = round((float) $evaluation->overall_score * $weight, 2);
        $evaluation->score_sheet_reversed_at = null;
        $evaluation->score_sheet_reversal_reason = null;
        $evaluation->save();

        StaffEvaluationAudit::create([
            'evaluation_id' => $evaluation->id,
            'action' => 'score_sheet_synced',
            'previous_values' => [
                'score_sheet_included' => false,
            ],
            'new_values' => $evaluation->only([
                'staff_id', 'evaluation_period_id', 'score_sheet_included',
                'score_sheet_added_at', 'score_sheet_weighted_points',
                'score_sheet_criterion', 'overall_score', 'performance_grade',
            ]),
            'adjustment_reason' => $reason ?? 'Evaluation included in the staff Score Sheet.',
            'performed_by' => $performedBy ?? auth()->id(),
        ]);

        return $evaluation;
    }

    /**
     * Mark an evaluation as excluded from the Score Sheet without deleting it.
     */
    private function markScoreSheetExcluded(
        StaffTaskEvaluation $evaluation,
        string $reason,
        ?int $performedBy = null
    ): void {
        $evaluation->score_sheet_included = false;
        $evaluation->score_sheet_reversed_at = now();
        $evaluation->score_sheet_reversal_reason = $reason;
        $evaluation->score_sheet_weighted_points = null;
    }

    /**
     * Every Kaizen/Admin Concern evaluation that belongs to a staff member's
     * Score Sheet for the period containing the given date.
     *
     * Included evaluations are the scores that actually contributed; reversed
     * or pending evaluations are returned too so the Score Sheet can show and
     * explain exclusions without losing the audit trail.
     */
    public function taskEvaluationsForScoreSheet(User $staff, Carbon $date): \Illuminate\Support\Collection
    {
        $period = $this->periodContaining($date);

        $query = StaffTaskEvaluation::query()
            ->forStaff($staff->id)
            ->whereIn('source_type', [self::SOURCE_KAIZEN_CONCERN, self::SOURCE_ADMIN_CONCERN])
            ->with(['evaluationPeriod', 'evaluatedBy', 'sourceRecord'])
            ->orderByDesc('score_sheet_added_at')
            ->orderByDesc('updated_at');

        if ($period !== null) {
            $query->where('evaluation_period_id', $period->id);
        } else {
            $query->whereDate('created_at', '>=', $date->copy()->startOfMonth())
                ->whereDate('created_at', '<=', $date->copy()->endOfMonth());
        }

        return $query->get();
    }

    private function periodContaining(Carbon $date): ?StaffEvaluationPeriod
    {
        return StaffEvaluationPeriod::active()->containingDate($date)->first()
            ?? StaffEvaluationPeriod::containingDate($date)->first();
    }

    /**
     * Weighted points an evaluation contributes to the official Score Sheet,
     * using the existing criterion weight (never a separate formula).
     */
    public function weightedContributionFor(StaffTaskEvaluation $evaluation): ?float
    {
        if ($evaluation->overall_score === null) {
            return null;
        }

        if ($evaluation->score_sheet_weighted_points !== null) {
            return round((float) $evaluation->score_sheet_weighted_points, 2);
        }

        $criterion = self::SCORE_SHEET_CRITERIA[$evaluation->source_type] ?? self::CRITERION_QUALITY;
        $weight = self::SCORE_SHEET_CRITERION_WEIGHTS[$criterion] ?? self::WEIGHT_QUALITY;

        return round((float) $evaluation->overall_score * $weight, 2);
    }

    /**
     * Reverse a contribution (undo points).
     */
    public function reverseContribution(
        StaffContributionRecord $contribution,
        string $reason,
        ?int $performedBy = null
    ): void {
        $contribution->update([
            'status' => StaffContributionRecord::STATUS_REVERSED,
        ]);

        StaffContributionAudit::create([
            'contribution_id' => $contribution->id,
            'action' => 'reversed',
            'previous_points' => $contribution->points,
            'new_points' => 0,
            'adjustment_reason' => $reason,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);
    }

    // ------------------------------------------------------------------
    // Private scoring methods
    // ------------------------------------------------------------------

    private function scoreKaizenQuality(KaizenConcern $concern, bool $isImplemented = false): array
    {
        $score = 0;
        $details = [];

        $challenge = trim((string) $concern->challenge);
        $solution = trim((string) $concern->recommended_solution);
        $notes = trim((string) $concern->notes);
        $evidenceCount = $concern->evidences_count ?? 0;

        // Challenge clarity (max 30)
        if ($challenge !== '') {
            $challengeLen = mb_strlen($challenge);
            if ($challengeLen >= 50) {
                $score += 30;
                $details[] = 'clear challenge description';
            } elseif ($challengeLen >= 20) {
                $score += 20;
                $details[] = 'adequate challenge description';
            } else {
                $score += 10;
                $details[] = 'brief challenge description';
            }
        } else {
            $details[] = 'no challenge described';
        }

        // Solution quality (max 30)
        if ($solution !== '') {
            $solutionLen = mb_strlen($solution);
            if ($solutionLen >= 50) {
                $score += 30;
                $details[] = 'detailed recommended solution';
            } elseif ($solutionLen >= 20) {
                $score += 20;
                $details[] = 'adequate recommended solution';
            } else {
                $score += 10;
                $details[] = 'brief recommended solution';
            }
        } else {
            $details[] = 'no recommended solution';
        }

        // Evidence (max 20)
        if ($evidenceCount > 0) {
            $score += min($evidenceCount * 5, 20);
            $details[] = $evidenceCount.' evidence file(s) attached';
        } else {
            $details[] = 'no evidence attached';
        }

        // Implementation confirmation (max 20)
        if ($isImplemented) {
            $score += 20;
            $details[] = 'confirmed as implemented';
        } else {
            $details[] = 'not yet implemented';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'explanation' => 'Quality based on '.implode(', ', $details).'.',
            'meta' => ['evidence_count' => $evidenceCount, 'is_implemented' => $isImplemented],
        ];
    }

    private function scoreKaizenTimeliness(KaizenConcern $concern, bool $isImplemented = false): array
    {
        $targetDate = $concern->target_date;
        $completed = $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null;

        if ($targetDate === null) {
            return [
                'score' => null,
                'explanation' => 'No deadline set for this Kaizen concern.',
                'meta' => ['evaluated' => 0, 'on_time' => 0, 'late' => 0, 'overdue' => 0],
            ];
        }

        $target = Carbon::parse($targetDate);
        $now = Carbon::now();

        if ($completed !== null) {
            $completedCarbon = Carbon::parse($completed);
            if ($completedCarbon->lte($target)) {
                // Completed on or before deadline
                if ($completedCarbon->lt($target)) {
                    $score = 100;
                    $detail = 'completed before deadline';
                } else {
                    $score = 90;
                    $detail = 'completed on deadline';
                }
            } else {
                // Completed late
                $daysLate = (int) $target->diffInDays($completedCarbon);
                $score = max(0, 90 - ($daysLate * 10));
                $detail = 'completed '.$daysLate.' day(s) late';
            }
        } elseif ($target->lt($now->startOfDay())) {
            // Overdue and not completed
            return [
                'score' => 0,
                'explanation' => 'overdue and not completed',
                'meta' => ['evaluated' => 1, 'on_time' => 0, 'late' => 0, 'overdue' => 1],
            ];
        } else {
            // Not yet completed, not yet overdue — pending
            return [
                'score' => null,
                'explanation' => 'deadline not yet reached and task not completed',
                'meta' => ['evaluated' => 0, 'on_time' => 0, 'late' => 0, 'overdue' => 0],
            ];
        }

        return [
            'score' => $score,
            'explanation' => $detail,
            'meta' => ['evaluated' => 1, 'on_time' => $score >= 90 ? 1 : 0, 'late' => $score < 90 && $score > 0 ? 1 : 0, 'overdue' => 0],
        ];
    }

    private function scoreKaizenResponsiveness(KaizenConcern $concern, bool $isImplemented = false): array
    {
        $created = Carbon::parse($concern->created_at);

        // First observable action: assignment or implementation
        $firstAction = null;
        if ($concern->implementation_date) {
            $firstAction = Carbon::parse($concern->implementation_date);
        } elseif ($concern->assigned_staff_id) {
            // We don't have an assignment timestamp; use creation as proxy
            // In a real system, we'd track assignment timestamp separately
            $firstAction = $created;
        }

        if ($firstAction === null) {
            return [
                'score' => null,
                'explanation' => 'no observable action yet',
                'meta' => ['evaluated' => 0, 'prompt' => 0, 'delayed' => 0, 'unanswered' => 1, 'avg_hours' => null],
            ];
        }

        $hours = (float) $created->diffInHours($firstAction);

        if ($hours <= self::RESPONSE_PROMPT_HOURS) {
            $score = 100;
            $detail = 'responded within '.round($hours, 1).' hour(s)';
        } elseif ($hours <= 48) {
            $score = 70;
            $detail = 'responded within '.round($hours, 1).' hour(s)';
        } elseif ($hours <= 72) {
            $score = 50;
            $detail = 'responded within '.round($hours, 1).' hour(s)';
        } else {
            $score = 30;
            $detail = 'responded within '.round($hours, 1).' hour(s)';
        }

        return [
            'score' => $score,
            'explanation' => $detail,
            'meta' => ['evaluated' => 1, 'prompt' => $hours <= self::RESPONSE_PROMPT_HOURS ? 1 : 0, 'delayed' => $hours > self::RESPONSE_PROMPT_HOURS ? 1 : 0, 'unanswered' => 0, 'avg_hours' => round($hours, 1)],
        ];
    }

    private function scoreAdminConcernQuality(KaizenConcern $concern): array
    {
        $score = 0;
        $details = [];

        $challenge = trim((string) $concern->challenge);
        $solution = trim((string) $concern->recommended_solution);
        $notes = trim((string) $concern->notes);
        $evidenceCount = $concern->evidences_count ?? 0;

        // Challenge clarity (max 25)
        if ($challenge !== '') {
            $challengeLen = mb_strlen($challenge);
            if ($challengeLen >= 50) {
                $score += 25;
                $details[] = 'clear challenge description';
            } elseif ($challengeLen >= 20) {
                $score += 15;
                $details[] = 'adequate challenge description';
            } else {
                $score += 5;
                $details[] = 'brief challenge description';
            }
        }

        // Resolution quality (max 35)
        if ($solution !== '') {
            $solutionLen = mb_strlen($solution);
            if ($solutionLen >= 50) {
                $score += 35;
                $details[] = 'detailed recommended solution';
            } elseif ($solutionLen >= 20) {
                $score += 25;
                $details[] = 'adequate recommended solution';
            } else {
                $score += 15;
                $details[] = 'brief recommended solution';
            }
        }

        // Evidence (max 20)
        if ($evidenceCount > 0) {
            $score += min($evidenceCount * 5, 20);
            $details[] = $evidenceCount.' evidence file(s)';
        }

        // Completion (max 20)
        if ($concern->isResolved()) {
            $score += 20;
            $details[] = 'confirmed as resolved';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'explanation' => 'Quality based on '.implode(', ', $details).'.',
            'meta' => ['evidence_count' => $evidenceCount, 'is_resolved' => $concern->isResolved()],
        ];
    }

    private function scoreAdminConcernTimeliness(KaizenConcern $concern): array
    {
        $targetDate = $concern->target_date;
        $completed = $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null;
        $created = Carbon::parse($concern->created_at);

        if ($targetDate === null) {
            return [
                'score' => null,
                'explanation' => 'no deadline set',
                'meta' => ['evaluated' => 0],
            ];
        }

        $target = Carbon::parse($targetDate);

        if ($completed !== null) {
            $completedCarbon = Carbon::parse($completed);
            if ($completedCarbon->lte($target)) {
                $score = $completedCarbon->lt($target) ? 100 : 90;
                $detail = $completedCarbon->lt($target) ? 'completed before deadline' : 'completed on deadline';
            } else {
                $daysLate = (int) $target->diffInDays($completedCarbon);
                $score = max(0, 90 - ($daysLate * 10));
                $detail = 'completed '.$daysLate.' day(s) late';
            }
        } elseif ($target->lt($now = Carbon::now()->startOfDay())) {
            return [
                'score' => 0,
                'explanation' => 'overdue and not completed',
                'meta' => ['evaluated' => 1, 'on_time' => 0, 'late' => 0, 'overdue' => 1],
            ];
        } else {
            return [
                'score' => null,
                'explanation' => 'deadline not yet reached and task not completed',
                'meta' => ['evaluated' => 0],
            ];
        }

        return [
            'score' => $score,
            'explanation' => $detail,
            'meta' => ['evaluated' => 1, 'on_time' => $score >= 90 ? 1 : 0, 'late' => $score < 90 && $score > 0 ? 1 : 0, 'overdue' => 0],
        ];
    }

    private function scoreAdminConcernResponsiveness(KaizenConcern $concern): array
    {
        $created = Carbon::parse($concern->created_at);

        // First action: assignment timestamp (we don't track it separately, so use created_at as proxy)
        // In practice, assignment happens shortly after creation for admin concerns
        $firstAction = $concern->implementation_date ? Carbon::parse($concern->implementation_date) : $created;

        $hours = (float) $created->diffInHours($firstAction);

        if ($hours <= self::RESPONSE_PROMPT_HOURS) {
            $score = 100;
            $detail = 'acknowledged within '.round($hours, 1).' hour(s)';
        } elseif ($hours <= 48) {
            $score = 70;
            $detail = 'acknowledged within '.round($hours, 1).' hour(s)';
        } elseif ($hours <= 72) {
            $score = 50;
            $detail = 'acknowledged within '.round($hours, 1).' hour(s)';
        } else {
            $score = 30;
            $detail = 'acknowledged within '.round($hours, 1).' hour(s)';
        }

        return [
            'score' => $score,
            'explanation' => $detail,
            'meta' => ['evaluated' => 1, 'prompt' => $hours <= self::RESPONSE_PROMPT_HOURS ? 1 : 0, 'delayed' => $hours > self::RESPONSE_PROMPT_HOURS ? 1 : 0, 'unanswered' => 0, 'avg_hours' => round($hours, 1)],
        ];
    }

    /**
     * Combine criterion scores into one overall score using configured weights.
     * Criteria with null are excluded and remaining weights are renormalized.
     */
    private function combineScores(array $criteria, array $weights): array
    {
        $available = array_filter($criteria, fn ($v) => $v !== null);

        if ($available === []) {
            return ['overall_score' => null, 'weights_used' => []];
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;
        $weightsUsed = [];

        foreach ($available as $criterion => $score) {
            $weight = (float) ($weights[$criterion] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            $totalWeight += $weight;
            $weightedSum += (float) $score * $weight;
            $weightsUsed[$criterion] = $weight;
        }

        if ($totalWeight <= 0) {
            return ['overall_score' => null, 'weights_used' => []];
        }

        return [
            'overall_score' => round($weightedSum / $totalWeight, 1),
            'weights_used' => $weightsUsed,
        ];
    }

    private function gradeForScore(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 95 => 'Outstanding',
            $score >= 90 => 'Excellent',
            $score >= 85 => 'Very Satisfactory',
            $score >= 80 => 'Satisfactory',
            $score >= 75 => 'Needs Improvement',
            default => 'Unsatisfactory',
        };
    }

    /**
     * Award contribution points for an activity. Idempotent: checks if a
     * contribution record already exists for this (staff, activity, source)
     * combination before creating a new one.
     */
    private function awardContribution(
        int $staffId,
        string $activityType,
        string $sourceType,
        int $sourceId,
        int $points,
        string $reason
    ): ?StaffContributionRecord {
        // Check for existing awarded contribution for this activity
        $existing = StaffContributionRecord::where('staff_id', $staffId)
            ->where('activity_type', $activityType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', StaffContributionRecord::STATUS_AWARDED)
            ->first();

        if ($existing) {
            return $existing;
        }

        $period = $this->currentPeriod();

        $contribution = StaffContributionRecord::create([
            'staff_id' => $staffId,
            'activity_type' => $activityType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'evaluation_period_id' => $period?->id,
            'points' => $points,
            'status' => StaffContributionRecord::STATUS_AWARDED,
            'awarded_at' => now(),
        ]);

        StaffContributionAudit::create([
            'contribution_id' => $contribution->id,
            'action' => 'created',
            'previous_points' => null,
            'new_points' => $points,
            'adjustment_reason' => $reason,
            'performed_by' => auth()->id(),
        ]);

        return $contribution;
    }

    /**
     * Get or create the current active evaluation period.
     */
    public function currentPeriod(): ?StaffEvaluationPeriod
    {
        $period = StaffEvaluationPeriod::active()->containingDate(Carbon::now())->first();

        if ($period) {
            return $period;
        }

        // Create a default period for the current month
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return StaffEvaluationPeriod::create([
            'name' => $start->format('F Y'),
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => true,
        ]);
    }
}
