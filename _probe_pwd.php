<?php
require __DIR__ . '/bootstrap/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo Hash::check('admin', '$2y$10$nTglK/yA6W6J/2tWRMhhJOqXqKoh.bv8geBbYOeCl0/TEBlD75Cya') ? 'YES' : 'NO';
