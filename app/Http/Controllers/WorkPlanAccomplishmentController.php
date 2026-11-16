<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkPlanAudit;
use App\Models\WorkPlanLegend;
use App\Models\WorkPlanSetting;
use App\Models\WorkPlanTask;
use App\Models\WorkPlanTaskProblem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class WorkPlanAccomplishmentController extends Controller
{
    public function index(Request $request): View
    {
        $weekStart = $request->get('week_start')
            ? Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now(config('app.timezone', 'Asia/Manila'))->startOfWeek(Carbon::MONDAY);

        $weekEnd = (clone $weekStart)->addDays(4);
        $user = $request->user();
        $isAdmin = $user->role === User::ROLE_ADMIN;
        $canSelectStaff = $user->isAdminOrSupervisor();

        $tasks = WorkPlanTask::query()
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->visibleTo($user)
            ->with(['user', 'legend', 'problems.reporter', 'problems.resolver'])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        $plannedTasks = $tasks->filter(fn (WorkPlanTask $task) => ! $task->isAdditional());
        $additionalTasks = $tasks->filter(fn (WorkPlanTask $task) => $task->isAdditional());

        $employees = $canSelectStaff
            ? User::query()
                ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
            : collect();

        return view('work-plan.accomplishment', [
            'tasksByDate' => $plannedTasks->groupBy(fn (WorkPlanTask $task) => $task->work_date->toDateString()),
            'additionalByDate' => $additionalTasks->groupBy(fn (WorkPlanTask $task) => $task->work_date->toDateString()),
            'employees' => $employees,
            'legends' => WorkPlanLegend::active()->orderBy('code')->get(),
            'weekDates' => collect(range(0, 4))->map(fn (int $offset) => $weekStart->copy()->addDays($offset)),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => (clone $weekStart)->subDays(7),
            'nextWeek' => (clone $weekStart)->addDays(7),
            'isAdmin' => $isAdmin,
            'canSelectStaff' => $canSelectStaff,
            'canEvaluate' => $user->isAdminOrSupervisor(),
            'editingEnabled' => WorkPlanSetting::isEditingEnabled(),
        ]);
    }

    public function update(Request $request, WorkPlanTask $task): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        $allowed = ['pending', 'in_progress', 'completed', 'cancelled', 'on_hold'];

        if ($request->user()->isAdminOrSupervisor()) {
            $allowed[] = WorkPlanTask::STATUS_RETURNED_FOR_REVISION;
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $allowed)],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'string', 'max:2000'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'returned_reason' => ['nullable', 'string', 'max:1000'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === WorkPlanTask::STATUS_COMPLETED
            && $task->requiresDetails()
            && trim((string) ($validated['details'] ?? $task->details)) === '') {
            return back()->withErrors([
                'details' => 'Details are required before this activity can be marked Completed.',
            ])->withInput();
        }

        $previousValues = $task->only(['status', 'completion_notes', 'details', 'evidence_path', 'returned_reason']);

        $task->status = $validated['status'];
        $task->completion_notes = $validated['completion_notes'] ?? null;
        $task->details = $validated['details'] ?? $task->details;
        $task->evidence_path = $validated['evidence_path'] ?? null;
        $task->edited_by = $request->user()->id;

        if ($task->status === WorkPlanTask::STATUS_COMPLETED) {
            $task->completed_at = Carbon::today()->toDateString();
            $task->completed_by = $request->user()->id;
            $task->returned_reason = null;

            // Additional/urgent activities default to a 9/10 rating once
            // completed; Admin/Supervisor may still override it separately.
            if ($task->isAdditional() && $task->additional_rating === null) {
                $task->additional_rating = 9;
                $task->additional_rated_at = now();
            }
        } else {
            $task->completed_at = null;
            $task->completed_by = null;

            if ($task->status === WorkPlanTask::STATUS_RETURNED_FOR_REVISION) {
                $task->returned_reason = $validated['returned_reason'] ?? null;
                $task->reviewed_by = $request->user()->id;
                $task->reviewed_at = now();
            } else {
                $task->returned_reason = null;
            }
        }

        $task->save();

        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'accomplishment_updated',
            'previous_values' => $previousValues,
            'new_values' => $task->only(['status', 'completion_notes', 'evidence_path', 'returned_reason']),
            'change_reason' => $validated['change_reason'] ?? null,
            'changed_at' => now(),
        ]);

        WorkPlanSetting::setLastUpdated($request->user()->id);

        ActivityLog::record(
            $request->user(),
            'work_plan_task.accomplishment_updated',
            "Recorded accomplishment for '{$task->getLegendLabel()}' ({$task->status})."
        );

        return back()->with('status', 'Accomplishment saved.');
    }

    public function storeProblem(Request $request, WorkPlanTask $task): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'proposed_solution' => ['nullable', 'string', 'max:2000'],
        ]);

        $problem = WorkPlanTaskProblem::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $request->user()->id,
            'description' => $validated['description'],
            'proposed_solution' => $validated['proposed_solution'] ?? null,
            'status' => WorkPlanTaskProblem::STATUS_OPEN,
        ]);

        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'problem_logged',
            'new_values' => $problem->only(['description', 'proposed_solution', 'status']),
            'change_reason' => 'Problem logged on accomplishment',
            'changed_at' => now(),
        ]);

        return back()->with('status', 'Problem logged.');
    }

    public function resolveProblem(Request $request, WorkPlanTask $task, WorkPlanTaskProblem $problem): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        abort_unless((int) $problem->work_plan_task_id === (int) $task->id, 404);

        $validated = $request->validate([
            'proposed_solution' => ['nullable', 'string', 'max:2000'],
        ]);

        $problem->status = WorkPlanTaskProblem::STATUS_RESOLVED;
        $problem->resolved_at = now();
        $problem->resolved_by = $request->user()->id;

        if (! empty($validated['proposed_solution'])) {
            $problem->proposed_solution = $validated['proposed_solution'];
        }

        $problem->save();

        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'problem_resolved',
            'new_values' => $problem->only(['description', 'proposed_solution', 'status', 'resolved_at']),
            'change_reason' => 'Problem resolved',
            'changed_at' => now(),
        ]);

        return back()->with('status', 'Problem marked as resolved.');
    }

    /**
     * Log an additional/urgent activity. Kept separate from planned tasks and
     * excluded from the 90-point planned completion score.
     */
    public function storeAdditional(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'legend_code' => ['nullable', 'string', 'max:10', 'exists:work_plan_legends,code'],
            'custom_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $target = $user;

        if ($user->isAdminOrSupervisor() && ! empty($validated['user_id'])) {
            $target = User::findOrFail($validated['user_id']);
        }

        abort_unless(in_array($target->role, [User::ROLE_STAFF, User::ROLE_SUPERVISOR], true), 403);

        if ($target->id !== $user->id) {
            abort_unless($user->isAdminOrSupervisor(), 403);
        }

        $workDate = Carbon::parse($validated['work_date']);

        if ($workDate->isWeekend()) {
            return back()->withErrors([
                'work_date' => 'Additional activities can only be logged on weekdays (Monday-Friday).',
            ])->withInput();
        }

        if (empty($validated['legend_code']) && empty($validated['custom_title'])) {
            return back()->withErrors([
                'legend_code' => 'Please select an activity code or enter a title.',
            ])->withInput();
        }

        $legend = ! empty($validated['legend_code'])
            ? WorkPlanLegend::query()->where('code', $validated['legend_code'])->first()
            : null;

        $task = WorkPlanTask::create([
            'user_id' => $target->id,
            'work_date' => $workDate->toDateString(),
            'legend_code' => $validated['legend_code'] ?? null,
            'legend_name_snapshot' => $legend?->name,
            'legend_category_snapshot' => $legend?->category,
            'custom_title' => $validated['custom_title'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_additional' => true,
            'status' => WorkPlanTask::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        WorkPlanAudit::create([
            'work_plan_task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'additional_created',
            'new_values' => $task->only(['legend_code', 'custom_title', 'description', 'is_additional', 'status']),
            'change_reason' => 'Additional/urgent activity logged',
            'changed_at' => now(),
        ]);

        ActivityLog::record(
            $user,
            'work_plan.additional_created',
            "Logged additional/urgent activity '{$task->getLegendLabel()}' for {$target->name}."
        );

        return back()->with('status', 'Additional/urgent activity logged.');
    }

    private function authorizeTask(Request $request, WorkPlanTask $task): void
    {
        $user = $request->user();

        if ($user->role === User::ROLE_ADMIN) {
            return;
        }

        abort_unless((int) $task->user_id === (int) $user->id, 403);
    }
}
