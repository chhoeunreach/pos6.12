<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialUserBlock extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_user_blocks';

    protected $fillable = ['blocker_type', 'blocker_id', 'blocked_type', 'blocked_id'];
}
