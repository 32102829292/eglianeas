<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Carbon;

// Create a test staff user like the test does
$user = User::create([
    'name' => 'Staff Work Plan Tester',
    'email' => 'staff'.uniqid().'@example.com',
    'password' => bcrypt('secret'),
    'role' => User::ROLE_STAFF,
    'email_verified_at' => now(),
    'confidentiality_acknowledged_at' => now(),
    'confidentiality_ack_version' => \App\Http\Middleware\EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
]);

echo "Role: " . $user->role . PHP_EOL;
echo "isAdmin: " . ($user->isAdmin() ? 'true' : 'false') . PHP_EOL;
echo "isStaff: " . ($user->isStaff() ? 'true' : 'false') . PHP_EOL;
echo "isSupervisor: " . ($user->isSupervisor() ? 'true' : 'false') . PHP_EOL;
echo "isOperational: " . ($user->isOperational() ? 'true' : 'false') . PHP_EOL;
echo "confidentiality_ack_version: " . $user->confidentiality_ack_version . PHP_EOL;
echo "CURRENT_VERSION: " . \App\Http\Middleware\EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION . PHP_EOL;