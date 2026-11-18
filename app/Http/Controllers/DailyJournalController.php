<?php

namespace App\Http\Controllers;

use App\Models\DailyGoal;
use App\Models\DailyJournal;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use App\Models\WorkPlanLegend;
use App\Models\WorkPlanTask;
use App\Services\AchievementAnalysisService;
use App\Services\AchievementCriteriaService;
use App\Services\AchievementRankingService;
use App\Services\DailyJournalService;
use App\Services\DailyReportScoreCalculator;
use App\Services\StaffScoringService;
use App\Services\TeamSupervisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
    public function __construct(private readonly TeamSupervisionService $supervision) {}

    /**
     * Show the journal form for the authenticated employee.
     * For a specific date (defaults to today).
     * Admin users are redirected to the monitoring page.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        // Admin users don't submit journals - redirect to monitoring
        if ($user->isAdmin()) {
            return redirect()->route('admin.daily-journal.admin-monitor');
        }

        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::today();

        // Reports are only required Monday to Friday. Never create a "missing"
        // row for a weekend: otherwise the badge/overdue state would inflate
        // for days that are not actually required.
        $requiresReport = DailyJournalService::isWorkingDay($date);

        $dayHeading = match (true) {
            $date->isToday() => 'Today',
            $date->isYesterday() => 'Yesterday',
            default => $date->format('l'),
        };

        if ($requiresReport) {
            // Employees can only submit for themselves
            $journal = DailyJournalService::getOrCreateForUserAndDate($user->id, $date);
        } else {
            // Show any historical weekend entry (read-only) without creating one.
            $journal = DailyJournal::query()
                ->where('user_id', $user->id)
                ->whereDate('required_date', $date->toDateString())
                ->first();
        }

        $isToday = $date->isToday();
        $isPast = $date->lt(Carbon::today());
        $isFuture = $date->gt(Carbon::today());

        $isSubmitted = $journal ? $journal->isNotMissing() : false;
        $statusBadge = $journal ? $journal->getStatusBadgeClass() : 'badge-info';
        $statusLabel = $journal ? $journal->getStatusLabel() : 'Not required';
        $statusIcon = $journal ? $journal->getStatusIcon() : '—';

        // Goals for the Day: active goals for the selected date
        $dailyGoals = DailyGoal::query()
            ->where('user_id', $user->id)
            ->where('target_date', $date->toDateString())
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        // Work plan tasks for the selected date
        $workPlanTasks = WorkPlanTask::query()
            ->where('user_id', $user->id)
            ->where('work_date', $date->toDateString())
            ->with(['legend', 'creator'])
            ->orderBy('id')
            ->get();

        // Active Achievement Legend Codes
        $achievementLegends = WorkPlanLegend::active()
            ->with('clients:id,name')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        // Clients offered in the picker for legend codes that require a client name.
        // Assumes clients are User rows with the client role; adjust if your
        // client names are stored somewhere else.
        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('name')
            ->get(['id', 'name']);

        $prompt = [
            'problems_encountered' => ['label' => 'Problems Encountered', 'placeholder' => 'Describe any problems, issues, or obstacles you encountered today...', 'hint' => 'Required. Include anything that delayed your work and why.', 'example' => 'Example: Client submitted the required documents late, so the filing could not be completed on schedule.'],
            'achievements' => ['label' => 'Achievements', 'placeholder' => 'Select Activity Legend Codes for completed work', 'hint' => 'Required. Select the codes for work you have completed.', 'example' => 'Example: A, B, F - select the codes for tasks you finished.'],
            'suggested_solutions' => ['label' => 'Suggested Solutions', 'placeholder' => 'Propose solutions for problems encountered, process improvements, or recommendations...', 'hint' => 'Required. Say what would stop this happening again, or what would make the job easier.', 'example' => 'Example: Ask clients to send source documents by the 5th so month-end closing is not compressed.'],
        ];

        // Urgent tasks: restore what the employee just typed if validation
        // failed (old input), otherwise nothing is pre-filled.
        $urgentTasks = collect((array) old('urgent_tasks', $journal?->urgent_tasks ?? []))
            ->filter(fn ($task) => is_array($task)
                && trim((string) ($task['description'] ?? '')) !== ''
                && trim((string) ($task['reason'] ?? '')) !== '')
            ->map(fn ($task) => [
                'description' => trim((string) $task['description']),
                'reason' => trim((string) $task['reason']),
            ])
            ->values()
            ->all();

        return view('daily-journal.create', [
            'journal' => $journal,
            'date' => $date,
            'isToday' => $isToday,
            'isPast' => $isPast,
            'isFuture' => $isFuture,
            'requiresReport' => $requiresReport,
            'user' => $user,
            'dailyGoals' => $dailyGoals,
            'workPlanTasks' => $workPlanTasks,
            'achievementLegends' => $achievementLegends,
            'clients' => $clients,
            'prompt' => $prompt,
            'dayHeading' => $dayHeading,
            'isSubmitted' => $isSubmitted,
            'statusBadge' => $statusBadge,
            'statusLabel' => $statusLabel,
            'statusIcon' => $statusIcon,
            'urgentTasks' => $urgentTasks,
        ]);
    }

    /**
     * Store the journal.
     *
     * The form posts an "action" field:
     *   - "submit" => validates every section and submits the report.
     *   - anything else (normally "draft") => saves the text as a draft and
     *     leaves the report editable (status stays "missing").
     *
     * Only Staff and Supervisor can submit journals.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Admin cannot submit journals
        if ($user->isAdmin()) {
            abort(403, 'Admin users do not submit daily journals.');
        }

        // Client cannot submit journals
        if ($user->isClient()) {
            abort(403, 'Client users cannot access daily journals.');
        }

        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $back = fn (): RedirectResponse => redirect()
            ->route('daily-journal.create', ['date' => $date->toDateString()]);

        // Reports are only required Monday to Friday; reject weekend submissions
        // so no weekend obligation is ever created.
        if (! DailyJournalService::isWorkingDay($date)) {
            return $back()->withErrors(['date' => 'Daily Accomplishment Reports are only required Monday through Friday.']);
        }

        // The form is disabled for future dates; enforce it on the server too.
        if ($date->gt(Carbon::today())) {
            return $back()->withErrors(['date' => 'This report is not due yet.']);
        }

        // Anything other than an explicit "submit" is treated as a draft.
        $isSubmit = $request->input('action') === 'submit';

        $journal = DailyJournalService::getOrCreateForUserAndDate($user->id, $date);

        // A submitted report is locked; changes go through the supervisor.
        if ($journal->isNotMissing()) {
            return $back()->withErrors(['date' => 'This report was already submitted. Please contact your supervisor to change it.']);
        }

        // The three text sections are required only when actually submitting;
        // a draft is allowed to be partly (or entirely) empty.
        $textRule = $isSubmit ? 'required|string' : 'nullable|string';

        $request->validate([
            'problems_encountered' => $textRule,
            'achievements' => $textRule,
            'suggested_solutions' => $textRule,
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            'urgent_tasks' => 'nullable|array',
            'urgent_tasks.*.description' => 'nullable|string|max:255',
            'urgent_tasks.*.reason' => 'nullable|string|max:255',
            'achievement_codes' => 'nullable|array',
            'achievement_codes.*' => 'string|max:20',
            'achievement_clients' => 'nullable|array',
            'achievement_clients.*' => 'array',
            'achievement_clients.*.*' => 'integer',
        ]);

        // Handle file uploads
        $newPaths = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $newPaths[] = $file->store('daily-journals/'.$user->id.'/'.$date->format('Y-m-d'), 'supabase');
            }
        }

        // Keep any files attached by an earlier draft.
        $allPaths = array_values(array_unique(array_merge($journal->evidencePaths(), $newPaths)));

        // Keep only codes that exist as active legends, with no duplicates.
        $validCodes = WorkPlanLegend::active()->pluck('code')->all();
        $codes = collect((array) $request->input('achievement_codes', []))
            ->filter(fn ($code) => is_string($code) && in_array($code, $validCodes, true))
            ->unique()
            ->values()
            ->all();

        // Clients are only kept for ticked codes that actually ask for a client,
        // and only if the id belongs to a real client account.
        $clientLegends = WorkPlanLegend::active()
            ->where('requires_client', true)
            ->with('clients:id')
            ->get()
            ->keyBy('code');
        $clientCodes = $clientLegends->keys()->all();
        $validClientIds = User::query()->where('role', User::ROLE_CLIENT)->pluck('id')->all();

        $achievementClients = collect((array) $request->input('achievement_clients', []))
            ->filter(fn ($ids, $code) => in_array($code, $codes, true) && in_array($code, $clientCodes, true))
            ->map(function ($ids, $code) use ($clientLegends, $validClientIds) {
                // Only the clients assigned to this code are allowed; if none are
                // assigned yet, any client account is accepted.
                $assigned = $clientLegends[$code]->clients->pluck('id')->all();
                $allowed = $assigned !== [] ? $assigned : $validClientIds;

                return collect((array) $ids)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => in_array($id, $allowed, true))
                    ->unique()
                    ->values()
                    ->all();
            })
            ->filter()
            ->all();

        // Submitting requires at least one client for each ticked client code.
        if ($isSubmit) {
            $missing = collect($codes)
                ->intersect($clientCodes)
                ->reject(fn ($code) => ! empty($achievementClients[$code]))
                ->values();

            if ($missing->isNotEmpty()) {
                return $back()
                    ->withInput()
                    ->withErrors(['achievement_clients' => 'Select at least one client for: '.$missing->implode(', ').'.']);
            }
        }

        // Urgent tasks (work not in Goals for the Day). They are stored so the score can count them.
        $urgentTasks = collect((array) $request->input('urgent_tasks', []))
            ->filter(fn ($task) => is_array($task)
                && trim((string) ($task['description'] ?? '')) !== ''
                && trim((string) ($task['reason'] ?? '')) !== '')
            ->map(fn ($task) => [
                'description' => trim((string) $task['description']),
                'reason' => trim((string) $task['reason']),
            ])
            ->values()
            ->all();

        // Saved before the submit/draft branch so both paths keep the codes
        // and clients (submitJournal() does not need to know about them).
        $journal->forceFill([
            'achievement_codes' => $codes,
            'achievement_clients' => $achievementClients,
            'urgent_tasks' => $urgentTasks,
        ])->save();

        if ($isSubmit) {
            DailyJournalService::submitJournal(
                $user->id,
                $date,
                $request->string('problems_encountered')->toString(),
                $request->string('achievements')->toString(),
                $request->string('suggested_solutions')->toString(),
                $allPaths
            );

            return $back()->with('status', 'Daily journal submitted successfully.');
        }

        // Draft: store the text only. The report stays "missing", so the form
        // remains editable and the overdue/missing counters are unaffected.
        $journal->forceFill([
            'problems_encountered' => $request->input('problems_encountered'),
            'achievements' => $request->input('achievements'),
            'suggested_solutions' => $request->input('suggested_solutions'),
            // Confirm this column name against DailyJournal::evidencePaths().
            'evidence_paths' => $allPaths,
        ])->save();

        return $back()->with('status', 'Draft saved. You can come back and finish it.');
    }

    /**
     * Admin monitoring view - shows all employees' journal status for a date.
     */
    public function adminMonitor(Request $request): View
    {
        $this->authorizeAdmin();

        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::today();
        $userId = $request->get('user_id');
        $role = $request->get('role');
        $status = $request->get('status');

        $journals = DailyJournalService::getFilteredJournals($date, $userId, $role, $status);

        $employees = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $availableDates = DailyJournalService::getAvailableDates();

        // Goals for the Day: active daily goals and Work Plan assignments for the selected date, grouped by user
        $dailyGoals = DailyGoal::query()
            ->where('target_date', $date->toDateString())
            ->where('status', DailyGoal::STATUS_ACTIVE)
            ->with(['user', 'creator'])
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        $workPlanGoals = WorkPlanTask::query()
            ->where('work_date', $date->toDateString())
            ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
            ->with(['user', 'legend'])
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        $goalItems = collect();

        foreach ($dailyGoals as $goal) {
            $goalItems->push((object) [
                'user_id' => $goal->user_id,
                'user' => $goal->user,
                'title' => $goal->title,
                'description' => $goal->description,
                'success_criteria' => $goal->success_criteria,
                'target_date' => $goal->target_date,
                'source' => 'daily_goal',
                'dedupe_key' => mb_strtolower(trim((string) $goal->title)),
            ]);
        }

        foreach ($workPlanGoals as $task) {
            $label = $task->getLegendLabel();
            $dedupeKey = mb_strtolower(trim(($task->legend_code ? $task->legend_code.'|' : '').$label));

            $goalItems->push((object) [
                'user_id' => $task->user_id,
                'user' => $task->user,
                'title' => $label,
                'description' => $task->description,
                'success_criteria' => $task->success_criteria,
                'target_date' => $task->work_date,
                'source' => 'work_plan',
                'dedupe_key' => $dedupeKey,
            ]);
        }

        $goalsByUser = $goalItems
            ->filter(fn ($goal) => $goal->user !== null)
            ->groupBy('user_id')
            ->map(function ($items) {
                $seen = [];

                return $items->filter(function ($item) use (&$seen) {
                    $key = $item->dedupe_key;
                    if (isset($seen[$key])) {
                        return false;
                    }
                    $seen[$key] = true;

                    return true;
                })->values();
            })
            ->filter(fn ($items) => $items->isNotEmpty());

        return view('daily-journal.admin-monitor', [
            'journals' => $journals,
            'employees' => $employees,
            'date' => $date,
            'selectedUserId' => $userId,
            'selectedRole' => $role,
            'selectedStatus' => $status,
            'availableDates' => $availableDates,
            'goalsByUser' => $goalsByUser,
        ]);
    }

    /**
     * Supervisor monitoring view - shows journals for staff under their supervision.
     */
    public function supervisorMonitor(Request $request): View
    {
        $this->authorizeSupervisor();

        $user = Auth::user();
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::today();
        $status = $request->get('status');

        $journals = DailyJournalService::getFilteredJournals($date, null, null, $status);
        $teamUserIds = $this->supervision->teamUserIds($user);

        // Filter to the supervisor's own journal plus the staff whose roster
        // entries place them under this supervisor.
        $journals = $journals->filter(function ($journal) use ($teamUserIds) {
            return $journal->user && $teamUserIds->contains($journal->user->id);
        })->values();

        $availableDates = DailyJournalService::getAvailableDates();

        return view('daily-journal.supervisor-monitor', [
            'journals' => $journals,
            'date' => $date,
            'selectedStatus' => $status,
            'availableDates' => $availableDates,
            'supervisor' => $user,
        ]);
    }

    /**
     * View a single journal entry (for admin/supervisor review).
     */
    public function show(DailyJournal $dailyJournal): View
    {
        $user = Auth::user();

        // Authorization: admin can see all, supervisor can see their staff, employee can see own
        if ($user->isAdmin()) {
            // allowed
        } elseif ($user->isSupervisor()) {
            abort_unless($this->canReviewAssessment($user, $dailyJournal), 403);
        } else {
            abort_unless($dailyJournal->user_id === $user->id, 403);
        }

        return view('daily-journal.show', [
            'journal' => $dailyJournal->load(['user', 'reviewer', 'assessmentRevisions.reviewer']),
            'categories' => config('achievements.categories', []),
            'levels' => collect(config('achievements.levels', []))->pluck('label')->values()->all(),
            'canReview' => $this->canReviewAssessment($user, $dailyJournal),
        ]);
    }

    /**
     * Internal achievement rankings. Staff, supervisors, and admins may view
     * recognition results; clients are denied by both role middleware and this
     * explicit check.
     */
    public function achievements(Request $request, AchievementRankingService $rankingService): View
    {
        $user = Auth::user();
        abort_if($user->isClient(), 403, 'Clients cannot access achievement rankings.');

        $period = $request->string('period')->toString() ?: 'week';

        if (! in_array($period, ['today', 'week', 'month', 'custom'], true)) {
            $period = 'week';
        }

        $today = Carbon::today();

        [$start, $end] = match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'custom' => $this->customPeriod($request),
            default => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
        };

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $rankings = $rankingService->rankings($start, $end, $user);

        $criteriaScored = $rankings->filter(function ($row) {
            return $row['quality_score'] !== null
                || $row['timeliness_score'] !== null
                || $row['responsiveness_score'] !== null
                || $row['client_feedback_score'] !== null;
        })->count();

        // --- Summary metrics for the ranking dashboard ---
        $overallScores = $rankings->pluck('overall_score')->filter(fn ($v) => $v !== null);
        $averageOverallScore = $overallScores->isNotEmpty()
            ? round($overallScores->avg(), 1)
            : null;
        $highestScore = $overallScores->isNotEmpty()
            ? (float) $overallScores->max()
            : null;

        // Latest assessed journal per ranked employee, for "View Score Sheet" links.
        $latestJournals = DailyJournal::query()
            ->whereIn('user_id', $rankings->pluck('user.id')->all())
            ->whereNotNull('achievement_score')
            ->latest('required_date')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($journals) => $journals->first());

        // Pending integrations: evaluations not yet linked to the Score Sheet.
        // The two conditions are grouped so the OR cannot escape the rest of
        // the query.
        $pendingIntegrations = StaffTaskEvaluation::query()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotIn('evaluation_status', [
                        StaffTaskEvaluation::STATUS_FINALIZED,
                        StaffTaskEvaluation::STATUS_REVERSED,
                    ])->whereNull('score_sheet_included');
                })->orWhere(function ($q) {
                    $q->where('score_sheet_included', false)
                        ->whereIn('evaluation_status', [
                            StaffTaskEvaluation::STATUS_FINALIZED,
                            StaffTaskEvaluation::STATUS_ADJUSTED,
                        ]);
                });
            })
            ->count();

        // Assessments awaiting admin review (provisional or awaiting_review).
        $awaitingReview = StaffTaskEvaluation::query()
            ->whereIn('evaluation_status', [
                StaffTaskEvaluation::STATUS_PROVISIONAL,
                StaffTaskEvaluation::STATUS_AWAITING_REVIEW,
            ])
            ->count();

        return view('daily-journal.achievements', [
            'rankings' => $rankings,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'verifiedCount' => $rankings->sum('verified_count'),
            'provisionalCount' => $rankings->sum('provisional_count'),
            'employeeCount' => $rankings->count(),
            'criteriaScored' => $criteriaScored,
            'averageOverallScore' => $averageOverallScore,
            'highestScore' => $highestScore,
            'pendingIntegrations' => $pendingIntegrations,
            'awaitingReview' => $awaitingReview,
            'latestJournals' => $latestJournals,
        ]);
    }

    /**
     * Correct an assessment. Admin only; the correction and its reason are
     * written to the audit history. Supervisors and staff cannot modify scores.
     */
    public function reviewAssessment(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $levels = collect(config('achievements.levels', []))->pluck('label')->all();

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:0,100'],
            'category' => ['required', 'string', Rule::in(config('achievements.categories', []))],
            'level' => ['nullable', 'string', Rule::in($levels)],
            'explanation' => ['required', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'reason' => [
                Rule::requiredIf($dailyJournal->hasAssessment()),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $tags = collect(preg_split('/[,;\n]+/', (string) ($validated['tags'] ?? '')))
            ->map(fn (string $tag): string => Str::limit(trim($tag), 40, ''))
            ->filter()
            ->take(8)
            ->values()
            ->all();

        app(AchievementAnalysisService::class)->applyReview(
            $dailyJournal,
            Auth::user(),
            (int) $validated['score'],
            $validated['category'],
            $validated['explanation'],
            $tags,
            $validated['reason'] ?? null,
            $validated['level'] ?? null
        );

        return back()->with('status', 'Manual assessment saved and verified.');
    }

    /**
     * Admin-only manual reassessment with per-criterion adjustments.
     * Preserves the original AI score and records each changed criterion.
     */
    public function manualReassess(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'criteria' => ['required', 'array'],
            'criteria.*.score' => ['required', 'numeric', 'between:0,100'],
            'criteria.*.max' => ['required', 'numeric', 'between:0,100'],
            'criteria.*.reason' => ['nullable', 'string', 'max:500'],
            'weights.target_completion' => ['nullable', 'numeric', 'between:0,100'],
            'weights.problems_solutions' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $weights = [
            'target_completion' => (float) ($validated['weights']['target_completion'] ?? DailyReportScoreCalculator::TASK_POINTS),
            'problems_solutions' => (float) ($validated['weights']['problems_solutions'] ?? DailyReportScoreCalculator::PROBLEMS_POINTS),
        ];

        app(AchievementAnalysisService::class)->manualReassess(
            $dailyJournal,
            Auth::user(),
            $validated['criteria'],
            $weights,
            $validated['reason']
        );

        return back()->with('status', 'Manual reassessment saved and verified.');
    }

    /**
     * Admin-only edit of the Activity Legend codes recorded on a journal.
     * Only the codes change: the report text and the assessment are untouched.
     */
    public function updateAchievementCodes(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $current = collect((array) ($dailyJournal->achievement_codes ?? []))
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->values()
            ->all();

        // Active legend codes, plus codes already on this report, so a retired
        // code that was previously selected is not rejected or silently dropped.
        $allowed = WorkPlanLegend::active()
            ->pluck('code')
            ->merge($current)
            ->unique()
            ->values()
            ->all();

        $validated = $request->validate([
            'achievement_codes' => ['nullable', 'array'],
            'achievement_codes.*' => ['string', Rule::in($allowed)],
            'achievement_clients' => ['nullable', 'array'],
            'achievement_clients.*' => ['array'],
            'achievement_clients.*.*' => ['integer'],
        ]);

        $codes = collect($validated['achievement_codes'] ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $dailyJournal->forceFill([
            'achievement_codes' => $codes,
            'achievement_clients' => $this->cleanAchievementClients((array) ($validated['achievement_clients'] ?? []), $codes),
        ])->save();

        logger()->info('Admin updated journal activity codes.', [
            'journal_id' => $dailyJournal->id,
            'admin_id' => Auth::id(),
            'before' => $current,
            'after' => $codes,
        ]);

        return redirect()
            ->to(route('daily-journal.show', $dailyJournal).'#activity-codes')
            ->with('status', 'Activity codes updated.');
    }

    /**
     * Explicitly re-run analysis when a reviewer asks for it. Admin only.
     */
    public function reassessAssessment(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        app(AchievementAnalysisService::class)->reassess(
            $dailyJournal,
            Auth::user(),
            $validated['reason'] ?? null
        );

        return back()->with('status', 'Automatic reassessment completed. Review and verify it if the result is correct.');
    }

    /**
     * Display the detailed score sheet for a single assessed journal.
     *
     * The score has two parts: tasks (90 points) and problems/solutions (10
     * points). See DailyReportScoreCalculator for the exact rules.
     */
    public function scoreSheet(DailyJournal $dailyJournal): View
    {
        $user = Auth::user();

        // Authorization: admin can see all, supervisor can see their staff,
        // employee can see own.
        if ($user->isAdmin()) {
            // allowed
        } elseif ($user->isSupervisor()) {
            abort_unless($this->canReviewAssessment($user, $dailyJournal), 403);
        } else {
            abort_unless($dailyJournal->user_id === $user->id, 403);
        }

        $dailyJournal->load(['user', 'reviewer', 'assessmentRevisions.reviewer']);

        $taskMax = (float) DailyReportScoreCalculator::TASK_POINTS;
        $problemsMax = (float) DailyReportScoreCalculator::PROBLEMS_POINTS;

        $breakdown = app(DailyReportScoreCalculator::class)->calculate($dailyJournal);
        $nothingScheduled = $breakdown['mode'] === 'nothing_scheduled';

        $weights = [
            'target_completion' => $taskMax,
            'problems_solutions' => $problemsMax,
        ];

        // Each part is shown out of 100 so it can be weighted (90 / 10) like before.
        $criteria = [
            'target_completion' => $nothingScheduled ? null : round($breakdown['task_points'] / $taskMax * 100, 1),
            'problems_solutions' => round($breakdown['problems_points'] / $problemsMax * 100, 1),
            'target_completion_explanation' => $nothingScheduled
                ? 'No tasks were assigned or added as urgent for this day, so this part is not evaluated.'
                : sprintf(
                    '%d of %d assigned task(s) completed (%.1f points each). %d urgent task(s) added (%.1f points each). Task points: %.1f of %d.',
                    $breakdown['assigned_completed'],
                    $breakdown['assigned_total'],
                    $breakdown['assigned_points_each'],
                    $breakdown['urgent_count'],
                    $breakdown['urgent_points_each'],
                    $breakdown['task_points'],
                    DailyReportScoreCalculator::TASK_POINTS
                ),
            'problems_solutions_explanation' => sprintf(
                'Problems Encountered %s. Suggested Solutions %s. Each filled-in section (at least %d characters) earns %.0f points.',
                $breakdown['problems_filled'] ? 'is filled in' : 'is missing or too short',
                $breakdown['solutions_filled'] ? 'is filled in' : 'is missing or too short',
                DailyReportScoreCalculator::MIN_TEXT_LENGTH,
                $problemsMax / 2
            ),
            'target_completion_meta' => [
                'assigned_total' => $breakdown['assigned_total'],
                'assigned_completed' => $breakdown['assigned_completed'],
                'urgent_count' => $breakdown['urgent_count'],
                'mode' => $breakdown['mode'],
            ],
            'problems_solutions_meta' => [
                'problems_filled' => $breakdown['problems_filled'],
                'solutions_filled' => $breakdown['solutions_filled'],
            ],
        ];

        $combined = app(AchievementCriteriaService::class)->combine($criteria, $weights);

        $criterionEvidence = [
            'target_completion' => $nothingScheduled
                ? 'No assigned work plan tasks or urgent tasks for this date.'
                : $breakdown['assigned_completed'].' of '.$breakdown['assigned_total'].' assigned work plan task(s) were completed, plus '.$breakdown['urgent_count'].' urgent task(s) listed in the report.',
            'problems_solutions' => 'Problems Encountered: '.($breakdown['problems_filled'] ? 'filled in' : 'missing or too short').'. Suggested Solutions: '.($breakdown['solutions_filled'] ? 'filled in' : 'missing or too short').'.',
        ];

        // Tasks behind the score: the day's assigned tasks and the urgent tasks.
        $assignedTasks = WorkPlanTask::query()
            ->where('user_id', $dailyJournal->user_id)
            ->where('work_date', $dailyJournal->required_date->toDateString())
            ->where('status', '!=', WorkPlanTask::STATUS_CANCELLED)
            ->with('legend')
            ->orderBy('id')
            ->get();

        $urgentTasks = collect((array) ($dailyJournal->urgent_tasks ?? []))
            ->filter(fn ($task) => is_array($task) && trim((string) ($task['description'] ?? '')) !== '')
            ->values();

        // Urgent tasks written in Accomplishment details also count.
        $detectedUrgent = collect(app(DailyReportScoreCalculator::class)->urgentFromDetails($dailyJournal))
            ->map(fn ($line) => ['description' => $line, 'reason' => 'Written in Accomplishment details']);
        $urgentTasks = $urgentTasks->concat($detectedUrgent)->values();

        $blockers = [];
        if ($breakdown['assigned_total'] > $breakdown['assigned_completed']) {
            $blockers[] = ($breakdown['assigned_total'] - $breakdown['assigned_completed']).' assigned task(s) were not completed.';
        }
        if ($nothingScheduled) {
            $blockers[] = 'No tasks were scheduled or added as urgent, so only the problems and solutions part could be scored.';
        }
        if (! $breakdown['problems_filled']) {
            $blockers[] = 'Problems Encountered is missing or too short.';
        }
        if (! $breakdown['solutions_filled']) {
            $blockers[] = 'Suggested Solutions is missing or too short.';
        }
        if (empty($blockers)) {
            $blockers[] = 'No specific blockers identified from the available records.';
        }

        $improvements = [];
        if ($breakdown['assigned_total'] > $breakdown['assigned_completed']) {
            $improvements[] = 'Complete the remaining assigned tasks. Each completed task adds '.number_format($breakdown['assigned_points_each'], 1).' points.';
        }
        if (! $breakdown['problems_filled'] || ! $breakdown['solutions_filled']) {
            $improvements[] = 'Describe the problems and suggest solutions (at least '.DailyReportScoreCalculator::MIN_TEXT_LENGTH.' characters each) to earn the 10 points.';
        }
        if ($breakdown['urgent_count'] === 0 && $breakdown['assigned_total'] > 0) {
            $improvements[] = 'Urgent work that is not in Goals for the Day can be added as an urgent task, and it earns extra points.';
        }
        if ($dailyJournal->evidenceCount() === 0) {
            $improvements[] = 'Attach supporting evidence to the report for stronger verification.';
        }
        if (empty($improvements)) {
            $improvements[] = 'Maintain the current level of performance.';
        }

        // Kaizen Strategy and Admin Concern evaluations that are linked to
        // this staff member's Score Sheet for the journal's period.
        $start = $dailyJournal->required_date?->copy()->startOfDay() ?? now()->startOfDay();
        $scoreSheetTasks = app(StaffScoringService::class)
            ->taskEvaluationsForScoreSheet($dailyJournal->user, $start);

        // ------------------------------------------------------------------
        // Transparent score-calculation data: points = score x (weight / 100).
        // ------------------------------------------------------------------
        $finalScore = $dailyJournal->finalScore();
        $isManual = $dailyJournal->assessment_source === 'reviewer';

        $criteriaNames = [
            'target_completion' => 'Task Completion',
            'problems_solutions' => 'Problems & Solutions',
        ];

        $calculationCriteria = [];
        $totalOriginalWeight = 0.0;
        $totalAvailableWeight = 0.0;
        $totalWeightedPoints = 0.0;

        foreach (array_keys($criteriaNames) as $key) {
            $scoreOrNull = $criteria[$key] ?? null;
            $weight = (float) ($weights[$key] ?? 0);
            $totalOriginalWeight += $weight;

            $evaluated = $scoreOrNull !== null && is_numeric($scoreOrNull);
            $score = $evaluated ? (float) $scoreOrNull : null;
            $weightedPoints = $evaluated ? round($score * ($weight / 100), 2) : null;

            if ($evaluated) {
                $totalAvailableWeight += $weight;
                $totalWeightedPoints += $weightedPoints;
            }

            $calculationCriteria[] = [
                'key' => $key,
                'name' => $criteriaNames[$key],
                'score' => $score,
                'weight' => $weight,
                'weighted_points' => $weightedPoints,
                'explanation' => $criteria[$key.'_explanation'] ?? 'Not yet evaluated.',
                'meta' => $criteria[$key.'_meta'] ?? [],
                'status' => $evaluated ? 'Evaluated' : 'Not evaluated',
            ];
        }

        // Renormalize over the weight that was actually available.
        $normalizedScore = null;
        $normalizationExplanation = null;
        if ($finalScore !== null && $totalAvailableWeight > 0) {
            $normalizedScore = round($totalWeightedPoints / ($totalAvailableWeight / 100), 1);
            $normalizationExplanation = sprintf(
                'Only %.0f%% of the total weight had data. Weighted points %.2f divided by the available weight (%.0f%% = %.2f) equals %.1f.',
                $totalAvailableWeight,
                $totalWeightedPoints,
                $totalAvailableWeight,
                $totalAvailableWeight / 100,
                $normalizedScore
            );
        }

        $assessmentMethodLabel = $isManual ? 'Manual Reassessment' : 'Automatic Assessment';
        $assessmentMethodDetail = $isManual
            ? 'A reviewer manually set this score and may have overridden the automatic result.'
            : 'The score was calculated automatically from the assigned tasks, urgent tasks, and the problems and solutions sections.';

        $scoreMeaning = match (true) {
            $finalScore === null => 'This report has not been assessed yet.',
            $finalScore >= 90 => 'Outstanding performance across the evaluated criteria.',
            $finalScore >= 80 => 'Excellent performance with minor areas for improvement.',
            $finalScore >= 70 => 'Good performance meeting most expectations.',
            $finalScore >= 60 => 'Needs further development in several areas.',
            default => 'Performance needs significant improvement across evaluated criteria.',
        };

        return view('daily-journal.score-sheet', [
            'journal' => $dailyJournal,
            'breakdown' => $breakdown,
            'assignedTasks' => $assignedTasks,
            'urgentTasks' => $urgentTasks,
            'criteria' => $criteria,
            'combined' => $combined,
            'weights' => $weights,
            'criterionEvidence' => $criterionEvidence,
            'blockers' => $blockers,
            'improvements' => $improvements,
            'canReview' => $user->isAdmin(),
            'scoreSheetTasks' => $scoreSheetTasks,
            'scoreSheetTasksTotal' => $scoreSheetTasks->sum(fn ($evaluation) => $evaluation->isIncludedInScoreSheet()
                ? (float) $evaluation->score_sheet_weighted_points
                : 0),
            'calculationCriteria' => $calculationCriteria,
            'totalOriginalWeight' => $totalOriginalWeight,
            'totalAvailableWeight' => $totalAvailableWeight,
            'totalWeightedPoints' => $totalWeightedPoints,
            'normalizedScore' => $normalizedScore,
            'normalizationExplanation' => $normalizationExplanation,
            'assessmentMethodLabel' => $assessmentMethodLabel,
            'assessmentMethodDetail' => $assessmentMethodDetail,
            'scoreMeaning' => $scoreMeaning,
        ]);
    }

    /**
     * Delete a journal entry (admin only).
     */
    public function destroy(DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $date = $dailyJournal->required_date;
        $userId = $dailyJournal->user_id;
        $userName = $dailyJournal->user ? $dailyJournal->user->name : 'Unknown / Deleted User';

        $dailyJournal->delete();

        DailyJournalService::forgetMissingCount($userId);

        return redirect()->route('daily-journal.admin-monitor', ['date' => $date->toDateString()])
            ->with('status', "Daily journal for {$userName} on {$date->format('F j, Y')} deleted.");
    }

    /**
     * Keep clients only for ticked codes that require a client, and only clients
     * assigned to that code (any client account while none are assigned yet).
     *
     * @param  array<string, array<int, mixed>>  $input
     * @param  array<int, string>  $codes
     * @return array<string, array<int, int>>
     */
    private function cleanAchievementClients(array $input, array $codes): array
    {
        $clientLegends = WorkPlanLegend::active()
            ->where('requires_client', true)
            ->with('clients:id')
            ->get()
            ->keyBy('code');
        $validClientIds = User::query()->where('role', User::ROLE_CLIENT)->pluck('id')->all();

        return collect($input)
            ->filter(fn ($ids, $code) => in_array($code, $codes, true) && $clientLegends->has($code))
            ->map(function ($ids, $code) use ($clientLegends, $validClientIds) {
                $assigned = $clientLegends[$code]->clients->pluck('id')->all();
                $allowed = $assigned !== [] ? $assigned : $validClientIds;

                return collect((array) $ids)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => in_array($id, $allowed, true))
                    ->unique()
                    ->values()
                    ->all();
            })
            ->filter()
            ->all();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admin access required.');
    }

    private function authorizeSupervisor(): void
    {
        abort_unless(Auth::user()->isSupervisor(), 403, 'Supervisor access required.');
    }

    private function authorizeAssessmentReviewer(DailyJournal $dailyJournal): void
    {
        abort_unless(
            $this->canReviewAssessment(Auth::user(), $dailyJournal),
            403,
            'You can only review assessments for your own team.'
        );
    }

    private function canReviewAssessment(User $reviewer, DailyJournal $journal): bool
    {
        if ($reviewer->isAdmin()) {
            return true;
        }

        if (! $reviewer->isSupervisor()) {
            return false;
        }

        $journal->loadMissing('user');

        return $journal->user_id === $reviewer->id
            || ($journal->user !== null && $this->supervision->supervises($reviewer, $journal->user));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function customPeriod(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        if ($start->diffInDays($end) > 366) {
            $end = $start->copy()->addDays(366)->endOfDay();
        }

        return [$start, $end];
    }
}