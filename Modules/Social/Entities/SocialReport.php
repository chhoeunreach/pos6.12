<?php

namespace Modules\Social\Entities;

use Illuminate\Database\Eloquent\Model;

class SocialReport extends Model
{
    protected $connection = 'mysql_feed';
    protected $table = 'social_reports';

    protected $fillable = [
        'target_type',
        'target_id',
        'reporter_type',
        'reporter_id',
        'reason',
        'details',
        'status',
    ];
}
