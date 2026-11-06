<?php

namespace App\Services;

use App\Models\DailyJournal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AchievementRankingService
{
    /**
     * Rank employees by average assessed quality, not by the number of entries.
     * Duplicate accomplishment text inside the period is counted once so
     * copy-pasted submissions cannot inflate a position.
     *
     * Each ranking row now also includes the four criterion scores (quality,
     * timeliness, responsiveness, client feedback) and an overall weighted
     * score. Criteria with insufficient data are reported as null and never
     * force a zero overall score.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankings(Carbon $start, Carbon $end, ?User $viewer = null): Collection
    {
        // The legacy daily-journal code refers to users.supervisor_id, but the
        // column is not present in every deployment. Only apply team scoping
        // when the relation actually exists, so ranking never breaks.
        $hasSupervisorColumn = Schema::hasColumn('users', 'supervisor_id');
        $userColumns = $hasSupervisorColumn ? 'id,name,role,supervisor_id' : 'id,name,role';

        $journals = DailyJournal::query()
            ->whereDate('required_date', '>=', $start->toDateString())
            ->whereDate('required_date', '<=', $end->toDateString())
            ->whereIn('status', [DailyJournal::STATUS_SUBMITTED, DailyJournal::STATUS_LATE])
            ->whereNotNull('achievement_score')
            ->with(['user:'.$userColumns])
            ->whereHas('user', function ($query) use ($viewer, $hasSupervisorColumn) {
                $query->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
                    ->whereNull('deleted_at');

                if ($hasSupervisorColumn && $viewer && $viewer->isSupervisor()) {
                    $query->where(function ($sub) use ($viewer) {
                        $sub->where('id', $viewer->id)
                            ->orWhere('supervisor_id', $viewer->id);
                    });
                }
            })
            ->orderByDesc('achievement_score')
            ->get();

        $criteriaService = app(AchievementCriteriaService::class);
        $weights = $this->scoringWeights();

        $rankings = $journals
            ->groupBy('user_id')
            ->map(function (Collection $entries) use ($criteriaService, $weights, $start, $end): ?array {
                $unique = $this->deduplicate($entries);

                if ($unique->isEmpty()) {
                    return null;
                }

                $user = $unique->first()->user;

                $average = round((float) $unique->avg('achievement_score'), 1);
                $verified = $unique->where('assessment_status', 'verified')->count();
                $submitted = $unique->count();
                $tags = $unique
                    ->flatMap(fn (DailyJournal $journal): array => $journal->recognition_tags ?? [])
                    ->filter()
                    ->unique()
                    ->values();

                if ($submitted >= 3 && ! $tags->contains('Consistency')) {
                    $tags->push('Consistency');
                }

                $criteria = $criteriaService->analyze($user, $start, $end);
                $combined = $criteriaService->combine($criteria, $weights);

                // Quality score is the existing daily-journal average. Overall
                // score combines all available criteria; if only quality has
                // data it equals the quality score.
                $qualityScore = $average;
                $overallScore = $combined['overall_score'] ?? $qualityScore;

                return [
                    'user' => $user,
                    'score' => $qualityScore,
                    'overall_score' => $overallScore,
                    'level' => AchievementAnalysisService::levelForScore((int) round($overallScore)),
                    'quality_score' => $qualityScore,
                    'timeliness_score' => $criteria['timeliness'],
                    'responsiveness_score' => $criteria['responsiveness'],
                    'client_feedback_score' => $criteria['client_feedback'],
                    'submitted_count' => $submitted,
                    'verified_count' => $verified,
                    'provisional_count' => $submitted - $verified,
                    'verification_status' => $verified === 0
                        ? 'provisional'
                        : ($verified === $submitted ? 'verified' : 'partial'),
                    'tags' => $tags->take(5)->values()->all(),
                    'last_assessed_at' => $unique->max('assessed_at'),
                    'criteria_explanation' => $this->criteriaExplanation($criteria),
                    'on_time_completion_rate' => $criteria['timeliness_meta']['on_time_rate'] ?? null,
                    'avg_first_response_hours' => $criteria['responsiveness_meta']['avg_response_hours'] ?? null,
                    'positive_feedback_count' => $criteria['client_feedback_meta']['positive'] ?? 0,
                    'negative_feedback_count' => $criteria['client_feedback_meta']['negative'] ?? 0,
                ];
            })
            ->filter()
            ->values();

        $sorted = $rankings->sortBy([
            ['overall_score', 'desc'],
            ['score', 'desc'],
            ['verified_count', 'desc'],
            ['submitted_count', 'desc'],
            ['user.name', 'asc'],
        ])->values();

        return $sorted->map(function (array $row, int $index): array {
            $row['rank'] = $index + 1;
            $row['badge'] = match ($index) {
                0 => 'gold',
                1 => 'silver',
                2 => 'bronze',
                default => null,
            };

            return $row;
        });
    }

    /**
     * Return scoring weights from config, with sensible defaults.
     *
     * @return array<string, float>
     */
    private function scoringWeights(): array
    {
        $configured = (array) config('achievements.scoring_weights', []);

        return [
            AchievementCriteriaService::CRITERION_QUALITY => (float) ($configured['quality'] ?? 50),
            AchievementCriteriaService::CRITERION_TIMELINESS => (float) ($configured['timeliness'] ?? 20),
            AchievementCriteriaService::CRITERION_RESPONSIVENESS => (float) ($configured['responsiveness'] ?? 20),
            AchievementCriteriaService::CRITERION_CLIENT_FEEDBACK => (float) ($configured['client_feedback'] ?? 10),
        ];
    }

    /**
     * Build a plain-language explanation of why a staff member received their
     * criterion scores.
     */
    private function criteriaExplanation(array $criteria): string
    {
        $parts = [];

        if ($criteria['quality'] !== null) {
            $parts[] = 'Quality: '.$criteria['quality_explanation'];
        } else {
            $parts[] = 'Quality: not yet evaluated (no assessed daily accomplishments).';
        }

        if ($criteria['timeliness'] !== null) {
            $parts[] = 'Timeliness: '.$criteria['timeliness_explanation'];
        } else {
            $parts[] = 'Timeliness: not yet evaluated (no assigned tasks with deadlines).';
        }

        if ($criteria['responsiveness'] !== null) {
            $parts[] = 'Responsiveness: '.$criteria['responsiveness_explanation'];
        } else {
            $parts[] = 'Responsiveness: not yet evaluated (no assigned tasks or concerns).';
        }

        if ($criteria['client_feedback'] !== null) {
            $parts[] = 'Client feedback: '.$criteria['client_feedback_explanation'];
        } else {
            $parts[] = 'Client feedback: not yet evaluated (no linked client feedback).';
        }

        return implode(' '.PHP_EOL, $parts);
    }

    /**
     * Keep the highest-scored entry per unique accomplishment text. Empty text
     * remains keyed by date so old records without a description are not
     * silently merged together.
     *
     * @param  Collection<int, DailyJournal>  $entries
     * @return Collection<int, DailyJournal>
     */
    private function deduplicate(Collection $entries): Collection
    {
        $seen = [];

        return $entries
            ->sortByDesc('achievement_score')
            ->filter(function (DailyJournal $journal) use (&$seen): bool {
                $text = $this->normalize((string) $journal->achievements);
                $key = $text === ''
                    ? 'date:'.$journal->getRawOriginal('required_date')
                    : hash('sha256', $text);

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->values();
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
