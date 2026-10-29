<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loans') && ! Schema::connection('mysql_loan')->hasColumn('loans', 'daily_penalty_rate')) {
            Schema::connection('mysql_loan')->table('loans', function (Blueprint $table) {
                $table->decimal('daily_penalty_rate', 18, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('mysql_loan')->hasColumn('loans', 'daily_penalty_rate')) {
            Schema::connection('mysql_loan')->table('loans', fn (Blueprint $table) => $table->dropColumn('daily_penalty_rate'));
        }
    }
};
