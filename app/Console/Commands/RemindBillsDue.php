<?php

namespace App\Console\Commands;

use App\Services\BillingReminderService;
use Illuminate\Console\Command;

class RemindBillsDue extends Command
{
    protected $signature = 'billing:remind-due';

    protected $description = 'Remind clients about unpaid bills that are due within 7 days or already overdue';

    public function handle(): int
    {
        $sent = BillingReminderService::remindBillsDue();

        $this->info("Sent/updated {$sent} bill due reminder(s).");

        return self::SUCCESS;
    }
}
