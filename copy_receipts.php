<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$localDir = storage_path('app/private/cash_daily_receipts');
$files = glob($localDir . '/*');
$supabase = \Illuminate\Support\Facades\Storage::disk('supabase');

foreach ($files as $file) {
    $basename = basename($file);
    $supabasePath = 'cash_daily_receipts/' . $basename;
    // Check if already exists in supabase to avoid overwrite
    if (!$supabase->exists($supabasePath)) {
        $contents = file_get_contents($file);
        $supabase->put($supabasePath, $contents);
        echo "Copied $basename to Supabase\n";
    } else {
        echo "Already in Supabase: $basename\n";
    }
}
echo "Done.\n";