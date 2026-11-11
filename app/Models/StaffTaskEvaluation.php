<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffTaskEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'source_type',
        'source_id',
        'evaluation_period_id',
        'accomplishment_quality_score',
        'timeliness_score',
        'immediate_responsiveness_score',
        'client_feedback_score',
        'overall_score',
        'performance_grade',
        'evaluation_status',
        'submitted_at',
        'acknowledged_at',
        'deadline_at',
        'completed_at',
        'finalized_at',
        'evaluated_by',
        'adjustment_reason',
        'score_sheet_included',
        'score_sheet_added_at',
        'score_sheet_weighted_points',
        'score_sheet_criterion',
        'score_sheet_reversed_at',
        'score_sheet_reversal_reason',
        'assessment_source',
        'original_ai_score',
        'assessment_explanation',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'deadline_at' => 'date',
            'completed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'assessed_at' => 'datetime',
            'score_sheet_included' => 'boolean',
            'score_sheet_added_at' => 'datetime',
            'score_sheet_weighted_points' => 'decimal:2',
            'score_sheet_reversed_at' => 'datetime',
            'accomplishment_quality_score' => 'decimal:2',
            'timeliness_score' => 'decimal:2',
            'immediate_responsiveness_score' => 'decimal:2',
            'client_feedback_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'original_ai_score' => 'decimal:2',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONAL = 'provisional';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_ADJUSTED = 'adjusted';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROVISIONAL => 'Provisional',
        self::STATUS_AWAITING_REVIEW => 'Awaiting Review',
        self::STATUS_FINALIZED => 'Finalized',
        self::STATUS_ADJUSTED => 'Adjusted',
        self::STATUS_REVERSED => 'Reversed',
    ];

    public const SOURCE_KAIZEN_CONCERN = 'kaizen_concern';
    public const SOURCE_ADMIN_CONCERN = 'admin_concern';

    public const SOURCE_TYPES = [
        self::SOURCE_KAIZEN_CONCERN => 'Kaizen Concern',
        self::SOURCE_ADMIN_CONCERN => 'Admin Concern',
    ];

    public const ASSESSMENT_SOURCE_AI = 'ai';
    public const ASSESSMENT_SOURCE_RULE_BASED = 'rule_based';
    public const ASSESSMENT_SOURCE_REVIEWER = 'reviewer';

    public const ASSESSMENT_SOURCES = [
        self::ASSESSMENT_SOURCE_AI => 'AI Assessment',
        self::ASSESSMENT_SOURCE_RULE_BASED => 'Rule-Based Assessment',
        self::ASSESSMENT_SOURCE_REVIEWER => 'Reviewer Assessment',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(StaffEvaluationPeriod::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(StaffEvaluationAudit::class, 'evaluation_id');
    }

    public function sourceConcern(): BelongsTo
    {
        if ($this->source_type === self::SOURCE_KAIZEN_CONCERN) {
            return $this->belongsTo(KaizenConcern::class, 'source_id');
        }

        return null;
    }

    public function sourceAdminConcern(): BelongsTo
    {
        if ($this->source_type === self::SOURCE_ADMIN_CONCERN) {
            return $this->belongsTo(KaizenConcern::class, 'source_id');
        }

        return null;
    }

    public function sourceRecord(): BelongsTo
    {
        // Both source types live in the shared kaizen_concerns table, so this
        // can always be an eager-loadable relation. The concrete label is
        // derived from source_type.
        return $this->belongsTo(KaizenConcern::class, 'source_id');
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForSource(Builder $query, string $sourceType, int $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }

    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('evaluation_status', self::STATUS_FINALIZED);
    }

    public function scopeNotReversed(Builder $query): Builder
    {
        return $query->where('evaluation_status', '!=', self::STATUS_REVERSED);
    }

    public function scopeIncludedInScoreSheet(Builder $query): Builder
    {
        return $query->where('score_sheet_included', true)
            ->whereNotNull('score_sheet_added_at')
            ->where('evaluation_status', '!=', self::STATUS_REVERSED);
    }

    /**
     * The single source of truth for the score-sheet integration badge.
     *
     * "ADDED TO SCORE SHEET" is only reported when the backend has confirmed
     * the evaluation is linked to a staff member and an evaluation period and
     * the link timestamp exists. Task completion, submission, or approval on
     * their own can never produce that state.
     *
     * @return array{key: string, label: string, class: string}
     */
    public function integrationBadge(): array
    {
        if ($this->evaluation_status === self::STATUS_REVERSED) {
            return [
                'key' => 'reversed',
                'label' => 'REVERSED / EXCLUDED',
                'class' => 'badge-score-reversed',
            ];
        }

        if (in_array($this->evaluation_status, [self::STATUS_PENDING, self::STATUS_AWAITING_REVIEW], true)) {
            return [
                'key' => 'admin_review',
                'label' => 'FOR ADMIN REVIEW',
                'class' => 'badge-score-review',
            ];
        }

        if ($this->score_sheet_included
            && $this->score_sheet_added_at !== null
            && $this->staff_id !== null
            && $this->evaluation_period_id !== null
            && $this->overall_score !== null) {
            if ($this->evaluation_status === self::STATUS_ADJUSTED) {
                return [
                    'key' => 'revised',
                    'label' => 'REVISED / SCORE UPDATED',
                    'class' => 'badge-score-revised',
                ];
            }

            return [
                'key' => 'added',
                'label' => 'ADDED TO SCORE SHEET',
                'class' => 'badge-score-added',
            ];
        }

        // Evaluation is complete (has a score) but not yet synced to Score Sheet
        if ($this->overall_score !== null && !$this->score_sheet_included) {
            return [
                'key' => 'pending_integration',
                'label' => 'PENDING INTEGRATION',
                'class' => 'badge-score-pending',
            ];
        }

        if ($this->overall_score === null) {
            return [
                'key' => 'pending',
                'label' => 'EVALUATION PENDING',
                'class' => 'badge-score-pending',
            ];
        }

        return [
            'key' => 'not_added',
            'label' => 'NOT YET ADDED',
            'class' => 'badge-score-not-added',
        ];
    }

    public function integrationBadgeLabel(): string
    {
        return $this->integrationBadge()['label'];
    }

    public function integrationBadgeClass(): string
    {
        return $this->integrationBadge()['class'];
    }

    public function isIncludedInScoreSheet(): bool
    {
        return $this->score_sheet_included
            && $this->score_sheet_added_at !== null
            && $this->evaluation_status !== self::STATUS_REVERSED;
    }

    public function criterionLabel(?string $criterion = null): string
    {
        $criterion ??= $this->score_sheet_criterion ?: 'quality';

        return match ($criterion) {
            'quality' => 'Accomplishment Quality',
            'timeliness' => 'Timeliness',
            'responsiveness' => 'Immediate Responsiveness',
            'client_feedback' => 'Client Feedback',
            default => ucfirst(str_replace('_', ' ', $criterion)),
        };
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? ucfirst(str_replace('_', ' ', (string) $this->source_type));
    }

    public function taskTitle(): string
    {
        $concern = $this->sourceRecord;

        if ($concern?->challenge) {
            return $concern->challenge;
        }

        return $this->sourceLabel().' #'.$this->source_id;
    }

    public function taskReferenceUrl(): ?string
    {
        if ($this->source_id === null) {
            return null;
        }

        return route('admin.kaizen-concerns.show', $this->source_id);
    }

    public function evaluationPeriodLabel(): string
    {
        return $this->evaluationPeriod?->name
            ?? ($this->evaluationPeriod?->dateRangeLabel() ?? 'No period');
    }

    public function overallScoreLetter(): ?string
    {
        if ($this->overall_score === null) {
            return null;
        }

        return match (true) {
            $this->overall_score >= 95 => 'Outstanding',
            $this->overall_score >= 90 => 'Excellent',
            $this->overall_score >= 85 => 'Very Satisfactory',
            $this->overall_score >= 80 => 'Satisfactory',
            $this->overall_score >= 75 => 'Needs Improvement',
            default => 'Unsatisfactory',
        };
    }

    /**
     * Compute a performance letter grade from a numeric score. Mirrors
     * StaffScoringService::gradeForScore() so controllers can compute grades
     * without pulling in the full scoring service.
     */
    public static function gradeForScore(?float $score): ?string
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

    public function criterionScore(string $criterion): ?int
    {
        return match ($criterion) {
            'quality' => $this->accomplishment_quality_score,
            'timeliness' => $this->timeliness_score,
            'responsiveness' => $this->immediate_responsiveness_score,
            'client_feedback' => $this->client_feedback_score,
            default => null,
        };
    }

    /**
     * Whether this evaluation has an AI-generated preliminary assessment.
     */
    public function isAiAssessed(): bool
    {
        return $this->assessment_source === self::ASSESSMENT_SOURCE_AI;
    }

    /**
     * Whether this evaluation is a pending AI/rule-based assessment awaiting
     * admin review.
     */
    public function isProvisionalAssessment(): bool
    {
        return $this->evaluation_status === self::STATUS_PROVISIONAL;
    }

    /**
     * Whether this evaluation is awaiting admin review (provisional or
     * awaiting_review) and has not yet been finalized.
     */
    public function isAwaitingAdminReview(): bool
    {
        return in_array($this->evaluation_status, [
            self::STATUS_PROVISIONAL,
            self::STATUS_AWAITING_REVIEW,
        ], true);
    }

    /**
     * Badge info for the AI assessment state shown on the concern detail page.
     *
     * @return array{key: string, label: string, class: string, action?: string}
     */
    public function aiAssessmentBadge(): array
    {
        if ($this->evaluation_status === self::STATUS_REVERSED) {
            return [
                'key' => 'reversed',
                'label' => 'REVERSED / EXCLUDED',
                'class' => 'badge-score-reversed',
            ];
        }

        // Evaluation has a score but hasn't been synced to Score Sheet yet
        if ($this->overall_score !== null && !$this->isIncludedInScoreSheet()) {
            return [
                'key' => 'pending_integration',
                'label' => 'PENDING INTEGRATION',
                'class' => 'badge-score-pending',
            ];
        }

        if ($this->evaluation_status === self::STATUS_FINALIZED) {
            return [
                'key' => 'finalized',
                'label' => 'FINALIZED',
                'class' => 'badge-score-finalized',
            ];
        }

        if ($this->evaluation_status === self::STATUS_ADJUSTED) {
            return [
                'key' => 'revised',
                'label' => 'REVISED / SCORE UPDATED',
                'class' => 'badge-score-revised',
            ];
        }

        if ($this->isAiAssessed() && $this->isProvisionalAssessment()) {
            return [
                'key' => 'ai_generated',
                'label' => 'AI ASSESSMENT GENERATED',
                'class' => 'badge-score-ai-generated',
                'action' => 'review',
            ];
        }

        if ($this->isAwaitingAdminReview()) {
            return [
                'key' => 'admin_review',
                'label' => 'FOR ADMIN REVIEW',
                'class' => 'badge-score-review',
                'action' => 'assess',
            ];
        }

        if ($this->overall_score === null) {
            return [
                'key' => 'pending',
                'label' => 'EVALUATION PENDING',
                'class' => 'badge-score-pending',
                'action' => 'assess',
            ];
        }

        return [
            'key' => 'not_added',
            'label' => 'NOT YET ADDED',
            'class' => 'badge-score-not-added',
        ];
    }

    /**
     * Weighted contribution using the quality criterion weight (50%), matching
     * the official Score Sheet rule for Kaizen/Admin Concern sources.
     */
    public function aiWeightedContribution(): ?float
    {
        if ($this->overall_score === null) {
            return null;
        }

        return round((float) $this->overall_score * 0.50, 2);
    }
}
