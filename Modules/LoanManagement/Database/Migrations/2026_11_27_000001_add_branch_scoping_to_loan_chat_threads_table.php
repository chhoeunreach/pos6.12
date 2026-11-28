<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_loan')->table('loan_chat_threads', function (Blueprint $table) {
            if (!Schema::connection('mysql_loan')->hasColumn('loan_chat_threads', 'business_id')) {
                $table->unsignedBigInteger('business_id')->nullable()->after('thread_number')->index();
            }
            if (!Schema::connection('mysql_loan')->hasColumn('loan_chat_threads', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('business_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->table('loan_chat_threads', function (Blueprint $table) {
            if (Schema::connection('mysql_loan')->hasColumn('loan_chat_threads', 'location_id')) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            }
            if (Schema::connection('mysql_loan')->hasColumn('loan_chat_threads', 'business_id')) {
                $table->dropIndex(['business_id']);
                $table->dropColumn('business_id');
            }
        });
    }
};
