<?php

use Illuminate\Database\Migrations\Migration;
use App\System;

return new class extends Migration
{
    public function up(): void
    {
        $key = 'fixstockmismatch_version';
        $existing = System::getProperty($key);
        if (empty($existing)) {
            System::addProperty($key, '1.0.0');
        }
    }

    public function down(): void
    {
        // Keep version property
    }
};

