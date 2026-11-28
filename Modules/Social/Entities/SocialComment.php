<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialComment extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql_feed';
    protected $table = 'social_comments';

    protected $fillable = [
        'post_id',
        'parent_comment_id',
        'actor_type',
        'actor_id',
        'actor_name_snapshot',
        'actor_avatar_snapshot',
        'content',
        'media_url',
        'likes_count',
        'replies_count',
        'status',
    ];
}
