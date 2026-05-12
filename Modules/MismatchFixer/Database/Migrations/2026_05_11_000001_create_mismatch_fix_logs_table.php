<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mismatch_fix_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_line_id')->index();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->string('problem_type', 100);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mismatch_fix_logs');
    }
};
