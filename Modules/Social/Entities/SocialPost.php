<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPost extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql_feed';
    protected $table = 'social_posts';

    protected $fillable = [
        'uuid',
        'business_id',
        'location_id',
        'location_name',
        'author_type',
        'author_id',
        'author_name',
        'author_avatar_url',
        'post_type',
        'shared_post_id',
        'title',
        'content',
        'language',
        'country_code',
        'pos_product_id',
        'price',
        'currency',
        'estimated_monthly_payment',
        'cta_type',
        'cta_phone',
        'media_urls',
        'thumbnail_url',
        'visibility',
        'is_pinned',
        'status',
        'likes_count',
        'comments_count',
        'shares_count',
        'views_count',
        'hot_score',
        'published_at',
    ];

    protected $casts = [
        'price' => 'float',
        'estimated_monthly_payment' => 'float',
        'media_urls' => 'array',
        'is_pinned' => 'boolean',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'shares_count' => 'integer',
        'views_count' => 'integer',
        'hot_score' => 'float',
        'published_at' => 'datetime',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(SocialMedia::class, 'post_id')->orderBy('sort_order');
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_post_id');
    }
}
