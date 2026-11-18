<?php

namespace App\Services;

use App\Models\DailyJournal;
use App\Models\WorkPlanTask;

/**
 * Scores one Daily Accomplishment Report out of 100.
 *
 *  - 90 points: tasks. The 90 points are divided equally among the tasks assigned
 *    for the day (Goals for the Day). A task earns its share only once it is
 *    completed. Each urgent task (not in the goals) adds 80% of one assigned
 *    task's share. Every urgent task the staff member lists counts as done.
 *  - 10 points: Problems Encountered (5) + Suggested Solutions (5), each only
 *    when it is actually filled in.
 *
 * Edge cases (change the constants below if the client wants different rules):
 *  - No assigned tasks but urgent tasks: the 90 points are divided among the urgent
 *    tasks, each worth 80%, so the task part tops out at 72.
 *  - Nothing scheduled and no urgent tasks: only the 10 points can be earned, and
 *    the score is scaled to 100 so the day is not unfairly zero.
 */
class DailyReportScoreCalculator
{
    public const TASK_POINTS = 90;

    public const PROBLEMS_POINTS = 10;

    /** Share of an assigned task's value that one urgent task is worth. */
    public const URGENT_FACTOR = 0.8;

    /** A section only counts as "filled in" with at least this many characters. */
    public const MIN_TEXT_LENGTH = 10;

    /**
     * @return array<string, mixed>
     */
    public function calculate(DailyJournal $journal): array
    {
        $assigned = WorkPlanTask::query()
            ->where('user_id', $journal->user_id)
            ->where('work_date', $journal->required_date->toDateString())
            ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
            ->get();

        $assignedTotal = $assigned->count();
        $assignedDone = $assigned->filter(fn (WorkPlanTask $task) => $task->isCompleted())->count();
        $detectedUrgent = $this->urgentFromDetails($journal);
        $urgentCount = $this->urgentCount($journal) + count($detectedUrgent);

        $mode = 'normal';
        $assignedShare = 0.0;
        $urgentShare = 0.0;

        if ($assignedTotal > 0) {
            $assignedShare = self::TASK_POINTS / $assignedTotal;
            $urgentShare = $assignedShare * self::URGENT_FACTOR;
        } elseif ($urgentCount > 0) {
            $mode = 'urgent_only';
            $urgentShare = (self::TASK_POINTS / $urgentCount) * self::URGENT_FACTOR;
        } else {
            $mode = 'nothing_scheduled';
        }

        $assignedPoints = $assignedDone * $assignedShare;
        $urgentPoints = $urgentCount * $urgentShare;
        $taskPoints = min(self::TASK_POINTS, $assignedPoints + $urgentPoints);

        $problemsPoints = 0.0;
        $half = self::PROBLEMS_POINTS / 2;
        $problemsFilled = $this->isFilled($journal->problems_encountered);
        $solutionsFilled = $this->isFilled($journal->suggested_solutions);
        $problemsPoints += $problemsFilled ? $half : 0;
        $problemsPoints += $solutionsFilled ? $half : 0;

        $score = $taskPoints + $problemsPoints;

        if ($mode === 'nothing_scheduled') {
            $score = ($problemsPoints / self::PROBLEMS_POINTS) * 100;
        }

        $score = round(max(0, min(100, $score)), 1);

        return [
            'score' => $score,
            'level' => $this->levelFor($score),
            'mode' => $mode,
            'task_points' => round($taskPoints, 2),
            'problems_points' => round($problemsPoints, 2),
            'assigned_total' => $assignedTotal,
            'assigned_completed' => $assignedDone,
            'assigned_points_each' => round($assignedShare, 2),
            'urgent_count' => $urgentCount,
            'urgent_from_details' => count($detectedUrgent),
            'urgent_points_each' => round($urgentShare, 2),
            'problems_filled' => $problemsFilled,
            'solutions_filled' => $solutionsFilled,
            'explanation' => $this->explain($mode, $assignedTotal, $assignedDone, $urgentCount, $taskPoints, $problemsPoints, $problemsFilled, $solutionsFilled, $score),
        ];
    }

    /**
     * Level label from config/achievements.php for a given score.
     */
    public function levelFor(float $score): string
    {
        foreach ((array) config('achievements.levels', []) as $level) {
            if ($score >= (float) $level['min']) {
                return (string) $level['label'];
            }
        }

        return 'Needs Further Development';
    }

    /**
     * Urgent tasks that staff wrote in "Accomplishment details" instead of using the
     * Urgent Task form. A line counts when it starts with the word "urgent"
     * ("Urgent: filed 2551Q for ABC", "- URGENT ...", "1. Urgent ...") or carries an
     * "[urgent]" / "(urgent)" tag. A line that repeats a task already listed in the
     * form is not counted twice.
     *
     * @return array<int, string>
     */
    public function urgentFromDetails(DailyJournal $journal): array
    {
        $prefix = '/^[\s\-\*\x{2022}\d\.\)]*urgent[\s:\-\x{2013}\x{2014}]*/iu';

        $listed = collect((array) ($journal->urgent_tasks ?? []))
            ->map(fn ($task) => mb_strtolower(trim((string) ($task['description'] ?? ''))))
            ->filter()
            ->values()
            ->all();

        return collect(preg_split('/\R/u', (string) $journal->achievements) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== ''
                && (preg_match('/^[\s\-\*\x{2022}\d\.\)]*urgent\b/iu', $line) === 1
                    || preg_match('/[\[\(]\s*urgent\s*[\]\)]/iu', $line) === 1))
            ->reject(function ($line) use ($listed, $prefix) {
                $clean = mb_strtolower(trim((string) preg_replace($prefix, '', $line)));

                if ($clean === '') {
                    return false;
                }

                foreach ($listed as $description) {
                    if ($clean === $description || str_contains($clean, $description) || str_contains($description, $clean)) {
                        return true;
                    }
                }

                return false;
            })
            ->unique(fn ($line) => mb_strtolower($line))
            ->values()
            ->all();
    }

    private function urgentCount(DailyJournal $journal): int
    {
        return collect((array) ($journal->urgent_tasks ?? []))
            ->filter(fn ($task) => is_array($task)
                && trim((string) ($task['description'] ?? '')) !== ''
                && trim((string) ($task['reason'] ?? '')) !== '')
            ->count();
    }

    private function isFilled(?string $text): bool
    {
        return mb_strlen(trim((string) $text)) >= self::MIN_TEXT_LENGTH;
    }

    private function explain(
        string $mode,
        int $assignedTotal,
        int $assignedDone,
        int $urgentCount,
        float $taskPoints,
        float $problemsPoints,
        bool $problemsFilled,
        bool $solutionsFilled,
        float $score
    ): string {
        $parts = [];

        if ($mode === 'nothing_scheduled') {
            $parts[] = 'No tasks were assigned or added as urgent for this day, so the score is based only on the problems and solutions sections.';
        } else {
            $parts[] = sprintf(
                'Assigned tasks completed: %d of %d. Urgent tasks added: %d. Task points: %.1f of %d.',
                $assignedDone,
                $assignedTotal,
                $urgentCount,
                $taskPoints,
                self::TASK_POINTS
            );
        }

        $parts[] = sprintf(
            'Problems encountered %s, suggested solutions %s: %.1f of %d points.',
            $problemsFilled ? 'filled in' : 'missing or too short',
            $solutionsFilled ? 'filled in' : 'missing or too short',
            $problemsPoints,
            self::PROBLEMS_POINTS
        );

        $parts[] = sprintf('Final score %.1f / 100.', $score);

        return implode(' ', $parts);
    }
}