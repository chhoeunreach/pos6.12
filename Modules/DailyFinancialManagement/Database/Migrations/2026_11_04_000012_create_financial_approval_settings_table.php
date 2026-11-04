<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_approval_settings')) {
            Schema::create('financial_approval_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('business_location_id')->nullable()->index();
                $table->decimal('cashier_limit', 22, 4)->default(50.0000);
                $table->decimal('branch_manager_limit', 22, 4)->default(500.0000);
                $table->decimal('accountant_limit', 22, 4)->default(2000.0000);
                $table->decimal('general_manager_limit', 22, 4)->default(10000.0000);
                $table->boolean('require_accountant_review')->default(true);
                $table->boolean('require_manager_approval')->default(true);
                $table->boolean('auto_approve_pos_sync')->default(true);
                $table->timestamps();

                $table->unique(['business_id', 'business_location_id'], 'unique_fin_approval_settings');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_approval_settings');
    }
};

