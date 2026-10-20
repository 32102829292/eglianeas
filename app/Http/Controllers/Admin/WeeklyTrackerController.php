<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Weekly Tracker — the single weekly feature.
 *
 * It renders the client's spreadsheet: one row per task with the Target Date,
 * Task Type, Client Name, the four stages (Pick-Up / Record / Return / Billing)
 * shown as Actual vs Target, and the Payment Date. It is backed by the existing
 * weekly_bookkeeping + weekly_bookkeeping_targets tables so every record that
 * was already captured stays where it is; the standalone weekly_tracker_tasks
 * table from the earlier draft was removed once this consolidated page existed.
 *
 * The controller keeps the admin.weekly-tracker.* route names, so navigation,
 * breadcrumbs and redirects all point at the unified feature.
 */
class WeeklyTrackerController extends Controller
{
    /**
     * The four stages of a weekly task, mapped onto the existing table columns.
     * Each entry names the task_type it belongs to, the label shown in the
     * column header, and the statuses accepted for that stage.
     */
    public const STAGES = [
        'pickup' => [
            'label' => 'Pick-Up',
            'statuses' => [
                WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING => 'Pending',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS => 'In Progress',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME => 'On Time',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED => 'Completed',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_MISSED => 'Missed',
            ],
            'done' => WeeklyBookkeepingTarget::ACTUAL_STATUS_ON_TIME,
            'done_label' => 'On Time',
        ],
        'record' => [
            'label' => 'Record',
            'statuses' => [
                WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING => 'Pending',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS => 'In Progress',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED => 'Completed',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_MISSED => 'Missed',
            ],
            'done' => WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED,
            'done_label' => 'Completed',
        ],
        'return' => [
            'label' => 'Return / Collect',
            'statuses' => [
                WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING => 'Pending',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS => 'In Progress',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_RETURNED => 'Returned',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_UNRETURNED => 'Unreturned',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_MISSED => 'Missed',
            ],
            'done' => WeeklyBookkeepingTarget::ACTUAL_STATUS_RETURNED,
            'done_label' => 'Returned',
        ],
        'payment' => [
            'label' => 'Billing / Payment',
            'statuses' => [
                WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING => 'Pending',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_IN_PROGRESS => 'In Progress',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_PAID => 'Paid',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_UNPAID => 'Unpaid',
                WeeklyBookkeepingTarget::ACTUAL_STATUS_MISSED => 'Missed',
            ],
            'done' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PAID,
            'done_label' => 'Paid',
        ],
    ];

    public function index(Request $request): View
    {
        $user = auth()->user();

        $weekParam = (string) $request->get('week_start');
        $weekStart = $weekParam !== ''
            ? Carbon::parse($weekParam)->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $clientFilter = (int) $request->get('client');
        $taskType = (string) $request->get('task_type');
        $dateFilter = (string) $request->get('target_date');

        // Each row is one weekly task. Visibility follows the same rule the old
        // bookkeeping pages used, so staff only ever see their own work while
        // admins and supervisors see the whole week.
        $plans = WeeklyBookkeeping::query()
            ->visibleTo($user)
            ->whereDate('week_start', $weekStart->format('Y-m-d'))
            ->pluck('id');

        $query = WeeklyBookkeepingTarget::query()
            ->with(['client', 'assignedStaff'])
            ->whereIn('weekly_bookkeeping_id', $plans)
            ->when($clientFilter, fn ($q) => $q->where('client_id', $clientFilter))
            ->when($taskType !== '', fn ($q) => $q->where('task_type', $taskType))
            ->when($dateFilter !== '', fn ($q) => $q->whereDate('target_date', $dateFilter));

        $targets = $query
            ->orderBy('target_date')
            ->orderBy('client_id')
            ->orderBy('task_type')
            ->get();

        /* The four stage columns need every one of this client's stages for the
           week, not just the one the row is banded under, so the payment
           roll-ups are resolved once here instead of per cell. */
        WeeklyBookkeepingTarget::primeUserRelations($targets);
        WeeklyBookkeepingTarget::primePaidBillings($targets);

        /* client_id => task_type => target, so each client row can fill its
           Pick-Up / Record / Return / Billing columns in one lookup. */
        $weekByClient = $targets
            ->groupBy('client_id')
            ->map(fn ($group) => $group->keyBy('task_type'));

        // Group the week's tasks under their weekday header (Mon–Sun). Days with
        // no work are kept so the board always reads as a full week. Within a
        // day the clients are grouped by the stage due that day — Pick-Up,
        // Record, Return / Collect, Billing / Payment — and every row carries
        // all four stage columns plus the Payment Date, like the workbook.
        $stageOrder = array_keys(self::STAGES);

        $days = collect();
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayTasks = $targets
                ->filter(fn (WeeklyBookkeepingTarget $t) => $t->target_date?->isSameDay($day))
                ->values();

            $stageGroups = collect($stageOrder)
                ->map(function (string $stageKey) use ($day, $dayTasks, $weekByClient) {
                    $bandTasks = $dayTasks->where('task_type', $stageKey)->values();

                    return [
                        'key' => $stageKey,
                        'label' => self::STAGES[$stageKey]['label'],
                        'tasks' => $bandTasks,
                        'rows' => $this->buildClientRows($bandTasks, $weekByClient, $stageKey),
                        'form_id' => 'wt-bulk-'.$day->format('Ymdd').'-'.$stageKey,
                    ];
                })
                // Only stages that actually have work on this day keep a band,
                // but a band with no work is still shown when the day has any
                // task, so the day's stage order stays obvious.
                ->filter(fn (array $group) => $dayTasks->isNotEmpty())
                ->values();

            $days->push([
                'date' => $day,
                'is_today' => $day->isSameDay(now()),
                'tasks' => $dayTasks,
                'stage_groups' => $stageGroups,
            ]);
        }

        $stats = [
            'tasks' => $targets->count(),
            'clients' => $targets->pluck('client_id')->unique()->count(),
            'pickup' => $targets->where('task_type', 'pickup')->filter->isCompleted()->count(),
            'record' => $targets->where('task_type', 'record')->filter->isCompleted()->count(),
            'return' => $targets->where('task_type', 'return')->filter->isCompleted()->count(),
            'billing' => $targets->where('task_type', 'payment')->filter->isCompleted()->count(),
            'completed' => $targets->filter(fn (WeeklyBookkeepingTarget $t) => $t->isCompleted())->count(),
        ];

        return view('admin.weekly-tracker.index', [
            'days' => $days,
            'targets' => $targets,
            'stats' => $stats,
            'clients' => $this->clients(),
            'taskTypes' => WeeklyBookkeepingTarget::TASK_TYPES,
            'stages' => self::STAGES,
            'paymentMethods' => WeeklyBookkeepingTarget::PAYMENT_METHODS,
            'activeWeekStart' => $weekStart->format('Y-m-d'),
            'weekStartCarbon' => $weekStart,
            'weekEndCarbon' => $weekEnd,
            // Reference-style heading, e.g. "2nd Week of October 2026".
            'weekHeading' => self::weekHeading($weekStart),
            'prevWeekStart' => $weekStart->copy()->subWeek()->format('Y-m-d'),
            'nextWeekStart' => $weekStart->copy()->addWeek()->format('Y-m-d'),
            'currentWeekStart' => now()->copy()->startOfWeek()->format('Y-m-d'),
            'isCurrentWeek' => $weekStart->isSameWeek(now()),
            'activeClient' => $clientFilter ?: null,
            'activeTaskType' => $taskType,
            'activeDate' => $dateFilter,
            'hasActiveFilters' => $clientFilter > 0 || $taskType !== '' || $dateFilter !== '',
        ]);
    }

    /**
     * One row per client inside a stage band.
     *
     * The row is anchored on the band's own task (the stage due that day) and
     * then filled across the client's other stages for the same week, so the
     * grid reads as "row = client, four stage columns" without inventing any
     * record: every cell comes from an existing target.
     *
     * @param  \Illuminate\Support\Collection<int, WeeklyBookkeepingTarget>  $bandTasks
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<string, WeeklyBookkeepingTarget>>  $weekByClient
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildClientRows($bandTasks, $weekByClient, string $stageKey): Collection
    {
        return $bandTasks
            ->groupBy('client_id')
            ->map(function ($tasks, $clientId) use ($weekByClient, $stageKey) {
                /* One (client, stage) pair per plan is the standing rule, so the
                   first task in the group is the row's anchor. */
                $anchor = $tasks->first();

                $stages = collect(self::STAGES)->map(function (array $config, string $key) use ($weekByClient, $clientId) {
                    $sibling = $weekByClient->get($clientId)?->get($key);

                    if ($sibling === null) {
                        return [
                            'has' => false,
                            'target' => null,
                            'label' => '—',
                            'done' => false,
                            'date_label' => null,
                        ];
                    }

                    return [
                        'has' => true,
                        'target' => $sibling,
                        'label' => $sibling->cellLabel(),
                        'done' => $sibling->isCompleted(),
                        'date_label' => $sibling->target_date?->format('M j'),
                    ];
                });

                $paymentTarget = $stages->get('payment')['target'];

                return [
                    'client_id' => (int) $clientId,
                    'client_name' => $anchor->displayClientName(),
                    'target' => $anchor,
                    'stages' => $stages,
                    'current_stage' => $stageKey,
                    'staff_name' => $anchor->assignedStaffDisplayName(),
                    'has_notes' => filled($anchor->notes) || $tasks->contains(fn (WeeklyBookkeepingTarget $t) => filled($t->notes)),
                    'payment_date_label' => $paymentTarget?->payment_at?->format('M j, Y'),
                    'payment_label' => $paymentTarget?->paymentLabel(),
                ];
            })
            ->sortBy(fn (array $row) => $row['client_name'])
            ->values();
    }

    /**
     * The ordinal week label for a week, e.g. "2nd Week of October 2026".
     *
     * The week is named for the month its FIRST day (Monday) falls in, using the
     * workbook's 1st/2nd/3rd/4th/5th ordinal. A week that straddles a month
     * boundary belongs to the month it starts in, which matches how the sheets
     * were labelled by hand.
     */
    public static function weekHeading(Carbon $weekStart): string
    {
        $start = $weekStart->copy()->startOfWeek();
        $ordinalWeek = (int) ceil($start->day / 7);

        $ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'];

        return sprintf(
            '%s Week of %s',
            $ordinals[$ordinalWeek] ?? $ordinalWeek.'th',
            $start->format('F Y')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_CLIENT)],
            'target_date' => ['required', 'date'],
            'task_type' => ['required', Rule::in(array_keys(WeeklyBookkeepingTarget::TASK_TYPES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $targetDate = Carbon::parse($validated['target_date']);
        $weekStart = $targetDate->copy()->startOfWeek();

        // The plan is the week itself; it is created on first use and reused
        // after that, so a week keeps a single owning row. Looked up by date to
        // sidestep the stored time component on the unique week_start column.
        $plan = WeeklyBookkeeping::query()
            ->whereDate('week_start', $weekStart->format('Y-m-d'))
            ->first();

        if ($plan === null) {
            $plan = WeeklyBookkeeping::query()->create([
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekStart->copy()->endOfWeek()->format('Y-m-d'),
                'staff_id' => auth()->id(),
                'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
            ]);
        }

        $exists = WeeklyBookkeepingTarget::query()
            ->where('weekly_bookkeeping_id', $plan->id)
            ->where('client_id', $validated['client_id'])
            ->where('task_type', $validated['task_type'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'task_type' => 'A task for this client and task type already exists this week.',
            ])->withInput();
        }

        $target = WeeklyBookkeepingTarget::query()->create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $validated['client_id'],
            'task_type' => $validated['task_type'],
            'target_date' => $targetDate->format('Y-m-d'),
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
        ]);

        $plan->syncOverallStatus();

        ActivityLog::record(
            auth()->user(),
            'weekly_tracker.task_created',
            sprintf(
                'Weekly tracker task added: %s — %s on %s.',
                $target->displayClientName(),
                $target->taskLabel(),
                $target->target_date->format('M j, Y')
            ),
            null,
            $plan
        );

        return redirect()
            ->route('admin.weekly-tracker.index', ['week_start' => $weekStart->format('Y-m-d')])
            ->with('status', 'Task added to the weekly tracker.');
    }

    public function updateStage(Request $request, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(array_keys(self::STAGES))],
            'status' => ['required', 'string'],
            'payment_method' => ['nullable', Rule::in(array_keys(WeeklyBookkeepingTarget::PAYMENT_METHODS))],
            'payment_date' => ['nullable', 'date'],
        ]);

        if ($target->task_type !== $validated['stage']) {
            return back()->withErrors(['stage' => 'That stage does not belong to this task.']);
        }

        $stage = $validated['stage'];
        $config = self::STAGES[$stage];

        if (! array_key_exists($validated['status'], $config['statuses'])) {
            return back()->withErrors(['status' => 'The selected status is not valid for this stage.']);
        }

        $result = $this->applyStage($target, $validated, auth()->user());

        return back()->with('status', $result['message']);
    }

    /**
     * Applies one stage change to every client row the planner ticked.
     *
     * The checkboxes carry the anchor task of each row, which belongs to the
     * band's own stage, so a stale selection from another band is skipped
     * rather than rewritten. Anything the actor may not manage is skipped the
     * same way, so a bulk submit can never reach a colleague's task.
     */
    public function bulkUpdateStage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_ids' => ['required', 'array', 'min:1'],
            'target_ids.*' => ['required', 'integer', 'exists:weekly_bookkeeping_targets,id'],
            'stage' => ['required', Rule::in(array_keys(self::STAGES))],
            'status' => ['required', 'string'],
            'payment_method' => ['nullable', Rule::in(array_keys(WeeklyBookkeepingTarget::PAYMENT_METHODS))],
            'payment_date' => ['nullable', 'date'],
        ]);

        $stage = $validated['stage'];
        $config = self::STAGES[$stage];

        if (! array_key_exists($validated['status'], $config['statuses'])) {
            return back()->withErrors(['status' => 'The selected status is not valid for this stage.']);
        }

        $targets = WeeklyBookkeepingTarget::query()
            ->with('client')
            ->whereIn('id', array_unique(array_map('intval', $validated['target_ids'])))
            ->where('task_type', $stage)
            ->get();

        $updated = 0;
        $skipped = 0;
        $autoStarted = 0;

        foreach ($targets as $target) {
            if (! $this->mayManageTarget($target)) {
                $skipped++;

                continue;
            }

            $result = $this->applyStage($target, $validated, auth()->user());

            $updated++;
            $autoStarted += $result['auto_started'] ? 1 : 0;
        }

        if ($updated === 0) {
            return back()->withErrors([
                'action' => 'No clients were updated. Tick at least one client row for '
                    .$config['label'].' that you are allowed to manage.',
            ]);
        }

        $message = sprintf(
            '%s updated for %d %s.',
            $config['label'],
            $updated,
            \Illuminate\Support\Str::plural('client', $updated)
        );

        if ($autoStarted > 0) {
            $next = self::STAGES[$targets->first()->nextStage() ?? ''] ?? null;
            $message .= sprintf(
                ' %d %s automatically started.',
                $autoStarted,
                $next ? \Illuminate\Support\Str::plural(mb_strtolower($next['label']), $autoStarted) : 'next stage'
            );
        }

        if ($skipped > 0) {
            $message .= sprintf(' %d skipped (not yours to manage).', $skipped);
        }

        return back()->with('status', $message);
    }

    /**
     * Writes one stage change onto one target and moves the chain along.
     *
     * Shared by the single-row cell and the bulk bar so both go through the
     * same rules: the stage's own status wording, Payment Date only on the
     * Billing stage, the performed-by trail, and an automatic start of the next
     * stage once this one is closed out.
     *
     * @return array{message: string, auto_started: bool}
     */
    private function applyStage(WeeklyBookkeepingTarget $target, array $validated, User $user): array
    {
        $stage = $validated['stage'];
        $config = self::STAGES[$stage];
        $status = $validated['status'];
        $isDone = $status === $config['done'];

        $attributes = [
            'actual_status' => $status,
        ];

        if ($isDone) {
            $attributes['ended_at'] = now();
            $attributes['performed_by_id'] = $target->performed_by_id ?? $user->id;
            $attributes['performed_by_name'] = $target->performed_by_name ?? $user->name;
            $attributes['performed_by_role'] = $target->performed_by_role ?? $user->role;
        } else {
            $attributes['ended_at'] = null;
            $attributes['timing'] = null;
        }

        if ($stage === 'payment') {
            $attributes['payment_method'] = $isDone
                ? ($validated['payment_method'] ?? null)
                : null;

            $paymentDate = $validated['payment_date'] ?? null;

            $attributes['payment_at'] = $isDone
                ? ($paymentDate !== null && $paymentDate !== '' ? Carbon::parse($paymentDate) : now())
                : null;
        }

        $target->update($attributes);

        // Timing is derived once the status/date are on the model.
        $target->update(['timing' => $target->resolveTiming()]);

        $target->weeklyBookkeeping?->syncOverallStatus();

        /* Closing a stage starts the next one so the Pick-Up → Record →
           Return → Billing chain keeps moving without anyone pressing Start on
           each following task. It only ever moves Pending → In Progress, so a
           person still records the actual result. */
        $autoStarted = $isDone ? $target->startNextStage($user) : null;

        $message = sprintf(
            '%s — %s %s for %s.',
            $target->displayClientName(),
            $config['label'],
            strtolower($config['statuses'][$status]),
            $target->target_date->format('M j, Y')
        );

        if ($autoStarted !== null) {
            $message .= ' '.self::STAGES[$autoStarted]['label'].' started automatically.';
        }

        ActivityLog::record(
            $user,
            'weekly_tracker.stage_updated',
            $message,
            null,
            $target->weeklyBookkeeping
        );

        return [
            'message' => $config['label'].' updated.',
            'auto_started' => $autoStarted !== null,
        ];
    }

    /**
     * Task-level accountability, mirroring the weekly bookkeeping plan: a staff
     * member only works on tasks assigned to them, while admins and
     * supervisors cover the whole week.
     */
    private function authorizeManageTarget(WeeklyBookkeepingTarget $target): void
    {
        abort_unless(
            $this->mayManageTarget($target),
            403,
            'This task is assigned to '.($target->assignedStaffDisplayName() !== '' ? $target->assignedStaffDisplayName() : 'someone else').'.'
        );
    }

    private function mayManageTarget(WeeklyBookkeepingTarget $target): bool
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return $target->isAssignedTo($user);
    }

    public function update(Request $request, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $target->update([
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        ActivityLog::record(
            auth()->user(),
            'weekly_tracker.task_updated',
            sprintf('Weekly tracker notes updated for %s — %s.', $target->displayClientName(), $target->taskLabel()),
            null,
            $target->weeklyBookkeeping
        );

        return back()->with('status', 'Notes updated.');
    }

    public function destroy(WeeklyBookkeepingTarget $target): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete tracker tasks.');

        $plan = $target->weeklyBookkeeping;

        ActivityLog::record(
            auth()->user(),
            'weekly_tracker.task_deleted',
            sprintf(
                'Weekly tracker task deleted: %s — %s on %s.',
                $target->displayClientName(),
                $target->taskLabel(),
                $target->target_date?->format('M j, Y') ?? '—'
            ),
            null,
            $plan
        );

        $target->delete();

        $plan?->syncOverallStatus();

        return back()->with('status', 'Task deleted.');
    }

    /**
     * Approved, non-deleted client accounts for the pickers.
     *
     * @return Collection<int, User>
     */
    private function clients(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->whereNotNull('approved_at')
            ->whereNull('declined_at')
            ->whereNull('deleted_at')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get(['id', 'name', 'business_name', 'client_code']);
    }
}
