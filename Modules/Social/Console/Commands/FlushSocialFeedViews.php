<?php

namespace Modules\Social\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Flushes Redis-buffered view counters into the dedicated `social` database
 * (`mysql_feed`) and recomputes `hot_score`.
 */
class FlushSocialFeedViews extends Command
{
    protected $signature = 'social:flush-views
        {--recompute-all : Also recompute hot_score for every published post, not only flushed ones}
        {--limit=2000 : Maximum number of posts to process in one run}';

    protected $description = 'Flush buffered social feed view counters into mysql_feed (social DB) and recompute hot_score';

    public function handle(): int
    {
        $limit = max(1, (int)$this->option('limit'));
        $dirtyIds = $this->collectDirtyIds($limit);

        if (empty($dirtyIds) && !$this->option('recompute-all')) {
            $this->info('Social feed: nothing to flush.');
            return self::SUCCESS;
        }

        $flushed = $this->flushViews($dirtyIds);
        $ids = $this->option('recompute-all') ? $this->collectAllPublishedIds($limit) : $dirtyIds;
        $recomputed = $this->recomputeHotScore($ids);

        $this->info("Social feed: flushed {$flushed} buffered view batch(es), recomputed hot_score for {$recomputed} post(s).");

        return self::SUCCESS;
    }

    protected function collectDirtyIds(int $limit): array
    {
        try {
            $members = Redis::smembers('social_feed:views_dirty');
        } catch (\Throwable $e) {
            $this->warn('Redis unavailable, skipping view flush: ' . $e->getMessage());
            return [];
        }

        if (!is_array($members) || empty($members)) {
            return [];
        }

        $ids = [];
        foreach ($members as $member) {
            $id = (int)$member;
            if ($id > 0) {
                $ids[$id] = $id;
            }
            if (count($ids) >= $limit) {
                break;
            }
        }

        return array_values($ids);
    }

    protected function flushViews(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $flushed = 0;

        foreach ($ids as $id) {
            try {
                $pending = (int)Redis::get("social_feed:views:{$id}");
            } catch (\Throwable $e) {
                $this->warn("Redis read failed for post {$id}: " . $e->getMessage());
                return $flushed;
            }

            if ($pending > 0) {
                try {
                    DB::connection('mysql_feed')
                        ->table('social_posts')
                        ->where('id', $id)
                        ->update([
                            'views_count' => DB::raw('views_count + ' . $pending),
                            'updated_at' => now(),
                        ]);
                    $flushed++;
                } catch (\Throwable $e) {
                    $this->warn("Failed writing views for post {$id}: " . $e->getMessage());
                    return $flushed;
                }
            }

            try {
                Redis::del("social_feed:views:{$id}");
                Redis::srem('social_feed:views_dirty', $id);
            } catch (\Throwable $e) {
                $this->warn("Redis cleanup failed for post {$id}: " . $e->getMessage());
            }
        }

        return $flushed;
    }

    protected function recomputeHotScore(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        try {
            $posts = DB::connection('mysql_feed')
                ->table('social_posts')
                ->whereIn('id', $ids)
                ->get(['id', 'likes_count', 'comments_count', 'shares_count', 'views_count', 'is_pinned', 'published_at', 'created_at']);
        } catch (\Throwable $e) {
            $this->warn('mysql_feed unavailable for hot_score recompute: ' . $e->getMessage());
            return 0;
        }

        $now = now()->timestamp;
        $updated = 0;

        foreach ($posts as $post) {
            $published = strtotime((string)($post->published_at ?? $post->created_at)) ?: $now;
            $ageHours = max(1.0, ($now - $published) / 3600);

            $engagement = ((int)$post->likes_count * 3)
                + ((int)$post->comments_count * 4)
                + ((int)$post->shares_count * 5)
                + ((int)$post->views_count * 0.5)
                + (!empty($post->is_pinned) ? 1000.0 : 0.0);

            $hotScore = round($engagement / pow($ageHours + 2, 1.5), 4);

            DB::connection('mysql_feed')
                ->table('social_posts')
                ->where('id', $post->id)
                ->update(['hot_score' => $hotScore]);

            $updated++;
        }

        try {
            $current = (int)Cache::get('social_feed:version', 1);
            Cache::put('social_feed:version', $current + 1, now()->addDays(7));
        } catch (\Throwable $e) {
            // Ignore cache failure.
        }

        return $updated;
    }

    protected function collectAllPublishedIds(int $limit): array
    {
        try {
            return DB::connection('mysql_feed')
                ->table('social_posts')
                ->where('status', 'published')
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int)$id)
                ->all();
        } catch (\Throwable $e) {
            $this->warn('Failed loading published posts: ' . $e->getMessage());
            return [];
        }
    }
}
