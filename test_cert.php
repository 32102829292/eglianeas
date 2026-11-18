<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::whereNotNull('cor_document_path')->take(5)->get();
foreach ($users as $u) {
    $p = $u->getClientProfile();
    $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($p->cor_document_path);
    echo $u->id . " path: " . $p->cor_document_path . " exists: " . ($exists ? 'yes' : 'no') . PHP_EOL;
}