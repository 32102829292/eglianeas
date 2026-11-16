<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkPlanLegend;
use App\Models\WorkPlanTask;
use App\Models\WorkPlanTaskProblem;
use App\Models\WorkPlanWeeklyEvaluation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkPlanScoringService
{
    public const PLANNED_WEIGHT = 0.9;

    public const PROBLEM_WEIGHT = 0.1;

    public const PLANNED_POINTS = 90;

    public const PROBLEM_POINTS = 10;

    public const STATE_NO_PLANNED_TASKS = 'no_planned_tasks';

    public const STATE_PENDING_EVALUATION = 'pending_evaluation';

    public const STATE_FINAL = 'final';

    public static function normalizeWeekStart(Carbon|string $weekStart): Carbon
    {
        return Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
    }

    public static function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute the weekly score for one employee.
     *
     * Planned component (90 points): completed eligible planned tasks divided
     * by eligible planned tasks, times 90. Non-working (S) days and cancelled
     * tasks are excluded from the denominator. A week with no eligible planned
     * tasks has no planned score at all.
     *
     * Problem-solving component (10 points): awarded only from an
     * Admin/Supervisor weekly evaluation using 10, 7, 5, or 0. Until that
     * evaluation exists the planned result is provisional (out of 90) and no
     * final out-of-100 score is shown.
     */
    public static function forUserWeek(int $userId, Carbon|string $weekStart): array
    {
        $start = self::normalizeWeekStart($weekStart);
        $end = $start->copy()->addDays(4);

        $tasks = self::queryWeekTasks($userId, $start, $end);

        $evaluation = WorkPlanWeeklyEvaluation::forUserWeek($userId, $start);

        return self::summarize($userId, $start, $end, $tasks, $evaluation);
    }

    /**
     * Compute weekly scores for every active staff/supervisor user.
     */
    public static function forAllUsers(Carbon|string $weekStart): Collection
    {
        $start = self::normalizeWeekStart($weekStart);
        $end = $start->copy()->addDays(4);

        $users = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $tasks = self::queryWeekTasks($users->pluck('id')->all(), $start, $end)
            ->groupBy('user_id');

        $evaluations = WorkPlanWeeklyEvaluation::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('week_start', $start->toDateString())
            ->get()
            ->keyBy('user_id');

        return $users->map(fn (User $user) => self::summarize(
            $user->id,
            $start,
            $end,
            $tasks->get($user->id) ?? collect(),
            $evaluations->get($user->id)
        ));
    }

    /**
     * Fetch tasks whose effective date falls inside the week, so carried-over
     * tasks keep counting towards their original target week.
     *
     * @param  int|array<int, int>  $userIds
     */
    protected static function queryWeekTasks(int|array $userIds, Carbon $start, Carbon $end): Collection
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return WorkPlanTask::query()
            ->whereIn('user_id', (array) $userIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('work_date', [$startDate, $endDate])
                    ->orWhereBetween('original_work_date', [$startDate, $endDate]);
            })
            ->with(['legend', 'problems'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->filter(function (WorkPlanTask $task) use ($start, $end) {
                $effective = $task->effectiveWorkDate();

                return $effective->gte($start) && $effective->lte($end);
            })
            ->values();
    }

    protected static function summarize(
        int $userId,
        Carbon $start,
        Carbon $end,
        Collection $tasks,
        ?WorkPlanWeeklyEvaluation $evaluation = null
    ): array {
        $planned = $tasks
            ->filter(fn (WorkPlanTask $task) => ! $task->isAdditional())
            ->filter(fn (WorkPlanTask $task) => ! $task->isNonWorking())
            ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
            ->values();

        $additional = $tasks->filter(fn (WorkPlanTask $task) => $task->isAdditional());

        $completed = $planned->where('status', WorkPlanTask::STATUS_COMPLETED);
        $cancelled = $tasks->where('status', WorkPlanTask::STATUS_CANCELLED);
        $incomplete = $planned->filter(fn (WorkPlanTask $task) => $task->isUnfinished());

        $eligibleCount = $planned->count();
        $completedCount = $completed->count();

        $hasEligible = $eligibleCount > 0;
        $completionRate = $hasEligible ? ($completedCount / $eligibleCount) * 100 : null;
        $plannedScore = $hasEligible ? ($completedCount / $eligibleCount) * self::PLANNED_POINTS : null;

        // Problem-solving is evaluation-only. No evaluation means zero points,
        // and a resolved problem never awards points on its own.
        $problemScore = $evaluation?->problem_solving_score ?? 0;
        $problemEvaluated = $evaluation !== null && $evaluation->problem_solving_score !== null;
        $problemRate = $problemScore * 10;

        if (! $hasEligible) {
            $scoreState = self::STATE_NO_PLANNED_TASKS;
            $overallScore = null;
            $provisionalScore = null;
        } elseif (! $problemEvaluated) {
            $scoreState = self::STATE_PENDING_EVALUATION;
            $overallScore = null;
            $provisionalScore = self::round2((float) $plannedScore);
        } else {
            $scoreState = self::STATE_FINAL;
            $overallScore = self::round2((float) $plannedScore + $problemScore);
            $provisionalScore = null;
        }

        $problems = $planned->flatMap(fn (WorkPlanTask $task) => $task->problems);
        $problemCount = $problems->count();
        $resolvedCount = $problems->where('status', WorkPlanTaskProblem::STATUS_RESOLVED)->count();

        $additionalRated = $additional->filter(fn (WorkPlanTask $task) => $task->additional_rating !== null);
        $additionalAverage = $additionalRated->isNotEmpty()
            ? $additionalRated->avg('additional_rating')
            : null;

        return [
            'user_id' => $userId,
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'planned_tasks' => $eligibleCount,
            'eligible_planned_tasks' => $eligibleCount,
            'has_eligible_tasks' => $hasEligible,
            'completed_tasks' => $completedCount,
            'incomplete_tasks' => $incomplete->count(),
            'cancelled_tasks' => $cancelled->count(),
            'completion_rate' => $completionRate !== null ? self::round2((float) $completionRate) : null,
            'planned_score' => $plannedScore !== null ? self::round2((float) $plannedScore) : null,
            'planned_contribution' => $plannedScore !== null ? self::round2((float) $plannedScore) : null,
            'provisional_score' => $provisionalScore,
            'score_state' => $scoreState,
            'problems_total' => $problemCount,
            'problems_resolved' => $resolvedCount,
            'problems_open' => $problemCount - $resolvedCount,
            'problem_evaluated' => $problemEvaluated,
            'problem_score' => $problemScore,
            'problem_rate' => $problemRate,
            'problem_contribution' => $problemScore,
            'problem_notes' => $evaluation?->notes,
            'evaluated_by' => $evaluation?->evaluated_by,
            'evaluated_at' => $evaluation?->evaluated_at?->toDateTimeString(),
            'additional_tasks' => $additional->count(),
            'additional_completed_tasks' => $additional->where('status', WorkPlanTask::STATUS_COMPLETED)->count(),
            'additional_rated_tasks' => $additionalRated->count(),
            'additional_rating_average' => $additionalAverage !== null ? self::round2((float) $additionalAverage) : null,
            'overall_score' => $overallScore,
            'code_accuracy' => self::codeAccuracy($planned),
            'breakdown' => [
                'planned' => [
                    'weight' => self::PLANNED_POINTS,
                    'rate' => $completionRate !== null ? self::round2((float) $completionRate) : null,
                    'contribution' => $plannedScore !== null ? self::round2((float) $plannedScore) : null,
                ],
                'problem_solving' => [
                    'weight' => self::PROBLEM_POINTS,
                    'points' => $problemScore,
                    'rate' => $problemRate,
                    'contribution' => $problemScore,
                    'evaluated' => $problemEvaluated,
                ],
            ],
        ];
    }

    public static function codeAccuracy(Collection $tasks): array
    {
        $validCodes = WorkPlanLegend::query()
            ->where('is_active', true)
            ->pluck('code')
            ->all();

        $entries = $tasks
            ->filter(fn (WorkPlanTask $task) => ! $task->isNonWorking())
            ->pluck('legend_code')
            ->filter()
            ->flatMap(fn (string $code) => array_filter(array_map('trim', explode(',', $code))));

        $total = $entries->count();
        $valid = $entries->filter(fn (string $code) => in_array($code, $validCodes, true))->count();

        return [
            'total' => $total,
            'valid' => $valid,
            'accuracy' => $total > 0 ? self::round2(($valid / $total) * 100) : 100.0,
        ];
    }
}
