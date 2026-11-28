<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialBookmark extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_bookmarks';

    protected $fillable = ['post_id', 'actor_type', 'actor_id'];
}
