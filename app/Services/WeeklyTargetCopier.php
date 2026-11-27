<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WeeklyTargetCopier
{
    public const COPIED = 'copied';
    public const ALREADY_HAS_TARGETS = 'already_has_targets';
    public const NO_SOURCE = 'no_source';

    /**
     * Copies the most recent earlier week's assignments into $weekStart.
     *
     * Only runs for a week that has no targets yet, so it can never overwrite
     * or duplicate existing work. Each target keeps its client, task type and
     * staff member, its date moves forward by the same number of days as the
     * week, and it starts fresh as Pending (status, notes, evidence and
     * payment details are not carried over). One transaction.
     *
     * @return array{status: string, count: int, source: ?Carbon}
     */
    public function copyInto(Carbon $weekStart, ?User $actor = null): array
    {
        $weekStart = $weekStart->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $weekKey = $weekStart->format('Y-m-d');

        $plan = WeeklyBookkeeping::query()->whereDate('week_start', $weekKey)->first();

        if ($plan && $plan->targets()->exists()) {
            return ['status' => self::ALREADY_HAS_TARGETS, 'count' => 0, 'source' => null];
        }

        $previousPlan = WeeklyBookkeeping::query()
            ->whereDate('week_start', '<', $weekKey)
            ->whereHas('targets')
            ->orderByDesc('week_start')
            ->first();

        if (! $previousPlan) {
            return ['status' => self::NO_SOURCE, 'count' => 0, 'source' => null];
        }

        $sourceStart = Carbon::parse($previousPlan->week_start)->startOfWeek();
        $dayShift = (int) $sourceStart->diffInDays($weekStart, false);

        // Scheduled runs have no signed-in user, so fall back to the previous
        // plan's owner, then to any admin.
        $actor ??= User::query()->find($previousPlan->staff_id)
            ?? User::query()->where('role', User::ROLE_ADMIN)->first();

        $count = DB::transaction(function () use ($plan, $previousPlan, $weekStart, $weekEnd, $dayShift, $actor, $sourceStart) {
            if ($plan === null) {
                $plan = WeeklyBookkeeping::query()->create([
                    'staff_id' => $previousPlan->staff_id,
                    'week_start' => $weekStart->format('Y-m-d'),
                    'week_end' => $weekEnd->format('Y-m-d'),
                    'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
                ]);
            }

            $count = 0;

            foreach ($previousPlan->targets()->get() as $source) {
                $date = $source->target_date
                    ? Carbon::parse($source->target_date)->addDays($dayShift)->startOfDay()
                    : null;

                // Keep the date inside the new week.
                if ($date && ($date->lt($weekStart->copy()->startOfDay()) || $date->gt($weekEnd->copy()->startOfDay()))) {
                    $date = $weekStart->copy()->startOfDay();
                }

                $plan->targets()->create([
                    'client_id' => $source->client_id,
                    'task_type' => $source->task_type,
                    'target_date' => $date?->format('Y-m-d'),
                    'assigned_staff_id' => $source->assigned_staff_id,
                    'assigned_staff_name' => $source->assigned_staff_name,
                    'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
                ]);

                $count++;
            }

            $plan->syncOverallStatus();

            if ($actor) {
                ActivityLog::record(
                    $actor,
                    'weekly_bookkeeping.targets_saved',
                    sprintf(
                        'Weekly target for %s – %s copied automatically from the week of %s (%d task%s).',
                        $weekStart->format('M j, Y'),
                        $weekEnd->format('M j, Y'),
                        $sourceStart->format('M j, Y'),
                        $count,
                        $count === 1 ? '' : 's'
                    ),
                    instance: null,
                    bookkeeping: $plan
                );
            }

            return $count;
        });

        return ['status' => self::COPIED, 'count' => $count, 'source' => $sourceStart];
    }
}