<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_feed');

        if (!$schema->hasTable('social_posts')) {
            $schema->create('social_posts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->string('location_name', 120)->nullable();
                $table->string('author_type', 24)->default('staff')->index(); // shop | staff | customer | user
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->string('author_name', 120)->nullable();
                $table->string('author_avatar_url', 512)->nullable();
                $table->string('post_type', 32)->default('product_promo')->index();
                $table->unsignedBigInteger('shared_post_id')->nullable()->index();
                $table->string('title', 255);
                $table->text('content')->nullable();
                $table->string('language', 10)->default('km')->index();
                $table->string('country_code', 4)->default('KH')->index();
                $table->unsignedBigInteger('pos_product_id')->nullable()->index();
                $table->decimal('price', 16, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->decimal('estimated_monthly_payment', 16, 2)->nullable();
                $table->string('cta_type', 24)->default('chat');
                $table->string('cta_phone', 40)->nullable();
                $table->json('media_urls')->nullable();
                $table->string('thumbnail_url', 512)->nullable();
                $table->string('visibility', 24)->default('public')->index();
                $table->boolean('is_pinned')->default(false)->index();
                $table->string('status', 24)->default('published')->index();
                $table->unsignedInteger('likes_count')->default(0);
                $table->unsignedInteger('comments_count')->default(0);
                $table->unsignedInteger('shares_count')->default(0);
                $table->unsignedInteger('views_count')->default(0);
                $table->double('hot_score')->default(0)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'visibility', 'is_pinned', 'id'], 'idx_social_public_cursor');
            });
        }

        if (!$schema->hasTable('social_media')) {
            $schema->create('social_media', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('post_id')->index();
                $table->string('media_type', 16)->default('image');
                $table->string('storage_provider', 24)->default('s3');
                $table->string('storage_key', 512);
                $table->string('thumbnail_key', 512)->nullable();
                $table->string('hls_manifest_key', 512)->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('social_reactions')) {
            $schema->create('social_reactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('post_id')->index();
                $table->string('actor_type', 24)->default('customer');
                $table->unsignedBigInteger('actor_id')->index();
                $table->string('reaction_type', 16)->default('like');
                $table->timestamps();

                $table->unique(['post_id', 'actor_type', 'actor_id'], 'uniq_social_reaction');
            });
        }

        if (!$schema->hasTable('social_comments')) {
            $schema->create('social_comments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('post_id')->index();
                $table->unsignedBigInteger('parent_comment_id')->nullable()->index();
                $table->string('actor_type', 24)->default('customer');
                $table->unsignedBigInteger('actor_id')->index();
                $table->string('actor_name_snapshot', 120);
                $table->string('actor_avatar_snapshot', 512)->nullable();
                $table->text('content');
                $table->string('media_url', 512)->nullable();
                $table->unsignedInteger('likes_count')->default(0);
                $table->unsignedInteger('replies_count')->default(0);
                $table->string('status', 24)->default('visible')->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!$schema->hasTable('social_bookmarks')) {
            $schema->create('social_bookmarks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('post_id')->index();
                $table->string('actor_type', 24)->default('customer');
                $table->unsignedBigInteger('actor_id')->index();
                $table->timestamps();

                $table->unique(['post_id', 'actor_type', 'actor_id'], 'uniq_social_bookmark');
            });
        }

        if (!$schema->hasTable('social_follows')) {
            $schema->create('social_follows', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('follower_customer_id')->index();
                $table->string('target_type', 24)->default('branch');
                $table->unsignedBigInteger('target_id')->index();
                $table->timestamps();

                $table->unique(['follower_customer_id', 'target_type', 'target_id'], 'uniq_social_follow');
            });
        }

        if (!$schema->hasTable('social_reports')) {
            $schema->create('social_reports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('target_type', 24)->index();
                $table->unsignedBigInteger('target_id')->index();
                $table->string('reporter_type', 24)->default('customer');
                $table->unsignedBigInteger('reporter_id')->nullable()->index();
                $table->string('reason', 64);
                $table->text('details')->nullable();
                $table->string('status', 24)->default('open')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('social_user_blocks')) {
            $schema->create('social_user_blocks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('blocker_type', 24)->default('customer');
                $table->unsignedBigInteger('blocker_id')->index();
                $table->string('blocked_type', 24)->default('customer');
                $table->unsignedBigInteger('blocked_id')->index();
                $table->timestamps();

                $table->unique(['blocker_type', 'blocker_id', 'blocked_type', 'blocked_id'], 'uniq_social_block');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down for installed social database.
    }
};
