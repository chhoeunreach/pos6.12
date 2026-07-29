<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('hr_sell_records')) {
            Schema::create('hr_sell_records', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->unsignedInteger('transaction_id')->index();
                $table->unsignedInteger('hr_user_id')->nullable()->index();
                $table->unsignedInteger('supervisor_id')->nullable()->index();
                $table->string('status', 40)->default('draft')->index();
                $table->string('approval_status', 40)->default('pending')->index();
                $table->string('commission_type', 20)->default('percent');
                $table->decimal('commission_value', 22, 4)->default(0);
                $table->decimal('commission_amount', 22, 4)->default(0);
                $table->decimal('sale_total', 22, 4)->default(0);
                $table->decimal('paid_total', 22, 4)->default(0);
                $table->decimal('due_total', 22, 4)->default(0);
                $table->date('follow_up_date')->nullable()->index();
                $table->string('follow_up_status', 40)->default('none')->index();
                $table->text('internal_note')->nullable();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->unsignedInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'transaction_id']);
                $table->index(['business_id', 'status', 'approval_status']);
                $table->index(['business_id', 'hr_user_id', 'follow_up_date']);
            });
        }

        if (! Schema::hasTable('hr_sell_notes')) {
            Schema::create('hr_sell_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('hr_sell_record_id')->index();
                $table->string('note_type', 40)->default('note')->index();
                $table->text('note');
                $table->date('next_follow_up_date')->nullable()->index();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_sell_approvals')) {
            Schema::create('hr_sell_approvals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('hr_sell_record_id')->index();
                $table->string('level', 40)->index();
                $table->string('status', 40)->default('pending')->index();
                $table->unsignedInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_sell_logs')) {
            Schema::create('hr_sell_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('hr_sell_record_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->longText('old_data')->nullable();
                $table->longText('new_data')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_sell_settings')) {
            Schema::create('hr_sell_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->unique();
                $table->string('commission_type', 20)->default('percent');
                $table->decimal('commission_value', 22, 4)->default(0);
                $table->unsignedTinyInteger('require_approval')->default(1);
                $table->json('approval_levels')->nullable();
                $table->unsignedInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        foreach ([
            'hr_sell.view',
            'hr_sell.create',
            'hr_sell.update',
            'hr_sell.approve',
            'hr_sell.report',
            'hr_sell.report.edit',
            'hr_sell.report.delete',
            'hr_sell.settings',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_sell_settings');
        Schema::dropIfExists('hr_sell_logs');
        Schema::dropIfExists('hr_sell_approvals');
        Schema::dropIfExists('hr_sell_notes');
        Schema::dropIfExists('hr_sell_records');
    }
};
