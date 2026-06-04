<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        $this->enhanceLoans();
        $this->enhanceLoanCustomers();
        $this->enhanceLoanItems();
        $this->enhanceSchedules();
        $this->enhancePayments();
        $this->enhanceGuarantors();
    }

    public function down(): void
    {
        $this->dropColumns('loans', [
            'sale_id', 'invoice_no', 'sale_date', 'created_by', 'approved_by_name_snapshot',
            'financed_amount', 'interest_rate', 'interest_type', 'duration_months',
            'penalty_type', 'principal_paid', 'interest_paid', 'outstanding_principal',
            'outstanding_interest', 'overdue_amount', 'next_due_amount', 'next_due_date',
            'last_followup_date', 'followup_result', 'repossession_date', 'repossession_reason',
        ]);
        $this->dropColumns('loan_customers', ['customer_group', 'national_id', 'occupation', 'employer_name', 'employer_phone']);
        $this->dropColumns('loan_items', ['product_id', 'product_name', 'sku', 'brand', 'category', 'serial_number']);
        $this->dropColumns('loan_payment_schedules', ['principal', 'interest', 'total', 'paid_date']);
        $this->dropColumns('loan_payments', ['principal_paid', 'interest_paid', 'reference_no', 'payment_date']);
        $this->dropColumns('loan_guarantors', ['national_id', 'relationship']);
    }

    protected function enhanceLoans(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            $this->unsignedBigInteger($table, 'sale_id', 'source_transaction_id');
            $this->string($table, 'invoice_no', 191, 'sale_id');
            $this->date($table, 'sale_date', 'invoice_no');
            $this->unsignedBigInteger($table, 'created_by', 'approved_by');
            $this->string($table, 'approved_by_name_snapshot', 191, 'created_by');
            $this->decimal($table, 'financed_amount', 'down_payment');
            $this->decimal($table, 'interest_rate', 'financed_amount');
            $this->string($table, 'interest_type', 30, 'interest_rate');
            $this->integer($table, 'duration_months', 'interest_type');
            $this->string($table, 'penalty_type', 50, 'penalty_amount');
            $this->decimal($table, 'principal_paid', 'paid_amount');
            $this->decimal($table, 'interest_paid', 'principal_paid');
            $this->decimal($table, 'outstanding_principal', 'interest_paid');
            $this->decimal($table, 'outstanding_interest', 'outstanding_principal');
            $this->decimal($table, 'overdue_amount', 'outstanding_interest');
            $this->decimal($table, 'next_due_amount', 'overdue_amount');
            $this->date($table, 'next_due_date', 'next_due_amount');
            $this->date($table, 'last_followup_date', 'last_contact_result');
            $this->string($table, 'followup_result', 191, 'last_followup_date');
            $this->date($table, 'repossession_date', 'repossession_status');
            $this->text($table, 'repossession_reason', 'repossession_date');
        });
    }

    protected function enhanceLoanCustomers(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_customers', function (Blueprint $table) {
            $this->string($table, 'customer_group', 191, 'customer_code');
            $this->string($table, 'national_id', 191, 'id_card_number');
            $this->string($table, 'occupation', 191, 'workplace');
            $this->string($table, 'employer_name', 191, 'occupation');
            $this->string($table, 'employer_phone', 191, 'employer_name');
        });
    }

    protected function enhanceLoanItems(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_items', function (Blueprint $table) {
            $this->unsignedBigInteger($table, 'product_id', 'loan_product_id');
            $this->string($table, 'product_name', 191, 'product_name_snapshot');
            $this->string($table, 'sku', 191, 'sku_snapshot');
            $this->string($table, 'brand', 191, 'sku');
            $this->string($table, 'category', 191, 'brand');
            $this->string($table, 'serial_number', 191, 'imei_snapshot');
        });
    }

    protected function enhanceSchedules(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_payment_schedules', function (Blueprint $table) {
            $this->decimal($table, 'principal', 'principal_due');
            $this->decimal($table, 'interest', 'interest_due');
            $this->decimal($table, 'total', 'amount_due');
            $this->date($table, 'paid_date', 'paid_at');
        });
    }

    protected function enhancePayments(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payments')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_payments', function (Blueprint $table) {
            $this->decimal($table, 'principal_paid', 'amount');
            $this->decimal($table, 'interest_paid', 'principal_paid');
            $this->string($table, 'reference_no', 191, 'payment_ref_no');
            $this->date($table, 'payment_date', 'paid_at');
        });
    }

    protected function enhanceGuarantors(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_guarantors')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_guarantors', function (Blueprint $table) {
            $this->string($table, 'national_id', 191, 'id_number');
            $this->string($table, 'relationship', 191, 'relation');
        });
    }

    protected function string(Blueprint $table, string $name, int $length, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->string($name, $length)->nullable();
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function text(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->text($name)->nullable();
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function date(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->date($name)->nullable();
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function integer(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->integer($name)->default(0);
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function unsignedBigInteger(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->unsignedBigInteger($name)->nullable();
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function decimal(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table->getTable(), $name)) {
            $column = $table->decimal($name, 18, 2)->default(0);
            if (Schema::connection($this->connection)->hasColumn($table->getTable(), $after)) {
                $column->after($after);
            }
        }
    }

    protected function dropColumns(string $table, array $columns): void
    {
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return;
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $column) {
                if (Schema::connection($this->connection)->hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
