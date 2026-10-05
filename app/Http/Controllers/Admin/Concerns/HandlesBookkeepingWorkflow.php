<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Monthly and Quarterly bookkeeping workflows, which are the same workflow
 * as the Weekly one with a different planning period.
 *
 * Everything period-agnostic lives here. A concrete controller supplies the two
 * model classes and keeps its public methods strongly typed so Laravel's route
 * model binding resolves `{bookkeeping}` and `{target}`; those methods then hand
 * straight to the matching method below.
 *
 * This is a copy of the Weekly Bookkeeping controller rather than an extraction
 * out of it, so `WeeklyBookkeepingController` stays untouched.
 */
trait HandlesBookkeepingWorkflow
{
    // ---------------------------------------------------------------------
    // Module description
    // ---------------------------------------------------------------------

    /** @return class-string<Model> */
    abstract protected function planClass(): string;

    /** @return class-string<Model> */
    abstract protected function targetClass(): string;

    /** @return array<string, mixed>|null */
    private ?array $configCache = null;

    protected function planForeignKey(): string
    {
        return $this->planClass()::FOREIGN_KEY;
    }

    /** Named ActivityLog::record() argument that links this module's plan. */
    protected function activityLinkName(): string
    {
        return $this->planClass()::ACTIVITY_LINK;
    }

    protected function storagePrefix(): string
    {
        return $this->planClass()::STORAGE_PREFIX;
    }

    protected function periodKind(): string
    {
        return $this->planClass()::PERIOD_KIND;
    }

    /**
     * Everything the shared Blade views need to describe this module.
     *
     * @return array<string, mixed>
     */
    protected function bookkeepingConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $planClass = $this->planClass();
        $plan = new $planClass;

        return $this->configCache = [
            'kind' => $planClass::PERIOD_KIND,
            'route_prefix' => $planClass::ROUTE_PREFIX,
            // Module title for page headings, e.g. "Monthly Bookkeeping".
            'title' => $planClass::PERIOD_KIND === BookkeepingPeriod::MONTHLY
                ? 'Monthly Bookkeeping'
                : 'Quarterly Bookkeeping',
            // What one planned row is called, e.g. "Monthly Target".
            'noun' => $plan->planNoun(),
            'noun_title' => $plan->planNounTitle(),
            'unit' => $plan->periodUnit(),
            'unit_plural' => $plan->periodUnitPlural(),
            'activity_prefix' => $planClass::ACTIVITY_PREFIX,
            'query_key' => BookkeepingPeriod::current($planClass::PERIOD_KIND)->queryName(),
            'start_field' => $planClass::PERIOD_START_FIELD,
            'start_column' => $planClass::PERIOD_START_COLUMN,
            'default_view' => 'all',
        ];
    }

    // ---------------------------------------------------------------------
    // Tracker
    // ---------------------------------------------------------------------

    /**
     * @param  Model  $plan
     * @return array<string, mixed>
     */
    public function indexData(Request $request): array
    {
        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor();
        $planClass = $this->planClass();
        $targetClass = $this->targetClass();
        $config = $this->bookkeepingConfig();

        $q = trim((string) $request->get('q'));
        $staffFilter = (int) $request->get('staff');
        $view = (string) $request->get('view', $config['default_view']);
        $taskType = $request->get('task_type');

        $view = array_key_exists($view, $this->viewOptions()) ? $view : $config['default_view'];

        /*
         * The period selection defaults to the most recent planned one, so the
         * screen opens on real data rather than an empty current period.
         */
        $period = $this->resolvePeriod($request, $user, $planClass);

        $startColumn = $planClass::PERIOD_START_COLUMN;

        $query = $planClass::query()
            ->visibleTo($user)
            ->with(['targets.client', 'targets.assignedStaff', 'targets.performedBy'])
            ->whereDate($startColumn, $period->startDate())
            ->when($staffFilter, fn ($query) => $query->whereHas(
                'targets',
                fn ($t) => $t->where('assigned_staff_id', $staffFilter)
            ))
            ->when($taskType, fn ($query) => $query->whereHas('targets', fn ($t) => $t->where('task_type', $taskType)))
            ->when($q !== '', fn ($query) => $query->whereHas('targets.client', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('business_name', 'like', "%{$q}%");
            }));

        $planIds = (clone $query)->pluck('id');

        $plans = (clone $query)
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $owners = $this->assignableStaff();

        $periods = $planClass::query()
            ->visibleTo($user)
            ->select($startColumn)
            ->distinct()
            ->orderByDesc($startColumn)
            ->limit(30)
            ->pluck($startColumn);

        // ---- Tracker grid: one row per target, laid out like the workbook ----
        $allTargets = $targetClass::query()
            ->whereIn($this->planForeignKey(), $planIds)
            ->with(['bookkeeping'])
            ->get();

        /* client / assignedStaff / performedBy all point at `users`, so load
           them together in one query instead of three overlapping ones. */
        $targetClass::primeUserRelations($allTargets);

        /* Resolve every payment target's "already paid in Billing" answer in a
           single query instead of one query per payment target on the page. */
        $targetClass::primePaidBillings($allTargets);

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
            'byTask' => collect($planClass::TASK_TYPES)
                ->map(fn ($label, $key) => $allTargets->where('task_type', $key)->count())
                ->all(),
        ];

        /* ---- Presentation layer for the period operations dashboard.
           Everything below is derived from data already loaded above, so the
           redesign adds no queries and changes no stored status. */
        $today = now();

        $attentionTargets = $allTargets->filter(fn ($t) => $this->needsAttention($t))->values();

        // "Today's priorities" = work that needs a decision now: anything
        // already flagged, anything underway, and anything due on/before today.
        $todayTargets = $allTargets
            ->filter(function ($t) use ($today) {
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
            ->map(fn ($t) => $this->presentTarget($t, $user, $seesAll))
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
        $typeProgress = collect($planClass::TASK_TYPES)
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

        return [
            'config' => $config,
            'period' => $period,
            'periods' => $periods,
            'plans' => $plans,
            'owners' => $owners,
            'q' => $q,
            'activePeriodKey' => $period->key(),
            'activeStaff' => $staffFilter ?: null,
            'activeTaskType' => $taskType,
            'activeView' => $view,
            'viewOptions' => $this->viewOptions(),
            'taskTypes' => $planClass::TASK_TYPES,
            'rows' => $rows,
            'unfinishedRows' => $unfinishedRows,
            'unpaidRows' => $unpaidRows,
            'stats' => $stats,
            'seesAll' => $seesAll,
            'prevPeriodKey' => $period->previous()->key(),
            'nextPeriodKey' => $period->next()->key(),
            'currentPeriodKey' => BookkeepingPeriod::current($this->periodKind())->key(),
            'isCurrentPeriod' => $period->isCurrent(),
            'hasActiveFilters' => $q !== '' || $staffFilter > 0 || ($taskType !== null && $taskType !== '') || $view !== $config['default_view'],
            'summary' => [
                'clients' => $stats['clients'],
                'tasks' => $stats['targets'],
                'completed' => $stats['completed'],
                'pending' => $stats['pending'],
                'attention' => $attentionTargets->count(),
            ],
            'attentionTargets' => $attentionTargets,
            'todayTargets' => $todayTargets,
            'todayItems' => $todayItems,
            'clientGroups' => $clientGroups,
            'typeProgress' => $typeProgress,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function viewOptions(): array
    {
        return [
            'all' => 'All Tasks',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'attention' => 'Needs Attention',
            'unfinished' => 'Uncollected / Unfinished',
            'unpaid' => 'Unpaid',
        ];
    }

    /**
     * Resolves the requested period, falling back to the newest period the
     * viewer actually has plans for.
     *
     * @param  class-string<Model>  $planClass
     */
    protected function resolvePeriod(Request $request, User $user, string $planClass): BookkeepingPeriod
    {
        $raw = trim((string) $request->get($this->bookkeepingConfig()['query_key'], ''));

        if ($raw !== '') {
            try {
                return BookkeepingPeriod::fromKey($this->periodKind(), $raw);
            } catch (InvalidArgumentException) {
                // Fall through to the newest planned period so a mistyped URL
                // still lands somewhere useful instead of erroring.
            }
        }

        $latest = $planClass::query()
            ->visibleTo($user)
            ->orderByDesc($planClass::PERIOD_START_COLUMN)
            ->value($planClass::PERIOD_START_COLUMN);

        if ($latest) {
            try {
                return BookkeepingPeriod::fromDate($this->periodKind(), Carbon::parse($latest));
            } catch (InvalidArgumentException) {
                // Fall through to the current period.
            }
        }

        return BookkeepingPeriod::current($this->periodKind());
    }

    // ---------------------------------------------------------------------
    // Tracker presentation helpers
    // ---------------------------------------------------------------------

    /**
     * Whether a task still needs a person to act on it.
     *
     * Composed only from facts the model already tracks: nobody is named on the
     * task, the period has closed with the task unfinished, or billing is still
     * outstanding.
     */
    protected function needsAttention(Model $target): bool
    {
        if ($target->isCompleted()) {
            return false;
        }

        return $target->assigned_staff_id === null
            || $target->isPastDue()
            || $target->isUnpaid();
    }

    /** Sort key so flagged work always leads the list. */
    protected function attentionRank(Model $target): int
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
     * @param  Collection<int, Model>  $group
     * @return array<string, mixed>
     */
    protected function buildClientGroup(Collection $group, User $user, bool $seesAll): array
    {
        $group = $group->sortBy(fn ($t) => [
            $this->attentionRank($t),
            $t->target_date?->format('Y-m-d') ?? '9999',
            $t->taskLabel(),
        ])->values();

        $items = $group
            ->map(fn ($t) => $this->presentTarget($t, $user, $seesAll))
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
     * inline actions the viewer is allowed to run. Mirrors
     * authorizeManageTarget() so a hidden button is never a 403 waiting to
     * happen.
     *
     * @return array<string, mixed>
     */
    protected function presentTarget(Model $t, User $user, bool $seesAll): array
    {
        $canManage = $seesAll || $t->isAssignedTo($user);
        $needsAttention = $this->needsAttention($t);
        $routePrefix = $this->bookkeepingConfig()['route_prefix'];

        $status = match (true) {
            $needsAttention => 'attention',
            $t->isCompleted() => 'completed',
            $t->isInProgress() => 'in_progress',
            default => 'pending',
        };

        return [
            'id' => $t->id,
            'bookkeeping_id' => $t->{$this->planForeignKey()},
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
            'show_url' => route($routePrefix.'.show', $t->{$this->planForeignKey()}).'#target-'.$t->id,
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
    protected function dueLabel(Model $target): string
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
     * Mirrors the comparison sheet: each target becomes one row, and only the
     * column matching its task type carries a value. The remaining columns
     * render as the workbook's "-", so the grid stays scannable.
     *
     * @param  Collection<int, Model>  $targets
     */
    protected function buildGridRows(Collection $targets): Collection
    {
        return $targets->map(function (Model $target) {
            $cells = [];
            foreach (array_keys($this->planClass()::TASK_TYPES) as $type) {
                $cells[$type] = $type === $target->task_type ? $target : null;
            }

            return [
                'target' => $target,
                'cells' => $cells,
            ];
        });
    }

    // ---------------------------------------------------------------------
    // Printable report
    // ---------------------------------------------------------------------

    /**
     * Printable/portable copy of the comparison sheet. It reuses the same
     * visibility rules as the tracker so a staff member can never export work
     * that was not assigned to them.
     *
     * @return array<string, mixed>
     */
    public function reportData(Request $request): array
    {
        $user = auth()->user();
        $seesAll = $user->isAdmin() || $user->isSupervisor();
        $planClass = $this->planClass();
        $targetClass = $this->targetClass();
        $config = $this->bookkeepingConfig();

        $period = $this->resolvePeriod($request, $user, $planClass);

        $plans = $planClass::query()
            ->visibleTo($user)
            ->whereDate($planClass::PERIOD_START_COLUMN, $period->startDate())
            ->with('staff')
            ->orderByDesc('id')
            ->get();

        $targets = $targetClass::query()
            ->whereIn($this->planForeignKey(), $plans->pluck('id'))
            ->with(['client', 'assignedStaff', 'performedBy'])
            ->get();

        $targetClass::primePaidBillings($targets);

        if (! $seesAll) {
            $targets = $targets->filter(
                fn ($t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id
            );
        }

        $rows = $this->buildGridRows($targets->sortBy(
            fn ($t) => [$t->target_date?->format('Y-m-d') ?? '9999', $t->displayClientName(), $t->task_type]
        )->values());

        return [
            'config' => $config,
            'period' => $period,
            'periodOptions' => $period->options(),
            'activePeriodKey' => $period->key(),
            'plans' => $plans,
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
        ];
    }

    // ---------------------------------------------------------------------
    // Target planning
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function createData(Request $request): array
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set targets.');

        $planClass = $this->planClass();
        $config = $this->bookkeepingConfig();

        $period = $this->resolvePeriod($request, $user, $planClass);

        $plan = $planClass::query()
            ->whereDate($planClass::PERIOD_START_COLUMN, $period->startDate())
            ->first();

        $existing = $plan ? $plan->targets()->get() : collect();

        $existingByClient = $existing->groupBy('client_id')
            ->map(fn ($targets) => $targets->groupBy('task_type'));

        $periodOptions = $period->options();

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('name')
            ->get(['id', 'name', 'business_name', 'profile_image_path']);

        return [
            'config' => $config,
            'period' => $period,
            'periodOptions' => $periodOptions,
            'activePeriodKey' => $period->key(),
            'clients' => $clients,
            'existingByClient' => $existingByClient,
            'plan' => $plan,
            'taskTypes' => $planClass::TASK_TYPES,
            'assignableStaff' => $this->assignableStaff(),
        ];
    }

    /**
     * Accounts allowed to own an individual task. Mirrors the workbook, where
     * every task names the staff member responsible for it.
     *
     * @return Collection<int, User>
     */
    protected function assignableStaff(): Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /**
     * Saves the whole period plan: upserts the selected targets, drops the ones
     * no longer selected while they are still pending, and leaves started work
     * (and its history) alone.
     */
    public function handleStore(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set targets.');

        $planClass = $this->planClass();
        $targetClass = $this->targetClass();
        $config = $this->bookkeepingConfig();
        $startField = $planClass::PERIOD_START_FIELD;

        /*
         * Derive the period from the submitted value first so the per-client
         * target dates can be range-checked against it: a target belonging to a
         * different month or quarter has no place in this plan.
         */
        $period = $this->periodForSubmission($request->input($startField));

        $validated = $request->validate([
            $startField => ['required', 'date'],
            'clients' => ['required', 'array', 'min:1'],
            'clients.*' => ['required', 'integer', 'exists:users,id'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['array'],
            'tasks.*.*' => ['required', 'in:pickup,record,return,payment'],
            /* Either one date for the whole client, which is how the planner has
               always submitted it, or a per-task map so two staff members on the
               same client can hold different dates. resolveAssignmentDate()
               range-checks whichever shape arrived. */
            'target_date' => ['nullable', 'array'],
            'target_date.*' => ['nullable'],
            'assignee' => ['nullable', 'array'],
            'assignee.*' => ['array'],
            'assignee.*.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $period = BookkeepingPeriod::fromKey($this->periodKind(), (string) $validated[$startField]);

        $selectedClientIds = array_values(array_map('intval', $validated['clients']));
        $tasks = $request->input('tasks', []);
        $dates = $request->input('target_date', []);
        $assigneeInput = $request->input('assignee', []);

        /* Resolve and range-check every date up front. The plan row is created
           below, and a rejected date must not leave a half-built period behind. */
        $resolvedDates = [];

        foreach ($selectedClientIds as $clientId) {
            foreach ($tasks[$clientId] ?? [] as $taskType) {
                /* Resolved per task, so one client can carry a different date for
                   each task type when the planner submits a per-task map. */
                $resolvedDates[$clientId][$taskType] = $this->resolveAssignmentDate(
                    $dates[$clientId] ?? null,
                    $taskType,
                    $period,
                    $clientId
                );
            }
        }

        $plan = $planClass::query()
            ->whereDate($planClass::PERIOD_START_COLUMN, $period->startDate())
            ->first();

        $wasNew = $plan === null;
        if ($plan === null) {
            $plan = $planClass::query()->create([
                'staff_id' => $user->id,
                $planClass::PERIOD_START_COLUMN => $period->startDate(),
                $planClass::PERIOD_END_COLUMN => $period->endDate(),
                'status' => $planClass::STATUS_NOT_STARTED,
            ]);
        } else {
            $plan->update([$planClass::PERIOD_END_COLUMN => $period->endDate()]);
        }

        // Only operational accounts may own a task; a Record-only bookkeeper
        // and a Pick-Up/Return/Payment bookkeeper are both valid assignees.
        $assignable = $this->assignableStaff();
        $assignableIds = $assignable->pluck('id')->map('intval')->all();
        $assignableNames = $assignable->pluck('name', 'id');

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
                            $this->logActivity(
                                $user,
                                $config['activity_prefix'].'.target.reassigned',
                                sprintf(
                                    '%s for %s reassigned from %s to %s.',
                                    $target->taskLabel(),
                                    $target->displayClientName(),
                                    $previous !== '' ? $previous : 'Unassigned',
                                    $assigneeId ? $assignableNames[$assigneeId] : 'Unassigned'
                                ),
                                $plan
                            );
                        }
                    }

                    $target->saveQuietly();
                } else {
                    $plan->targets()->create([
                        'client_id' => $clientId,
                        'task_type' => $taskType,
                        'target_date' => $date,
                        'assigned_staff_id' => $assigneeId,
                        'assigned_staff_name' => $assigneeId ? $assignableNames[$assigneeId] : null,
                        'actual_status' => $targetClass::ACTUAL_STATUS_PENDING,
                    ]);

                    $this->logActivity(
                        $user,
                        $config['activity_prefix'].'.target_added',
                        sprintf(
                            'Target added: %s — %s (Target date: %s%s).',
                            $this->clientName($clientId),
                            $targetClass::TASK_TYPES[$taskType] ?? $taskType,
                            $date ? Carbon::parse($date)->format('M j, Y') : 'Any day',
                            $assigneeId ? ', assigned to '.$assignableNames[$assigneeId] : ''
                        ),
                        $plan
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

            if ($target->actual_status === $targetClass::ACTUAL_STATUS_PENDING) {
                $target->delete();
                $removed++;

                $this->logActivity(
                    $user,
                    $config['activity_prefix'].'.target_removed',
                    sprintf('Target removed: %s — %s.', $this->clientName($target->client_id), $target->taskLabel()),
                    $plan
                );
            } else {
                $this->logActivity(
                    $user,
                    $config['activity_prefix'].'.target_removed',
                    sprintf(
                        'Target removed from the %s list: %s — %s (work already recorded, history preserved).',
                        strtolower($config['unit']),
                        $this->clientName($target->client_id),
                        $target->taskLabel()
                    ),
                    $plan
                );
            }
        }

        $plan->syncOverallStatus();

        $clientCount = count(array_unique($selectedClientIds));
        $taskCount = count($keepKeys);

        $activity = sprintf(
            '%s saved for %s (%d client%s, %d task%s, %d reassigned, %d removed).',
            ucfirst($config['noun']),
            $period->rangeLabel(),
            $clientCount,
            $clientCount === 1 ? '' : 's',
            $taskCount,
            $taskCount === 1 ? '' : 's',
            $reassigned,
            $removed
        );

        if ($wasNew) {
            $activity = sprintf(
                '%s created for %s. %d client%s, %d task%s selected.',
                ucfirst($config['noun']),
                $period->rangeLabel(),
                $clientCount,
                $clientCount === 1 ? '' : 's',
                $taskCount,
                $taskCount === 1 ? '' : 's'
            );
        }

        $this->logActivity($user, $config['activity_prefix'].'.targets_saved', $activity, $plan);

        return redirect()->route($config['route_prefix'].'.show', $plan)
            ->with('status', $config['noun_title'].'s saved.');
    }

    /**
     * Adds one staff-centered batch of assignments to the period: a single staff
     * member, one target date, one task type and any number of clients.
     *
     * This writes exactly the same row the client matrix writes — one target per
     * (client, task type) — but reached from the other direction, so naming
     * Angeli once covers three clients instead of the administrator repeating
     * the same selection for every row.
     *
     * The period is still the organising unit and the target date is still
     * stored per assignment, so two staff members inside the same month or
     * quarter keep their own independent dates.
     */
    public function handleBulkAssign(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isOperational(), 403, 'Only operational accounts can set targets.');

        $planClass = $this->planClass();
        $targetClass = $this->targetClass();
        $config = $this->bookkeepingConfig();
        $startField = $planClass::PERIOD_START_FIELD;

        /*
         * Resolve the period first so the submitted target date can be
         * range-checked against it: work dated outside this month or quarter
         * has no place in this plan.
         */
        $period = $this->periodForSubmission($request->input($startField));

        $validated = $request->validate([
            $startField => ['required', 'date'],
            'assigned_staff_id' => ['required', 'integer', 'exists:users,id'],
            'task_type' => ['required', 'in:pickup,record,return,payment'],
            'target_date' => [
                'required',
                'date',
                'after_or_equal:'.$period->startDate(),
                'before_or_equal:'.$period->endDate(),
            ],
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $period = BookkeepingPeriod::fromKey($this->periodKind(), (string) $validated[$startField]);

        // Only operational accounts may own a task, matching the client matrix.
        $assignee = $this->assignableStaff()->firstWhere('id', (int) $validated['assigned_staff_id']);

        if (! $assignee) {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'The selected account cannot be assigned a task.',
            ]);
        }

        $clientIds = array_values(array_unique(array_map('intval', $validated['client_ids'])));

        // Bookkeeping work is only ever handed to a client account, so a client
        // id that resolves to any other role is rejected rather than silently
        // creating a target against a staff member.
        $validClientIds = User::query()
            ->whereIn('id', $clientIds)
            ->where('role', User::ROLE_CLIENT)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validClientIds) !== count($clientIds)) {
            throw ValidationException::withMessages([
                'client_ids' => 'One or more selected accounts are not clients.',
            ]);
        }

        $plan = $planClass::query()
            ->whereDate($planClass::PERIOD_START_COLUMN, $period->startDate())
            ->first();

        $wasNew = $plan === null;

        if ($plan === null) {
            $plan = $planClass::query()->create([
                'staff_id' => $user->id,
                $planClass::PERIOD_START_COLUMN => $period->startDate(),
                $planClass::PERIOD_END_COLUMN => $period->endDate(),
                'status' => $planClass::STATUS_NOT_STARTED,
            ]);
        } else {
            $plan->update([$planClass::PERIOD_END_COLUMN => $period->endDate()]);
        }

        $taskType = $validated['task_type'];
        $taskLabel = $targetClass::TASK_TYPES[$taskType] ?? $taskType;
        $targetDate = Carbon::parse($validated['target_date'])->format('Y-m-d');

        $added = 0;
        $updated = 0;
        $reassigned = 0;

        foreach ($clientIds as $clientId) {
            /* One client + one task type per period is the existing rule, so a
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
                        $this->logActivity(
                            $user,
                            $config['activity_prefix'].'.target.reassigned',
                            sprintf(
                                '%s for %s reassigned from %s to %s.',
                                $target->taskLabel(),
                                $target->displayClientName(),
                                $previous !== '' ? $previous : 'Unassigned',
                                $assignee->name
                            ),
                            $plan
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
                    'actual_status' => $targetClass::ACTUAL_STATUS_PENDING,
                ]);

                $added++;

                $this->logActivity(
                    $user,
                    $config['activity_prefix'].'.target_added',
                    sprintf(
                        'Target added: %s — %s (Target date: %s, assigned to %s).',
                        $this->clientName($clientId),
                        $taskLabel,
                        Carbon::parse($targetDate)->format('M j, Y'),
                        $assignee->name
                    ),
                    $plan
                );
            }
        }

        $plan->syncOverallStatus();

        $this->logActivity(
            $user,
            $config['activity_prefix'].'.targets_saved',
            sprintf(
                '%s — %s assigned to %s (Target date: %s, %d client%s, %d added, %d updated, %d reassigned).',
                $config['noun_title'],
                $taskLabel,
                $assignee->name,
                Carbon::parse($targetDate)->format('M j, Y'),
                count($clientIds),
                count($clientIds) === 1 ? '' : 's',
                $added,
                $updated,
                $reassigned
            ),
            $plan
        );

        $message = sprintf(
            '%s assigned to %s for %s: %d client%s on %s.',
            $taskLabel,
            $assignee->name,
            $period->shortRangeLabel(),
            count($clientIds),
            count($clientIds) === 1 ? '' : 's',
            Carbon::parse($targetDate)->format('M j, Y')
        );

        if ($wasNew) {
            $message = sprintf('%s %s', $config['noun_title'], 'created for '.$period->shortRangeLabel().'. ').$message;
        }

        /* Back to the planner for the same period so the next staff member can
           be added without re-picking the period or rebuilding the form. */
        return redirect()
            ->route($config['route_prefix'].'.create', [$config['query_key'] => $period->key()])
            ->with('status', $message);
    }

    /**
     * Resolves the target date for a single (client, task type) assignment.
     *
     * The submitted value is either one date for the whole client, which is how
     * the client matrix has always posted it, or a per-task map. Either way the
     * date is range-checked against the plan's own period so a month or quarter
     * can never end up holding work dated outside itself.
     *
     * The error is keyed the way the planner addresses the field, so an invalid
     * date is reported against the client whose row it came from.
     *
     * @throws ValidationException
     */
    protected function resolveAssignmentDate(mixed $raw, string $taskType, BookkeepingPeriod $period, int|string $clientId): ?string
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
            throw ValidationException::withMessages([
                $errorKey => 'The target date is not a valid date.',
            ]);
        }

        if (! $period->contains($date->format('Y-m-d'))) {
            throw ValidationException::withMessages([
                $errorKey => 'The target date must fall between '
                    .$period->startDate().' and '.$period->endDate().'.',
            ]);
        }

        return $date->format('Y-m-d');
    }

    /**
     * Normalises a submitted period value, falling back to the current period so
     * a malformed value still produces a validation message rather than a 500.
     */
    protected function periodForSubmission(mixed $raw): BookkeepingPeriod
    {
        try {
            return BookkeepingPeriod::fromKey($this->periodKind(), (string) $raw);
        } catch (InvalidArgumentException) {
            return BookkeepingPeriod::current($this->periodKind());
        }
    }

    // ---------------------------------------------------------------------
    // Plan screens
    // ---------------------------------------------------------------------

    /**
     * @param  Model  $bookkeeping
     * @return array<string, mixed>
     */
    public function showData(Model $bookkeeping): array
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
                fn ($t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id
            );

        $planTargets = $visibleTargets->sortBy(fn ($t) => ($t->client?->name ?: '').'|'.$t->task_type)->values();

        return [
            'config' => $this->bookkeepingConfig(),
            'bookkeeping' => $bookkeeping,
            'period' => $bookkeeping->period(),
            'targets' => $planTargets,
            'taskTypes' => $this->planClass()::TASK_TYPES,
            'canManage' => $canManage,
            'canReassign' => $canReassign,
            'assignableStaff' => $this->assignableStaff(),
            'paymentMethods' => $this->targetClass()::PAYMENT_METHODS,
            'badgeClasses' => $this->badgeClasses(),
            'eventLabels' => $this->eventLabels(),
        ];
    }

    /**
     * @param  Model  $bookkeeping
     * @return array<string, mixed>
     */
    public function historyData(Model $bookkeeping): array
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
                ->filter(fn ($t) => $t->isAssignedTo($user) || $t->performed_by_id === $user->id)
                ->pluck('client_id')
                ->map(fn ($id) => $this->clientName($id));

            $history = $history->filter(function (ActivityLog $entry) use ($user, $mine) {
                return $entry->user_id === $user->id
                    || collect($mine)->contains(fn ($name) => str_contains((string) $entry->description, $name));
            })->values();
        }

        return [
            'config' => $this->bookkeepingConfig(),
            'bookkeeping' => $bookkeeping,
            'period' => $bookkeeping->period(),
            'history' => $history,
            'taskTypes' => $this->planClass()::TASK_TYPES,
            'badgeClasses' => $this->badgeClasses(),
            'eventLabels' => $this->eventLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function badgeClasses(): array
    {
        $planClass = $this->planClass();

        return [
            $planClass::STATUS_NOT_STARTED => 'badge-neutral',
            $planClass::STATUS_IN_PROGRESS => 'badge-info',
            $planClass::STATUS_COMPLETED => 'badge-success',
            $planClass::STATUS_DELAYED => 'badge-warn',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function eventLabels(): array
    {
        $prefix = $this->bookkeepingConfig()['activity_prefix'];
        $noun = ucfirst($this->bookkeepingConfig()['noun']);

        return [
            $prefix.'.created' => $noun.' created',
            $prefix.'.target_added' => 'Target added',
            $prefix.'.target_removed' => 'Target removed',
            $prefix.'.targets_saved' => 'Targets saved',
            $prefix.'.target.started' => 'Actual work started',
            $prefix.'.target.completed' => 'Actual work completed',
            $prefix.'.target.reassigned' => 'Task reassigned',
            $prefix.'.attachment_uploaded' => 'Evidence uploaded',
            $prefix.'.attachment_replaced' => 'Evidence replaced',
            $prefix.'.owner_updated' => 'Target owner changed',
        ];
    }

    /**
     * @param  Model  $bookkeeping
     */
    public function changeOwner(Request $request, Model $bookkeeping): RedirectResponse
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
            return back()->withErrors(['staff_id' => 'The selected account cannot own targets.']);
        }

        if ($bookkeeping->staff_id === $newOwner->id) {
            return back()->with('status', "{$newOwner->name} is already the target owner.");
        }

        $previousName = $bookkeeping->displayOwnerName();
        $bookkeeping->update(['staff_id' => $newOwner->id]);

        $this->logActivity(
            $user,
            $this->bookkeepingConfig()['activity_prefix'].'.owner_updated',
            "Target owner changed from {$previousName} to {$newOwner->name}.",
            $bookkeeping
        );

        return back()->with('status', 'Target owner updated.');
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function editTarget(Request $request, Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        // Allow editing notes when task is in progress (for assigned staff) or pending (for target date)
        // Supervisors and admins can edit regardless of status
        $user = auth()->user();
        $isOversight = $user->isAdmin() || $user->isSupervisor();
        $isAssignedStaff = $target->isAssignedTo($user);

        if (! $target->isPending() && ! ($target->isInProgress() && $isAssignedStaff) && ! $isOversight) {
            return back()->withErrors(['action' => 'This target has actual work recorded and can no longer be edited.']);
        }

        /* Balance columns are validated inside `targetWorkflowAttributes`, and
           only when the submitted form actually carried them. */
        $validated = $request->validate([
            'target_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $targetDate = $validated['target_date'] ?? $target->target_date?->format('Y-m-d');

        // A target date may only move inside the plan's own period, so a month
        // or quarter can never end up holding work that belongs to another one.
        if ($targetDate !== null && ! $bookkeeping->period()->contains($targetDate)) {
            return back()->withErrors([
                'target_date' => 'The target date must fall between '
                    .$bookkeeping->period()->startDate().' and '.$bookkeeping->period()->endDate().'.',
            ]);
        }

        // Target date can only be changed when the task is pending (or by oversight users)
        $canUpdateDate = $target->isPending() || $isOversight;

        // If user tries to change target_date on a non-pending task and is not oversight, return error
        $dateChanged = $targetDate !== null && $targetDate !== $target->target_date?->format('Y-m-d');
        if ($dateChanged && ! $canUpdateDate) {
            return back()->withErrors([
                'action' => 'Target date can only be changed while the task is pending.'
            ]);
        }

        $updateData = ['notes' => $validated['notes'] ?? null];
        if ($canUpdateDate) {
            $updateData['target_date'] = $targetDate;
        }

        $updateData = array_merge($updateData, $this->targetWorkflowAttributes($request, $target, $targetDate));

        $target->update($updateData);

        $refreshed = $target->task_type === 'pickup'
            ? $target->resequenceFrom($targetDate, fn ($sibling) => $this->mayManageTarget($bookkeeping, $sibling))
            : [];

        $message = "Target updated: {$target->displayClientName()} — {$target->taskLabel()} (Target date: "
            .$target->target_date?->format('M j, Y').').';

        if ($refreshed !== []) {
            $labels = array_map(
                fn ($taskType) => $target::SEQUENCE_LABELS[$taskType] ?? $taskType,
                $refreshed
            );

            $message .= ' Rescheduled to match the new Pick-Up date: '.implode(', ', $labels).'.';
        }

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.targets_saved',
            $message,
            $bookkeeping
        );

        return back()->with('status', $refreshed === [] ? 'Target updated.' : 'Target updated and later stages rescheduled.');
    }

    /**
     * The same task-level rule as `authorizeTarget`, phrased as a question so the
     * Pick-Up reschedule can check it for each sibling stage before touching it.
     * The plan-level rule is not repeated here: the siblings live in the same
     * plan, which the caller has already been authorized against.
     *
     * @param  Model  $target
     */
    private function mayManageTarget(Model $bookkeeping, Model $target): bool
    {
        $user = auth()->user();

        return $user->isAdmin()
            || $user->isSupervisor()
            || $target->isAssignedTo($user);
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
     * @param  Model  $target
     * @return array<string, mixed>
     */
    private function targetWorkflowAttributes(Request $request, Model $target, ?string $targetDate): array
    {
        $attributes = [];

        if ($request->has('target_date') && $targetDate !== $target->target_date?->format('Y-m-d')) {
            $attributes['target_date_auto'] = false;
        }

        if (! $request->has('payment_status')) {
            return $attributes;
        }

        $validated = $request->validate([
            'payment_status' => ['nullable', Rule::in(array_keys($target::PAYMENT_STATUSES))],
            'balance_amount' => ['nullable', 'numeric', 'min:0', 'required_if:payment_status,with_balance'],
            'balance_note' => ['nullable', 'string', 'max:500'],
        ]);

        $withBalance = $validated['payment_status'] === $target::PAYMENT_STATUS_WITH_BALANCE;

        return [
            ...$attributes,
            'payment_status' => $validated['payment_status'] ?? null,
            'balance_amount' => $withBalance ? ($validated['balance_amount'] ?? null) : null,
            'balance_note' => $withBalance ? ($validated['balance_note'] ?? null) : null,
        ];
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleStartTarget(Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        try {
            $target->startProcessing(auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.target.started',
            "{$target->taskLabel()} started for {$target->displayClientName()} at {$target->started_at->format('g:i A')}.",
            $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} marked as in progress.");
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleCompleteTarget(Request $request, Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        $targetClass = $this->targetClass();

        $validated = $request->validate([
            'payment_method' => ['nullable', 'in:'.implode(',', array_keys($targetClass::PAYMENT_METHODS))],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $this->validateAttachment($file, $target->task_type);

            $path = $file->store($this->storagePrefix()."/{$target->task_type}", 'supabase');

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
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        $performer = $target->performedByDisplayName();
        $duration = $target->durationHuman();
        $proof = $target->attachment_name;

        $description = "{$target->taskLabel()} {$target->effectiveStatusLabel()} for {$target->displayClientName()} by {$performer}."
            . ($target->timingLabel() ? " {$target->timingLabel()}." : '')
            . ($duration ? " Duration: {$duration}." : '')
            . ($proof ? " Evidence: {$proof}." : '');

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.target.completed',
            $description,
            $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} {$target->effectiveStatusLabel()}.");
    }

    /**
     * Reassigns a single task to different staff. Kept separate from saving the
     * whole period plan so a supervisor can correct ownership on one task, which
     * is the accountability trail the workbook relies on.
     *
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleReassignTarget(Request $request, Model $bookkeeping, Model $target): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403, 'Only admins or supervisors can reassign tasks.');

        // Oversight still has to name a task that belongs to the plan in the URL.
        $this->authorizeTarget($bookkeeping, $target);

        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newAssignee = $this->assignableStaff()->firstWhere('id', (int) ($validated['assigned_staff_id'] ?? 0));

        if (($validated['assigned_staff_id'] ?? null) && ! $newAssignee) {
            return back()->withErrors(['assigned_staff_id' => 'The selected account cannot be assigned a task.']);
        }

        $previous = $target->assignedStaffDisplayName();

        $target->update([
            'assigned_staff_id' => $newAssignee?->id,
            'assigned_staff_name' => $newAssignee?->name,
        ]);

        $this->logActivity(
            $user,
            $this->bookkeepingConfig()['activity_prefix'].'.target.reassigned',
            sprintf(
                '%s for %s reassigned from %s to %s.',
                $target->taskLabel(),
                $target->displayClientName(),
                $previous !== '' ? $previous : 'Unassigned',
                $newAssignee?->name ?? 'Unassigned'
            ),
            $bookkeeping
        );

        return back()->with('status', "{$target->taskLabel()} reassigned to ".($newAssignee?->name ?? 'Unassigned').'.');
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleUploadAttachment(Request $request, Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        if ($target->isCompleted()) {
            return back()->withErrors(['action' => 'Cannot upload evidence to a completed task.']);
        }

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['attachment'];
        $this->validateAttachment($file, $target->task_type);

        $path = $file->store($this->storagePrefix()."/{$target->task_type}", 'supabase');

        if ($target->attachment_path) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $target->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ]);

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.attachment_uploaded',
            "Uploaded {$target->attachmentHint()} for {$target->taskLabel()} — {$target->displayClientName()} ({$file->getClientOriginalName()}).",
            $bookkeeping
        );

        return back()->with('status', 'Evidence uploaded.');
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleReplaceAttachment(Request $request, Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['attachment'];
        $this->validateAttachment($file, $target->task_type);

        if ($target->attachment_path) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $path = $file->store($this->storagePrefix()."/{$target->task_type}", 'supabase');

        $target->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ]);

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.attachment_replaced',
            "Replaced {$target->attachmentHint()} for {$target->taskLabel()} — {$target->displayClientName()} ({$file->getClientOriginalName()}).",
            $bookkeeping
        );

        return back()->with('status', 'Evidence replaced.');
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleDownloadAttachment(Model $bookkeeping, Model $target): StreamedResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        abort_unless($target->attachment_path, 404);
        abort_unless(Storage::disk('supabase')->exists($target->attachment_path), 404);

        return Storage::disk('supabase')->download($target->attachment_path, $target->attachment_name);
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function handleViewAttachment(Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        abort_unless($target->attachment_path, 404);
        abort_unless(Storage::disk('supabase')->exists($target->attachment_path), 404);

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($target->attachment_path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    public function removeTarget(Model $bookkeeping, Model $target): RedirectResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        if (! $target->isPending()) {
            return back()->withErrors(['action' => 'Cannot delete a target with actual work recorded. Remove it from the '.$this->bookkeepingConfig()['unit'].' target list instead (history is preserved).']);
        }

        if ($target->attachment_path && Storage::disk('supabase')->exists($target->attachment_path)) {
            Storage::disk('supabase')->delete($target->attachment_path);
        }

        $clientName = $target->displayClientName();
        $taskLabel = $target->taskLabel();

        $target->delete();
        $bookkeeping->syncOverallStatus();

        $this->logActivity(
            auth()->user(),
            $this->bookkeepingConfig()['activity_prefix'].'.target_removed',
            "Target removed: {$clientName} — {$taskLabel}.",
            $bookkeeping
        );

        return back()->with('status', 'Target removed.');
    }

    /**
     * @param  Model  $bookkeeping
     */
    public function deletePlan(Model $bookkeeping): RedirectResponse
    {
        $config = $this->bookkeepingConfig();

        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete targets.');

        foreach ($bookkeeping->targets as $target) {
            if ($target->attachment_path && Storage::disk('supabase')->exists($target->attachment_path)) {
                Storage::disk('supabase')->delete($target->attachment_path);
            }
        }

        $periodLabel = $bookkeeping->period()->rangeLabel();
        $targetCount = $bookkeeping->targets()->count();

        $bookkeeping->delete();

        $this->logActivity(
            auth()->user(),
            'admin.'.$config['activity_prefix'].'_deleted',
            "Deleted {$config['noun']} plan for {$periodLabel} ({$targetCount} target tasks).",
            null
        );

        return redirect()->route($config['route_prefix'].'.index')
            ->with('status', $config['noun_title'].' plan deleted.');
    }

    // ---------------------------------------------------------------------
    // Authorisation, naming and attachments
    // ---------------------------------------------------------------------

    /**
     * @param  Model  $bookkeeping
     */
    protected function authorizeManage(Model $bookkeeping): void
    {
        $user = auth()->user();
        abort_unless(
            $user->isAdmin() || $user->isSupervisor() || $bookkeeping->isAssignedTo($user),
            403,
            'You are not assigned to this '.$this->bookkeepingConfig()['noun'].' plan.'
        );
    }

    /**
     * Task-level accountability: a staff member may only work on tasks assigned
     * to them, which is what keeps a Record-only bookkeeper out of Pick-Up,
     * Return and Payment work. Admins and supervisors cover the whole period.
     *
     * @param  Model  $bookkeeping
     * @param  Model  $target
     */
    protected function authorizeTarget(Model $bookkeeping, Model $target): void
    {
        $user = auth()->user();
        $target->loadMissing('bookkeeping');

        abort_unless(
            $bookkeeping->id === $target->{$this->planForeignKey()},
            404,
            'That task does not belong to this plan.'
        );

        $isOversight = $user->isAdmin() || $user->isSupervisor();

        abort_unless(
            $isOversight || $target->isAssignedTo($user),
            403,
            'This task is assigned to '.($target->assignedStaffDisplayName() !== '' ? $target->assignedStaffDisplayName() : 'someone else').'.'
        );

        $this->authorizeManage($bookkeeping);
    }

    /**
     * @param  Model|null  $bookkeeping
     */
    protected function logActivity(?User $user, string $action, ?string $description, ?Model $bookkeeping = null): void
    {
        $links = ['instance' => null];

        if ($bookkeeping !== null) {
            $links[$this->activityLinkName()] = $bookkeeping;
        }

        ActivityLog::record($user, $action, $description, ...$links);
    }

    protected function clientName(int $clientId): string
    {
        $client = User::query()->whereKey($clientId)->first(['id', 'name', 'business_name']);

        return $client?->business_name ?: $client?->name ?: 'Client #'.$clientId;
    }

    /**
     * @throws ValidationException
     */
    protected function validateAttachment(UploadedFile $file, string $taskType): void
    {
        $allowedMimes = [
            'pickup' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'record' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'return' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'payment' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];

        $mime = $file->getMimeType();
        if (! in_array($mime, $allowedMimes[$taskType] ?? [], true)) {
            throw ValidationException::withMessages(['attachment' => 'Invalid file type for this task.']);
        }
    }
}
