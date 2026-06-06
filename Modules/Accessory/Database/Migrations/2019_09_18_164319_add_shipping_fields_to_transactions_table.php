<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        if (! Schema::hasColumn('transactions', 'shipping_address')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->text('shipping_address')->nullable()->after('shipping_details');
            });
        }

        if (! Schema::hasColumn('transactions', 'shipping_status')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('shipping_status')->nullable()->after('shipping_address');
            });
        }

        if (! Schema::hasColumn('transactions', 'delivered_to')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('delivered_to')->nullable()->after('shipping_status');
            });
        }

        Permission::firstOrCreate(['name' => 'access_shipping', 'guard_name' => 'web']);
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
