<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyGoal;
use App\Models\DailyGoalVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DailyGoalController extends Controller
{
    /**
     * Display the weekly goals management screen.
     *
     * Shows all active goals grouped by week for admin review and editing.
     */
    public function index(Request $request): View
    {
        Gate::authorize('adminOnly');

        $weekStart = $request->get('week_start')
            ? Carbon::parse($request->get('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = (clone $weekStart)->addDays(4);

        $goals = DailyGoal::query()
            ->whereBetween('week_start_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->with(['user', 'creator', 'editor'])
            ->orderBy('user_id')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        $employees = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $grouped = $goals->groupBy('user_id');

        return view('admin.daily-goals.index', [
            'goals' => $goals,
            'grouped' => $grouped,
            'employees' => $employees,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => (clone $weekStart)->subDays(7),
            'nextWeek' => (clone $weekStart)->addDays(7),
        ]);
    }

    /**
     * Create a new daily goal (or multiple goals for a week).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('adminOnly');

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', function ($attribute, $value, $fail) {
                $user = User::find($value);
                if ($user && ! in_array($user->role, [User::ROLE_STAFF, User::ROLE_SUPERVISOR], true)) {
                    $fail('Only staff and supervisors can be assigned goals.');
                }
            }],
            'week_start_date' => ['required', 'date'],
            'target_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'success_criteria' => ['nullable', 'string', 'max:500'],
        ]);

        $weekStart = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY);
        $targetDate = Carbon::parse($validated['target_date']);

        // Validate target_date falls within the week_start week (Mon-Fri)
        if ($targetDate->lt($weekStart) || $targetDate->gt((clone $weekStart)->addDays(4))) {
            return back()->withErrors([
                'target_date' => 'Target date must fall within the selected workweek (Monday–Friday).',
            ])->withInput();
        }

        $goal = DailyGoal::create([
            'user_id'         => $validated['user_id'],
            'week_start_date' => $weekStart->toDateString(),
            'target_date'     => $targetDate->toDateString(),
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'success_criteria'=> $validated['success_criteria'] ?? null,
            'status'          => DailyGoal::STATUS_ACTIVE,
            'created_by'      => auth()->id(),
            'effective_from'  => now(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'daily_goal.created',
            "Created daily goal '{$goal->title}' for {$goal->user->name} on {$goal->target_date->format('F j, Y')}."
        );

        return back()->with('status', 'Goal created successfully.');
    }

    /**
     * Return goal data for the edit form (AJAX).
     */
    public function edit(DailyGoal $goal)
    {
        Gate::authorize('adminOnly');

        $goal->load(['user', 'creator', 'editor', 'versions.changer']);

        return response()->json([
            'id' => $goal->id,
            'user_id' => $goal->user_id,
            'week_start_date' => $goal->week_start_date?->format('Y-m-d'),
            'target_date' => $goal->target_date?->format('Y-m-d'),
            'title' => $goal->title,
            'description' => $goal->description,
            'success_criteria' => $goal->success_criteria,
            'status' => $goal->status,
            'versions' => $goal->versions->map(function ($v) {
                return [
                    'changed_at' => $v->changed_at?->format('Y-m-d H:i'),
                    'changed_by' => $v->changer?->name,
                    'change_reason' => $v->change_reason,
                    'previous_values' => $v->previous_values,
                    'new_values' => $v->new_values,
                ];
            }),
        ]);
    }

    /**
     * Update an existing goal. Records version history before changing values.
     */
    public function update(Request $request, DailyGoal $goal): RedirectResponse
    {
        Gate::authorize('adminOnly');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'success_criteria' => ['nullable', 'string', 'max:500'],
            'target_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:active,completed,cancelled'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $targetDate = Carbon::parse($validated['target_date']);
        $weekStart = $goal->week_start_date;

        // Validate target_date still falls within the goal's week
        if ($targetDate->lt($weekStart) || $targetDate->gt((clone $weekStart)->addDays(4))) {
            return back()->withErrors([
                'target_date' => 'Target date must fall within the goal\'s workweek (Monday–Friday).',
            ])->withInput();
        }

        // Capture previous values for version history
        $previousValues = [
            'title' => $goal->title,
            'description' => $goal->description,
            'success_criteria' => $goal->success_criteria,
            'target_date' => $goal->target_date?->format('Y-m-d'),
            'status' => $goal->status,
        ];

        $newValues = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'success_criteria' => $validated['success_criteria'] ?? null,
            'target_date' => $targetDate->toDateString(),
            'status' => $validated['status'],
        ];

        $goal->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'success_criteria' => $validated['success_criteria'] ?? null,
            'target_date' => $targetDate->toDateString(),
            'status' => $validated['status'],
            'edited_by' => auth()->id(),
            'previous_values' => $previousValues,
            'effective_from' => now(),
        ]);

        // Record version history
        DailyGoalVersion::create([
            'daily_goal_id' => $goal->id,
            'previous_values' => $previousValues,
            'new_values' => $newValues,
            'change_reason' => $validated['change_reason'] ?? null,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'daily_goal.updated',
            "Updated daily goal '{$goal->title}' for {$goal->user->name} on {$goal->target_date->format('F j, Y')}."
        );

        return back()->with('status', 'Goal updated successfully.');
    }

    /**
     * Delete (cancel) a goal.
     */
    public function destroy(DailyGoal $goal): RedirectResponse
    {
        Gate::authorize('adminOnly');

        $goal->update([
            'status' => DailyGoal::STATUS_CANCELLED,
            'edited_by' => auth()->id(),
            'effective_from' => now(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'daily_goal.cancelled',
            "Cancelled daily goal '{$goal->title}' for {$goal->user->name} on {$goal->target_date->format('F j, Y')}."
        );

        return back()->with('status', 'Goal cancelled.');
    }

    /**
     * Return active goals for a specific user on a specific date.
     * Used by the Daily Accomplishment Monitor to show "Goals for the Day".
     */
    public function goalsForDate(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->get('date'));

        $goals = DailyGoal::query()
            ->where('user_id', $request->get('user_id'))
            ->where('target_date', $date->toDateString())
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->with(['creator'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'goals' => $goals->map(function ($g) {
                return [
                    'id' => $g->id,
                    'title' => $g->title,
                    'description' => $g->description,
                    'success_criteria' => $g->success_criteria,
                    'target_date' => $g->target_date?->format('Y-m-d'),
                    'status' => $g->status,
                    'status_label' => $g->statusLabel(),
                    'created_by' => $g->creator?->name,
                    'created_at' => $g->created_at?->format('Y-m-d H:i'),
                ];
            }),
        ]);
    }
}
