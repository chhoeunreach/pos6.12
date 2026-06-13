<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('smart_stock_action_logs')) {
            Schema::create('smart_stock_action_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('action_type', 100)->index();
                $table->string('reference_type', 100)->nullable()->index();
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->longText('old_data')->nullable();
                $table->longText('new_data')->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('smart_stock_inventory_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        Schema::table('smart_stock_inventory_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        Schema::table('smart_stock_fix_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_fix_logs', 'location_id')) {
                $table->unsignedInteger('location_id')->nullable()->index()->after('business_id');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'product_id')) {
                $table->unsignedInteger('product_id')->nullable()->index()->after('location_id');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'variation_id')) {
                $table->unsignedInteger('variation_id')->nullable()->index()->after('product_id');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'problem_type')) {
                $table->string('problem_type', 100)->nullable()->index()->after('fix_type');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'old_qty')) {
                $table->decimal('old_qty', 22, 4)->nullable()->after('problem_type');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'new_qty')) {
                $table->decimal('new_qty', 22, 4)->nullable()->after('old_qty');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'reason')) {
                $table->string('reason', 500)->nullable()->after('new_qty');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'risk_level')) {
                $table->string('risk_level', 40)->nullable()->index()->after('reason');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'rollbackable')) {
                $table->unsignedTinyInteger('rollbackable')->default(1)->index()->after('risk_level');
            }
            if (! Schema::hasColumn('smart_stock_fix_logs', 'rollback_of_fix_log_id')) {
                $table->unsignedBigInteger('rollback_of_fix_log_id')->nullable()->index()->after('rollbackable');
            }
        });

        foreach (['stock_inventory.update', 'stock_inventory.rollback', 'stock_inventory.logs'] as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        // Keep permissions and extra columns to avoid breaking existing installs.
        Schema::dropIfExists('smart_stock_action_logs');
    }
};

