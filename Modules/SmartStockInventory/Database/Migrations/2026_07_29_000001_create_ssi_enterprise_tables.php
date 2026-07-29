<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ssi_audits')) {
            Schema::create('ssi_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('audit_no', 60)->index();
                $table->string('name', 191);
                $table->string('audit_type', 40)->default('cycle')->index();
                $table->string('count_mode', 40)->default('normal')->index();
                $table->string('status', 40)->default('draft')->index();
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable()->index();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->unsignedInteger('assigned_to')->nullable()->index();
                $table->json('scope')->nullable();
                $table->json('settings')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'audit_no']);
                $table->index(['business_id', 'status', 'audit_type']);
                $table->index(['business_id', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('ssi_audit_items')) {
            Schema::create('ssi_audit_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('audit_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->unsignedInteger('product_id')->nullable()->index();
                $table->unsignedInteger('variation_id')->nullable()->index();
                $table->string('sku', 191)->nullable()->index();
                $table->string('product_name', 255)->nullable();
                $table->string('imei', 191)->nullable()->index();
                $table->string('serial', 191)->nullable()->index();
                $table->string('lot_number', 191)->nullable()->index();
                $table->string('warehouse', 120)->nullable()->index();
                $table->string('zone', 120)->nullable()->index();
                $table->string('rack', 120)->nullable()->index();
                $table->string('shelf', 120)->nullable()->index();
                $table->string('bin', 120)->nullable()->index();
                $table->decimal('expected_qty', 22, 4)->default(0);
                $table->decimal('counted_qty', 22, 4)->default(0);
                $table->decimal('difference_qty', 22, 4)->default(0);
                $table->string('verification_status', 40)->default('pending')->index();
                $table->string('mismatch_type', 80)->nullable()->index();
                $table->unsignedInteger('counted_by')->nullable()->index();
                $table->timestamp('counted_at')->nullable()->index();
                $table->unsignedInteger('verified_by')->nullable()->index();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedTinyInteger('recount_required')->default(0)->index();
                $table->unsignedInteger('recount_of_item_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_id', 'audit_id', 'verification_status']);
                $table->index(['business_id', 'variation_id', 'location_id']);
                $table->index(['business_id', 'warehouse', 'zone', 'rack', 'shelf', 'bin']);
            });
        }

        if (! Schema::hasTable('ssi_audit_scans')) {
            Schema::create('ssi_audit_scans', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('audit_id')->index();
                $table->unsignedBigInteger('audit_item_id')->nullable()->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('scan_type', 40)->default('barcode')->index();
                $table->string('scan_value', 191)->index();
                $table->string('normalized_value', 191)->nullable()->index();
                $table->decimal('quantity', 22, 4)->default(1);
                $table->string('warehouse', 120)->nullable()->index();
                $table->string('zone', 120)->nullable()->index();
                $table->string('rack', 120)->nullable()->index();
                $table->string('shelf', 120)->nullable()->index();
                $table->string('bin', 120)->nullable()->index();
                $table->string('device_id', 191)->nullable()->index();
                $table->string('device_name', 191)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->unsignedInteger('scanned_by')->nullable()->index();
                $table->timestamp('scanned_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'audit_id', 'scan_type']);
                $table->index(['business_id', 'normalized_value', 'scanned_at']);
            });
        }

        if (! Schema::hasTable('ssi_investigations')) {
            Schema::create('ssi_investigations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('audit_id')->nullable()->index();
                $table->unsignedBigInteger('audit_item_id')->nullable()->index();
                $table->string('case_no', 60)->index();
                $table->string('case_type', 80)->index();
                $table->string('status', 40)->default('open')->index();
                $table->string('priority', 40)->default('normal')->index();
                $table->unsignedInteger('assigned_to')->nullable()->index();
                $table->unsignedInteger('opened_by')->nullable()->index();
                $table->timestamp('opened_at')->nullable()->index();
                $table->unsignedInteger('closed_by')->nullable()->index();
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('attachments')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'case_no']);
                $table->index(['business_id', 'status', 'case_type']);
            });
        }

        if (! Schema::hasTable('ssi_approvals')) {
            Schema::create('ssi_approvals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('audit_id')->index();
                $table->string('approval_level', 60)->index();
                $table->unsignedSmallInteger('sequence')->default(1)->index();
                $table->string('status', 40)->default('pending')->index();
                $table->unsignedInteger('requested_by')->nullable()->index();
                $table->unsignedInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'audit_id', 'sequence', 'status']);
            });
        }

        if (! Schema::hasTable('ssi_logs')) {
            Schema::create('ssi_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('audit_id')->nullable()->index();
                $table->unsignedBigInteger('audit_item_id')->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->string('subject_type', 120)->nullable()->index();
                $table->string('log_type', 60)->default('activity')->index();
                $table->string('action', 120)->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->string('device_id', 191)->nullable()->index();
                $table->string('ip_address', 64)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'audit_id', 'log_type']);
                $table->index(['business_id', 'action', 'created_at']);
            });
        }

        if (! Schema::hasTable('ssi_dashboard_cache')) {
            Schema::create('ssi_dashboard_cache', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('cache_key', 191)->index();
                $table->json('payload');
                $table->timestamp('computed_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['business_id', 'location_id', 'cache_key'], 'ssi_dashboard_cache_unique');
            });
        }

        if (! Schema::hasTable('ssi_settings')) {
            Schema::create('ssi_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->unique();
                $table->unsignedTinyInteger('blind_count_default')->default(0);
                $table->unsignedTinyInteger('require_recount_for_mismatch')->default(1);
                $table->unsignedTinyInteger('auto_create_investigations')->default(1);
                $table->unsignedTinyInteger('auto_stock_adjustment')->default(0);
                $table->decimal('recount_threshold', 22, 4)->default(1);
                $table->json('approval_levels')->nullable();
                $table->json('scanner_settings')->nullable();
                $table->json('report_settings')->nullable();
                $table->unsignedInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ssi_settings');
        Schema::dropIfExists('ssi_dashboard_cache');
        Schema::dropIfExists('ssi_logs');
        Schema::dropIfExists('ssi_approvals');
        Schema::dropIfExists('ssi_investigations');
        Schema::dropIfExists('ssi_audit_scans');
        Schema::dropIfExists('ssi_audit_items');
        Schema::dropIfExists('ssi_audits');
    }
};
