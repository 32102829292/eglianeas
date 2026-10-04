<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DailyJournal;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DailyJournalService
{
    /**
     * Minimum gap between reminders for the same missing journal, in days.
     */
    public const REMINDER_COOLDOWN_DAYS = 1;

    /**
     * Per-request memo for getMissingCountForUser(), keyed by user id.
     *
     * The nav partial and the daily-journal alert partial both render the
     * badge, and the nav partial is rendered twice per dashboard page (mobile
     * drawer + desktop sidebar). Without this memo the same COUNT(*) is issued
     * up to three times on every single page view, which is very expensive when
     * the database is not local (each round trip costs real latency).
     *
     * Safe because a request is short-lived and every call site reads the
     * count for display only; no caller writes a journal and then re-reads it.
     *
     * @var array<int, int>
     */
    private static array $missingCountMemo = [];

    /**
     * Drop the memoized counts. Called from write paths (store/update/destroy)
     * so a later read inside the same request cannot see a stale value.
     */
    public static function forgetMissingCount(?int $userId = null): void
    {
        if ($userId === null) {
            self::$missingCountMemo = [];

            return;
        }

        unset(self::$missingCountMemo[$userId]);
    }

    /**
     * Check if a given date is a working day (not weekend).
     * Can be extended with holiday logic later.
     */
    public static function isWorkingDay(Carbon $date): bool
    {
        return ! $date->isWeekend();
    }

    /**
     * Get all employees (staff and supervisors) who should submit journals.
     * Admin and Client are excluded.
     */
    public static function getJournalEmployees(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Ensure a journal record exists for each employee for the given date.
     * Returns the count of newly created missing journal records.
     */
    public static function ensureJournalsForDate(Carbon $date): int
    {
        if (! self::isWorkingDay($date)) {
            return 0;
        }

        $employees = self::getJournalEmployees();

        $created = 0;
        $dateString = $date->toDateString();

        foreach ($employees as $employee) {
            $journal = DailyJournal::where('user_id', $employee->id)
                ->whereDate('required_date', $dateString)
                ->first();

            if (! $journal) {
                $journal = DailyJournal::create([
                    'user_id' => $employee->id,
                    'required_date' => $dateString,
                    'status' => DailyJournal::STATUS_MISSING,
                    'problems_encountered' => '',
                    'achievements' => '',
                    'suggested_solutions' => '',
                    'evidence_paths' => [],
                ]);
                $wasRecentlyCreated = true;
            } else {
                $wasRecentlyCreated = false;
            }

            if ($wasRecentlyCreated) {
                $created++;
                self::forgetMissingCount($employee->id);

                ActivityLog::record(
                    null,
                    'daily_journal.created',
                    "Daily journal created for {$employee->name} on {$date->format('F j, Y')} (missing)."
                );
            }
        }

        return $created;
    }

    /**
     * Get missing journals for a specific date.
     */
    public static function getMissingForDate(Carbon $date): \Illuminate\Database\Eloquent\Collection
    {
        return DailyJournal::query()
            ->whereDate('required_date', $date->toDateString())
            ->where('status', DailyJournal::STATUS_MISSING)
            ->with('user')
            ->get();
    }

    /**
     * Get journal status summary for a date (for admin/supervisor monitoring).
     */
    public static function getStatusForDate(Carbon $date, ?int $supervisorId = null): array
    {
        $query = DailyJournal::query()
            ->whereDate('required_date', $date->toDateString())
            ->with('user');

        if ($supervisorId) {
            $query->whereHas('user', function ($q) use ($supervisorId) {
                $q->where('supervisor_id', $supervisorId)
                    ->orWhere('id', $supervisorId); // Include supervisor's own journal
            });
        }

        $journals = $query->get();

        return [
            'submitted' => $journals->where('status', DailyJournal::STATUS_SUBMITTED),
            'late' => $journals->where('status', DailyJournal::STATUS_LATE),
            'missing' => $journals->where('status', DailyJournal::STATUS_MISSING),
            'total' => $journals->count(),
        ];
    }

    /**
     * Get an employee's missing journals count (for sidebar badge).
     */
    public static function getMissingCountForUser(int $userId): int
    {
        if (isset(self::$missingCountMemo[$userId])) {
            return self::$missingCountMemo[$userId];
        }

        return self::$missingCountMemo[$userId] = DailyJournal::query()
            ->where('user_id', $userId)
            ->where('status', DailyJournal::STATUS_MISSING)
            ->count();
    }

    /**
     * Check if an employee has a missing journal for today.
     */
    public static function hasMissingForToday(int $userId): bool
    {
        return DailyJournal::query()
            ->where('user_id', $userId)
            ->whereDate('required_date', now()->toDateString())
            ->where('status', DailyJournal::STATUS_MISSING)
            ->exists();
    }

    /**
     * Send reminders for missing journals on a specific date.
     * Uses the existing Notification::remind() to prevent duplicate flooding.
     */
    public static function remindMissingForDate(Carbon $date): int
    {
        if (! self::isWorkingDay($date)) {
            return 0;
        }

        $sent = 0;
        $cooldownStart = now()->subDays(self::REMINDER_COOLDOWN_DAYS);

        $missingJournals = DailyJournal::query()
            ->whereDate('required_date', $date->toDateString())
            ->where('status', DailyJournal::STATUS_MISSING)
            ->where(function ($q) use ($cooldownStart) {
                $q->whereNull('reminded_at')
                    ->orWhere('reminded_at', '<=', $cooldownStart);
            })
            ->with('user')
            ->get();

        foreach ($missingJournals as $journal) {
            $user = $journal->user;

            if (! $user) {
                continue;
            }

            $groupKey = "daily_journal:{$journal->user_id}:{$journal->getRawOriginal('required_date')}";
            $title = 'Daily Accomplishment Report Missing';
            $body = "You have not submitted your Daily Accomplishment Report for {$date->format('F j, Y')}. Please submit your journal.";
            $link = route('daily-journal.create', ['date' => $date->toDateString()]);

            // In-app notification (with reminder collapsing)
            Notification::remind(
                $user->id,
                $groupKey,
                $title,
                $body,
                'daily_journal_missing',
                $link
            );

            // Push notification
            \App\Services\PushNotificationService::send($user, $title, $body, $link);

            // Update reminder tracking on the journal record
            $journal->incrementReminder();

            ActivityLog::record(
                null,
                'daily_journal.reminder_sent',
                "Reminder sent to {$user->name} for daily journal on {$date->format('F j, Y')}."
            );

            $sent++;
        }

        return $sent;
    }

    /**
     * Resolve (mark as read) missing journal notifications when employee submits.
     */
    public static function resolveMissingNotification(int $userId, Carbon $date): void
    {
        $groupKey = "daily_journal:{$userId}:{$date->toDateString()}";
        Notification::resolveGroup($userId, $groupKey);
    }

    /**
     * Get the journal for a user on a specific date (create if not exists for operational users).
     */
    public static function getOrCreateForUserAndDate(int $userId, Carbon $date): DailyJournal
    {
        return DailyJournal::getOrCreateForUserAndDate($userId, $date);
    }

    /**
     * Submit a journal entry.
     */
    public static function submitJournal(
        int $userId,
        Carbon $date,
        string $problems,
        string $achievements,
        string $solutions,
        ?array $evidencePaths = null
    ): DailyJournal {
        $journal = self::getOrCreateForUserAndDate($userId, $date);

        $isLate = $journal->status === DailyJournal::STATUS_MISSING && $date->lt(Carbon::today());

        $journal->update([
            'problems_encountered' => $problems,
            'achievements' => $achievements,
            'suggested_solutions' => $solutions,
            'evidence_paths' => $evidencePaths ?? [],
            'status' => $isLate ? DailyJournal::STATUS_LATE : DailyJournal::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Resolve the notification
        self::resolveMissingNotification($userId, $date);

        // The journal just left the "missing" bucket, so any count read later
        // in this request must be re-fetched.
        self::forgetMissingCount($userId);

        $user = User::find($userId);
        if ($user) {
            ActivityLog::record(
                $user,
                'daily_journal.submitted',
                "Submitted daily journal for {$date->format('F j, Y')} ({$journal->getStatusLabel()})."
            );
        }

        return $journal;
    }

    /**
     * Get journals for a date range with filters (for admin monitoring).
     */
    public static function getFilteredJournals(
        ?Carbon $date = null,
        ?int $userId = null,
        ?string $role = null,
        ?string $status = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = DailyJournal::query()->with('user');

        if ($date) {
            $query->whereDate('required_date', $date->toDateString());
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($role) {
            $query->whereHas('user', fn ($q) => $q->where('role', $role));
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('required_date', 'desc')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * Get all dates that have journals (for date filter dropdown).
     */
    public static function getAvailableDates(): \Illuminate\Support\Collection
    {
        return DailyJournal::query()
            ->select('required_date')
            ->distinct()
            ->orderByDesc('required_date')
            ->limit(60)
            ->pluck('required_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->values();
    }
}