<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only guard that detects an out-of-date database before a work-plan
 * page queries columns that may not exist yet. It never writes anything.
 */
class WorkPlanSchema
{
    public const REQUIRED_TABLES = [
        'work_plan_tasks',
        'work_plan_legends',
        'work_plan_task_problems',
        'work_plan_weekly_evaluations',
        'work_plan_task_carries',
    ];

    public const REQUIRED_TASK_COLUMNS = [
        'original_work_date',
        'carried_over_at',
        'legend_name_snapshot',
        'legend_category_snapshot',
        'is_additional',
        'additional_rating',
        'returned_reason',
        'evidence_path',
        'reviewed_by',
        'details',
    ];

    public const REQUIRED_LEGEND_COLUMNS = [
        'is_non_working',
        'requires_details',
        'is_custom',
    ];

    /**
     * @return array<int, string> Human readable list of missing schema pieces.
     */
    public static function missing(): array
    {
        $missing = [];

        foreach (self::REQUIRED_TABLES as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missing[] = "table:{$table}";
                }
            } catch (Throwable) {
                $missing[] = "table:{$table}";
            }
        }

        foreach (self::REQUIRED_TASK_COLUMNS as $column) {
            try {
                if (! Schema::hasColumn('work_plan_tasks', $column)) {
                    $missing[] = "column:work_plan_tasks.{$column}";
                }
            } catch (Throwable) {
                $missing[] = "column:work_plan_tasks.{$column}";
            }
        }

        foreach (self::REQUIRED_LEGEND_COLUMNS as $column) {
            try {
                if (! Schema::hasColumn('work_plan_legends', $column)) {
                    $missing[] = "column:work_plan_legends.{$column}";
                }
            } catch (Throwable) {
                $missing[] = "column:work_plan_legends.{$column}";
            }
        }

        return $missing;
    }

    public static function isUpToDate(): bool
    {
        return self::missing() === [];
    }
}
