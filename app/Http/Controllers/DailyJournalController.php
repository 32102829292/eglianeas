<?php

namespace App\Http\Controllers;

use App\Models\DailyJournal;
use App\Models\User;
use App\Services\AchievementAnalysisService;
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

        return view('daily-journal.achievements', [
            'rankings' => $rankings,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'verifiedCount' => $rankings->sum('verified_count'),
            'provisionalCount' => $rankings->sum('provisional_count'),
            'employeeCount' => $rankings->count(),
        ]);
    }

    /**
     * Correct an assessment. Authorized admins and supervisors only; the
     * correction and its reason are written to the audit history.
     */
    public function reviewAssessment(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAssessmentReviewer($dailyJournal);

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
     * Explicitly re-run analysis when a reviewer asks for it.
     */
    public function reassessAssessment(Request $request, DailyJournal $dailyJournal): RedirectResponse
    {
        $this->authorizeAssessmentReviewer($dailyJournal);

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
