<?php

namespace Database\Seeders;

use App\Models\WorkPlanLegend;
use Illuminate\Database\Seeder;

class WorkPlanLegendSeeder extends Seeder
{
    public function run(): void
    {
        $count = WorkPlanLegend::initializeDefaultLegends();

        if ($count === 0) {
            $this->command?->warn('No admin or supervisor account found; work plan legends were not seeded.');
        }
    }
}
