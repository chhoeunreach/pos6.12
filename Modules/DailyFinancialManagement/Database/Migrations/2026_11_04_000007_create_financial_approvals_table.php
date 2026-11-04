<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_approvals')) {
            Schema::create('financial_approvals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('financial_transaction_id')->index();
                $table->enum('action', [
                    'submitted',
                    'reviewed',
                    'approved',
                    'rejected',
                    'cancelled',
                    'reversed',
                ])->index();
                $table->unsignedInteger('actor_id')->index();
                $table->string('actor_role', 50)->nullable();
                $table->text('comments')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_approvals');
    }
};

