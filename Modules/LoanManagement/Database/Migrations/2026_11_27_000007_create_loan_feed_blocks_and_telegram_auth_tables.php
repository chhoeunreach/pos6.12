<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $loanSchema = Schema::connection('mysql_loan');
        if (!$loanSchema->hasTable('loan_telegram_auth_sessions')) {
            $loanSchema->create('loan_telegram_auth_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('token', 64)->unique();
                $table->string('status', 24)->default('pending')->index(); // pending | awaiting_phone | verified | expired
                $table->unsignedBigInteger('telegram_user_id')->nullable()->index();
                $table->string('telegram_username', 120)->nullable();
                $table->string('telegram_name', 160)->nullable();
                $table->string('phone', 40)->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->boolean('is_new_customer')->default(false);
                $table->text('api_token')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if ($loanSchema->hasTable('loan_customers') && !$loanSchema->hasColumn('loan_customers', 'telegram_user_id')) {
            $loanSchema->table('loan_customers', function (Blueprint $table) {
                $table->unsignedBigInteger('telegram_user_id')->nullable()->after('telegram')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->dropIfExists('loan_telegram_auth_sessions');
    }
};
