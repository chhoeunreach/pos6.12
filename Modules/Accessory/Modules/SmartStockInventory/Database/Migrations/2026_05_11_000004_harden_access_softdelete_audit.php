<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('smart_stock_action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_action_logs', 'user_name')) {
                $table->string('user_name', 191)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('smart_stock_action_logs', 'module_name')) {
                $table->string('module_name', 100)->nullable()->index()->after('business_id');
            }
            if (! Schema::hasColumn('smart_stock_action_logs', 'table_name')) {
                $table->string('table_name', 100)->nullable()->index()->after('module_name');
            }
            if (! Schema::hasColumn('smart_stock_action_logs', 'record_id')) {
                $table->unsignedBigInteger('record_id')->nullable()->index()->after('table_name');
            }
            if (! Schema::hasColumn('smart_stock_action_logs', 'ip_address')) {
                $table->string('ip_address', 64)->nullable()->after('reason');
            }
            if (! Schema::hasColumn('smart_stock_action_logs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('smart_stock_fix_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_fix_logs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('smart_inventory_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'action_type')) {
                $table->string('action_type', 100)->nullable()->index()->after('user_id');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'module_name')) {
                $table->string('module_name', 100)->nullable()->index()->after('action_type');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'table_name')) {
                $table->string('table_name', 100)->nullable()->index()->after('module_name');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'record_id')) {
                $table->unsignedBigInteger('record_id')->nullable()->index()->after('table_name');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'user_name')) {
                $table->string('user_name', 191)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'reason')) {
                $table->string('reason', 500)->nullable()->after('new_value');
            }
            if (! Schema::hasColumn('smart_inventory_audit_logs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('smart_imei_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_imei_histories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('smart_lot_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_lot_histories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('smart_stock_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_settings', 'enable_super_admin_override')) {
                $table->unsignedTinyInteger('enable_super_admin_override')->default(1);
            }
        });
    }

    public function down(): void
    {
    }
};

