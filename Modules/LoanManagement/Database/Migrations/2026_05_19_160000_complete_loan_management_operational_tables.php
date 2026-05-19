<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLoanCallLogs();
        $this->createLoanExportLogs();
        $this->completeGuarantors();
        $this->completePaymentMethods();
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->dropIfExists('loan_export_logs');
        Schema::connection('mysql_loan')->dropIfExists('loan_call_logs');
    }

    private function createLoanCallLogs(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_call_logs')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_call_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('staff_name_snapshot')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('channel_name')->nullable()->index();
            $table->string('call_direction', 20)->default('outgoing')->index();
            $table->string('status', 30)->default('ringing')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->text('token_meta_json')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createLoanExportLogs(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_export_logs')) {
            return;
        }

        Schema::connection('mysql_loan')->create('loan_export_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('export_type', 60)->index();
            $table->string('format', 20)->default('xlsx');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('requested_by_name_snapshot')->nullable();
            $table->text('filters_json')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('rows_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function completeGuarantors(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_guarantors')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loan_guarantors', function (Blueprint $table) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_guarantors', 'photo_file_id')) {
                $table->unsignedBigInteger('photo_file_id')->nullable()->after('id_card_number');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_guarantors', 'status')) {
                $table->string('status', 30)->default('active')->after('note')->index();
            }
        });
    }

    private function completePaymentMethods(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loan_payment_methods', function (Blueprint $table) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'code')) {
                $table->string('code', 60)->nullable()->after('id')->index();
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });
    }
};
