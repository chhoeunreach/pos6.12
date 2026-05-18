<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'broadcast='.config('broadcasting.default').PHP_EOL;
echo 'loan_db='.config('database.connections.mysql_loan.database').PHP_EOL;
echo 'loan_customers_table='.(Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasTable('loan_customers') ? 'yes' : 'no').PHP_EOL;
$customers = Illuminate\Support\Facades\DB::connection('mysql_loan')
    ->table('loan_customers as c')
    ->orderByDesc('c.id')
    ->paginate(20);
echo 'loan_customers_count='.$customers->total().PHP_EOL;
echo 'view_rendered='.strlen(view('loanmanagement::customers.index', [
    'customers' => $customers,
    'tableExists' => true,
])->render()).PHP_EOL;
