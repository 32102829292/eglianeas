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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeeklyBookkeepingController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor();

        $q = trim((string) $request->get('q'));
        $weekStart = $request->get('week_start');
        $staffFilter = (int) $request->get('staff');
        $view = (string) $request->get('view', 'week');
        $taskType = $request->get('task_type');

        // Week selection defaults to the most recent planned week so the screen
        // opens on real data rather than an empty current week.
        if ($weekStart === null || $weekStart === '') {
            $weekStart = WeeklyBookkeeping::query()
                ->visibleTo($user)
                ->orderByDesc('week_start')
                ->value('week_start');
            $weekStart = $weekStart ? Carbon::parse($weekStart)->format('Y-m-d') : now()->startOfWeek()->format('Y-m-d');
        }

        $query = WeeklyBookkeeping::query()
            ->visibleTo($user)
            ->with(['targets.client', 'targets.assignedStaff', 'targets.performedBy'])
            ->when($weekStart, fn ($query) => $query->whereDate('week_start', $weekStart))
            ->when($staffFilter, fn ($query) => $query->whereHas(
                'targets',
                fn ($t) => $t->where('assigned_staff_id', $staffFilter)
            ))
            ->when($taskType, fn ($query) => $query->whereHas('targets', fn ($t) => $t->where('task_type', $taskType)))
            ->when($q !== '', fn ($query) => $query->whereHas('targets.client', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('business_name', 'like', "%{$q}%");
            }));

        $plans = $query->orderByDesc('week_start')->orderByDesc('id')->paginate(50)->withQueryString();

        $owners = $this->assignableStaff();

        $weeks = WeeklyBookkeeping::query()
            ->visibleTo($user)
            ->select('week_start')
            ->distinct()
            ->orderByDesc('week_start')
            ->limit(30)
            ->pluck('week_start');

        // ---- Weekly grid: one row per target, laid out like the workbook ----
        $allTargets = WeeklyBookkeepingTarget::query()
            ->whereIn('weekly_bookkeeping_id', $query->pluck('id'))
            ->with(['weeklyBookkeeping'])
            ->get();

        /* client / assignedStaff / performedBy all point at `users`, so load
           them together in one query instead of three overlapping ones. */
        WeeklyBookkeepingTarget::primeUserRelations($allTargets);

        /* Resolve every payment target's "already paid in Billing" answer in a
           single query. Without this, isUnpaid()/isPaidViaBilling() below each
           ran their own Billing query for every payment target on the page. */
        WeeklyBookkeepingTarget::primePaidBillings($allTargets);

        if (! $seesAll) {
            $allTargets = $allTargets->filter(fn ($t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id);
        }

        if ($staffFilter) {
            $allTargets = $allTargets->where('assigned_staff_id', $staffFilter);
        }
        if ($taskType !== null && $taskType !== '') {
            $allTargets = $allTargets->where('task_type', $taskType);
        }
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $allTargets = $allTargets->filter(
                fn ($t) => str_contains(mb_strtolower($t->displayClientName()), $needle)
            );
        }

        // Section filters mirror the workbook's Uncollected / Unfinished and
        // Unpaid columns, plus the tracker's "Attention" bucket.
        if ($view === 'unfinished') {
            $allTargets = $allTargets->filter(fn ($t) => $t->isUnfinished() || $t->isPastDue());
        } elseif ($view === 'unpaid') {
            $allTargets = $allTargets->filter(fn ($t) => $t->isUnpaid());
        } elseif ($view === 'attention') {
            $allTargets = $allTargets->filter(fn ($t) => $this->needsAttention($t));
        } elseif ($view === 'pending') {
            $allTargets = $allTargets->filter(fn ($t) => $t->isPending() || $t->isInProgress());
        } elseif ($view === 'completed') {
            $allTargets = $allTargets->filter(fn ($t) => $t->isCompleted());
        }

        $rows = $this->buildGridRows($allTargets->sortBy(
            fn ($t) => [$t->target_date?->format('Y-m-d') ?? '9999', $t->displayClientName(), $t->task_type]
        )->values());

        // Side columns from the workbook: work not finished, and billing not paid.
        $unfinishedRows = $rows->filter(fn ($row) => $row['target']->isUnfinished() || $row['target']->isPastDue())->values();
        $unpaidRows = $rows->filter(fn ($row) => $row['target']->isUnpaid())->values();

        $stats = [
            'clients' => $allTargets->pluck('client_id')->unique()->count(),
            'targets' => $allTargets->count(),
            'completed' => $allTargets->filter(fn ($t) => $t->isCompleted())->count(),
            'onTime' => $allTargets->filter(fn ($t) => $t->isOnTime())->count(),
            'late' => $allTargets->filter(fn ($t) => $t->isLate())->count(),
            'pending' => $allTargets->filter(fn ($t) => $t->isPending() || $t->isInProgress())->count(),
            'unfinished' => $allTargets->filter(fn ($t) => $t->isUnfinished() || $t->isPastDue())->count(),
            'unpaid' => $allTargets->filter(fn ($t) => $t->isUnpaid())->count(),
            'byTask' => collect(WeeklyBookkeeping::TASK_TYPES)
                ->map(fn ($label, $key) => $allTargets->where('task_type', $key)->count())
                ->all(),
        ];

        /* ---- Presentation layer for the weekly operations dashboard.
           Everything below is derived from data already loaded above, so the
           redesign adds no queries and changes no stored status. */
        $weekStartCarbon = Carbon::parse($weekStart)->startOfWeek();
        $weekEndCarbon = $weekStartCarbon->copy()->endOfWeek();
        $today = now();

        $attentionTargets = $allTargets->filter(fn ($t) => $this->needsAttention($t))->values();

        // "Today's priorities" = work that needs a decision now: anything
        // already flagged, anything underway, and anything due on/before today.
        $todayTargets = $allTargets
            ->filter(function (WeeklyBookkeepingTarget $t) use ($today) {
                if ($t->isCompleted()) {
                    return false;
                }

                if ($this->needsAttention($t) || $t->isInProgress()) {
                    return true;
                }

                return $t->target_date !== null
                    && $t->target_date->startOfDay()->lte($today->copy()->startOfDay());
            })
            ->sortBy(fn ($t) => [$this->attentionRank($t), $t->target_date?->format('Y-m-d') ?? '9999', $t->displayClientName()])
            ->values();

        $todayItems = $todayTargets
            ->map(fn (WeeklyBookkeepingTarget $t) => $this->presentTarget($t, $user, $seesAll))
            ->values();

        $clientGroups = $allTargets
            ->groupBy('client_id')
            ->map(fn ($group) => $this->buildClientGroup($group, $user, $seesAll))
            ->sortBy(fn ($group) => [
                $group['attention'] > 0 ? 0 : ($group['open'] > 0 ? 1 : 2),
                $group['name'],
            ])
            ->values();

        // Per task type: how many are done vs still open, for the progress bars.
        $typeProgress = collect(WeeklyBookkeeping::TASK_TYPES)
            ->map(function ($label, $key) use ($allTargets) {
                $group = $allTargets->where('task_type', $key);
                $completed = $group->filter(fn ($t) => $t->isCompleted())->count();
                $total = $group->count();
                $attention = $group->filter(fn ($t) => $this->needsAttention($t))->count();

                return [
                    'key' => $key,
                    'label' => $label,
                    'total' => $total,
                    'completed' => $completed,
                    'attention' => $attention,
                    'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                ];
            })
            ->values();

        return view('admin.weekly-bookkeeping.index', [
            'plans' => $plans,
            'owners' => $owners,
            'weeks' => $weeks,
            'q' => $q,
            'activeWeekStart' => $weekStart,
            'activeStaff' => $staffFilter ?: null,
            'activeTaskType' => $taskType,
            'activeView' => $view,
            'taskTypes' => WeeklyBookkeeping::TASK_TYPES,
            'rows' => $rows,
            'unfinishedRows' => $unfinishedRows,
            'unpaidRows' => $unpaidRows,
            'stats' => $stats,
            'seesAll' => $seesAll,
            'weekStartCarbon' => $weekStartCarbon,
            'weekEndCarbon' => $weekEndCarbon,
            'prevWeekStart' => $weekStartCarbon->copy()->subWeek()->format('Y-m-d'),
            'nextWeekStart' => $weekStartCarbon->copy()->addWeek()->format('Y-m-d'),
            'currentWeekStart' => $today->copy()->startOfWeek()->format('Y-m-d'),
            'isCurrentWeek' => $weekStartCarbon->isSameWeek($today),
            'hasActiveFilters' => $q !== '' || $staffFilter > 0 || ($taskType !== null && $taskType !== '') || $view !== 'week',
            'summary' => [
                'clients' => $stats['clients'],
                'tasks' => $stats['targets'],
                'completed' => $stats['completed'],
                'pending' => $allTargets->filter(fn ($t) => $t->isPending() || $t->isInProgress())->count(),
                'attention' => $attentionTargets->count(),
            ],
            'attentionTargets' => $attentionTargets,
            'todayTargets' => $todayTargets,
            'todayItems' => $todayItems,
            'clientGroups' => $clientGroups,
            'typeProgress' => $typeProgress,
        ]);
    }

    /**
     * Whether a task still needs a person to act on it.
     *
     * Composed only from facts the model already tracks: nobody is named on the
     * task, the week has closed with the task unfinished, or billing is still
     * outstanding. Nothing new is persisted and no existing status rule moves.
     */
    private function needsAttention(WeeklyBookkeepingTarget $target): bool
    {
        if ($target->isCompleted()) {
            return false;
        }

        return $target->assigned_staff_id === null
            || $target->isPastDue()
            || $target->isUnpaid();
    }

    /** Sort key so flagged work always leads the list. */
    private function attentionRank(WeeklyBookkeepingTarget $target): int
    {
        if ($this->needsAttention($target)) {
            return 0;
        }

        return $target->isInProgress() ? 1 : 2;
    }

    /**
     * One client's tasks, shaped for the client-centric task list. Each entry
     * carries the action flags the view needs so the template does not have to
     * re-derive permissions or status.
     *
     * @param  \Illuminate\Support\Collection<int, WeeklyBookkeepingTarget>  $group
     * @return array<string, mixed>
     */
    private function buildClientGroup($group, User $user, bool $seesAll): array
    {
        $group = $group->sortBy(fn (WeeklyBookkeepingTarget $t) => [
            $this->attentionRank($t),
            $t->target_date?->format('Y-m-d') ?? '9999',
            $t->taskLabel(),
        ])->values();

        $items = $group
            ->map(fn (WeeklyBookkeepingTarget $t) => $this->presentTarget($t, $user, $seesAll))
            ->values();

        return [
            'client_id' => $group->first()?->client_id,
            'name' => $group->first()?->displayClientName() ?? 'Client',
            'items' => $items,
            'total' => $items->count(),
            'completed' => $items->where('status', 'completed')->count(),
            'attention' => $items->where('status', 'attention')->count(),
            'open' => $items->where('status', '!=', 'completed')->count(),
        ];
    }

    /**
     * Flatten a target into exactly what the dashboard renders, including which
     * inline actions the viewer is allowed to run. Mirrors the controller's
     * authorizeManageTarget() rules so a hidden button is never a 403 waiting to
     * happen.
     *
     * @return array<string, mixed>
     */
    private function presentTarget(WeeklyBookkeepingTarget $t, User $user, bool $seesAll): array
    {
        $canManage = $seesAll || $t->isAssignedTo($user);
        $needsAttention = $this->needsAttention($t);

        $status = match (true) {
            $needsAttention => 'attention',
            $t->isCompleted() => 'completed',
            $t->isInProgress() => 'in_progress',
            default => 'pending',
        };

        return [
            'id' => $t->id,
            'bookkeeping_id' => $t->weekly_bookkeeping_id,
            'client_name' => $t->displayClientName(),
            'task_type' => $t->task_type,
            'task_label' => $t->taskLabel(),
            'status' => $status,
            'status_label' => match ($status) {
                'completed' => $t->isLate() ? 'Completed Late' : 'Completed',
                'in_progress' => 'In Progress',
                'attention' => 'Attention',
                default => 'Pending',
            },
            'target_date' => $t->target_date,
            'due_label' => $this->dueLabel($t),
            'is_overdue' => $needsAttention && $t->target_date !== null && $t->target_date->isPast(),
            'staff_name' => $t->assignedStaffDisplayName(),
            'cell_label' => $t->cellLabel(),
            'timing_label' => $t->timingLabel(),
            'show_url' => route('admin.weekly-bookkeeping.show', $t->weekly_bookkeeping_id).'#target-'.$t->id,
            'can_start' => $canManage && $t->isPending(),
            'can_manage' => $canManage,
            'can_reassign' => $seesAll,
            'needs_attention' => $needsAttention,
            'has_attachment' => $t->hasAttachment(),
            /* Compact extras for the tracker row: an outstanding balance and
               whether a remark was left, so neither needs a new column. */
            'balance_summary' => $t->balanceSummary(),
            'balance_note' => $t->balance_note,
            'has_remarks' => filled($t->notes),
        ];
    }

    /** Plain-language due text, relative to today, for the task list. */
    private function dueLabel(WeeklyBookkeepingTarget $target): string
    {
        if ($target->target_date === null) {
            return 'No date';
        }

        $days = (int) now()->startOfDay()->diffInDays($target->target_date->copy()->startOfDay(), false);

        return match (true) {
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            $days === -1 => '1 day overdue',
            $days < 0 => abs($days).' days overdue',
            $days <= 6 => 'Due '.now()->copy()->startOfDay()->addDays($days)->format('D'),
            default => 'Due '.$target->target_date->format('M j'),
        };
    }

    /**
     * Printable/portable copy of the comparison sheet. It reuses the same
     * visibility rules as the tracker so a staff member can never export work
     * that was not assigned to them.
     */
    public function report(Request $request): View
    {
        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor();

        $weekStart = (string) $request->get('week_start');
        $weekStart = $weekStart !== ''
            ? Carbon::parse($weekStart)->startOfWeek()
            : WeeklyBookkeeping::query()
                ->visibleTo($user)
                ->orderByDesc('week_start')
                ->value('week_start');

        $weekStart = $weekStart
            ? Carbon::parse($weekStart)->startOfWeek()
            : now()->startOfWeek();

        $plans = WeeklyBookkeeping::query()
            ->visibleTo($user)
            ->whereDate('week_start', $weekStart->format('Y-m-d'))
            ->with('staff')
            ->orderByDesc('id')
            ->get();

        $targets = WeeklyBookkeepingTarget::query()
            ->whereIn('weekly_bookkeeping_id', $plans->pluck('id'))
            ->with(['client', 'assignedStaff', 'performedBy'])
            ->get();

        if (! $seesAll) {
            $targets = $targets->filter(
                fn ($t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id
            );
        }

        $rows = $this->buildGridRows($targets->sortBy(
            fn ($t) => [$t->target_date?->format('Y-m-d') ?? '9999', $t->displayClientName(), $t->task_type]
        )->values());

        return view('admin.weekly-bookkeeping.report', [
            'plans' => $plans,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->endOfWeek(),
            'rows' => $rows,
            'unfinishedRows' => $rows->filter(fn ($row) => $row['target']->isUnfinished() || $row['target']->isPastDue())->values(),
            'unpaidRows' => $rows->filter(fn ($row) => $row['target']->isUnpaid())->values(),
            'stats' => [
                'clients' => $targets->pluck('client_id')->unique()->count(),
                'targets' => $targets->count(),
                'completed' => $targets->filter(fn ($t) => $t->isCompleted())->count(),
                'onTime' => $targets->filter(fn ($t) => $t->isOnTime())->count(),
                'late' => $targets->filter(fn ($t) => $t->isLate())->count(),
                'pending' => $targets->filter(fn ($t) => $t->isPending() || $t->isInProgress())->count(),
            ],
        ]);
    }

    /**
     * Mirrors the comparison sheet: each target becomes one row, and only the
     * column matching its task type carries a value. The remaining columns
     * render as the workbook's "-", so the grid stays scannable.
     */
    private function buildGridRows($targets): \Illuminate\Support\Collection
    {
        return $targets->map(function (WeeklyBookkeepingTarget $target) {
            $cells = [];
            foreach (array_keys(WeeklyBookkeeping::TASK_TYPES) as $type) {
                $cells[$type] = $type === $target->task_type ? $target : null;
            }

            return [
                'target' => $target,
                'cells' => $cells,
            ];
        });
    }

    public function create(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set weekly targets.');

        $weekStart = (string) $request->get('week_start');
        $weekStartCarbon = $weekStart !== ''
            ? Carbon::parse($weekStart)->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $weekStartCarbon->copy()->endOfWeek();

        $plan = WeeklyBookkeeping::query()
            ->whereDate('week_start', $weekStartCarbon->format('Y-m-d'))
            ->first();

        $existing = $plan ? $plan->targets()->get() : collect();

        $existingByClient = $existing->groupBy('client_id')
            ->map(fn ($targets) => $targets->groupBy('task_type'));

        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->copy()->startOfWeek()->addWeeks($i);
            $weeks[$date->format('Y-m-d')] = $date->format('M j, Y') . ' – ' . $date->copy()->endOfWeek()->format('M j, Y');
        }

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('name')
            ->get(['id', 'name', 'business_name', 'profile_image_path']);

        return view('admin.weekly-bookkeeping.create', [
            'weeks' => $weeks,
            'activeWeekStart' => $weekStartCarbon->format('Y-m-d'),
            'activeWeekEnd' => $weekEnd->format('Y-m-d'),
            'clients' => $clients,
            'existingByClient' => $existingByClient,
            'plan' => $plan,
            'taskTypes' => WeeklyBookkeeping::TASK_TYPES,
            'upcomingWeeks' => $weeks,
            'assignableStaff' => $this->assignableStaff(),
        ]);
    }

    /**
     * Accounts allowed to own an individual task. Mirrors the workbook, where
     * every task names the staff member responsible for it.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignableStaff(): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set weekly targets.');

        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'clients' => ['required', 'array', 'min:1'],
            'clients.*' => ['required', 'integer', 'exists:users,id'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['array'],
            'tasks.*.*' => ['required', 'in:pickup,record,return,payment'],
            /* Either one date for the whole client, which is how the planner has
               always submitted it, or a per-task map so two staff members on the
               same client can hold different dates. resolveAssignmentDate()
               range-checks whichever shape arrived against the plan's week. */
            'target_date' => ['nullable', 'array'],
            'target_date.*' => ['nullable'],
            'assignee' => ['nullable', 'array'],
            'assignee.*' => ['array'],
            'assignee.*.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $selectedClientIds = array_values(array_map('intval', $validated['clients']));
        $tasks = $request->input('tasks', []);
        $dates = $request->input('target_date', []);
        $assigneeInput = $request->input('assignee', []);

        /* Resolve and range-check every date up front. The plan row is created
           below, and a rejected date must not leave a half-built week behind. */
        $resolvedDates = [];

        foreach ($selectedClientIds as $clientId) {
            foreach ($tasks[$clientId] ?? [] as $taskType) {
                /* Resolved per task, so one client can carry a different date for
                   each task type when the planner submits a per-task map. */
                $resolvedDates[$clientId][$taskType] = $this->resolveAssignmentDate(
                    $dates[$clientId] ?? null,
                    $taskType,
                    $weekStart,
                    $weekEnd,
                    $clientId
                );
            }
        }

        $plan = WeeklyBookkeeping::query()
            ->whereDate('week_start', $weekStart->format('Y-m-d'))
            ->first();

        $wasNew = $plan === null;
        if ($plan === null) {
            $plan = WeeklyBookkeeping::query()->create([
                'staff_id' => $user->id,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
            ]);
        } else {
            $plan->update(['week_end' => $weekEnd->format('Y-m-d')]);
        }

        // Only operational accounts may own a task; a Record-only bookkeeper
        // and a Pick-Up/Return/Payment bookkeeper are both valid assignees.
        $assignableIds = $this->assignableStaff()->pluck('id')->map('intval')->all();
        $assignableNames = $this->assignableStaff()->pluck('name', 'id');

        $newTargets = 0;
        $updatedTargets = 0;
        $reassigned = 0;
        $keepKeys = [];

        foreach ($selectedClientIds as $clientId) {
            $clientTasks = $tasks[$clientId] ?? [];

            foreach ($clientTasks as $taskType) {
                $date = $resolvedDates[$clientId][$taskType] ?? null;

                $keepKeys[] = $clientId.':'.$taskType;

                $rawAssignee = $assigneeInput[$clientId][$taskType] ?? null;
                $assigneeId = ($rawAssignee !== null && $rawAssignee !== '' && in_array((int) $rawAssignee, $assignableIds, true))
                    ? (int) $rawAssignee
                    : null;

                $target = $plan->targets()->where('client_id', $clientId)
                    ->where('task_type', $taskType)
                    ->first();

                if ($target) {
                    $target->target_date = $date;

                    if ($target->assigned_staff_id !== $assigneeId) {
                        $previous = $target->assignedStaffDisplayName();
                        $target->assigned_staff_id = $assigneeId;
                        $target->assigned_staff_name = $assigneeId ? $assignableNames[$assigneeId] : null;
                        $reassigned++;

                        if (! $target->isPending()) {
                            ActivityLog::record(
                                $user,
                                'weekly_bookkeeping.target.reassigned',
                                sprintf(
                                    '%s for %s reassigned from %s to %s.',
                                    $target->taskLabel(),
                                    $target->displayClientName(),
                                    $previous !== '' ? $previous : 'Unassigned',
                                    $assigneeId ? $assignableNames[$assigneeId] : 'Unassigned'
                                ),
                                instance: null,
                                bookkeeping: $plan
                            );
                        }
                    }

                    $target->saveQuietly();
                    $updatedTargets++;
                } else {
                    $plan->targets()->create([
                        'client_id' => $clientId,
                        'task_type' => $taskType,
                        'target_date' => $date,
                        'assigned_staff_id' => $assigneeId,
                        'assigned_staff_name' => $assigneeId ? $assignableNames[$assigneeId] : null,
                        'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
                    ]);
                    $newTargets++;

                    ActivityLog::record(
                        $user,
                        'weekly_bookkeeping.target_added',
                        sprintf(
                            'Target added: %s — %s (Target date: %s%s).',
                            $this->clientName($clientId),
                            WeeklyBookkeepingTarget::TASK_TYPES[$taskType] ?? $taskType,
                            $date ? Carbon::parse($date)->format('M j, Y') : 'Any day',
                            $assigneeId ? ', assigned to '.$assignableNames[$assigneeId] : ''
                        ),
                        instance: null,
                        bookkeeping: $plan
                    );
                }
            }
        }

        $removed = 0;
        foreach ($plan->targets as $target) {
            $key = $target->client_id.':'.$target->task_type;
            if (in_array($key, $keepKeys, true)) {
                continue;
            }

            if ($target->actual_status === WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING) {
                $target->delete();
                $removed++;

                ActivityLog::record(
                    $user,
                    'weekly_bookkeeping.target_removed',
                    sprintf('Target removed: %s — %s.', $this->clientName($target->client_id), $target->taskLabel()),
                    instance: null,
                    bookkeeping: $plan
                );
            } else {
                ActivityLog::record(
                    $user,
                    'weekly_bookkeeping.target_removed',
                    sprintf(
                        'Target removed from weekly list: %s — %s (work already recorded, history preserved).',
                        $this->clientName($target->client_id),
                        $target->taskLabel()
                    ),
                    instance: null,
                    bookkeeping: $plan
                );
            }
        }

        $plan->syncOverallStatus();

        $activity = sprintf(
            'Weekly target saved for %s – %s (%d client%s, %d task%s, %d reassigned).',
            $plan->week_start->format('M j, Y'),
            $plan->week_end->format('M j, Y'),
            count(array_unique($selectedClientIds)),
            count(array_unique($selectedClientIds)) === 1 ? '' : 's',
            count($keepKeys),
            count($keepKeys) === 1 ? '' : 's',
            $reassigned
        );
        if ($wasNew) {
            $activity = 'Weekly target created for week of '.$plan->week_start->format('M j, Y').'. '.sprintf(
                '%d client%s, %d task%s selected.',
                count(array_unique($selectedClientIds)),
                count(array_unique($selectedClientIds)) === 1 ? '' : 's',
                count($keepKeys),
                count($keepKeys) === 1 ? '' : 's'
            );
        }

        ActivityLog::record($user, 'weekly_bookkeeping.targets_saved', $activity, instance: null, bookkeeping: $plan);

        return redirect()->route('admin.weekly-bookkeeping.show', $plan)
            ->with('status', 'Weekly targets saved.');
    }

    /**
     * Resolves the target date for a single (client, task type) assignment.
     *
     * The submitted value is either one date for the whole client, which is how
     * the client matrix has always posted it, or a per-task map. Either way the
     * date is range-checked against the plan's own week.
     *
     * The error is keyed the way the planner addresses the field, so an invalid
     * date is reported against the client whose row it came from.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveAssignmentDate(mixed $raw, string $taskType, Carbon $weekStart, Carbon $weekEnd, int|string $clientId): ?string
    {
        $errorKey = 'target_date.'.$clientId;

        if (is_array($raw)) {
            $errorKey .= '.'.$taskType;
            $raw = $raw[$taskType] ?? null;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            $date = Carbon::parse($raw);
        } catch (\Throwable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => 'The target date is not a valid date.',
            ]);
        }

        $from = $weekStart->copy()->startOfDay();
        $to = $weekEnd->copy()->endOfDay();

        if ($date->startOfDay()->lt($from) || $date->startOfDay()->gt($to)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => 'The target date must fall between '
                    .$from->format('M j, Y').' and '.$to->format('M j, Y').'.',
            ]);
        }

        return $date->format('Y-m-d');
    }

    /**
     * Adds one staff-centered batch of weekly assignments: a single staff member,
     * one target date, one task type and any number of clients.
     *
     * This writes exactly the same row the client matrix writes — one target per
     * (client, task type) — but reached from the other direction, so naming
     * Angeli once covers three clients instead of the administrator repeating the
     * same selection for every row.
     *
     * The week is still the organising unit and the target date is still stored
     * per assignment, so two staff members inside the same week keep their own
     * independent dates.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set weekly targets.');

        $weekStart = Carbon::parse((string) $request->input('week_start'))->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'assigned_staff_id' => ['required', 'integer', 'exists:users,id'],
            'task_type' => ['required', 'in:pickup,record,return,payment'],
            'target_date' => [
                'required',
                'date',
                'after_or_equal:'.$weekStart->format('Y-m-d'),
                'before_or_equal:'.$weekEnd->format('Y-m-d'),
            ],
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Only operational accounts may own a task, matching the client matrix.
        $assignee = $this->assignableStaff()->firstWhere('id', (int) $validated['assigned_staff_id']);

        if (! $assignee) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assigned_staff_id' => 'The selected account cannot be assigned a task.',
            ]);
        }

        $clientIds = array_values(array_unique(array_map('intval', $validated['client_ids'])));

        /* Bookkeeping work is only ever handed to a client account, so an id that
           resolves to any other role is rejected rather than silently creating a
           target against a staff member. */
        $validClientIds = User::query()
            ->whereIn('id', $clientIds)
            ->where('role', User::ROLE_CLIENT)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validClientIds) !== count($clientIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'client_ids' => 'One or more selected accounts are not clients.',
            ]);
        }

        $plan = WeeklyBookkeeping::query()
            ->whereDate('week_start', $weekStart->format('Y-m-d'))
            ->first();

        $wasNew = $plan === null;

        if ($plan === null) {
            $plan = WeeklyBookkeeping::query()->create([
                'staff_id' => $user->id,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'status' => WeeklyBookkeeping::STATUS_NOT_STARTED,
            ]);
        } else {
            $plan->update(['week_end' => $weekEnd->format('Y-m-d')]);
        }

        $taskType = $validated['task_type'];
        $taskLabel = WeeklyBookkeepingTarget::TASK_TYPES[$taskType] ?? $taskType;
        $targetDate = Carbon::parse($validated['target_date'])->format('Y-m-d');

        $added = 0;
        $updated = 0;
        $reassigned = 0;

        foreach ($clientIds as $clientId) {
            /* One client + one task type per week is the existing rule, so a
               repeat submission moves the existing target rather than creating a
               duplicate the unique index would reject. */
            $target = $plan->targets()
                ->where('client_id', $clientId)
                ->where('task_type', $taskType)
                ->first();

            if ($target) {
                $previous = $target->assignedStaffDisplayName();

                $target->target_date = $targetDate;

                if ($target->assigned_staff_id !== $assignee->id) {
                    $target->assigned_staff_id = $assignee->id;
                    $target->assigned_staff_name = $assignee->name;
                    $reassigned++;

                    if (! $target->isPending()) {
                        ActivityLog::record(
                            $user,
                            'weekly_bookkeeping.target.reassigned',
                            sprintf(
                                '%s for %s reassigned from %s to %s.',
                                $target->taskLabel(),
                                $target->displayClientName(),
                                $previous !== '' ? $previous : 'Unassigned',
                                $assignee->name
                            ),
                            instance: null,
                            bookkeeping: $plan
                        );
                    }
                }

                $target->saveQuietly();
                $updated++;
            } else {
                $plan->targets()->create([
                    'client_id' => $clientId,
                    'task_type' => $taskType,
                    'target_date' => $targetDate,
                    'assigned_staff_id' => $assignee->id,
                    'assigned_staff_name' => $assignee->name,
                    'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
                ]);

                $added++;

                ActivityLog::record(
                    $user,
                    'weekly_bookkeeping.target_added',
                    sprintf(
                        'Target added: %s — %s (Target date: %s, assigned to %s).',
                        $this->clientName($clientId),
                        $taskLabel,
                        Carbon::parse($targetDate)->format('M j, Y'),
                        $assignee->name
                    ),
                    instance: null,
                    bookkeeping: $plan
                );
            }
        }

        $plan->syncOverallStatus();

        ActivityLog::record(
            $user,
            'weekly_bookkeeping.targets_saved',
            sprintf(
                'Weekly target — %s assigned to %s (Target date: %s, %d client%s, %d added, %d updated, %d reassigned).',
                $taskLabel,
                $assignee->name,
                Carbon::parse($targetDate)->format('M j, Y'),
                count($clientIds),
                count($clientIds) === 1 ? '' : 's',
                $added,
                $updated,
                $reassigned
            ),
            instance: null,
            bookkeeping: $plan
        );

        $message = sprintf(
            '%s assigned to %s for %s – %s: %d client%s on %s.',
            $taskLabel,
            $assignee->name,
            $weekStart->format('M j, Y'),
            $weekEnd->format('M j, Y'),
            count($clientIds),
            count($clientIds) === 1 ? '' : 's',
            Carbon::parse($targetDate)->format('M j, Y')
        );

        if ($wasNew) {
            $message = 'Weekly target created. '.$message;
        }

        /* Back to the planner for the same week so the next staff member can be
           added without re-picking the week or rebuilding the form. */
        return redirect()
            ->route('admin.weekly-bookkeeping.create', ['week_start' => $weekStart->format('Y-m-d')])
            ->with('status', $message);
    }

    public function show(WeeklyBookkeeping $bookkeeping): View
    {
        $this->authorizeManage($bookkeeping);

        $bookkeeping->load('staff', 'targets.client', 'targets.assignedStaff', 'targets.performedBy', 'history.user');

        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor();
        $canManage = $seesAll || $bookkeeping->isOwnedBy($user) || $bookkeeping->isAssignedTo($user);
        $canReassign = $seesAll;

        // Plain staff only ever see the work assigned to them, matching the
        // tracker, so the plan screen cannot leak another person's clients.
        $visibleTargets = $seesAll || $bookkeeping->isOwnedBy($user)
            ? $bookkeeping->targets
            : $bookkeeping->targets->filter(
                fn (WeeklyBookkeepingTarget $t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id
            );

        $planTargets = $visibleTargets->sortBy(function (WeeklyBookkeepingTarget $t) {
            return ($t->client?->name ?: '').'|'.$t->task_type;
        });

        return view('admin.weekly-bookkeeping.show', [
            'bookkeeping' => $bookkeeping,
            'targets' => $planTargets,
            'taskTypes' => WeeklyBookkeeping::TASK_TYPES,
            'canManage' => $canManage,
            'canReassign' => $canReassign,
            'assignableStaff' => $this->assignableStaff(),
            'paymentMethods' => WeeklyBookkeepingTarget::PAYMENT_METHODS,
            'badgeClasses' => [
                WeeklyBookkeeping::STATUS_NOT_STARTED => 'badge-neutral',
                WeeklyBookkeeping::STATUS_IN_PROGRESS => 'badge-info',
                WeeklyBookkeeping::STATUS_COMPLETED => 'badge-success',
                WeeklyBookkeeping::STATUS_DELAYED => 'badge-warn',
            ],
            'eventLabels' => [
                'weekly_bookkeeping.created' => 'Weekly target created',
                'weekly_bookkeeping.target_added' => 'Target added',
                'weekly_bookkeeping.target_removed' => 'Target removed',
                'weekly_bookkeeping.targets_saved' => 'Targets saved',
                'weekly_bookkeeping.target.started' => 'Actual work started',
                'weekly_bookkeeping.target.completed' => 'Actual work completed',
                'weekly_bookkeeping.target.reassigned' => 'Task reassigned',
                'weekly_bookkeeping.attachment_uploaded' => 'Evidence uploaded',
                'weekly_bookkeeping.attachment_replaced' => 'Evidence replaced',
                'weekly_bookkeeping.owner_updated' => 'Target owner changed',
            ],
        ]);
    }

    public function history(WeeklyBookkeeping $bookkeeping): View
    {
        $this->authorizeManage($bookkeeping);

        $bookkeeping->load('staff', 'targets.client', 'history.user');

        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor() || $bookkeeping->isOwnedBy($user);

        $history = $bookkeeping->history;

        if (! $seesAll) {
            // The trail names clients and colleagues, so plain staff see only the
            // entries that concern their own tasks.
            $mine = $bookkeeping->targets
                ->filter(fn (WeeklyBookkeepingTarget $t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id)
                ->pluck('client_id')
                ->map(fn ($id) => $this->clientName($id));

            $history = $history->filter(function (ActivityLog $entry) use ($user, $mine) {
                return $entry->user_id === $user->id
                    || collect($mine)->contains(fn ($name) => str_contains((string) $entry->description, $name));
            })->values();
        }

        return view('admin.weekly-bookkeeping.history', [
            'bookkeeping' => $bookkeeping,
            'history' => $history,
            'taskTypes' => WeeklyBookkeeping::TASK_TYPES,
            'badgeClasses' => [
                WeeklyBookkeeping::STATUS_NOT_STARTED => 'badge-neutral',
                WeeklyBookkeeping::STATUS_IN_PROGRESS => 'badge-info',
                WeeklyBookkeeping::STATUS_COMPLETED => 'badge-success',
                WeeklyBookkeeping::STATUS_DELAYED => 'badge-warn',
            ],
            'eventLabels' => [
                'weekly_bookkeeping.created' => 'Weekly target created',
                'weekly_bookkeeping.target_added' => 'Target added',
                'weekly_bookkeeping.target_removed' => 'Target removed',
                'weekly_bookkeeping.targets_saved' => 'Targets saved',
                'weekly_bookkeeping.target.started' => 'Actual work started',
                'weekly_bookkeeping.target.completed' => 'Actual work completed',
                'weekly_bookkeeping.target.reassigned' => 'Task reassigned',
                'weekly_bookkeeping.attachment_uploaded' => 'Evidence uploaded',
                'weekly_bookkeeping.attachment_replaced' => 'Evidence replaced',
                'weekly_bookkeeping.owner_updated' => 'Target owner changed',
            ],
        ]);
    }

    public function updateOwner(Request $request, WeeklyBookkeeping $bookkeeping): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403, 'Only admins or supervisors can change the target owner.');

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newOwner = User::query()
            ->whereKey($validated['staff_id'])
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN])
            ->whereNull('deleted_at')
            ->first();

        if (! $newOwner) {
            return back()->withErrors(['staff_id' => 'The selected account cannot own weekly targets.']);
        }

        if ($bookkeeping->staff_id === $newOwner->id) {
            return back()->with('status', "{$newOwner->name} is already the target owner.");
        }

        $previousName = $bookkeeping->displayOwnerName();
        $bookkeeping->update(['staff_id' => $newOwner->id]);

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.owner_updated',
            "Target owner changed from {$previousName} to {$newOwner->name}.",
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', 'Target owner updated.');
    }

    public function updateTarget(Request $request, WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        $user = auth()->user();
        $isOversight = $user->isAdmin() || $user->isSupervisor();

        /* Remarks are the one field that stays writable once the actual work has
           started: the assigned staff member keeps explaining what happened for as
           long as the task is In Progress, which is exactly when a remark is worth
           writing. This is an exception for the `notes` column alone — the target
           date, the balance columns, the assignment and the completion status all
           stay behind the pending lock they have always had. */
        $canEditRemarks = $target->isPending()
            || $isOversight
            || ($target->isInProgress() && $target->isAssignedTo($user));

        if (! $canEditRemarks) {
            return back()->withErrors(['action' => 'This task is finalized, so its remarks are read-only.']);
        }

        /* Balance columns are validated inside `targetWorkflowAttributes`, and
           only when the submitted form actually carried them. */
        $validated = $request->validate([
            'target_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /* The submitted date, falling back to what is already stored so a
           remarks-only save never blanks the deadline. */
        $targetDate = $validated['target_date'] ?? $target->target_date?->format('Y-m-d');

        /* A target date may only move inside the plan's own week, so a week can
           never end up holding work that belongs to another one. */
        if ($targetDate !== null) {
            $weekStart = $bookkeeping->week_start->copy()->startOfDay();
            $weekEnd = $bookkeeping->week_end->copy()->endOfDay();
            $parsed = Carbon::parse($targetDate)->startOfDay();

            if ($parsed->lt($weekStart) || $parsed->gt($weekEnd)) {
                return back()->withErrors([
                    'target_date' => 'The target date must fall between '
                        .$weekStart->format('M j, Y').' and '.$weekEnd->format('M j, Y').'.',
                ]);
            }
        }

        /* The date itself only moves while the task is still pending (or under
           oversight). The remarks exception above deliberately does not extend
           to it. */
        $canUpdateDate = $target->isPending() || $isOversight;
        $dateChanged = $targetDate !== null && $targetDate !== $target->target_date?->format('Y-m-d');

        if ($dateChanged && ! $canUpdateDate) {
            return back()->withErrors([
                'action' => 'Target date can only be changed while the task is pending.',
            ]);
        }

        $attributes = $this->targetWorkflowAttributes($request, $target, $targetDate);

        /* Only write the remarks when the form actually carried them. A save that
           is about something else must never blank a remark that is already on
           the task, and the remarks editor sends `notes` on its own. */
        if ($request->has('notes')) {
            $notes = trim((string) ($validated['notes'] ?? ''));
            $attributes['notes'] = $notes === '' ? null : $notes;
        }

        if ($canUpdateDate) {
            $attributes['target_date'] = $targetDate;
        }

        $target->update($attributes);

        $refreshed = $target->task_type === 'pickup'
            ? $target->resequenceFrom($targetDate, fn ($sibling) => $this->mayManageTarget($sibling))
            : [];

        $message = "Target updated: {$target->displayClientName()} — {$target->taskLabel()} (Target date: "
            .$target->target_date?->format('M j, Y').').';

        if ($refreshed !== []) {
            $labels = array_map(
                fn ($taskType) => WeeklyBookkeepingTarget::SEQUENCE_LABELS[$taskType] ?? $taskType,
                $refreshed
            );

            $message .= ' Rescheduled to match the new Pick-Up date: '.implode(', ', $labels).'.';
        }

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.targets_saved',
            $message,
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', $refreshed === [] ? 'Target updated.' : 'Target updated and later stages rescheduled.');
    }

    public function startTarget(WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        try {
            $target->startProcessing(auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.target.started',
            "{$target->taskLabel()} started for {$target->displayClientName()} at {$target->started_at->format('g:i A')}.",
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} marked as in progress.");
    }

    public function completeTarget(Request $request, WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        $validated = $request->validate([
            'payment_method' => ['nullable', 'in:'.implode(',', array_keys(WeeklyBookkeepingTarget::PAYMENT_METHODS))],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $this->validateAttachment($file, $target->task_type);

            $path = $file->store("weekly-bookkeeping/{$target->task_type}", 'supabase');

            if ($target->attachment_path) {
                Storage::disk('supabase')->delete($target->attachment_path);
            }

            $target->attachment_path = $path;
            $target->attachment_name = $file->getClientOriginalName();
            $target->attachment_mime = $file->getMimeType();
        }

        if ($target->requiresAttachment() && ! $target->attachment_path) {
            return back()->withErrors(['action' => "{$target->attachmentHint()} is required to complete this task."]);
        }

        try {
            $target->complete(
                auth()->user(),
                $validated['payment_method'] ?? null,
                isset($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        $performer = $target->performedByDisplayName();
        $duration = $target->durationHuman();
        $proof = $target->attachment_name;

        $description = "{$target->taskLabel()} {$target->effectiveStatusLabel()} for {$target->displayClientName()} by {$performer}."
            . ($target->timingLabel() ? " {$target->timingLabel()}." : '')
            . ($duration ? " Duration: {$duration}." : '')
            . ($proof ? " Evidence: {$proof}." : '');

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.target.completed',
            $description,
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} {$target->effectiveStatusLabel()}.");
    }

    /**
     * Reassigns a single task to different staff. Kept separate from saving the
     * whole weekly plan so a supervisor can correct ownership on one task, which
     * is the accountability trail the workbook relies on.
     */
    public function reassignTarget(Request $request, WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403, 'Only admins or supervisors can reassign tasks.');

        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newAssignee = $this->assignableStaff()->firstWhere('id', (int) ($validated['assigned_staff_id'] ?? 0));

        if ($validated['assigned_staff_id'] && ! $newAssignee) {
            return back()->withErrors(['assigned_staff_id' => 'The selected account cannot be assigned a weekly task.']);
        }

        $previous = $target->assignedStaffDisplayName();

        $target->update([
            'assigned_staff_id' => $newAssignee?->id,
            'assigned_staff_name' => $newAssignee?->name,
        ]);

        ActivityLog::record(
            $user,
            'weekly_bookkeeping.target.reassigned',
            sprintf(
                '%s for %s reassigned from %s to %s.',
                $target->taskLabel(),
                $target->displayClientName(),
                $previous !== '' ? $previous : 'Unassigned',
                $newAssignee?->name ?? 'Unassigned'
            ),
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} reassigned to ".($newAssignee?->name ?? 'Unassigned').'.');
    }

    public function uploadAttachment(Request $request, WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        if ($target->isCompleted()) {
            return back()->withErrors(['action' => 'Cannot upload evidence to a completed task.']);
        }

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['attachment'];
        $this->validateAttachment($file, $target->task_type);

        $path = $file->store("weekly-bookkeeping/{$target->task_type}", 'supabase');

        if ($target->attachment_path) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $target->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.attachment_uploaded',
            "Uploaded {$target->attachmentHint()} for {$target->taskLabel()} — {$target->displayClientName()} ({$file->getClientOriginalName()}).",
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', 'Evidence uploaded.');
    }

    public function replaceAttachment(Request $request, WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['attachment'];
        $this->validateAttachment($file, $target->task_type);

        if ($target->attachment_path) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $path = $file->store("weekly-bookkeeping/{$target->task_type}", 'supabase');

        $target->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.attachment_replaced',
            "Replaced {$target->attachmentHint()} for {$target->taskLabel()} — {$target->displayClientName()} ({$file->getClientOriginalName()}).",
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', 'Evidence replaced.');
    }

    public function downloadAttachment(WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): StreamedResponse
    {
        $this->authorizeManageTarget($target);

        abort_unless($target->attachment_path, 404);
        abort_unless(Storage::disk('supabase')->exists($target->attachment_path), 404);

        return Storage::disk('supabase')->download($target->attachment_path, $target->attachment_name);
    }

    public function viewAttachment(WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        abort_unless($target->attachment_path, 404);
        abort_unless(Storage::disk('supabase')->exists($target->attachment_path), 404);

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($target->attachment_path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function destroyTarget(WeeklyBookkeeping $bookkeeping, WeeklyBookkeepingTarget $target): RedirectResponse
    {
        $this->authorizeManageTarget($target);

        if (! $target->isPending()) {
            return back()->withErrors(['action' => 'Cannot delete a target with actual work recorded. Remove it from the weekly target list instead (history is preserved).']);
        }

        if ($target->attachment_path && Storage::disk('supabase')->exists($target->attachment_path)) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $clientName = $target->displayClientName();
        $taskLabel = $target->taskLabel();

        $target->delete();
        $bookkeeping->syncOverallStatus();

        ActivityLog::record(
            auth()->user(),
            'weekly_bookkeeping.target_removed',
            "Target removed: {$clientName} — {$taskLabel}.",
            instance: null,
            bookkeeping: $bookkeeping
        );

        return back()->with('status', 'Target removed.');
    }

    public function destroy(WeeklyBookkeeping $bookkeeping): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete weekly targets.');

        foreach ($bookkeeping->targets as $target) {
            if ($target->attachment_path && Storage::disk('supabase')->exists($target->attachment_path)) {
                Storage::disk('supabase')->delete($target->attachment_path);
            }
        }

        $weekLabel = $bookkeeping->week_start?->format('M j, Y');
        $targetCount = $bookkeeping->targets()->count();

        $bookkeeping->delete();

        ActivityLog::record(
            auth()->user(),
            'admin.weekly_bookkeeping_deleted',
            "Deleted weekly target plan for week of {$weekLabel} ({$targetCount} target tasks).",
            instance: null,
            bookkeeping: null
        );

        return redirect()->route('admin.weekly-bookkeeping.index')->with('status', 'Weekly target plan deleted.');
    }

    private function authorizeManage(WeeklyBookkeeping $bookkeeping): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isSupervisor() || $bookkeeping->isAssignedTo($user), 403, 'You are not assigned to this weekly target plan.');
    }

    /**
     * Task-level accountability: a staff member may only work on tasks assigned
     * to them, which is what keeps a Record-only bookkeeper out of Pick-Up,
     * Return and Payment work. Admins and supervisors cover the whole week.
     */
    private function authorizeManageTarget(WeeklyBookkeepingTarget $target): void
    {
        $target->loadMissing('weeklyBookkeeping');

        abort_unless(
            $this->mayManageTarget($target),
            403,
            "This task is assigned to ".($target->assignedStaffDisplayName() !== '' ? $target->assignedStaffDisplayName() : 'someone else').'.'
        );

        $this->authorizeManage($target->weeklyBookkeeping);
    }

    /**
     * The same rule as `authorizeManageTarget`, phrased as a question so the
     * Pick-Up reschedule can check it for each sibling stage before touching it.
     */
    private function mayManageTarget(WeeklyBookkeepingTarget $target): bool
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return $target->isAssignedTo($user);
    }

    /**
     * The target-date origin flag and the balance columns, kept together so the
     * weekly and period edit endpoints stay in step.
     *
     * A date is only marked manual when the submitted value actually differs from
     * what is stored, so re-saving an unchanged form does not quietly strip the
     * automatic flag off a suggested date.
     *
     * Balance columns are written only when the form carried them, and are
     * cleared once the status is no longer With Balance, so a stale amount cannot
     * outlive the status that gave it meaning.
     *
     * @return array<string, mixed>
     */
    private function targetWorkflowAttributes(
        Request $request,
        WeeklyBookkeepingTarget $target,
        ?string $targetDate,
    ): array {
        $attributes = [];

        if ($request->has('target_date') && $targetDate !== $target->target_date?->format('Y-m-d')) {
            $attributes['target_date_auto'] = false;
        }

        if (! $request->has('payment_status')) {
            return $attributes;
        }

        $validated = $request->validate([
            'payment_status' => ['nullable', Rule::in(array_keys(WeeklyBookkeepingTarget::PAYMENT_STATUSES))],
            'balance_amount' => ['nullable', 'numeric', 'min:0', 'required_if:payment_status,with_balance'],
            'balance_note' => ['nullable', 'string', 'max:500'],
        ]);

        $withBalance = $validated['payment_status'] === WeeklyBookkeepingTarget::PAYMENT_STATUS_WITH_BALANCE;

        return [
            ...$attributes,
            'payment_status' => $validated['payment_status'] ?? null,
            'balance_amount' => $withBalance ? ($validated['balance_amount'] ?? null) : null,
            'balance_note' => $withBalance ? ($validated['balance_note'] ?? null) : null,
        ];
    }

    private function clientName(int $clientId): string
    {
        $client = User::query()->whereKey($clientId)->first(['id', 'name', 'business_name']);

        return $client?->business_name ?: $client?->name ?: 'Client #'.$clientId;
    }

    private function validateAttachment(\Illuminate\Http\UploadedFile $file, string $taskType): void
    {
        $allowedMimes = [
            'pickup' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'record' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'return' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'payment' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];

        $mime = $file->getMimeType();
        if (! in_array($mime, $allowedMimes[$taskType] ?? [], true)) {
            throw new \InvalidArgumentException('Invalid file type for this task.');
        }
    }
}