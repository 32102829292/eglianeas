<?php

namespace App\Console\Commands;

use App\Services\DailyJournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckDailyJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-journal:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missing daily journals and send reminders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $yesterday = Carbon::yesterday();

        // Ensure journals exist for yesterday (creates missing records for employees)
        $created = DailyJournalService::ensureJournalsForDate($yesterday);

        // Send reminders for yesterday's missing journals
        $reminded = DailyJournalService::remindMissingForDate($yesterday);

        // Also check for today if it's a working day (for early reminders)
        $today = Carbon::today();
        if (DailyJournalService::isWorkingDay($today)) {
            DailyJournalService::ensureJournalsForDate($today);
        }

        $this->info("Daily Journal Check for {$yesterday->format('F j, Y')}:");
        $this->info("  - Missing journal records created: {$created}");
        $this->info("  - Reminders sent: {$reminded}");

        return self::SUCCESS;
    }
}