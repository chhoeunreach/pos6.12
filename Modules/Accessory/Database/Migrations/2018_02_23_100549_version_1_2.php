<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = \Carbon::now()->toDateTimeString();
        $permissions = [
            [
                'name' => 'profit_loss_report.view',
                'guard_name' => 'web',
                'created_at' => $now,
            ],
            [
                'name' => 'direct_sell.access',
                'guard_name' => 'web',
                'created_at' => $now,
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                [
                    'created_at' => $permission['created_at'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
