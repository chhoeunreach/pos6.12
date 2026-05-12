<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        foreach (['mismatch_fixer.view', 'mismatch_fixer.fix', 'mismatch_fixer.logs', 'mismatch_fixer.settings'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        // keep permissions for compatibility
    }
};
