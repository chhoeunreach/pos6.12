<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

class AddRepairPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Permission::firstOrCreate(['name' => 'repair.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair.delete', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'repair_status.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair_status.access', 'guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
