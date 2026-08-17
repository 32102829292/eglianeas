<?php

namespace App\Console\Commands;

use App\Services\BillingReminderService;
use Illuminate\Console\Command;

class RemindMissingSales extends Command
{
    protected $signature = 'billing:remind-missing-sales';

    protected $description = 'Remind clients who have not submitted their Sales for the current quarter';

    public function handle(): int
    {
        $sent = BillingReminderService::remindMissingSales();

        $this->info("Sent/updated {$sent} missing-sales reminder(s).");

        return self::SUCCESS;
    }
}
