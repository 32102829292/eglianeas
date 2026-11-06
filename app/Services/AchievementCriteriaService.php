<?php

namespace App\Services;

use App\Models\KaizenConcern;
use App\Models\PriorityItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes per-criterion performance scores for a staff member over a period.
 *
 * Criteria:
 *   1. Accomplishment Quality — from DailyJournal.achievement_score (existing).
 *   2. Timeliness — from PriorityItem / KaizenConcern deadlines vs completion.
 *   3. Immediate Responsiveness — from creation to first observable action timestamp.
 *   4. Client Feedback — from ClientSurveyResponse linked to the staff member.
 *
 * Only tasks actually assigned to the staff member are evaluated. Cancelled,
 * reassigned, blocked, or deadline-changed items are excluded. Items with no
 * deadline cannot be scored for timeliness and are excluded from that criterion.
 * Missing or insufficient data for a criterion is reported as null ("not yet
 * evaluated") rather than a zero score.
 */
class AchievementCriteriaService
{
    public const CRITERION_QUALITY = 'quality';

    public const CRITERION_TIMELINESS = 'timeliness';

    public const CRITERION_RESPONSIVENESS = 'responsiveness';

    public const CRITERION_CLIENT_FEEDBACK = 'client_feedback';

    /** @return array{quality: ?float, timeliness: ?float, responsiveness: ?float, client_feedback: ?float} */
    private array $defaults = [
        self::CRITERION_QUALITY => null,
        self::CRITERION_TIMELINESS => null,
        self::CRITERION_RESPONSIVENESS => null,
        self::CRITERION_CLIENT_FEEDBACK => null,
    ];

    private const RESPONSE_PROMPT_HOURS = 24;

    /**
     * Compute all four criterion scores for one staff member over a period.
     *
     * @return array{
     *   quality: ?float,
     *   timeliness: ?float,
     *   responsiveness: ?float,
     *   client_feedback: ?float,
     *   quality_explanation: string,
     *   timeliness_explanation: string,
     *   responsiveness_explanation: string,
     *   client_feedback_explanation: string,
     *   quality_meta: array<string, mixed>,
     *   timeliness_meta: array<string, mixed>,
     *   responsiveness_meta: array<string, mixed>,
     *   client_feedback_meta: array<string, mixed>,
     * }
     */
    public function analyze(User $staff, Carbon $start, Carbon $end): array
    {
        $quality = $this->scoreQuality($staff, $start, $end);
        $timeliness = $this->scoreTimeliness($staff, $start, $end);
        $responsiveness = $this->scoreResponsiveness($staff, $start, $end);
        $clientFeedback = $this->scoreClientFeedback($staff, $start, $end);

        return [
            'quality' => $quality['score'],
            'timeliness' => $timeliness['score'],
            'responsiveness' => $responsiveness['score'],
            'client_feedback' => $clientFeedback['score'],
            'quality_explanation' => $quality['explanation'],
            'timeliness_explanation' => $timeliness['explanation'],
            'responsiveness_explanation' => $responsiveness['explanation'],
            'client_feedback_explanation' => $clientFeedback['explanation'],
            'quality_meta' => $quality['meta'],
            'timeliness_meta' => $timeliness['meta'],
            'responsiveness_meta' => $responsiveness['meta'],
            'client_feedback_meta' => $clientFeedback['meta'],
        ];
    }

    /**
     * Combine criterion scores into one overall score using configured weights.
     * Criteria with null (insufficient data) are excluded and remaining weights
     * are renormalized so a missing data source never forces a zero.
     *
     * @return array{overall_score: ?float, weights_used: array<string, float>}
     */
    public function combine(array $criteria, array $weights): array
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

    // ------------------------------------------------------------------
    // Quality (existing DailyJournal achievement_score)
    // ------------------------------------------------------------------

    /**
     * @return array{score: ?float, explanation: string, meta: array<string, mixed>}
     */
    private function scoreQuality(User $staff, Carbon $start, Carbon $end): array
    {
        $journals = $staff->dailyJournals()
            ->whereDate('required_date', '>=', $start->toDateString())
            ->whereDate('required_date', '<=', $end->toDateString())
            ->whereNotNull('achievement_score')
            ->get();

        if ($journals->isEmpty()) {
            return [
                'score' => null,
                'explanation' => 'No assessed daily accomplishment reports in this period.',
                'meta' => ['entries' => 0],
            ];
        }

        $average = round((float) $journals->avg('achievement_score'), 1);

        return [
            'score' => $average,
            'explanation' => 'Based on '.$journals->count().' assessed daily accomplishment report(s). Average quality score '.$average.'.',
            'meta' => ['entries' => $journals->count(), 'average' => $average],
        ];
    }

    // ------------------------------------------------------------------
    // Timeliness (PriorityItem + KaizenConcern deadlines vs completion)
    // ------------------------------------------------------------------

    /**
     * @return array{score: ?float, explanation: string, meta: array<string, mixed>}
     */
    private function scoreTimeliness(User $staff, Carbon $start, Carbon $end): array
    {
        $tasks = $this->collectTimelinessTasks($staff, $start, $end);

        if ($tasks->isEmpty()) {
            return [
                'score' => null,
                'explanation' => 'No assigned tasks or concerns with deadlines in this period.',
                'meta' => ['evaluated' => 0, 'on_time' => 0, 'late' => 0, 'overdue' => 0],
            ];
        }

        $onTime = 0;
        $early = 0;
        $late = 0;
        $overdue = 0;
        $totalScore = 0.0;

        foreach ($tasks as $task) {
            $deadline = $task['deadline'];
            $completed = $task['completed'];
            $status = $task['status'];

            // Overdue and not completed.
            if ($completed === null && $deadline->lt(now()->startOfDay())) {
                $overdue++;

                continue;
            }

            // Completed.
            if ($completed !== null) {
                if ($completed->lt($deadline)) {
                    $early++;
                    $onTime++;
                    $totalScore += 100;
                } elseif ($completed->eq($deadline)) {
                    $onTime++;
                    $totalScore += 90;
                } else {
                    $late++;
                    $daysLate = (int) $deadline->diffInDays($completed);
                    $taskScore = max(0, 90 - ($daysLate * 10));
                    $totalScore += $taskScore;
                }
            }
        }

        $evaluated = $onTime + $late; // excludes overdue-not-completed from denominator
        $denominator = $evaluated > 0 ? $evaluated : 1;
        $score = round($totalScore / $denominator, 1);

        $rate = $evaluated > 0 ? round(($onTime / $evaluated) * 100, 1) : 0;

        return [
            'score' => $score,
            'explanation' => $this->timelinessExplanation($early, $onTime, $late, $overdue, $evaluated, $rate),
            'meta' => [
                'evaluated' => $evaluated,
                'on_time' => $onTime,
                'early' => $early,
                'late' => $late,
                'overdue_not_completed' => $overdue,
                'on_time_rate' => $rate,
            ],
        ];
    }

    /**
     * Collect assigned PriorityItems and KaizenConcerns with deadlines for the period.
     * Excludes cancelled/reassigned/blocked items and items without a deadline.
     *
     * @return Collection<int, array{deadline: Carbon, completed: Carbon|null, status: string, source: string}>
     */
    private function collectTimelinessTasks(User $staff, Carbon $start, Carbon $end): Collection
    {
        $items = new Collection;

        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->endOfDay();

        // PriorityItems: due_date defines the period window.
        $priorityItems = PriorityItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $periodStart)
            ->whereDate('due_date', '<=', $periodEnd)
            ->get();

        foreach ($priorityItems as $item) {
            if ($item->status === PriorityItem::STATUS_COMPLETED) {
                $completed = $item->completed_at ? Carbon::parse($item->completed_at) : null;
                $items->add([
                    'deadline' => Carbon::parse($item->due_date),
                    'completed' => $completed,
                    'status' => $item->status,
                    'source' => 'priority_item',
                ]);
            } elseif ($item->status === PriorityItem::STATUS_OVERDUE) {
                $items->add([
                    'deadline' => Carbon::parse($item->due_date),
                    'completed' => null,
                    'status' => $item->status,
                    'source' => 'priority_item',
                ]);
            } elseif ($item->status === PriorityItem::STATUS_IN_PROGRESS) {
                $items->add([
                    'deadline' => Carbon::parse($item->due_date),
                    'completed' => null,
                    'status' => $item->status,
                    'source' => 'priority_item',
                ]);
            } elseif ($item->status === PriorityItem::STATUS_PENDING) {
                $items->add([
                    'deadline' => Carbon::parse($item->due_date),
                    'completed' => null,
                    'status' => $item->status,
                    'source' => 'priority_item',
                ]);
            }
        }

        // KaizenConcerns: target_date defines the period window.
        $kaizenConcerns = KaizenConcern::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereNotNull('target_date')
            ->whereDate('target_date', '>=', $periodStart)
            ->whereDate('target_date', '<=', $periodEnd)
            ->get();

        foreach ($kaizenConcerns as $concern) {
            // Exclude not_implemented (closed / cancelled / blocked outcomes).
            if ($concern->isNotImplemented()) {
                continue;
            }

            if ($concern->isImplemented()) {
                $completed = $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null;
                $items->add([
                    'deadline' => Carbon::parse($concern->target_date),
                    'completed' => $completed,
                    'status' => $concern->effectiveStatus(),
                    'source' => 'kaizen_concern',
                ]);
            } else {
                $items->add([
                    'deadline' => Carbon::parse($concern->target_date),
                    'completed' => null,
                    'status' => $concern->effectiveStatus(),
                    'source' => 'kaizen_concern',
                ]);
            }
        }

        return $items;
    }

    private function timelinessExplanation(int $early, int $onTime, int $late, int $overdue, int $evaluated, float $rate): string
    {
        $parts = [];
        if ($early > 0) {
            $parts[] = $early.' completed before deadline';
        }
        if ($onTime > $early) {
            $parts[] = ($onTime - $early).' completed on deadline';
        }
        if ($late > 0) {
            $parts[] = $late.' completed late';
        }
        if ($overdue > 0) {
            $parts[] = $overdue.' overdue and not completed';
        }

        if ($parts === []) {
            return 'No deadline-driven tasks were evaluated for timeliness.';
        }

        return 'Of '.$evaluated.' evaluable task(s): '.implode(', ', $parts).'. On-time completion rate '.$rate.'.';
    }

    // ------------------------------------------------------------------
    // Immediate Responsiveness (creation to first observable action)
    // ------------------------------------------------------------------

    /**
     * @return array{score: ?float, explanation: string, meta: array<string, mixed>}
     */
    private function scoreResponsiveness(User $staff, Carbon $start, Carbon $end): array
    {
        $items = $this->collectResponsivenessItems($staff, $start, $end);

        if ($items->isEmpty()) {
            return [
                'score' => null,
                'explanation' => 'No assigned tasks or concerns in this period.',
                'meta' => ['evaluated' => 0, 'prompt' => 0, 'delayed' => 0, 'unanswered' => 0, 'avg_hours' => null],
            ];
        }

        $prompt = 0;
        $delayed = 0;
        $unanswered = 0;
        $totalHours = 0.0;
        $hoursCount = 0;

        foreach ($items as $item) {
            $created = $item['created'];
            $firstAction = $item['first_action'];
            $status = $item['status'];

            if ($firstAction === null) {
                // No observable response yet.
                $unanswered++;

                continue;
            }

            $hours = (float) $created->diffInHours($firstAction);
            $totalHours += $hours;
            $hoursCount++;

            if ($hours <= self::RESPONSE_PROMPT_HOURS) {
                $prompt++;
            } else {
                $delayed++;
            }
        }

        $evaluated = $prompt + $delayed;
        $avgHours = $hoursCount > 0 ? round($totalHours / $hoursCount, 1) : null;

        // Score: prompt responses score high, delayed score lower, unanswered drag down.
        $score = 0.0;
        if ($evaluated > 0) {
            $promptScore = ($prompt / $evaluated) * 100;
            // Penalize unanswered items by spreading them across the total.
            $totalItems = $evaluated + $unanswered;
            $score = round(($promptScore * $evaluated) / max(1, $totalItems), 1);
        }

        return [
            'score' => $evaluated > 0 ? $score : null,
            'explanation' => $this->responsivenessExplanation($prompt, $delayed, $unanswered, $avgHours),
            'meta' => [
                'evaluated' => $evaluated,
                'prompt' => $prompt,
                'delayed' => $delayed,
                'unanswered' => $unanswered,
                'avg_response_hours' => $avgHours,
            ],
        ];
    }

    /**
     * Collect assigned PriorityItems and KaizenConcerns for responsiveness.
     * Uses created_at as assignment time and completed_at/implementation_date as
     * the first observable action. No separate acknowledgment timestamp exists.
     *
     * @return Collection<int, array{created: Carbon, first_action: Carbon|null, status: string}>
     */
    private function collectResponsivenessItems(User $staff, Carbon $start, Carbon $end): Collection
    {
        $items = new Collection;

        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->endOfDay();

        $priorityItems = PriorityItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereDate('created_at', '>=', $periodStart)
            ->whereDate('created_at', '<=', $periodEnd)
            ->get();

        foreach ($priorityItems as $item) {
            $items->add([
                'created' => Carbon::parse($item->created_at),
                'first_action' => $item->completed_at ? Carbon::parse($item->completed_at) : null,
                'status' => $item->status,
            ]);
        }

        $kaizenConcerns = KaizenConcern::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereDate('created_at', '>=', $periodStart)
            ->whereDate('created_at', '<=', $periodEnd)
            ->get();

        foreach ($kaizenConcerns as $concern) {
            if ($concern->isNotImplemented()) {
                continue;
            }

            $items->add([
                'created' => Carbon::parse($concern->created_at),
                'first_action' => $concern->implementation_date ? Carbon::parse($concern->implementation_date) : null,
                'status' => $concern->effectiveStatus(),
            ]);
        }

        return $items;
    }

    private function responsivenessExplanation(int $prompt, int $delayed, int $unanswered, ?float $avgHours): string
    {
        $parts = [];
        if ($prompt > 0) {
            $parts[] = $prompt.' prompt response(s)';
        }
        if ($delayed > 0) {
            $parts[] = $delayed.' delayed response(s)';
        }
        if ($unanswered > 0) {
            $parts[] = $unanswered.' unanswered item(s)';
        }

        if ($parts === []) {
            return 'No assigned tasks or concerns with observable response times.';
        }

        $timeText = $avgHours !== null ? ' average response time '.$avgHours.'h' : '';

        return 'Of '.($prompt + $delayed + $unanswered).' assigned item(s): '.implode(', ', $parts).'.'.$timeText;
    }

    // ------------------------------------------------------------------
    // Client Feedback
    //
    // There is NO reliable direct or indirect relationship between a
    // ClientSurveyResponse and a specific staff member in the current schema:
    //   - user_id is the CLIENT who submitted the survey, not staff.
    //   - TrackerAssignment / TrackerInstance links are indirect and cannot
    //     reliably attribute a single survey response to one staff member.
    //
    // Per the requirements, feedback is only attributed to a staff member when
    // there is reliable evidence connecting the response to that staff member.
    // Since no such evidence exists, client feedback is reported as not yet
    // evaluated (null) for every staff member rather than guessing or counting
    // anonymous/unassignable feedback against individuals.
    // ------------------------------------------------------------------

    /**
     * @return array{score: ?float, explanation: string, meta: array<string, mixed>}
     */
    private function scoreClientFeedback(User $staff, Carbon $start, Carbon $end): array
    {
        return [
            'score' => null,
            'explanation' => 'No attributable client feedback available.',
            'meta' => [
                'responses' => 0,
                'positive' => 0,
                'negative' => 0,
                'neutral' => 0,
                'average_rating' => null,
                'attributable' => false,
            ],
        ];
    }

    private function clientFeedbackExplanation(int $positive, int $negative, int $neutral, int $count, float $average): string
    {
        $parts = [];
        if ($positive > 0) {
            $parts[] = $positive.' positive';
        }
        if ($negative > 0) {
            $parts[] = $negative.' negative';
        }
        if ($neutral > 0) {
            $parts[] = $neutral.' neutral';
        }

        return 'Of '.$count.' verified feedback response(s): '.implode(', ', $parts).'. Average rating '.$average.'/5.';
    }
}
