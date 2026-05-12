<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_details')) {
            Schema::connection('mysql_loan')->create('loan_payment_details', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('payment_method_id')->nullable();
                $table->string('payment_method_snapshot');
                $table->string('currency', 10)->default('USD');
                $table->decimal('amount', 22, 4)->default(0);
                $table->decimal('exchange_rate', 22, 8)->default(1);
                $table->decimal('amount_base', 22, 4)->default(0);
                $table->string('reference_number')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('payment_id');
                $table->index('payment_method_id');
                $table->index('payment_method_snapshot');
            });
        } else {
            Schema::connection('mysql_loan')->table('loan_payment_details', function (Blueprint $table) {
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'payment_method_id')) {
                    $table->unsignedBigInteger('payment_method_id')->nullable()->after('payment_id');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'payment_method_snapshot')) {
                    $table->string('payment_method_snapshot')->default('Unknown')->after('payment_method_id');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'currency')) {
                    $table->string('currency', 10)->default('USD')->after('payment_method_snapshot');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'amount')) {
                    $table->decimal('amount', 22, 4)->default(0)->after('currency');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 22, 8)->default(1)->after('amount');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'amount_base')) {
                    $table->decimal('amount_base', 22, 4)->default(0)->after('exchange_rate');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'reference_number')) {
                    $table->string('reference_number')->nullable()->after('amount_base');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'note')) {
                    $table->text('note')->nullable()->after('reference_number');
                }
                if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_details', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            Schema::connection('mysql_loan')->drop('loan_payment_methods');
        }
    }

    public function down(): void
    {
        // Keep loan_payment_details for history safety.
    }
};
