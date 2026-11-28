<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_feed');

        if (!$schema->hasTable('social_settings')) {
            $schema->create('social_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('platform_name', 120)->default('KNEAYERNG Social');
                $table->string('mode', 24)->default('sample'); // sample | live | external
                $table->boolean('api_enabled')->default(true);
                $table->boolean('public_feed_enabled')->default(true);
                $table->boolean('customer_posting_enabled')->default(true);
                $table->boolean('allow_video_transcode')->default(true);
                $table->unsignedSmallInteger('per_page_limit')->default(15);
                $table->unsignedInteger('cache_ttl_seconds')->default(45);
                $table->string('default_language', 10)->default('km');
                $table->string('default_country_code', 4)->default('KH');
                $table->string('external_api_base_url', 512)->nullable();
                $table->string('external_api_key', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if ($schema->hasTable('social_settings') && DB::connection('mysql_feed')->table('social_settings')->count() === 0) {
            DB::connection('mysql_feed')->table('social_settings')->insert([
                'id' => 1,
                'platform_name' => 'KNEAYERNG Social',
                'mode' => 'sample',
                'api_enabled' => true,
                'public_feed_enabled' => true,
                'customer_posting_enabled' => true,
                'allow_video_transcode' => true,
                'per_page_limit' => 15,
                'cache_ttl_seconds' => 45,
                'default_language' => 'km',
                'default_country_code' => 'KH',
                'notes' => 'Sample social API mode before connecting to full standalone Social platform.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            if (Schema::hasTable('system')) {
                DB::table('system')->updateOrInsert(
                    ['key' => 'social_version'],
                    ['value' => '1.0.0']
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal if POS system table differs.
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};
