<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\CashDailyReceipt::all() as $r) {
    echo $r->id . ' path: ' . $r->file_path . ' type: ' . $r->file_type . ' name: ' . $r->file_name . PHP_EOL;
}