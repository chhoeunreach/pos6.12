<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customer_locations_realtime')) {
            Schema::connection('mysql_loan')->create('loan_customer_locations_realtime', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('loan_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 10, 2)->nullable();
                $table->decimal('speed', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->decimal('battery_level', 10, 2)->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
                $table->index(['customer_id', 'recorded_at'], 'loan_cust_rt_customer_recorded_idx');
            });
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_customer_location_latest')) {
            Schema::connection('mysql_loan')->create('loan_customer_location_latest', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('customer_id')->unique();
                $table->unsignedBigInteger('loan_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 10, 2)->nullable();
                $table->decimal('speed', 10, 2)->nullable();
                $table->decimal('heading', 10, 2)->nullable();
                $table->decimal('battery_level', 10, 2)->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};

