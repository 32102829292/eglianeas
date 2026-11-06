<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DailyJournal;
use App\Models\DailyJournalAssessmentRevision;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AchievementAnalysisService
{
    public function __construct(
        private RuleBasedAchievementAnalyzer $rules,
        private AiAchievementAnalyzer $ai,
    ) {}

    public static function levelForScore(int $score): string
    {
        $score = max(0, min(100, $score));
        $levels = collect(config('achievements.levels', []))->sortByDesc('min');

        foreach ($levels as $level) {
            if ($score >= (int) $level['min']) {
                return (string) $level['label'];
            }
        }

        return 'Needs Further Development';
    }

    public function analyze(DailyJournal $journal, bool $force = false): DailyJournal
    {
        // Automatic analysis must never silently replace a reviewer's manual
        // decision. Only an explicit reassessment request may do that.
        if (! $force && $journal->isManuallyAssessed()) {
            return $journal;
        }

        $hash = $this->contentHash($journal);

        if (! $force && $journal->hasAssessment() && hash_equals((string) $journal->assessment_hash, $hash)) {
            return $journal;
        }

        $aiConfigured = $this->ai->isConfigured();
        $result = null;

        try {
            $result = $this->ai->analyze($journal);
        } catch (Throwable $exception) {
            Log::warning('Achievement AI assessment failed; using rule-based fallback.', [
                'daily_journal_id' => $journal->id,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($result === null) {
            $result = $this->rules->analyze($journal);

            if ($aiConfigured) {
                $result['explanation'] = 'Rule-based assessment (AI unavailable or invalid response): '
                    .$result['explanation'];
            }
        }

        $score = max(0, min(100, (int) ($result['score'] ?? 0)));

        // Preserve the original AI/rule-based score on first automatic
        // assessment. Never overwrite it on later manual reassessment so the
        // score sheet can always show "Original AI score" vs "Final score".
        $originalAiScore = $journal->original_ai_score ?? $score;

        $journal->forceFill([
            'achievement_score' => $score,
            'original_ai_score' => $originalAiScore,
            'achievement_category' => $result['category'],
            'achievement_level' => self::levelForScore($score),
            'achievement_explanation' => $result['explanation'],
            'recognition_tags' => array_values($result['tags'] ?? []),
            'assessment_source' => $result['source'] ?? DailyJournal::ASSESSMENT_SOURCE_RULE_BASED,
            'assessment_status' => DailyJournal::ASSESSMENT_STATUS_PROVISIONAL,
            'assessed_at' => now(),
            'assessment_hash' => $hash,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ])->save();

        return $journal->refresh();
    }

    public function reassess(DailyJournal $journal, User $reviewer, ?string $reason = null): DailyJournal
    {
        $previous = $this->snapshot($journal);

        $journal = $this->analyze($journal, true);

        $this->recordRevision(
            $journal,
            $reviewer,
            'reassessed',
            $previous,
            $this->snapshot($journal),
            $reason ?: 'Reviewer requested reassessment.'
        );

        ActivityLog::record(
            $reviewer,
            'daily_journal.assessment_reassessed',
            "Reassessed daily journal #{$journal->id} for {$journal->user?->name}."
        );

        return $journal;
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function applyReview(
        DailyJournal $journal,
        User $reviewer,
        int $score,
        string $category,
        string $explanation,
        array $tags,
        ?string $reason = null,
        ?string $level = null
    ): DailyJournal {
        $previous = $this->snapshot($journal);

        $score = max(0, min(100, $score));
        $tags = array_slice(array_values(array_unique(array_filter($tags))), 0, 8);

        $journal->forceFill([
            'achievement_score' => $score,
            'achievement_category' => $category,
            'achievement_level' => $level ?: self::levelForScore($score),
            'achievement_explanation' => $explanation,
            'recognition_tags' => $tags,
            'assessment_source' => DailyJournal::ASSESSMENT_SOURCE_REVIEWER,
            'assessment_status' => DailyJournal::ASSESSMENT_STATUS_VERIFIED,
            'assessed_at' => now(),
            'assessment_hash' => $this->contentHash($journal),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ])->save();

        $journal = $journal->refresh();

        $this->recordRevision(
            $journal,
            $reviewer,
            'corrected',
            $previous,
            $this->snapshot($journal),
            $reason
        );

        ActivityLog::record(
            $reviewer,
            'daily_journal.assessment_corrected',
            "Corrected achievement assessment for daily journal #{$journal->id} ({$journal->user?->name})."
        );

        return $journal;
    }

    /**
     * Admin-only manual reassessment with per-criterion adjustments.
     *
     * Preserves the original AI score and records each changed criterion with
     * its reason. The final score is computed from the configured weights
     * over the supplied criterion scores.
     *
     * @param  array<string, array{score: float|int, max: float|int, reason: string}>  $criteria
     * @param  array<string, float>  $weights
     */
    public function manualReassess(
        DailyJournal $journal,
        User $reviewer,
        array $criteria,
        array $weights,
        string $reason
    ): DailyJournal {
        $previous = $this->snapshot($journal);

        // Validate that every supplied criterion score is within its max.
        $normalizedCriteria = [];
        foreach ($criteria as $criterion => $data) {
            $score = max(0, min((float) $data['max'], (float) $data['score']));
            $normalizedCriteria[$criterion] = [
                'score' => $score,
                'max' => (float) $data['max'],
                'reason' => trim((string) ($data['reason'] ?? '')),
            ];
        }

        // Compute the final score from available criteria using configured
        // weights. Criteria with null/missing data are excluded.
        $criterionValues = [];
        foreach ($normalizedCriteria as $criterion => $data) {
            $criterionValues[$criterion] = $data['score'];
        }

        $combined = (new AchievementCriteriaService)->combine($criterionValues, $weights);
        $finalScore = $combined['overall_score'] ?? null;

        if ($finalScore === null) {
            $finalScore = $journal->achievement_score ?? 0;
        }

        $finalScore = (int) round(max(0, min(100, $finalScore)));

        $levels = collect(config('achievements.levels', []))->pluck('label')->values()->all();
        $category = config('achievements.categories', [])[0] ?? 'Productivity';

        $journal->forceFill([
            'achievement_score' => $finalScore,
            'achievement_category' => $category,
            'achievement_level' => self::levelForScore($finalScore),
            'achievement_explanation' => 'Manual reassessment by '.($reviewer->name ?? 'Admin').'. '.trim($reason),
            'recognition_tags' => [],
            'assessment_source' => DailyJournal::ASSESSMENT_SOURCE_REVIEWER,
            'assessment_status' => DailyJournal::ASSESSMENT_STATUS_VERIFIED,
            'assessed_at' => now(),
            'assessment_hash' => $this->contentHash($journal),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
            'criterion_scores' => $normalizedCriteria,
            // Preserve the original AI score on first manual reassessment.
            // If the journal was never automatically assessed, original_ai_score
            // stays null and the final score stands alone.
            'original_ai_score' => $journal->original_ai_score ?? $journal->achievement_score,
        ])->save();

        $journal = $journal->refresh();

        $this->recordRevision(
            $journal,
            $reviewer,
            'manually_reassessed',
            $previous,
            $this->snapshot($journal),
            $reason
        );

        ActivityLog::record(
            $reviewer,
            'daily_journal.assessment_manually_reassessed',
            "Manually reassessed daily journal #{$journal->id} for {$journal->user?->name}."
        );

        return $journal;
    }

    public function contentHash(DailyJournal $journal): string
    {
        return hash('sha256', implode("\n", [
            (string) $journal->achievements,
            (string) $journal->problems_encountered,
            (string) $journal->suggested_solutions,
            (string) $journal->evidenceCount(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DailyJournal $journal): array
    {
        return [
            'achievement_score' => $journal->achievement_score,
            'achievement_category' => $journal->achievement_category,
            'achievement_level' => $journal->achievement_level,
            'achievement_explanation' => $journal->achievement_explanation,
            'recognition_tags' => $journal->recognition_tags ?? [],
            'assessment_source' => $journal->assessment_source,
            'assessment_status' => $journal->assessment_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $new
     */
    private function recordRevision(
        DailyJournal $journal,
        User $reviewer,
        string $action,
        array $previous,
        array $new,
        ?string $reason
    ): void {
        DailyJournalAssessmentRevision::create([
            'daily_journal_id' => $journal->id,
            'reviewer_id' => $reviewer->id,
            'action' => $action,
            'previous_score' => $previous['achievement_score'],
            'previous_category' => $previous['achievement_category'],
            'previous_level' => $previous['achievement_level'],
            'previous_explanation' => $previous['achievement_explanation'],
            'previous_tags' => $previous['recognition_tags'],
            'previous_source' => $previous['assessment_source'],
            'previous_status' => $previous['assessment_status'],
            'new_score' => $new['achievement_score'],
            'new_category' => $new['achievement_category'],
            'new_level' => $new['achievement_level'],
            'new_explanation' => $new['achievement_explanation'],
            'new_tags' => $new['recognition_tags'],
            'new_source' => $new['assessment_source'],
            'new_status' => $new['assessment_status'],
            'reason' => $reason,
        ]);
    }
}
