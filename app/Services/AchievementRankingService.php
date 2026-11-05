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

        $rankings = $journals
            ->groupBy('user_id')
            ->map(function (Collection $entries): ?array {
                $unique = $this->deduplicate($entries);

                if ($unique->isEmpty()) {
                    return null;
                }

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

                return [
                    'user' => $unique->first()->user,
                    'score' => $average,
                    'level' => AchievementAnalysisService::levelForScore((int) round($average)),
                    'submitted_count' => $submitted,
                    'verified_count' => $verified,
                    'provisional_count' => $submitted - $verified,
                    'verification_status' => $verified === 0
                        ? 'provisional'
                        : ($verified === $submitted ? 'verified' : 'partial'),
                    'tags' => $tags->take(5)->values()->all(),
                    'last_assessed_at' => $unique->max('assessed_at'),
                ];
            })
            ->filter()
            ->values();

        $sorted = $rankings->sortBy([
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
