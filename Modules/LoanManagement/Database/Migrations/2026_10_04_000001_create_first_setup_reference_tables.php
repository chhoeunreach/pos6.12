<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->createCustomerGroupsTable();
        $this->ensureLoanPaymentMethodColumns();
    }

    public function down(): void
    {
        // Shared customer groups and payment method metadata may predate this upgrade.
        // Keep them when rolling back feature code.
    }

    /**
     * The loan module reads customer_groups from the loan connection (App\CustomerGroup,
     * LoanCreateController) and from the default connection (LoanDashboardService,
     * CreateLoanFromSellService, LoanFromSellController). Keep both in sync.
     */
    protected function createCustomerGroupsTable(): void
    {
        $loanDatabase = Schema::connection('mysql_loan')->getConnection()->getDatabaseName();
        $defaultDatabase = DB::connection()->getDatabaseName();

        $schemas = [Schema::connection('mysql_loan')];

        if ($loanDatabase !== $defaultDatabase) {
            $schemas[] = Schema::connection(config('database.default'));
        }

        foreach ($schemas as $schema) {
            if ($schema->hasTable('customer_groups')) {
                continue;
            }

            $schema->create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->default(1)->index();
                $table->string('name');
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('price_calculation_type', 50)->default('percentage');
                $table->unsignedBigInteger('selling_price_group_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'name']);
            });
        }
    }

    /**
     * SettingsController orders payment methods by sort_order and exposes a code
     * column, but no migration ever added them.
     */
    protected function ensureLoanPaymentMethodColumns(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            return;
        }

        if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'code')) {
            Schema::connection('mysql_loan')->table('loan_payment_methods', function (Blueprint $table) {
                $table->string('code', 60)->nullable()->after('id')->index();
            });
        }

        if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'sort_order')) {
            Schema::connection('mysql_loan')->table('loan_payment_methods', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('is_active');
            });
        }
    }
};
