<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyJournal;
use App\Models\User;
use App\Services\DailyJournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
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

        // Employees can only submit for themselves
        $journal = DailyJournalService::getOrCreateForUserAndDate($user->id, $date);

        $isToday = $date->isToday();
        $isPast = $date->lt(Carbon::today());
        $isFuture = $date->gt(Carbon::today());

        return view('daily-journal.create', [
            'journal' => $journal,
            'date' => $date,
            'isToday' => $isToday,
            'isPast' => $isPast,
            'isFuture' => $isFuture,
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
                $path = $file->store('daily-journals/' . $user->id . '/' . $date->format('Y-m-d'), 'supabase');
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
        // Filter to only show journals for staff under this supervisor + supervisor's own journal
        $journals = $journals->filter(function ($journal) use ($user) {
            $journalUser = $journal->user;
            return $journalUser && ($journalUser->id === $user->id || $journalUser->supervisor_id === $user->id);
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
            abort_unless($dailyJournal->user_id === $user->id || $dailyJournal->user->supervisor_id === $user->id, 403);
        } else {
            abort_unless($dailyJournal->user_id === $user->id, 403);
        }

        return view('daily-journal.show', [
            'journal' => $dailyJournal->load('user'),
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

        \App\Services\DailyJournalService::forgetMissingCount($dailyJournal->user_id);

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
}