<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notification_templates') && !Schema::hasColumn('notification_templates', 'business_id')) {
            Schema::rename('notification_templates', 'notification_center_templates');
        }
    }

    public function down()
    {
        if (Schema::hasTable('notification_center_templates')) {
            Schema::rename('notification_center_templates', 'notification_templates');
        }
    }
};
