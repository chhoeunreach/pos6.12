<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLoanCustomerLocations();
        $this->createLoanGuarantors();
        $this->createLoanCustomerNotes();
        $this->createLoanCustomerFollowups();
        $this->createLoanAppSettings();
        $this->createLoanCurrencies();
        $this->createLoanPaymentMethods();
    }

    public function down(): void
    {
        foreach ([
            'loan_payment_methods',
            'loan_currencies',
            'loan_app_settings',
            'loan_customer_followups',
            'loan_customer_notes',
            'loan_guarantors',
            'loan_customer_locations',
        ] as $table) {
            Schema::connection('mysql_loan')->dropIfExists($table);
        }
    }

    private function createLoanCustomerLocations(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_customer_locations')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_customer_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->string('source', 20)->default('mobile');
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamps();
        });
    }

    private function createLoanGuarantors(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_guarantors')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_guarantors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('relationship')->nullable();
            $table->text('address')->nullable();
            $table->string('workplace')->nullable();
            $table->string('id_card_number')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createLoanCustomerNotes(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_customer_notes')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_customer_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->text('note');
            $table->string('note_type', 40)->default('general')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createLoanCustomerFollowups(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_customer_followups')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_customer_followups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->date('follow_up_date')->index();
            $table->string('follow_up_type', 40)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('assigned_staff_id')->nullable()->index();
            $table->string('assigned_staff_name_snapshot')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createLoanAppSettings(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_app_settings')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_app_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 30)->default('string');
            $table->timestamps();
        });
    }

    private function createLoanCurrencies(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_currencies')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name', 60);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createLoanPaymentMethods(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};

