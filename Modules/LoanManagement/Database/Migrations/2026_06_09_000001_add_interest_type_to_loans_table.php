<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')
            || Schema::connection($this->connection)->hasColumn('loans', 'interest_type')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            $table->string('interest_type', 30)->default('flat')->after('interest_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')
            || ! Schema::connection($this->connection)->hasColumn('loans', 'interest_type')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            $table->dropColumn('interest_type');
        });
    }
};
