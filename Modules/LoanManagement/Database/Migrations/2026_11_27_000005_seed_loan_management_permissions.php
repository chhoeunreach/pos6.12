<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\LoanManagement\Services\BranchPermissionService;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach (BranchPermissionService::ALL_PERMISSIONS as $perm) {
            $exists = DB::table('permissions')
                ->where('name', $perm)
                ->where('guard_name', 'web')
                ->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $perm,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('name', BranchPermissionService::ALL_PERMISSIONS)
            ->delete();
    }
};
