<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_audit_logs')) {
            Schema::create('financial_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('action', 50)->index();
                $table->string('entity_type', 100)->index();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['business_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
    }
};

