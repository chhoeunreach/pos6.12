<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('mysql_loan');
        if (! $schema->hasTable('loans')) return;
        $columns = [
            'ptp_date' => fn (Blueprint $t) => $t->date('ptp_date')->nullable(),
            'ptp_amount' => fn (Blueprint $t) => $t->decimal('ptp_amount', 22, 4)->nullable(),
            'ptp_note' => fn (Blueprint $t) => $t->text('ptp_note')->nullable(),
            'ptp_status' => fn (Blueprint $t) => $t->string('ptp_status', 30)->nullable(),
            'collection_status' => fn (Blueprint $t) => $t->string('collection_status', 50)->nullable(),
        ];
        foreach ($columns as $name => $add) {
            if (! $schema->hasColumn('loans', $name)) $schema->table('loans', $add);
        }
    }

    public function down(): void
    {
        // Retain promises and collection history when rolling back feature code.
    }
};
