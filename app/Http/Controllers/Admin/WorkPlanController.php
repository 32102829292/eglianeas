<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailyGoal;
use App\Models\User;
use App\Models\WorkPlanAudit;
use App\Models\WorkPlanLegend;
use App\Models\WorkPlanSetting;
use App\Models\WorkPlanTask;
use App\Models\WorkPlanTaskCarry;
use App\Models\WorkPlanWeeklyEvaluation;
use App\Services\WorkPlanScoringService;
use App\Services\WorkPlanWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkPlanController extends Controller
{
    /**
     * Enforce the planning permissions on every write endpoint. Admin may edit
     * anyone at any time, Supervisors may edit anyone unless manually locked,
     * Staff only their own row while the window is open. Blocked writes get a
     * 403 with the shared locked message.
     */
    private function authorizeWrite(int $targetUserId): void
    {
        WorkPlanWindow::authorizeEdit(auth()->user(), $targetUserId);
    }

    /**
     * Record an Admin/Supervisor override edit (outside the automatic window or
     * on another employee's plan) with the before/after codes.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private function logOverride(int $targetUserId, string $date, array $before, array $after): void
    {
        if (! WorkPlanWindow::requiresOverrideLog(auth()->user(), $targetUserId)) {
            return;
        }

        $target = User::find($targetUserId);

        ActivityLog::record(
            auth()->user(),
            'work_plan.override_edit',
            sprintf(
                'Override edit of work plan for %s on %s. Before: [%s]. After: [%s].',
                $target?->name ?? ('user #'.$targetUserId),
                $date,
                implode(', ', $before),
                implode(', ', $after)
            )
        );
    }

    /**
     * A stable hash of a week's planned tasks, used for optimistic locking on
     * the bulk save endpoint.
     */
    private function weekVersion(Carbon $weekStart): string
    {
        $weekEnd = (clone $weekStart)->addDays(4);

        $rows = WorkPlanTask::query()
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('id')
            ->get(['id', 'user_id', 'work_date', 'legend_code', 'status', 'updated_at'])
            ->map(fn (WorkPlanTask $task) => implode('|', [
                $task->id,
                $task->user_id,
                $task->work_date?->toDateString(),
                (string) $task->legend_code,
                $task->status,
                optional($task->updated_at)->timestamp,
            ]))
            ->implode(';');

        return sha1($rows);
    }

    private function legendSnapshot(?string $code): array
    {
        if (! $code) {
            return [
                'legend_name_snapshot' => null,
                'legend_category_snapshot' => null,
            ];
        }

        $legend = WorkPlanLegend::query()->where('code', $code)->first();

        return [
            'legend_name_snapshot' => $legend?->name,
            'legend_category_snapshot' => $legend?->category,
        ];
    }

    /**
     * Display the weekly work plan management screen.
     *
     * Shows all work plan tasks for the selected week, grouped by employee.
     */
    public function index(Request $request): View
    {
        WorkPlanSetting::populateCache();

        $weekStart = $request->get('week_start')
            ? Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now(config('app.timezone', 'Asia/Manila'))->startOfWeek(Carbon::MONDAY);

        $weekEnd = (clone $weekStart)->addDays(4); // Friday

        $actor = $request->user();
        $canEditAny = $actor->isAdminOrSupervisor();

        $employees = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at');

        if (! $canEditAny) {
            $employees->where('id', $actor->id);
        }

        $employees = $employees->orderBy('role')
            ->orderBy('name')
            ->get();

        // Get all work plan tasks for the week
        $tasks = WorkPlanTask::query()
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        if (! $canEditAny) {
            $tasks->where('user_id', $actor->id);
        }

        $tasks = $tasks->with(['user', 'creator', 'editor', 'completer', 'legend'])
            ->orderBy('user_id')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        // Group by user and date
        $grouped = $tasks->groupBy('user_id');
        $groupedByUserAndDate = $tasks->groupBy(function ($task) {
            return $task->user_id.'|'.$task->work_date->toDateString();
        });

        // Get daily goals for context
        $dailyGoals = DailyGoal::query()
            ->whereBetween('target_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->with(['user', 'creator'])
            ->orderBy('user_id')
            ->orderBy('target_date')
            ->get();

        $goalsByUserAndDate = $dailyGoals->groupBy(function ($goal) {
            return $goal->user_id.'|'.$goal->target_date;
        });

        // Get legends for dropdown
        $legends = WorkPlanLegend::active()->orderBy('code')->get();

        // Get work plan settings
        $editingEnabled = WorkPlanSetting::isEditingEnabled();
        $nextStateChange = WorkPlanSetting::getNextStateChange();
        $lockedAt = WorkPlanSetting::getLockedAt();
        $lastUpdated = WorkPlanSetting::getLastUpdated();
        $lastUpdatedBy = WorkPlanSetting::getLastUpdatedBy();

        return view('admin.work-plan.index', [
            'tasks' => $tasks,
            'grouped' => $grouped,
            'groupedByUserAndDate' => $groupedByUserAndDate,
            'employees' => $employees,
            'legends' => $legends,
            'dailyGoals' => $dailyGoals,
            'goalsByUserAndDate' => $goalsByUserAndDate,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => (clone $weekStart)->subDays(7),
            'nextWeek' => (clone $weekStart)->addDays(7),
            'editingEnabled' => $editingEnabled,
            'nextStateChange' => $nextStateChange,
            'lockedAt' => $lockedAt,
            'lastUpdated' => $lastUpdated,
            'lastUpdatedBy' => $lastUpdatedBy,
            'canEditAny' => $canEditAny,
            'canEditNow' => WorkPlanWindow::canEdit($actor),
            'isManuallyLocked' => WorkPlanSetting::isManuallyLocked(),
            'weekVersion' => $this->weekVersion($weekStart),
        ]);
    }

    /**
     * Store a new work plan task.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', function ($attribute, $value, $fail) {
                $user = User::find($value);
                if ($user && ! in_array($user->role, [User::ROLE_STAFF, User::ROLE_SUPERVISOR], true)) {
                    $fail('Only staff and supervisors can be assigned work plan tasks.');
                }
            }],
            'work_date' => ['required', 'date'],
            'legend_code' => ['sometimes', 'string', 'max:10', 'exists:work_plan_legends,code'],
            'custom_title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'details' => ['nullable', 'string', 'max:2000'],
            'success_criteria' => ['nullable', 'string', 'max:500'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:20'],
            'is_additional' => ['sometimes', 'boolean'],
        ]);

        $this->authorizeWrite((int) $validated['user_id']);

        $workDate = Carbon::parse($validated['work_date']);

        // Validate it's a weekday
        if ($workDate->isWeekend()) {
            return back()->withErrors([
                'work_date' => 'Work plan tasks can only be scheduled on weekdays (Monday-Friday).',
            ])->withInput();
        }

        // Ensure either legend_code or custom_title is provided
        if (empty($validated['legend_code']) && empty($validated['custom_title'])) {
            return back()->withErrors([
                'legend_code' => 'Please select a task code or enter a custom title.',
            ])->withInput();
        }

        $task = WorkPlanTask::create(array_merge([
            'user_id' => $validated['user_id'],
            'work_date' => $workDate->toDateString(),
            'legend_code' => $validated['legend_code'] ?? null,
            'custom_title' => $validated['custom_title'] ?? null,
            'description' => $validated['description'] ?? null,
            'details' => $validated['details'] ?? null,
            'success_criteria' => $validated['success_criteria'] ?? null,
            'is_additional' => $request->boolean('is_additional'),
            'status' => WorkPlanTask::STATUS_PENDING,
            'client_name' => $validated['client_name'] ?? null,
            'client_code' => $validated['client_code'] ?? null,
            'created_by' => auth()->id(),
        ], $this->legendSnapshot($validated['legend_code'] ?? null)));

        // Record audit
        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'new_values' => $task->only([
                'legend_code', 'legend_name_snapshot', 'custom_title', 'description',
                'success_criteria', 'is_additional', 'status', 'client_name', 'client_code',
            ]),
            'change_reason' => 'New work plan task created',
            'changed_at' => now(),
        ]);

        WorkPlanSetting::setLastUpdated(auth()->id());

        ActivityLog::record(
            auth()->user(),
            'work_plan_task.created',
            "Created work plan task '{$task->getLegendLabel()}' for {$task->user->name} on {$task->work_date->format('F j, Y')}."
        );

        return back()->with('status', 'Work plan task created successfully.');
    }

    /**
     * Return task data for the edit form (AJAX).
     */
    public function edit(WorkPlanTask $task)
    {
        $task->load(['user', 'creator', 'editor', 'completer', 'legend', 'audits.changer']);

        return response()->json([
            'id' => $task->id,
            'user_id' => $task->user_id,
            'work_date' => $task->work_date?->format('Y-m-d'),
            'legend_code' => $task->legend_code,
            'custom_title' => $task->custom_title,
            'description' => $task->description,
            'details' => $task->details,
            'success_criteria' => $task->success_criteria,
            'status' => $task->status,
            'client_name' => $task->client_name,
            'client_code' => $task->client_code,
            'client_details' => $task->client_details,
            'audits' => $task->audits->map(function ($a) {
                return [
                    'changed_at' => $a->changed_at?->format('Y-m-d H:i'),
                    'changed_by' => $a->changer?->name,
                    'change_reason' => $a->change_reason,
                    'previous_values' => $a->previous_values,
                    'new_values' => $a->new_values,
                ];
            }),
        ]);
    }

    /**
     * Update an existing work plan task.
     */
    public function update(Request $request, WorkPlanTask $task): RedirectResponse
    {
        $this->authorizeWrite((int) $task->user_id);

        $validated = $request->validate([
            'legend_code' => ['sometimes', 'nullable', 'string', 'max:10', 'exists:work_plan_legends,code'],
            'custom_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'details' => ['nullable', 'string', 'max:2000'],
            'success_criteria' => ['nullable', 'string', 'max:500'],
            'work_date' => ['sometimes', 'date'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,cancelled,on_hold,returned_for_revision'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $oldLegendCode = $task->legend_code;

        if (($request->has('legend_code') || $request->has('custom_title'))
            && empty($validated['legend_code'] ?? null)
            && empty($validated['custom_title'] ?? null)) {
            return back()->withErrors([
                'legend_code' => 'Please select a task code or enter a custom title.',
            ])->withInput();
        }

        $previousValues = $task->only([
            'legend_code', 'legend_name_snapshot', 'custom_title', 'description', 'success_criteria',
            'status', 'work_date', 'original_work_date', 'client_name', 'client_code',
        ]);

        $newValues = [];
        $oldWorkDate = $task->work_date?->toDateString();

        if ($request->has('legend_code')) {
            $newValues['legend_code'] = $validated['legend_code'];
            $task->legend_code = $validated['legend_code'];

            $snapshot = $this->legendSnapshot($validated['legend_code']);
            $task->legend_name_snapshot = $snapshot['legend_name_snapshot'];
            $task->legend_category_snapshot = $snapshot['legend_category_snapshot'];
            $newValues['legend_name_snapshot'] = $task->legend_name_snapshot;
        }
        if ($request->has('custom_title')) {
            $newValues['custom_title'] = $validated['custom_title'];
            $task->custom_title = $validated['custom_title'];
        }
        if ($request->has('description')) {
            $newValues['description'] = $validated['description'];
            $task->description = $validated['description'];
        }
        if ($request->has('details')) {
            $newValues['details'] = $validated['details'];
            $task->details = $validated['details'];
        }
        if ($request->has('success_criteria')) {
            $newValues['success_criteria'] = $validated['success_criteria'];
            $task->success_criteria = $validated['success_criteria'];
        }
        if ($request->has('work_date')) {
            $newDate = Carbon::parse($validated['work_date']);

            if ($newDate->isWeekend()) {
                return back()->withErrors([
                    'work_date' => 'Work plan tasks can only be scheduled on weekdays (Monday-Friday).',
                ])->withInput();
            }

            $newDateString = $newDate->toDateString();
            $newValues['work_date'] = $newDateString;

            if ($oldWorkDate && $newDateString !== $oldWorkDate) {
                // Preserve the original target date the first time a task is moved.
                if (! $task->original_work_date) {
                    $task->original_work_date = $oldWorkDate;
                    $newValues['original_work_date'] = $oldWorkDate;
                }

                $task->carried_over_at = now();
                $task->carried_over_by = auth()->id();
                $newValues['carried_over_at'] = $task->carried_over_at;
                $newValues['carried_over_by'] = $task->carried_over_by;
            }

            $task->work_date = $newDateString;
        }
        if ($request->has('client_name')) {
            $newValues['client_name'] = $validated['client_name'];
            $task->client_name = $validated['client_name'];
        }
        if ($request->has('client_code')) {
            $newValues['client_code'] = $validated['client_code'];
            $task->client_code = $validated['client_code'];
        }
        if ($request->has('status')) {
            $newValues['status'] = $validated['status'];
            $task->status = $validated['status'];

            if ($validated['status'] === WorkPlanTask::STATUS_COMPLETED) {
                $task->completed_at = Carbon::today()->toDateString();
                $task->completed_by = auth()->id();
                $newValues['completed_at'] = $task->completed_at;
                $newValues['completed_by'] = auth()->id();
            } else {
                $task->completed_at = null;
                $task->completed_by = null;
            }
        }

        $task->edited_by = auth()->id();
        $task->save();

        $isCarryOver = isset($newValues['carried_over_at'])
            && ($newValues['work_date'] ?? null) !== $oldWorkDate;

        if ($isCarryOver) {
            WorkPlanAudit::create([
                'work_plan_task_id' => $task->id,
                'user_id' => auth()->id(),
                'action' => 'carried_over',
                'previous_values' => ['work_date' => $oldWorkDate],
                'new_values' => [
                    'work_date' => $newValues['work_date'],
                    'original_work_date' => $task->original_work_date?->toDateString(),
                ],
                'change_reason' => $validated['change_reason'] ?? 'Task carried over to a new date',
                'changed_at' => now(),
            ]);

            WorkPlanTaskCarry::create([
                'work_plan_task_id' => $task->id,
                'user_id' => $task->user_id,
                'from_date' => $oldWorkDate,
                'to_date' => $newValues['work_date'],
                'reason' => $validated['change_reason'] ?? 'Task carried over to a new date',
                'created_by' => auth()->id(),
            ]);
        }

        // Record audit
        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'previous_values' => $previousValues,
            'new_values' => $this->auditValues($newValues),
            'change_reason' => $validated['change_reason'] ?? null,
            'changed_at' => now(),
        ]);

        WorkPlanSetting::setLastUpdated(auth()->id());

        ActivityLog::record(
            auth()->user(),
            'work_plan_task.updated',
            "Updated work plan task '{$task->getLegendLabel()}' for {$task->user->name} on {$task->work_date->format('F j, Y')}."
        );

        $this->logOverride(
            (int) $task->user_id,
            $oldWorkDate ?? $task->work_date->toDateString(),
            array_filter([$oldLegendCode]),
            array_filter([$task->legend_code])
        );

        return back()->with('status', 'Work plan task updated successfully.');
    }

    /**
     * Cancel (soft delete) a work plan task.
     */
    public function destroy(WorkPlanTask $task): RedirectResponse
    {
        $this->authorizeWrite((int) $task->user_id);

        $previousStatus = $task->status;

        $task->update([
            'status' => WorkPlanTask::STATUS_CANCELLED,
            'edited_by' => auth()->id(),
        ]);

        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => auth()->id(),
            'action' => 'cancelled',
            'previous_values' => ['status' => $previousStatus],
            'new_values' => ['status' => WorkPlanTask::STATUS_CANCELLED],
            'change_reason' => 'Work plan task cancelled',
            'changed_at' => now(),
        ]);

        WorkPlanSetting::setLastUpdated(auth()->id());

        ActivityLog::record(
            auth()->user(),
            'work_plan_task.cancelled',
            "Cancelled work plan task '{$task->getLegendLabel()}' for {$task->user->name} on {$task->work_date->format('F j, Y')}."
        );

        return back()->with('status', 'Work plan task cancelled.');
    }

    /**
     * Replace the set of activity codes for one employee/day cell.
     *
     * Completed tasks are preserved for history. Pending/on-hold/returned
     * tasks that are no longer selected are cancelled, and newly selected
     * codes are created as pending tasks.
     */
    public function syncDay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', function ($attribute, $value, $fail) {
                $user = User::find($value);
                if ($user && ! in_array($user->role, [User::ROLE_STAFF, User::ROLE_SUPERVISOR], true)) {
                    $fail('Only staff and supervisors can be assigned work plan tasks.');
                }
            }],
            'work_date' => ['required', 'date'],
            'legend_codes' => ['nullable', 'array'],
            'legend_codes.*' => ['string', 'max:10', 'exists:work_plan_legends,code'],
        ]);

        $this->authorizeWrite((int) $validated['user_id']);

        $workDate = Carbon::parse($validated['work_date']);

        if ($workDate->isWeekend()) {
            return back()->withErrors([
                'work_date' => 'Work plan tasks can only be scheduled on weekdays (Monday-Friday).',
            ])->withInput();
        }

        $dateString = $workDate->toDateString();
        $codes = array_values(array_unique(array_filter(
            $validated['legend_codes'] ?? [],
            fn ($code) => is_string($code) && $code !== ''
        )));

        $beforeCodes = WorkPlanTask::query()
            ->where('user_id', $validated['user_id'])
            ->where('work_date', $dateString)
            ->where('is_additional', false)
            ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
            ->pluck('legend_code')
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($validated, $dateString, $codes) {
            $existing = WorkPlanTask::query()
                ->where('user_id', $validated['user_id'])
                ->where('work_date', $dateString)
                ->where('is_additional', false)
                ->get();

            $activeCodes = $existing
                ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
                ->pluck('legend_code')
                ->filter()
                ->all();

            foreach ($existing as $task) {
                if ($task->status === WorkPlanTask::STATUS_CANCELLED) {
                    continue;
                }

                $removable = in_array($task->status, [
                    WorkPlanTask::STATUS_PENDING,
                    WorkPlanTask::STATUS_ON_HOLD,
                    WorkPlanTask::STATUS_RETURNED_FOR_REVISION,
                ], true);

                if ($removable && $task->legend_code && ! in_array($task->legend_code, $codes, true)) {
                    $task->update([
                        'status' => WorkPlanTask::STATUS_CANCELLED,
                        'edited_by' => auth()->id(),
                    ]);

                    WorkPlanAudit::create([
                        'work_plan_task_id' => $task->id,
                        'user_id' => auth()->id(),
                        'action' => 'cancelled',
                        'previous_values' => ['status' => WorkPlanTask::STATUS_PENDING, 'legend_code' => $task->legend_code],
                        'new_values' => ['status' => WorkPlanTask::STATUS_CANCELLED],
                        'change_reason' => 'Removed from the daily cell',
                        'changed_at' => now(),
                    ]);
                }
            }

            foreach ($codes as $code) {
                if (in_array($code, $activeCodes, true)) {
                    continue;
                }

                $task = WorkPlanTask::create(array_merge([
                    'user_id' => $validated['user_id'],
                    'work_date' => $dateString,
                    'legend_code' => $code,
                    'status' => WorkPlanTask::STATUS_PENDING,
                    'created_by' => auth()->id(),
                ], $this->legendSnapshot($code)));

                WorkPlanAudit::create([
                    'work_plan_task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'action' => 'created',
                    'new_values' => $task->only(['legend_code', 'legend_name_snapshot', 'status']),
                    'change_reason' => 'Added from the daily cell',
                    'changed_at' => now(),
                ]);
            }
        });

        WorkPlanSetting::setLastUpdated(auth()->id());

        $this->logOverride((int) $validated['user_id'], $dateString, $beforeCodes, $codes);

        return back()->with('status', 'Daily work plan codes updated.');
    }

    /**
     * Bulk-save the Excel-style grid in one transaction. Each change replaces
     * the planned codes for one employee/day cell. A stale week version aborts
     * with 409 so two editors cannot silently overwrite each other.
     */
    public function save(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'version' => ['nullable', 'string', 'max:64'],
            'changes' => ['required', 'array'],
            'changes.*.user_id' => ['required', 'exists:users,id'],
            'changes.*.work_date' => ['required', 'date'],
            'changes.*.codes' => ['nullable', 'array'],
            'changes.*.codes.*.code' => ['required', 'string', 'max:10', 'exists:work_plan_legends,code'],
            'changes.*.codes.*.details' => ['nullable', 'string', 'max:2000'],
        ]);

        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY);

        if (! empty($validated['version']) && $validated['version'] !== $this->weekVersion($weekStart)) {
            $message = 'This weekly work plan was changed by someone else. Reload and try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return back()->withErrors(['work_plan' => $message])->withInput();
        }

        foreach ($validated['changes'] as $change) {
            $this->authorizeWrite((int) $change['user_id']);
        }

        $created = 0;
        $cancelled = 0;

        DB::transaction(function () use ($validated, &$created, &$cancelled) {
            foreach ($validated['changes'] as $change) {
                $dateString = Carbon::parse($change['work_date'])->toDateString();
                $codes = collect($change['codes'] ?? [])
                    ->map(fn (array $row) => [
                        'code' => $row['code'],
                        'details' => $row['details'] ?? null,
                    ])
                    ->unique('code')
                    ->values();

                $existing = WorkPlanTask::query()
                    ->where('user_id', $change['user_id'])
                    ->where('work_date', $dateString)
                    ->where('is_additional', false)
                    ->get();

                $active = $existing->where('status', '!=', WorkPlanTask::STATUS_CANCELLED);
                $activeCodes = $active->pluck('legend_code')->filter()->values()->all();

                foreach ($existing as $task) {
                    if ($task->status === WorkPlanTask::STATUS_CANCELLED) {
                        continue;
                    }

                    $removable = in_array($task->status, [
                        WorkPlanTask::STATUS_PENDING,
                        WorkPlanTask::STATUS_IN_PROGRESS,
                        WorkPlanTask::STATUS_ON_HOLD,
                        WorkPlanTask::STATUS_RETURNED_FOR_REVISION,
                    ], true);

                    if ($removable && $task->legend_code && ! $codes->contains('code', $task->legend_code)) {
                        $task->update(['status' => WorkPlanTask::STATUS_CANCELLED, 'edited_by' => auth()->id()]);
                        $cancelled++;
                    }
                }

                foreach ($codes as $row) {
                    $existingTask = $active->firstWhere('legend_code', $row['code']);

                    if ($existingTask) {
                        if ($request->has('changes') && $row['details'] !== null && $existingTask->details !== $row['details']) {
                            $existingTask->update(['details' => $row['details'], 'edited_by' => auth()->id()]);
                        }

                        continue;
                    }

                    if (in_array($row['code'], $activeCodes, true)) {
                        continue;
                    }

                    WorkPlanTask::create(array_merge([
                        'user_id' => $change['user_id'],
                        'work_date' => $dateString,
                        'legend_code' => $row['code'],
                        'details' => $row['details'],
                        'status' => WorkPlanTask::STATUS_PENDING,
                        'created_by' => auth()->id(),
                    ], $this->legendSnapshot($row['code'])));

                    $created++;
                }
            }
        });

        WorkPlanSetting::setLastUpdated(auth()->id());

        $version = $this->weekVersion($weekStart);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'saved',
                'created' => $created,
                'cancelled' => $cancelled,
                'version' => $version,
            ]);
        }

        return back()->with('status', 'Weekly work plan saved.');
    }

    /**
     * Record the weekly problem-solving evaluation. Only Admin/Supervisor
     * evaluations award points; the allowed values are 10, 7, 5, and 0.
     */
    public function evaluateProblemSolving(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrSupervisor(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', function ($attribute, $value, $fail) {
                $user = User::find($value);
                if ($user && ! in_array($user->role, [User::ROLE_STAFF, User::ROLE_SUPERVISOR], true)) {
                    $fail('Only staff and supervisors can be evaluated.');
                }
            }],
            'week_start' => ['required', 'date'],
            'problem_solving_score' => ['required', 'integer', 'in:10,7,5,0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $weekStart = WorkPlanScoringService::normalizeWeekStart($validated['week_start']);

        $evaluation = WorkPlanWeeklyEvaluation::query()
            ->where('user_id', $validated['user_id'])
            ->whereDate('week_start', $weekStart->toDateString())
            ->first() ?? new WorkPlanWeeklyEvaluation([
                'user_id' => $validated['user_id'],
                'week_start' => $weekStart->toDateString(),
            ]);

        $evaluation->fill([
            'problem_solving_score' => $validated['problem_solving_score'],
            'notes' => $validated['notes'] ?? null,
            'evaluated_by' => auth()->id(),
            'evaluated_at' => now(),
        ])->save();

        ActivityLog::record(
            $request->user(),
            'work_plan.problem_evaluated',
            "Evaluated problem solving for user #{$evaluation->user_id} as {$evaluation->problem_solving_score}/10 for the week of {$weekStart->format('M j, Y')}."
        );

        return back()->with('status', 'Problem-solving score saved.');
    }

    /**
     * Rate an additional/urgent activity on a 0-10 scale.
     */
    public function rateAdditional(Request $request, WorkPlanTask $task): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrSupervisor(), 403);
        abort_unless($task->isAdditional(), 422);

        $validated = $request->validate([
            'additional_rating' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $task->update([
            'additional_rating' => $validated['additional_rating'],
            'additional_rated_by' => auth()->id(),
            'additional_rated_at' => now(),
        ]);

        ActivityLog::record(
            $request->user(),
            'work_plan.additional_rated',
            "Rated additional activity '{$task->getLegendLabel()}' {$validated['additional_rating']}/10."
        );

        return back()->with('status', 'Additional activity rating saved.');
    }

    /**
     * Toggle the work plan editing lock (emergency override).
     */
    public function toggleLock(Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $currentlyEnabled = WorkPlanSetting::isEditingEnabled();

        if ($currentlyEnabled) {
            WorkPlanSetting::lock(auth()->id());

            ActivityLog::record(
                auth()->user(),
                'work_plan.locked',
                "Work plan editing locked by {$auth()->user()->name}. Reason: {$request->reason}"
            );

            return back()->with('status', 'Work plan editing has been locked.');
        } else {
            WorkPlanSetting::unlock(auth()->id());

            ActivityLog::record(
                auth()->user(),
                'work_plan.unlocked',
                "Work plan editing unlocked by {$auth()->user()->name}. Reason: {$request->reason}"
            );

            return back()->with('status', 'Work plan editing has been unlocked.');
        }
    }

    /**
     * Get tasks for a specific user on a specific date (AJAX).
     */
    public function tasksForDate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->get('date'));

        abort_unless(
            $request->user()->isAdminOrSupervisor() || (int) $request->user()->id === (int) $request->get('user_id'),
            403
        );

        $tasks = WorkPlanTask::query()
            ->where('user_id', $request->get('user_id'))
            ->where('work_date', $date->toDateString())
            ->with(['legend', 'creator', 'completer'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'tasks' => $tasks->map(function ($t) {
                return [
                    'id' => $t->id,
                    'legend_code' => $t->legend_code,
                    'custom_title' => $t->custom_title,
                    'description' => $t->description,
                    'details' => $t->details,
                    'success_criteria' => $t->success_criteria,
                    'status' => $t->status,
                    'legend_label' => $t->getLegendLabel(),
                    'client_name' => $t->client_name,
                    'client_code' => $t->client_code,
                    'completed_at' => $t->completed_at?->format('Y-m-d'),
                    'completed_by' => $t->completer?->name,
                ];
            }),
        ]);
    }

    /**
     * Get tasks for a specific user on a specific date including planned targets.
     */
    public function tasksWithTargets(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        abort_unless(
            $request->user()->isAdminOrSupervisor() || (int) $request->user()->id === (int) $request->get('user_id'),
            403
        );

        $date = Carbon::parse($request->get('date'));

        $tasks = WorkPlanTask::query()
            ->where('user_id', $request->get('user_id'))
            ->where('work_date', $date->toDateString())
            ->with(['legend', 'creator', 'completer'])
            ->orderBy('id')
            ->get();

        // Get planned targets from DailyGoal for this user on this date
        $plannedTargets = DailyGoal::scopeForDate($request->get('user_id'), $date)
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'description' => $g->description,
                'success_criteria' => $g->success_criteria,
            ]);

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'tasks' => $tasks->map(function ($t) {
                return [
                    'id' => $t->id,
                    'legend_code' => $t->legend_code,
                    'custom_title' => $t->custom_title,
                    'description' => $t->description,
                    'success_criteria' => $t->success_criteria,
                    'status' => $t->status,
                    'legend_label' => $t->getLegendLabel(),
                    'client_name' => $t->client_name,
                    'client_code' => $t->client_code,
                    'completed_at' => $t->completed_at?->format('Y-m-d'),
                    'completed_by' => $t->completer?->name,
                ];
            }),
            'planned_targets' => $plannedTargets,
        ]);
    }

    /**
     * Get incomplete tasks for a user on a specific date.
     */
    public function incompleteTasks(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->get('date'));

        $tasks = WorkPlanTask::query()
            ->where('user_id', $request->get('user_id'))
            ->where('work_date', $date->toDateString())
            ->where('is_additional', false)
            ->whereIn('status', WorkPlanTask::INCOMPLETE_STATUSES)
            ->with(['legend', 'creator'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'incomplete_count' => $tasks->count(),
            'tasks' => $tasks->map(function ($t) {
                return [
                    'id' => $t->id,
                    'legend_code' => $t->legend_code,
                    'custom_title' => $t->custom_title,
                    'description' => $t->description,
                    'status' => $t->status,
                    'legend_label' => $t->getLegendLabel(),
                ];
            }),
        ]);
    }

    /**
     * Calculate the 90/10 performance score for a user for a given week.
     *
     * 90%: Planned tasks completed.
     * 10%: Problem-solving (logged problems that were resolved).
     */
    public function performanceScore(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = WorkPlanScoringService::normalizeWeekStart($request->get('week_start'));
        $userId = (int) $request->get('user_id');

        $score = WorkPlanScoringService::forUserWeek($userId, $weekStart);

        return response()->json($score);
    }

    /**
     * Admin page listing every incomplete task for the selected week.
     */
    public function incompleteTasksPage(Request $request): View
    {
        WorkPlanSetting::populateCache();

        $weekStart = $request->get('week_start')
            ? Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now(config('app.timezone', 'Asia/Manila'))->startOfWeek(Carbon::MONDAY);

        $weekEnd = (clone $weekStart)->addDays(4);

        $tasks = WorkPlanTask::query()
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('is_additional', false)
            ->whereIn('status', WorkPlanTask::INCOMPLETE_STATUSES)
            ->with(['user', 'legend', 'problems'])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        return view('admin.work-plan.incomplete', [
            'tasks' => $tasks,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => (clone $weekStart)->subDays(7),
            'nextWeek' => (clone $weekStart)->addDays(7),
        ]);
    }

    /**
     * Admin page showing the weekly 90/10 score for every employee.
     */
    public function performancePage(Request $request): View
    {
        WorkPlanSetting::populateCache();

        $weekStart = $request->get('week_start')
            ? Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now(config('app.timezone', 'Asia/Manila'))->startOfWeek(Carbon::MONDAY);

        $weekEnd = (clone $weekStart)->addDays(4);

        $users = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->keyBy('id');

        $scores = WorkPlanScoringService::forAllUsers($weekStart)
            ->map(function (array $score) use ($users) {
                $score['user'] = $users->get($score['user_id']);

                return $score;
            })
            ->sortByDesc('overall_score')
            ->values();

        return view('admin.work-plan.performance', [
            'scores' => $scores,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => (clone $weekStart)->subDays(7),
            'nextWeek' => (clone $weekStart)->addDays(7),
        ]);
    }

    /**
     * Get incomplete tasks summary for a user for a week.
     */
    public function incompleteTasksSummary(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(4);
        $userId = $request->get('user_id');

        $tasks = WorkPlanTask::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('is_additional', false)
            ->whereIn('status', WorkPlanTask::INCOMPLETE_STATUSES)
            ->get();

        $incompleteByDate = $tasks->groupBy(function ($t) {
            return $t->work_date->toDateString();
        })->map(function ($tasks) {
            return $tasks->count();
        });

        $totalIncomplete = $tasks->count();
        $byStatus = $tasks->groupBy('status')->map(function ($tasks) {
            return $tasks->count();
        });

        return response()->json([
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'user_id' => $userId,
            'total_incomplete' => $totalIncomplete,
            'incomplete_by_date' => $incompleteByDate,
            'incomplete_by_status' => $byStatus,
            'details' => $tasks->map(function ($t) {
                return [
                    'date' => $t->work_date,
                    'legend_code' => $t->legend_code,
                    'status' => $t->status,
                    'legend_label' => $t->getLegendLabel(),
                ];
            }),
        ]);
    }

    private function auditValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value)
            ->all();
    }
}
