<?php
require __DIR__ . '/bootstrap/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\User::where('username', 'reach')->first();
echo 'username=' . $u->username . PHP_EOL;
echo 'pwd_len=' . strlen($u->password) . PHP_EOL;
echo 'pwd=' . $u->password . PHP_EOL;
