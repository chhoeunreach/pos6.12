<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_loan');

        if (!$schema->hasTable('loan_location_requests')) {
            $schema->create('loan_location_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('requested_by_staff_id')->index();
                $table->unsignedBigInteger('loan_id')->nullable()->index();
                $table->unsignedBigInteger('thread_id')->nullable()->index();
                $table->string('mode', 16)->default('once'); // once | live
                $table->unsignedInteger('duration_minutes')->default(15);
                $table->string('reason', 255)->nullable();
                $table->string('status', 32)->default('pending')->index(); // pending | approved | rejected | expired | stopped
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('loan_location_points')) {
            $schema->create('loan_location_points', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->timestamp('recorded_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->dropIfExists('loan_location_points');
        Schema::connection('mysql_loan')->dropIfExists('loan_location_requests');
    }
};
