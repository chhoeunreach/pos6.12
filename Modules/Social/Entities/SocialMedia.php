<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_media';

    protected $fillable = [
        'post_id',
        'media_type',
        'storage_provider',
        'storage_key',
        'thumbnail_key',
        'hls_manifest_key',
        'width',
        'height',
        'duration_seconds',
        'sort_order',
    ];
}
