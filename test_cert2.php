<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$profiles = App\Models\ClientProfile::whereNotNull('cor_document_path')->take(5)->get();
foreach ($profiles as $p) {
    $user = $p->user; // assuming relationship
    $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($p->cor_document_path);
    echo "User ID " . $user->id . " path: " . $p->cor_document_path . " exists: " . ($exists ? 'yes' : 'no') . PHP_EOL;
}