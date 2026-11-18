<?php
require 'vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$routes = app('router')->getRoutes();

foreach ($routes as $route) {
    $name = $route->getName();
    if ($name !== null) {
        echo "Name: $name | URI: " . $route->uri() . " | Controller: " . $route->getControllerClass() . "\n";
    }
}
?>