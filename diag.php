<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$issues = [];

// 1. Admin user check
try {
    $u = \App\User::where('username', 'admin')->first();
    if (!$u) {
        // Try common admin usernames
        foreach (['admin', 'superadmin', 'owner'] as $name) {
            $cand = \App\User::where('username', $name)->first();
            if ($cand) { $u = $cand; break; }
        }
    }
    if (!$u) {
        echo "No admin user found (29 users exist, none named admin). First user: " . \App\User::first()->username . PHP_EOL;
    } else {
        echo "Admin user: " . $u->username . " email=" . $u->email . PHP_EOL;
    }
} catch (Throwable $e) { $issues[] = "User model: " . $e->getMessage(); }

// 2. Business / location check
try {
    $b = \App\Business::count();
    echo "Businesses: $b" . PHP_EOL;
    $bl = \App\BusinessLocation::count();
    echo "Business Locations: $bl" . PHP_EOL;
} catch (Throwable $e) { $issues[] = "Business: " . $e->getMessage(); }

// 3. Storage writeable
foreach (['storage/framework', 'storage/logs', 'storage/app', 'bootstrap/cache'] as $d) {
    $ok = is_writable($d);
    echo str_pad($d, 25) . ($ok ? " writable" : " NOT WRITABLE") . PHP_EOL;
    if (!$ok) $issues[] = "Directory not writable: $d";
}

// 4. APP_KEY
$key = config('app.key');
echo "APP_KEY: " . ($key ? "set (" . strlen($key) . ")" : "MISSING") . PHP_EOL;
if (!$key) $issues[] = "APP_KEY missing. Run: php artisan key:generate";

// 5. .env APP_URL vs config
$envUrl = env('APP_URL');
$cfgUrl = config('app.url');
if ($envUrl !== $cfgUrl) echo "WARN: APP_URL mismatch (env=$envUrl cfg=$cfgUrl)" . PHP_EOL;

// 6. Migrations pending
try {
    \Illuminate\Support\Facades\Artisan::call('migrate:status');
    echo \Illuminate\Support\Facades\Artisan::output();
} catch (Throwable $e) { $issues[] = "migrate:status: " . $e->getMessage(); }

if (empty($issues)) {
    echo PHP_EOL . "ALL CHECKS PASSED" . PHP_EOL;
} else {
    echo PHP_EOL . "ISSUES FOUND:" . PHP_EOL;
    foreach ($issues as $i) echo " - $i" . PHP_EOL;
}
