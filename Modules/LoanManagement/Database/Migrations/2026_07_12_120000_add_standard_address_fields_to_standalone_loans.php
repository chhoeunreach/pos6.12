<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        $this->addLoanCustomerColumns();
        $this->addLoanSnapshotColumns();
    }

    public function down(): void
    {
        $this->dropColumns('loan_customers', [
            'province_code',
            'district_code',
            'commune_code',
            'village_code',
        ]);

        $this->dropColumns('loans', [
            'customer_province_snapshot',
            'customer_province_code_snapshot',
            'customer_district_snapshot',
            'customer_district_code_snapshot',
            'customer_commune_snapshot',
            'customer_commune_code_snapshot',
            'customer_village_snapshot',
            'customer_village_code_snapshot',
        ]);
    }

    private function addLoanCustomerColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_customers', function (Blueprint $table) {
            $this->stringColumn($table, 'province', 191, 'address');
            $this->stringColumn($table, 'province_code', 20, 'province');
            $this->stringColumn($table, 'district', 191, 'province_code');
            $this->stringColumn($table, 'district_code', 20, 'district');
            $this->stringColumn($table, 'commune', 191, 'district_code');
            $this->stringColumn($table, 'commune_code', 20, 'commune');
            $this->stringColumn($table, 'village', 191, 'commune_code');
            $this->stringColumn($table, 'village_code', 20, 'village');
        });
    }

    private function addLoanSnapshotColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            $this->stringColumn($table, 'customer_province_snapshot', 191, 'customer_address_snapshot');
            $this->stringColumn($table, 'customer_province_code_snapshot', 20, 'customer_province_snapshot');
            $this->stringColumn($table, 'customer_district_snapshot', 191, 'customer_province_code_snapshot');
            $this->stringColumn($table, 'customer_district_code_snapshot', 20, 'customer_district_snapshot');
            $this->stringColumn($table, 'customer_commune_snapshot', 191, 'customer_district_code_snapshot');
            $this->stringColumn($table, 'customer_commune_code_snapshot', 20, 'customer_commune_snapshot');
            $this->stringColumn($table, 'customer_village_snapshot', 191, 'customer_commune_code_snapshot');
            $this->stringColumn($table, 'customer_village_code_snapshot', 20, 'customer_village_snapshot');
        });
    }

    private function stringColumn(Blueprint $table, string $name, int $length, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->string($name, $length)->nullable();

            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    private function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::connection($this->connection)->hasTable($tableName)) {
            return;
        }

        Schema::connection($this->connection)->table($tableName, function (Blueprint $table) use ($tableName, $columns) {
            foreach ($columns as $column) {
                if (Schema::connection($this->connection)->hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
