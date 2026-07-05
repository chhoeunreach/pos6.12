<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\User::all(['id', 'username', 'email']);
echo "Total users: " . $users->count() . PHP_EOL;
foreach ($users as $u) {
    echo $u->id . ' | ' . $u->username . ' | ' . $u->email . PHP_EOL;
}
