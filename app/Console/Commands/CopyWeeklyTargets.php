<?php

namespace App\Console\Commands;

use App\Services\WeeklyTargetCopier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CopyWeeklyTargets extends Command
{
    protected $signature = 'weekly-bookkeeping:copy-forward
                            {--week=next : "next", "current", or any date inside the target week (Y-m-d)}';

    protected $description = 'Default an empty week\'s bookkeeping targets to the latest earlier week\'s assignments.';

    public function handle(WeeklyTargetCopier $copier): int
    {
        $option = (string) $this->option('week');

        $weekStart = match ($option) {
            'current' => now()->startOfWeek(),
            'next' => now()->addWeek()->startOfWeek(),
            default => Carbon::parse($option)->startOfWeek(),
        };

        $result = $copier->copyInto($weekStart);

        match ($result['status']) {
            WeeklyTargetCopier::COPIED => $this->info(sprintf(
                'Copied %d task(s) from the week of %s into the week of %s.',
                $result['count'],
                $result['source']->format('M j, Y'),
                $weekStart->format('M j, Y')
            )),
            WeeklyTargetCopier::ALREADY_HAS_TARGETS => $this->line(
                'Week of '.$weekStart->format('M j, Y').' already has targets. Nothing copied.'
            ),
            default => $this->warn('No earlier week with assignments to copy.'),
        };

        return self::SUCCESS;
    }
}