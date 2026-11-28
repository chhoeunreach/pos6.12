<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Feed tables moved to dedicated Modules/Social (`social_*` in `social` DB).
     */
    public function up(): void
    {
        // Managed by Modules/Social/Database/Migrations.
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
