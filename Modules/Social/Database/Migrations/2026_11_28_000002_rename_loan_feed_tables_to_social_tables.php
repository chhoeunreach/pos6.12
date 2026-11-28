<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_feed');

        $renames = [
            'loan_feed_posts' => 'social_posts',
            'loan_feed_media' => 'social_media',
            'loan_feed_reactions' => 'social_reactions',
            'loan_feed_comments' => 'social_comments',
            'loan_feed_bookmarks' => 'social_bookmarks',
            'loan_feed_follows' => 'social_follows',
            'loan_feed_reports' => 'social_reports',
            'loan_feed_user_blocks' => 'social_user_blocks',
        ];

        foreach ($renames as $from => $to) {
            if ($schema->hasTable($from) && !$schema->hasTable($to)) {
                $schema->rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};
