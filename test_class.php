<?php
echo "Class: " . App\Http\Controllers\Admin\ClientController::class . PHP_EOL;
$r = new ReflectionClass(App\Http\Controllers\Admin\ClientController::class);
echo "File: " . $r->getFileName() . PHP_EOL;
echo "Method exists: " . (method_exists(App\Http\Controllers\Admin\ClientController::class, 'viewCertificateOfRegistration') ? 'yes' : 'no') . PHP_EOL;
?>