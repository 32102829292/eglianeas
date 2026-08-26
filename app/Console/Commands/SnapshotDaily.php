<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\DailySnapshot;
use Illuminate\Console\Command;

class SnapshotDaily extends Command
{
    protected $signature = 'snapshot:daily';

    protected $description = 'Record daily metrics snapshot (revenue, new billings, overdue count)';

    public function handle(): int
    {
        $yesterday = now()->subDay()->toDateString();

        $revenue = Billing::query()
            ->where('status', Billing::STATUS_PAID)
            ->whereDate('paid_at', $yesterday)
            ->sum('total');

        $newBillings = Billing::query()
            ->whereDate('created_at', $yesterday)
            ->count();

        $overdueCount = Billing::query()
            ->where('status', Billing::STATUS_OVERDUE)
            ->count();

        DailySnapshot::updateOrCreate(
            ['date' => $yesterday],
            [
                'revenue_collected' => $revenue,
                'new_billings'      => $newBillings,
                'overdue_count'     => $overdueCount,
            ],
        );

        $this->info("Snapshot saved for {$yesterday}: revenue=₱{$revenue}, new_billings={$newBillings}, overdue={$overdueCount}");

        return self::SUCCESS;
    }
}
