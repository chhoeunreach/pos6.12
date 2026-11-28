<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_loan');
        if ($schema->hasTable('loan_payment_submissions')) {
            return;
        }

        $schema->create('loan_payment_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->string('reference_no', 64)->unique();
            $table->string('payment_method', 32)->default('khqr'); // khqr | proof
            $table->decimal('amount', 16, 4);
            $table->string('currency', 10)->default('USD');
            $table->text('qr_string')->nullable();
            $table->unsignedBigInteger('proof_file_id')->nullable();
            $table->string('proof_file_url', 512)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('transaction_ref', 120)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 32)->default('pending')->index(); // pending | paid | approved | rejected | expired
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('pos_payment_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->dropIfExists('loan_payment_submissions');
    }
};
