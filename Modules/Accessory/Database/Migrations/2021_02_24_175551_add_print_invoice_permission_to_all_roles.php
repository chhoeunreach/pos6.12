<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Permission::firstOrCreate([
            'name' => 'print_invoice',
            'guard_name' => 'web',
        ]);
        $roles = Role::all();

        foreach ($roles as $role) {
            $role->givePermissionTo('print_invoice');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
