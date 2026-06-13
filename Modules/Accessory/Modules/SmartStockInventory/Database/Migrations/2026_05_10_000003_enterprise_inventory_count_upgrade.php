<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('smart_stock_inventory_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'warehouse')) {
                $table->string('warehouse', 191)->nullable()->after('location_id');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'count_type')) {
                $table->string('count_type', 40)->default('full_count')->index()->after('warehouse');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'count_method')) {
                $table->string('count_method', 40)->default('manual')->index()->after('count_type');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'count_by')) {
                $table->string('count_by', 40)->default('product')->index()->after('count_method');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'start_date')) {
                $table->timestamp('start_date')->nullable()->after('count_by');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'end_date')) {
                $table->timestamp('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'approved_by')) {
                $table->unsignedInteger('approved_by')->nullable()->index()->after('completed_by');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'blind_count')) {
                $table->unsignedTinyInteger('blind_count')->default(0)->index()->after('approved_at');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'freeze_mode')) {
                $table->unsignedTinyInteger('freeze_mode')->default(0)->index()->after('blind_count');
            }
            if (! Schema::hasColumn('smart_stock_inventory_sessions', 'status')) {
                $table->string('status', 40)->default('draft')->index();
            }
        });

        Schema::table('smart_stock_inventory_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'counted_by_user_id')) {
                $table->unsignedInteger('counted_by_user_id')->nullable()->index()->after('session_id');
            }
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'verified_by_user_id')) {
                $table->unsignedInteger('verified_by_user_id')->nullable()->index()->after('counted_by_user_id');
            }
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'verification_status')) {
                $table->string('verification_status', 40)->default('verification_pending')->index()->after('verified_by_user_id');
            }
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'recount_required')) {
                $table->unsignedTinyInteger('recount_required')->default(0)->index()->after('verification_status');
            }
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'rack')) {
                $table->string('rack', 191)->nullable()->index()->after('lot_number');
            }
            if (! Schema::hasColumn('smart_stock_inventory_lines', 'is_damaged_lot')) {
                $table->unsignedTinyInteger('is_damaged_lot')->default(0)->after('rack');
            }
        });

        Schema::table('smart_stock_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('smart_stock_settings', 'allow_negative_adjustment')) {
                $table->unsignedTinyInteger('allow_negative_adjustment')->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'require_approval')) {
                $table->unsignedTinyInteger('require_approval')->default(1);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'blind_count_default')) {
                $table->unsignedTinyInteger('blind_count_default')->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'freeze_sell_during_count')) {
                $table->unsignedTinyInteger('freeze_sell_during_count')->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'mismatch_threshold')) {
                $table->decimal('mismatch_threshold', 22, 4)->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'recount_threshold')) {
                $table->decimal('recount_threshold', 22, 4)->default(5);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'auto_generate_adjustment')) {
                $table->unsignedTinyInteger('auto_generate_adjustment')->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'auto_close_session')) {
                $table->unsignedTinyInteger('auto_close_session')->default(0);
            }
            if (! Schema::hasColumn('smart_stock_settings', 'require_imei_validation')) {
                $table->unsignedTinyInteger('require_imei_validation')->default(0);
            }
        });

        Schema::create('smart_inventory_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('line_id')->index();
            $table->unsignedInteger('first_count_by')->nullable()->index();
            $table->unsignedInteger('verified_by')->nullable()->index();
            $table->decimal('first_qty', 22, 4)->default(0);
            $table->decimal('verified_qty', 22, 4)->default(0);
            $table->string('status', 40)->default('verification_pending')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('smart_inventory_recounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('line_id')->index();
            $table->string('recount_reason', 500);
            $table->unsignedInteger('recount_by')->nullable()->index();
            $table->timestamp('recount_date')->nullable();
            $table->decimal('before_qty', 22, 4)->default(0);
            $table->decimal('after_qty', 22, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('smart_inventory_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedInteger('approved_by')->nullable()->index();
            $table->string('approval_level', 40)->default('supervisor')->index();
            $table->string('status', 40)->default('pending')->index();
            $table->string('note', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('smart_inventory_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('line_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('device', 191)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->timestamps();
        });

        Schema::create('smart_inventory_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('category_id')->nullable()->index();
            $table->unsignedInteger('brand_id')->nullable()->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->string('rack', 191)->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('smart_inventory_freeze_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->string('freeze_type', 40)->default('full_location')->index();
            $table->unsignedTinyInteger('is_active')->default(1)->index();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        $permissions = [
            'stock_inventory.approve',
            'stock_inventory.recount',
            'stock_inventory.verify',
            'stock_inventory.mobile',
            'stock_inventory.freeze',
            'stock_inventory.report',
            'stock_inventory.adjust',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }
    }

    public function down(): void
    {
    }
};

