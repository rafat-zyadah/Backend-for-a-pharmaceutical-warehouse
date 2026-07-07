<?php

use App\Models\User;
use App\Support\Users\SupervisorPasswordRecoveryService;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$supervisor = User::query()->where('username', 'supervisor')->first();

if ($supervisor === null) {
    fwrite(STDERR, "Supervisor user not found. Run: php artisan system:install\n");
    exit(1);
}

$supervisor->email = 'rafatzyadah@gmail.com';
$supervisor->save();

$channels = app(SupervisorPasswordRecoveryService::class)->send($supervisor->fresh());

echo 'Sent via: '.implode(', ', $channels).PHP_EOL;
echo 'To: '.$supervisor->email.PHP_EOL;
