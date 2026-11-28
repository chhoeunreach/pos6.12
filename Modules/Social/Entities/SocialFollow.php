<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialFollow extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_follows';

    protected $fillable = ['follower_customer_id', 'target_type', 'target_id'];
}
