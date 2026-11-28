<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialSetting extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_settings';

    protected $fillable = [
        'platform_name',
        'mode',
        'api_enabled',
        'public_feed_enabled',
        'customer_posting_enabled',
        'allow_video_transcode',
        'per_page_limit',
        'cache_ttl_seconds',
        'default_language',
        'default_country_code',
        'external_api_base_url',
        'external_api_key',
        'notes',
    ];

    protected $casts = [
        'api_enabled' => 'boolean',
        'public_feed_enabled' => 'boolean',
        'customer_posting_enabled' => 'boolean',
        'allow_video_transcode' => 'boolean',
        'per_page_limit' => 'integer',
        'cache_ttl_seconds' => 'integer',
    ];
}
