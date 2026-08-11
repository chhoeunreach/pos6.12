<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_sell_lines', 'grade')) {
                $table->string('grade', 10)->nullable()->after('sell_line_note');
            }
        });
    }

    public function down()
    {
        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_sell_lines', 'grade')) {
                $table->dropColumn('grade');
            }
        });
    }
};
