<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoicePrefixToBusinessLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Intentionally no-op for compatibility across Ultimate POS versions.
        // LoanManagement should not mutate or depend on custom columns in core tables.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No-op.
    }
}
