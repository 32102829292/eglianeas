<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:remind-due')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('billing:remind-missing-sales')->weeklyOn(1, '09:30')->withoutOverlapping();
Schedule::command('snapshot:daily')->dailyAt('00:05');
