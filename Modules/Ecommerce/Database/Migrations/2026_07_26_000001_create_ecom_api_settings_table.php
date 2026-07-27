<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('ecom_api_settings')) {
            Schema::create('ecom_api_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id');
                $table->unsignedInteger('location_id');
                $table->string('api_token', 128)->unique();
                $table->string('shop_domain')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['business_id', 'location_id']);
                $table->index(['api_token', 'is_active']);
            });

            return;
        }

        Schema::table('ecom_api_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ecom_api_settings', 'business_id')) {
                $table->unsignedInteger('business_id')->after('id');
            }
            if (! Schema::hasColumn('ecom_api_settings', 'location_id')) {
                $table->unsignedInteger('location_id')->after('business_id');
            }
            if (! Schema::hasColumn('ecom_api_settings', 'api_token')) {
                $table->string('api_token', 128)->unique()->after('location_id');
            }
            if (! Schema::hasColumn('ecom_api_settings', 'shop_domain')) {
                $table->string('shop_domain')->nullable()->after('api_token');
            }
            if (! Schema::hasColumn('ecom_api_settings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('shop_domain');
            }
            if (! Schema::hasColumn('ecom_api_settings', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('ecom_api_settings');
    }
};
