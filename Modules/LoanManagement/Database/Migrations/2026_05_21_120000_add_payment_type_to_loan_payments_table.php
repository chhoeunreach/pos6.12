<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_payments';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->table, 'payment_type')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->string('payment_type', 20)->default('monthly')->after('loan_id')->index();
            });
        }

        DB::connection($this->connection)->table($this->table)
            ->whereNull('payment_type')
            ->orWhere('payment_type', '')
            ->update(['payment_type' => 'monthly']);

        $columns = Schema::connection($this->connection)->getColumnListing($this->table);
        $downPaymentColumns = array_values(array_intersect(
            ['payment_ref_no', 'receipt_number', 'reference_number', 'payment_number'],
            $columns
        ));
        $hasNote = in_array('note', $columns, true);

        if (! empty($downPaymentColumns) || $hasNote) {
            DB::connection($this->connection)->table($this->table)
                ->where(function ($query) use ($downPaymentColumns, $hasNote) {
                    foreach ($downPaymentColumns as $column) {
                        $query->orWhere($column, 'like', 'IMP-DOWN-%');
                    }
                    if ($hasNote) {
                        $query->orWhere('note', 'like', '%initial/down payment%');
                    }
                })
                ->update(['payment_type' => 'loan']);
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable($this->table)
            && Schema::connection($this->connection)->hasColumn($this->table, 'payment_type')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};
