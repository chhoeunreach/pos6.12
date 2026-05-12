<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $schema = Illuminate\Support\Facades\Schema::connection('mysql_loan');
    if (! $schema->hasTable('loan_customers')) {
        echo 'NO_TABLE';
        exit;
    }
    echo implode(',', $schema->getColumnListing('loan_customers'));
} catch (Throwable $e) {
    echo 'ERR:' . $e->getMessage();
}
