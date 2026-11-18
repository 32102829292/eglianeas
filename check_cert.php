<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\User::find(142);
$p = $c->getClientProfile();
echo "cor_document_path: " . ($p ? $p->cor_document_path : 'null') . PHP_EOL;
echo "cor_document_name: " . ($p ? $p->cor_document_name : 'null') . PHP_EOL;
if ($p && $p->cor_document_path) {
    $exists = \Illuminate\Support\Facades\Storage::disk('local')->exists($p->cor_document_path);
    echo "exists on local disk: " . ($exists ? 'yes' : 'no') . PHP_EOL;
} else {
    echo "exists on local disk: n/a (no path)" . PHP_EOL;
}