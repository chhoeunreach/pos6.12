<?php

namespace Modules\Social\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\LoanManagement\Services\BranchPermissionService;
use Modules\LoanManagement\Services\FileUploadService;
use Modules\LoanManagement\Services\LoanNotificationService;
use Modules\Social\Entities\SocialBookmark;
use Modules\Social\Entities\SocialComment;
use Modules\Social\Entities\SocialFollow;
use Modules\Social\Entities\SocialMedia;
use Modules\Social\Entities\SocialPost;
use Modules\Social\Entities\SocialReaction;
use Modules\Social\Entities\SocialReport;
use Modules\Social\Entities\SocialUserBlock;
use Modules\Social\Jobs\TranscodeFeedVideoJob;

/**
 * Facebook-style public & branch-scoped Social News Feed service backed by dedicated `social` DB (`mysql_feed`).
 */
class SocialFeedService
{
    public function __construct(
        protected BranchPermissionService $branchPerms,
        protected FileUploadService $files,
        protected LoanNotificationService $notifications,
    ) {
    }

    public function listPublicFeed(
        ?int $locationId = null,
        ?string $postType = null,
        ?int $customerId = null,
        ?int $cursor = null,
        int $limit = 15
    ): array {
        $limit = max(1, min($limit, 40));
        $version = (int)Cache::get('social_feed:version', 1);
        $cacheKey = sprintf(
            'social_feed:v%d:loc_%s:type_%s:cur_%s:lim_%d',
            $version,
            $locationId ?: 'all',
            $postType ?: 'all',
            $cursor ?: '0',
            $limit
        );

        $rows = Cache::remember($cacheKey, now()->addSeconds((int)config('social.cache_ttl_seconds', 45)), function () use ($locationId, $postType, $cursor, $limit) {
            $query = SocialPost::query()
                ->with(['media', 'sharedPost'])
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                });

            if ($locationId !== null && $locationId > 0) {
                $query->where(function ($q) use ($locationId) {
                    $q->whereNull('location_id')->orWhere('location_id', $locationId);
                });
            }

            if ($postType !== null && trim($postType) !== '' && $postType !== 'all') {
                $query->where('post_type', trim($postType));
            }

            if ($cursor !== null && $cursor > 0) {
                $query->where('id', '<', $cursor);
            }

            return $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get();
        });

        if ($customerId !== null && $customerId > 0) {
            $blockedCustomerIds = SocialUserBlock::query()
                ->where('blocker_type', 'customer')
                ->where('blocker_id', $customerId)
                ->where('blocked_type', 'customer')
                ->pluck('blocked_id')
                ->all();
            if (!empty($blockedCustomerIds)) {
                $rows = $rows->reject(
                    fn (SocialPost $p) => $p->author_type === 'customer'
                        && in_array((int)$p->author_id, $blockedCustomerIds, true)
                )->values();
            }
        }

        $hasMore = $rows->count() > $limit;
        $items = $hasMore ? $rows->slice(0, $limit)->values() : $rows->values();
        $nextCursor = $hasMore && $items->isNotEmpty() ? (int)$items->last()->id : null;

        $reactionsByPost = [];
        $bookmarkedPostIds = [];
        if ($customerId !== null && $customerId > 0 && $items->isNotEmpty()) {
            $postIds = $items->pluck('id')->all();
            $reactionsByPost = SocialReaction::query()
                ->where('actor_type', 'customer')
                ->where('actor_id', $customerId)
                ->whereIn('post_id', $postIds)
                ->pluck('reaction_type', 'post_id')
                ->all();
            $bookmarkedPostIds = SocialBookmark::query()
                ->where('actor_type', 'customer')
                ->where('actor_id', $customerId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->all();
        }

        $items->transform(function (SocialPost $post) use ($reactionsByPost, $bookmarkedPostIds) {
            $myReaction = $reactionsByPost[$post->id] ?? null;
            $post->setAttribute('my_reaction', $myReaction);
            $post->setAttribute('is_liked', $myReaction !== null);
            $post->setAttribute('is_bookmarked', in_array($post->id, $bookmarkedPostIds, true));
            return $post;
        });

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function showPublicPost(int $postId, ?int $customerId = null): SocialPost
    {
        $post = SocialPost::query()
            ->with(['media', 'sharedPost'])
            ->where('status', 'published')
            ->findOrFail($postId);

        $reaction = $customerId
            ? SocialReaction::query()
                ->where('post_id', $post->id)
                ->where('actor_type', 'customer')
                ->where('actor_id', $customerId)
                ->value('reaction_type')
            : null;
        $isBookmarked = $customerId
            ? SocialBookmark::query()
                ->where('post_id', $post->id)
                ->where('actor_type', 'customer')
                ->where('actor_id', $customerId)
                ->exists()
            : false;

        $post->setAttribute('my_reaction', $reaction);
        $post->setAttribute('is_liked', $reaction !== null);
        $post->setAttribute('is_bookmarked', $isBookmarked);

        return $post;
    }

    public function incrementView(int $postId): array
    {
        try {
            $buffered = (int)Redis::incr("social_feed:views:{$postId}");
            Redis::sadd('social_feed:views_dirty', $postId);
            return ['views_count' => $buffered, 'buffered' => true];
        } catch (\Throwable $e) {
            $post = SocialPost::query()->where('status', 'published')->findOrFail($postId);
            $post->increment('views_count');
            return ['views_count' => (int)$post->views_count, 'buffered' => false];
        }
    }

    public function toggleReaction(int $postId, int $customerId, string $reactionType = 'like'): array
    {
        $allowed = ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'];
        $reactionType = in_array($reactionType, $allowed, true) ? $reactionType : 'like';
        $post = SocialPost::query()->where('status', 'published')->findOrFail($postId);

        return DB::connection('mysql_feed')->transaction(function () use ($post, $customerId, $reactionType) {
            $existing = SocialReaction::query()
                ->where('post_id', $post->id)
                ->where('actor_type', 'customer')
                ->where('actor_id', $customerId)
                ->first();

            if ($existing && $existing->reaction_type === $reactionType) {
                $existing->delete();
                if ($post->likes_count > 0) {
                    $post->decrement('likes_count');
                }
                $myReaction = null;
            } elseif ($existing) {
                $existing->reaction_type = $reactionType;
                $existing->save();
                $myReaction = $reactionType;
            } else {
                SocialReaction::create([
                    'post_id' => $post->id,
                    'actor_type' => 'customer',
                    'actor_id' => $customerId,
                    'reaction_type' => $reactionType,
                ]);
                $post->increment('likes_count');
                $myReaction = $reactionType;
            }

            $post->refresh();
            $this->bumpFeedCacheVersion();

            return [
                'liked' => $myReaction !== null,
                'my_reaction' => $myReaction,
                'likes_count' => (int)$post->likes_count,
            ];
        });
    }

    public function toggleBookmark(int $postId, int $customerId): array
    {
        SocialPost::query()->where('status', 'published')->findOrFail($postId);

        $existing = SocialBookmark::query()
            ->where('post_id', $postId)
            ->where('actor_type', 'customer')
            ->where('actor_id', $customerId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['bookmarked' => false];
        }

        SocialBookmark::create([
            'post_id' => $postId,
            'actor_type' => 'customer',
            'actor_id' => $customerId,
        ]);

        return ['bookmarked' => true];
    }

    public function toggleFollow(int $customerId, string $targetType, int $targetId): array
    {
        $existing = SocialFollow::query()
            ->where('follower_customer_id', $customerId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['following' => false];
        }

        SocialFollow::create([
            'follower_customer_id' => $customerId,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);

        return ['following' => true];
    }

    public function sharePost(int $postId, $customer, ?string $caption = null): SocialPost
    {
        $original = SocialPost::query()->where('status', 'published')->findOrFail($postId);

        return DB::connection('mysql_feed')->transaction(function () use ($original, $customer, $caption) {
            $original->increment('shares_count');

            $shared = SocialPost::create([
                'uuid' => (string)Str::uuid(),
                'business_id' => $original->business_id,
                'location_id' => $original->location_id,
                'location_name' => $original->location_name,
                'author_type' => 'customer',
                'author_id' => (int)($customer->id ?? 0),
                'author_name' => (string)($customer->name ?? $customer->phone ?? 'Customer'),
                'post_type' => 'share',
                'shared_post_id' => $original->id,
                'title' => $original->title,
                'content' => $caption ? trim($caption) : null,
                'visibility' => 'public',
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->bumpFeedCacheVersion();
            return $shared->load('sharedPost');
        });
    }

    public function listComments(int $postId, ?int $parentCommentId = null, int $limit = 30)
    {
        return SocialComment::query()
            ->where('post_id', $postId)
            ->where('status', 'visible')
            ->when(
                $parentCommentId === null,
                fn ($q) => $q->whereNull('parent_comment_id'),
                fn ($q) => $q->where('parent_comment_id', $parentCommentId)
            )
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    public function addComment(int $postId, string $actorType, int $actorId, string $actorName, string $content, ?int $parentCommentId = null): SocialComment
    {
        $post = SocialPost::query()->where('status', 'published')->findOrFail($postId);

        return DB::connection('mysql_feed')->transaction(function () use ($post, $actorType, $actorId, $actorName, $content, $parentCommentId) {
            $comment = SocialComment::create([
                'post_id' => $post->id,
                'parent_comment_id' => $parentCommentId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_name_snapshot' => $actorName,
                'content' => trim($content),
                'status' => 'visible',
            ]);

            $post->increment('comments_count');
            if ($parentCommentId) {
                SocialComment::query()->where('id', $parentCommentId)->increment('replies_count');
            }
            $this->bumpFeedCacheVersion();

            return $comment;
        });
    }

    public function createCustomerPost($customer, array $payload, ?UploadedFile $imageFile = null): SocialPost
    {
        $mediaUrls = isset($payload['media_urls']) && is_array($payload['media_urls'])
            ? array_values(array_filter(array_map('strval', $payload['media_urls'])))
            : [];
        $thumbnailUrl = $payload['thumbnail_url'] ?? null;

        if ($imageFile) {
            $upload = $this->files->uploadChatAttachment($imageFile, 'feed_media');
            if (!empty($upload['url'])) {
                array_unshift($mediaUrls, $upload['url']);
                $thumbnailUrl = $thumbnailUrl ?: $upload['url'];
            }
        }

        return DB::connection('mysql_feed')->transaction(function () use ($customer, $payload, $mediaUrls, $thumbnailUrl) {
            $post = SocialPost::create([
                'uuid' => (string)Str::uuid(),
                'location_id' => $payload['location_id'] ?? null,
                'location_name' => $payload['location_name'] ?? null,
                'author_type' => 'customer',
                'author_id' => (int)($customer->id ?? 0),
                'author_name' => (string)($customer->name ?? $customer->phone ?? 'Customer'),
                'post_type' => $payload['post_type'] ?? (empty($mediaUrls) ? 'tips' : 'photo'),
                'title' => (string)$payload['title'],
                'content' => $payload['content'] ?? null,
                'language' => $payload['language'] ?? 'km',
                'country_code' => $payload['country_code'] ?? 'KH',
                'cta_type' => 'none',
                'media_urls' => $mediaUrls,
                'thumbnail_url' => $thumbnailUrl ?: ($mediaUrls[0] ?? null),
                'visibility' => 'public',
                'is_pinned' => false,
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->persistMediaItems($post, $mediaUrls, $thumbnailUrl);
            $this->bumpFeedCacheVersion();

            return $post->load('media');
        });
    }

    public function deleteCustomerPost(int $customerId, int $postId): void
    {
        $post = SocialPost::query()->findOrFail($postId);
        if ($post->author_type !== 'customer' || (int)$post->author_id !== $customerId) {
            abort(403, 'You can only delete your own post.');
        }
        $post->delete();
        $this->bumpFeedCacheVersion();
    }

    public function reportTarget(string $reporterType, int $reporterId, string $targetType, int $targetId, string $reason, ?string $details = null): SocialReport
    {
        return SocialReport::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reporter_type' => $reporterType,
            'reporter_id' => $reporterId,
            'reason' => trim($reason),
            'details' => $details ? trim($details) : null,
            'status' => 'open',
        ]);
    }

    public function blockActor(int $blockerCustomerId, string $blockedType, int $blockedId): void
    {
        SocialUserBlock::query()->firstOrCreate([
            'blocker_type' => 'customer',
            'blocker_id' => $blockerCustomerId,
            'blocked_type' => $blockedType,
            'blocked_id' => $blockedId,
        ]);
    }

    public function generatePresignedUpload(string $extension, string $mimeType): array
    {
        $cleanExt = preg_replace('/[^a-zA-Z0-9]/', '', $extension) ?: 'bin';
        $key = 'feed/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . strtolower($cleanExt);
        $diskName = config('social.media_disk', config('filesystems.default', 'public'));
        $disk = Storage::disk($diskName);

        try {
            if (method_exists($disk, 'temporaryUploadUrl')) {
                $signed = $disk->temporaryUploadUrl($key, now()->addMinutes(10), [
                    'ContentType' => $mimeType,
                ]);
                return [
                    'upload_url' => $signed['url'] ?? null,
                    'headers' => $signed['headers'] ?? ['Content-Type' => $mimeType],
                    'storage_key' => $key,
                    'public_url' => method_exists($disk, 'url') ? $disk->url($key) : $key,
                    'direct_s3' => true,
                ];
            }
        } catch (\Throwable $e) {
            // Fall back to standard Laravel multipart upload.
        }

        return [
            'upload_url' => null,
            'storage_key' => $key,
            'public_url' => null,
            'direct_s3' => false,
        ];
    }

    public function listStaffPosts($staff, ?int $locationId = null)
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.view')
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.create')) {
            abort(403, 'Forbidden');
        }

        $query = SocialPost::query()->with(['media'])->orderByDesc('is_pinned')->orderByDesc('id');
        $businessId = $this->branchPerms->getBusinessId($staff);
        if ($businessId) {
            $query->where(function ($q) use ($businessId) {
                $q->whereNull('business_id')->orWhere('business_id', $businessId);
            });
        }

        $requestedLoc = $this->branchPerms->resolveRequestedLocationId($staff, $locationId);
        if ($requestedLoc !== null) {
            $query->where('location_id', $requestedLoc);
        } else {
            $permitted = $this->branchPerms->getPermittedLocationIds($staff);
            if (is_array($permitted)) {
                if (empty($permitted)) {
                    return collect();
                }
                $query->where(function ($q) use ($permitted) {
                    $q->whereNull('location_id')->orWhereIn('location_id', $permitted);
                });
            }
        }

        return $query->limit(100)->get();
    }

    public function createPost($staff, array $payload, ?UploadedFile $imageFile = null): SocialPost
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.create')) {
            abort(403, 'You do not have permission to create feed posts.');
        }

        $businessId = $this->branchPerms->getBusinessId($staff);
        $locationId = $this->branchPerms->resolveRequestedLocationId($staff, $payload['location_id'] ?? null);
        $mediaUrls = isset($payload['media_urls']) && is_array($payload['media_urls'])
            ? array_values(array_filter(array_map('strval', $payload['media_urls'])))
            : [];
        $thumbnailUrl = $payload['thumbnail_url'] ?? null;

        if ($imageFile) {
            $upload = $this->files->uploadChatAttachment($imageFile, 'feed_media');
            if (!empty($upload['url'])) {
                array_unshift($mediaUrls, $upload['url']);
                $thumbnailUrl = $thumbnailUrl ?: $upload['url'];
            }
        }

        $status = $payload['status'] ?? 'published';
        if ($status === 'published'
            && !$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.publish')) {
            $status = 'draft';
        }

        return DB::connection('mysql_feed')->transaction(function () use ($staff, $payload, $businessId, $locationId, $mediaUrls, $thumbnailUrl, $status) {
            $post = SocialPost::create([
                'uuid' => (string)Str::uuid(),
                'business_id' => $businessId,
                'location_id' => $locationId,
                'location_name' => $payload['location_name'] ?? null,
                'author_type' => 'staff',
                'author_id' => (int)($staff->id ?? 0),
                'author_name' => (string)($staff->name ?? $staff->username ?? 'Shop'),
                'post_type' => $payload['post_type'] ?? 'product_promo',
                'shared_post_id' => $payload['shared_post_id'] ?? null,
                'title' => (string)$payload['title'],
                'content' => $payload['content'] ?? null,
                'language' => $payload['language'] ?? 'km',
                'country_code' => $payload['country_code'] ?? 'KH',
                'pos_product_id' => $payload['pos_product_id'] ?? null,
                'price' => $payload['price'] ?? null,
                'currency' => strtoupper((string)($payload['currency'] ?? 'USD')),
                'estimated_monthly_payment' => $payload['estimated_monthly_payment'] ?? null,
                'cta_type' => $payload['cta_type'] ?? 'chat',
                'cta_phone' => $payload['cta_phone'] ?? null,
                'media_urls' => $mediaUrls,
                'thumbnail_url' => $thumbnailUrl ?: ($mediaUrls[0] ?? null),
                'visibility' => $payload['visibility'] ?? 'public',
                'is_pinned' => (bool)($payload['is_pinned'] ?? false),
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ]);

            $this->persistMediaItems($post, $mediaUrls, $thumbnailUrl);
            $this->bumpFeedCacheVersion();

            if (!empty($payload['send_push']) && $status === 'published') {
                $this->broadcastNewPostToFollowers($post);
            }

            return $post->load('media');
        });
    }

    public function deletePost($staff, int $postId): void
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.delete')) {
            abort(403, 'You do not have permission to delete feed posts.');
        }

        $post = SocialPost::query()->findOrFail($postId);
        if (!$this->branchPerms->canAccessLocation($staff, $post->location_id)) {
            abort(403, 'Branch access denied.');
        }

        $post->delete();
        $this->bumpFeedCacheVersion();
    }

    public function listReports($staff)
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.delete')) {
            abort(403, 'Forbidden');
        }

        return SocialReport::query()
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function resolveReport($staff, int $reportId, string $action): SocialReport
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.feed.delete')) {
            abort(403, 'Forbidden');
        }

        $report = SocialReport::query()->findOrFail($reportId);
        if ($action === 'hide_target') {
            if ($report->target_type === 'post') {
                SocialPost::query()->where('id', $report->target_id)->update(['status' => 'flagged']);
            } elseif ($report->target_type === 'comment') {
                SocialComment::query()->where('id', $report->target_id)->update(['status' => 'hidden']);
            }
            $this->bumpFeedCacheVersion();
            $report->status = 'reviewed';
        } else {
            $report->status = 'dismissed';
        }
        $report->save();

        return $report;
    }

    protected function persistMediaItems(SocialPost $post, array $mediaUrls, ?string $thumbnailUrl): void
    {
        foreach ($mediaUrls as $idx => $url) {
            $isVideo = $this->isVideoUrl($url);

            $media = SocialMedia::create([
                'post_id' => $post->id,
                'media_type' => $isVideo ? 'video' : 'image',
                'storage_provider' => config('social.media_disk', config('filesystems.default', 's3')),
                'storage_key' => $url,
                'thumbnail_key' => $idx === 0 ? ($thumbnailUrl ?: $url) : $url,
                'sort_order' => $idx,
            ]);

            if ($isVideo) {
                try {
                    $pending = TranscodeFeedVideoJob::dispatch((int)$media->id, (string)$url);
                    if (config('queue.default') === 'sync') {
                        $pending->afterResponse();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Social video transcode dispatch failed: ' . $e->getMessage());
                }
            }
        }
    }

    protected function isVideoUrl(string $url): bool
    {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH) ?: $url);

        foreach (['.mp4', '.mov', '.m4v', '.webm', '.mkv', '.avi', '.3gp'] as $ext) {
            if (str_contains($path, $ext)) {
                return true;
            }
        }

        return false;
    }

    protected function broadcastNewPostToFollowers(SocialPost $post): void
    {
        $customerIds = SocialFollow::query()
            ->when(
                $post->location_id,
                fn ($q) => $q->where('target_type', 'branch')->where('target_id', $post->location_id),
                fn ($q) => $q->where('target_type', 'business')
            )
            ->pluck('follower_customer_id')
            ->unique()
            ->take(200)
            ->all();

        foreach ($customerIds as $customerId) {
            $this->notifications->notifyUser(
                'customer',
                (int)$customerId,
                'system',
                $post->title,
                Str::limit((string)($post->content ?? 'New promotion posted'), 120),
                ['post_id' => $post->id],
                $post->business_id,
                $post->location_id
            );
        }
    }

    protected function bumpFeedCacheVersion(): void
    {
        try {
            $current = (int)Cache::get('social_feed:version', 1);
            Cache::put('social_feed:version', $current + 1, now()->addDays(7));
        } catch (\Throwable $e) {
            // Ignore cache failure.
        }
    }
}
