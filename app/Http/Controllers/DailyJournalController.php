<?php

namespace App\Http\Controllers;

use App\Models\DailyJournal;
use App\Models\User;
use App\Services\AchievementAnalysisService;
use App\Services\AchievementCriteriaService;
use App\Services\AchievementRankingService;
use App\Services\DailyJournalService;
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

        return view('daily-journal.create', [
            'journal' => $journal,
            'date' => $date,
            'isToday' => $isToday,
            'isPast' => $isPast,
            'isFuture' => $isFuture,
            'requiresReport' => $requiresReport,
            'user' => $user,
        ]);
    }

    /**
     * Store the journal submission.
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

        $date = $request->get('date') ? Carbon::parse($request->get('date')) : Carbon::today();

        // Reports are only required Monday to Friday; reject weekend submissions
        // so no weekend obligation is ever created.
        if (! DailyJournalService::isWorkingDay($date)) {
            return redirect()
                ->route('daily-journal.create', ['date' => $date->toDateString()])
                ->withErrors(['date' => 'Daily Accomplishment Reports are only required Monday through Friday.']);
        }

        $request->validate([
            'problems_encountered' => 'required|string',
            'achievements' => 'required|string',
            'suggested_solutions' => 'required|string',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        // Handle file uploads
        $evidencePaths = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('daily-journals/'.$user->id.'/'.$date->format('Y-m-d'), 'supabase');
                $evidencePaths[] = $path;
            }
        }

        DailyJournalService::submitJournal(
            $user->id,
            $date,
            $request->string('problems_encountered')->toString(),
            $request->string('achievements')->toString(),
            $request->string('suggested_solutions')->toString(),
            $evidencePaths
        );

        return redirect()->route('daily-journal.create', ['date' => $date->toDateString()])
            ->with('status', 'Daily journal submitted successfully.');
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

        return view('daily-journal.admin-monitor', [
            'journals' => $journals,
            'employees' => $employees,
            'date' => $date,
            'selectedUserId' => $userId,
            'selectedRole' => $role,
            'selectedStatus' => $status,
            'availableDates' => $availableDates,
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

        return view('daily-journal.achievements', [
            'rankings' => $rankings,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'verifiedCount' => $rankings->sum('verified_count'),
            'provisionalCount' => $rankings->sum('provisional_count'),
            'employeeCount' => $rankings->count(),
            'criteriaScored' => $criteriaScored,
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

        $levels = collect(config('achievements.levels', []))->pluck('label')->all();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'criteria' => ['required', 'array'],
            'criteria.*.score' => ['required', 'numeric', 'between:0,100'],
            'criteria.*.max' => ['required', 'numeric', 'between:0,100'],
            'criteria.*.reason' => ['nullable', 'string', 'max:500'],
            'weights.quality' => ['nullable', 'numeric', 'between:0,100'],
            'weights.timeliness' => ['nullable', 'numeric', 'between:0,100'],
            'weights.responsiveness' => ['nullable', 'numeric', 'between:0,100'],
            'weights.client_feedback' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $weights = [
            'quality' => (float) ($validated['weights']['quality'] ?? config('achievements.scoring_weights.quality', 50)),
            'timeliness' => (float) ($validated['weights']['timeliness'] ?? config('achievements.scoring_weights.timeliness', 20)),
            'responsiveness' => (float) ($validated['weights']['responsiveness'] ?? config('achievements.scoring_weights.responsiveness', 20)),
            'client_feedback' => (float) ($validated['weights']['client_feedback'] ?? config('achievements.scoring_weights.client_feedback', 10)),
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
     * Display the detailed AI score sheet for a single assessed journal.
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

        $criteriaService = app(AchievementCriteriaService::class);
        $weights = [
            'quality' => (float) config('achievements.scoring_weights.quality', 50),
            'timeliness' => (float) config('achievements.scoring_weights.timeliness', 20),
            'responsiveness' => (float) config('achievements.scoring_weights.responsiveness', 20),
            'client_feedback' => (float) config('achievements.scoring_weights.client_feedback', 10),
        ];

        $start = $dailyJournal->required_date?->copy()->startOfDay() ?? now()->startOfDay();
        $end = $dailyJournal->required_date?->copy()->endOfDay() ?? now()->endOfDay();

        $criteria = $criteriaService->analyze($dailyJournal->user, $start, $end);
        $combined = $criteriaService->combine($criteria, $weights);

        // Pre-compute per-criterion evidence text to keep the Blade template
        // simple and avoid complex inline expressions.
        $criterionEvidence = [];
        foreach (['quality', 'timeliness', 'responsiveness', 'client_feedback'] as $key) {
            $meta = $criteria[$key.'_meta'] ?? [];
            $score = $criteria[$key] ?? null;
            match ($key) {
                'quality' => $criterionEvidence[$key] = 'Based on '.(int) ($meta['entries'] ?? 0).' assessed daily accomplishment report(s) in this period. Average quality score '.(float) ($meta['average'] ?? $score).'.',
                'timeliness' => $criterionEvidence[$key] = (int) ($meta['evaluated'] ?? 0).' evaluable task(s): '.(int) ($meta['on_time'] ?? 0).' on time, '.(int) ($meta['late'] ?? 0).' late, '.(int) ($meta['overdue_not_completed'] ?? 0).' overdue. On-time rate '.(float) ($meta['on_time_rate'] ?? 0).'%.',
                'responsiveness' => $criterionEvidence[$key] = (int) ($meta['evaluated'] ?? 0).' evaluated response(s): '.(int) ($meta['prompt'] ?? 0).' prompt, '.(int) ($meta['delayed'] ?? 0).' delayed, '.(int) ($meta['unanswered'] ?? 0).' unanswered. Average response time '.(($meta['avg_response_hours'] ?? null) !== null ? number_format((float) $meta['avg_response_hours'], 1).'h' : '—').'.',
                'client_feedback' => $criterionEvidence[$key] = (int) ($meta['responses'] ?? 0).' verified feedback response(s): '.(int) ($meta['positive'] ?? 0).' positive, '.(int) ($meta['negative'] ?? 0).' negative, '.(int) ($meta['neutral'] ?? 0).' neutral. Average rating '.(($meta['average_rating'] ?? null) !== null ? number_format((float) $meta['average_rating'], 2).'/5' : '—').'.',
            };
        }

        // Pre-compute explanation bullet points to avoid complex inline PHP
        // in the Blade template.
        $tMeta = $criteria['timeliness_meta'] ?? [];
        $rMeta = $criteria['responsiveness_meta'] ?? [];
        $blockers = [];
        if ((int) ($tMeta['late'] ?? 0) > 0) {
            $blockers[] = (int) ($tMeta['late'] ?? 0).' task(s) completed after the deadline.';
        }
        if ((int) ($tMeta['overdue_not_completed'] ?? 0) > 0) {
            $blockers[] = (int) ($tMeta['overdue_not_completed'] ?? 0).' task(s) overdue and not completed.';
        }
        if ((int) ($rMeta['delayed'] ?? 0) > 0) {
            $blockers[] = (int) ($rMeta['delayed'] ?? 0).' response(s) delayed beyond 24 hours.';
        }
        if ((int) ($rMeta['unanswered'] ?? 0) > 0) {
            $blockers[] = (int) ($rMeta['unanswered'] ?? 0).' assigned item(s) left unanswered.';
        }
        if (empty($blockers)) {
            $blockers[] = 'No specific blockers identified from the available records.';
        }

        $improvements = [];
        if ((int) ($tMeta['late'] ?? 0) > 0) {
            $improvements[] = 'Complete assigned tasks on or before their deadlines to improve the timeliness score.';
        }
        if ((int) ($rMeta['unanswered'] ?? 0) > 0) {
            $improvements[] = 'Acknowledge assigned concerns promptly so they are not left unanswered.';
        }
        if ((int) ($rMeta['delayed'] ?? 0) > 0) {
            $improvements[] = 'Respond to assignments within 24 hours to improve the responsiveness score.';
        }
        if ($dailyJournal->evidenceCount() === 0) {
            $improvements[] = 'Attach supporting evidence to accomplishment reports for stronger verification.';
        }
        if (empty($improvements)) {
            $improvements[] = 'Maintain the current level of performance across all criteria.';
        }

        return view('daily-journal.score-sheet', [
            'journal' => $dailyJournal,
            'criteria' => $criteria,
            'combined' => $combined,
            'weights' => $weights,
            'criterionEvidence' => $criterionEvidence,
            'blockers' => $blockers,
            'improvements' => $improvements,
            'canReview' => $user->isAdmin(),
        ]);
    }

    /**
     * Delete a journal entry (admin only).
     */
    public function destroy(DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAdmin();

        $date = $dailyJournal->required_date;
        $userName = $dailyJournal->user->name;

        $dailyJournal->delete();

        DailyJournalService::forgetMissingCount($dailyJournal->user_id);

        return redirect()->route('daily-journal.admin-monitor', ['date' => $date->toDateString()])
            ->with('status', "Daily journal for {$userName} on {$date->format('F j, Y')} deleted.");
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admin access required.');
    }

    private function authorizeSupervisor(): void
    {
        abort_unless(Auth::user()->isSupervisor(), 403, 'Supervisor access required.');
    }

    private function authorizeAssessmentReviewer(DailyJournal $journal): void
    {
        abort_unless(
            $this->canReviewAssessment(Auth::user(), $journal),
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
