<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialReaction extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_reactions';

    protected $fillable = [
        'post_id',
        'actor_type',
        'actor_id',
        'reaction_type',
    ];
}
