<?php

namespace Modules\Social\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Social\Services\SocialActorResolver;
use Modules\Social\Services\SocialFeedService;
use Modules\Social\Services\SocialSettingsService;

class SocialFeedController extends Controller
{
    public function __construct(
        protected SocialFeedService $feed,
        protected ?SocialActorResolver $actors = null,
    ) {
        $this->actors = $actors ?? new SocialActorResolver();
        $this->middleware(function ($request, $next) {
            $settings = SocialSettingsService::get();
            if (empty($settings['api_enabled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Social Platform API is currently disabled by administrator.',
                ], 503);
            }
            return $next($request);
        })->except(['health']);
    }

    public function health()
    {
        $settings = SocialSettingsService::get();
        $connected = false;
        $tablesReady = false;
        $database = null;
        try {
            $conn = DB::connection('mysql_feed');
            $conn->getPdo();
            $database = $conn->getDatabaseName();
            $connected = true;
            $schema = $conn->getSchemaBuilder();
            $tablesReady = collect([
                'social_posts',
                'social_media',
                'social_reactions',
                'social_comments',
                'social_bookmarks',
                'social_follows',
                'social_reports',
                'social_user_blocks',
                'social_settings',
            ])->every(fn (string $table) => $schema->hasTable($table));
        } catch (\Throwable $e) {
            $connected = false;
        }

        $ready = $connected && $tablesReady;
        $message = 'Social database unavailable';
        if ($connected) {
            $message = $tablesReady ? 'Social module database ready' : 'Social database migrations required';
        }

        return response()->json([
            'success' => $ready && !empty($settings['api_enabled']),
            'message' => $message,
            'data' => [
                'module' => 'Social',
                'platform_name' => $settings['platform_name'],
                'mode' => $settings['mode'],
                'api_enabled' => (bool)$settings['api_enabled'],
                'public_feed_enabled' => (bool)$settings['public_feed_enabled'],
                'customer_posting_enabled' => (bool)$settings['customer_posting_enabled'],
                'connection' => 'mysql_feed',
                'database' => $database,
                'connected' => $connected,
                'tables_ready' => $tablesReady,
            ],
        ], $ready ? 200 : 503);
    }

    protected function resolveActor(Request $request, ?string $preferredType = null): ?array
    {
        return ($this->actors ?? new SocialActorResolver())->resolve($request, $preferredType);
    }

    protected function requireActor(Request $request, ?string $preferredType = null): array
    {
        return ($this->actors ?? new SocialActorResolver())->requireActor($request, $preferredType);
    }

    protected function resolveCustomerId(Request $request): ?int
    {
        $actor = $this->resolveActor($request);
        return $actor ? (int)$actor['id'] : null;
    }

    public function publicIndex(Request $request)
    {
        $settings = SocialSettingsService::get();
        if (empty($settings['public_feed_enabled'])) {
            abort(403, 'Public Social Feed is disabled in Admin Social Panel.');
        }

        $locationId = $request->query('location_id') ? (int)$request->query('location_id') : null;
        $postType = $request->query('post_type') ? (string)$request->query('post_type') : null;
        $cursor = $request->query('cursor') ? (int)$request->query('cursor') : null;
        $defaultLimit = (int)($settings['per_page_limit'] ?? 15);
        $limit = (int)$request->query('limit', $request->query('per_page', $defaultLimit));
        $actor = $this->resolveActor($request);

        $result = $this->feed->listPublicFeed(
            $locationId,
            $postType,
            $actor ? (int)$actor['id'] : null,
            $cursor,
            $limit,
            $actor['type'] ?? 'customer'
        );

        return response()->json([
            'success' => true,
            'message' => 'Feed loaded',
            'data' => $result['items'],
            'meta' => [
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
                'mode' => $settings['mode'],
                'platform_name' => $settings['platform_name'],
            ],
        ]);
    }

    public function publicShow(Request $request, $post)
    {
        $settings = SocialSettingsService::get();
        if (empty($settings['public_feed_enabled'])) {
            abort(403, 'Public Social Feed is disabled in Admin Social Panel.');
        }

        $actor = $this->resolveActor($request);
        $item = $this->feed->showPublicPost(
            (int)$post,
            $actor ? (int)$actor['id'] : null,
            $actor['type'] ?? 'customer'
        );

        return response()->json([
            'success' => true,
            'message' => 'Post loaded',
            'data' => $item,
        ]);
    }

    public function publicComments(Request $request, $post)
    {
        $parentId = $request->query('parent_id') ? (int)$request->query('parent_id') : null;
        $limit = (int)$request->query('limit', 30);

        return response()->json([
            'success' => true,
            'message' => 'Comments loaded',
            'data' => $this->feed->listComments((int)$post, $parentId, $limit),
        ]);
    }

    public function recordView($post)
    {
        return response()->json([
            'success' => true,
            'message' => 'View recorded',
            'data' => $this->feed->incrementView((int)$post),
        ]);
    }

    public function customerStore(Request $request)
    {
        return $this->actorStore($request, SocialActorResolver::ACTOR_CUSTOMER);
    }

    public function actorStore(Request $request, ?string $preferredType = null)
    {
        $actor = $this->requireActor($request, $preferredType);
        $settings = SocialSettingsService::get();
        if ($actor['type'] === SocialActorResolver::ACTOR_CUSTOMER && empty($settings['customer_posting_enabled'])) {
            abort(403, 'Customer posting is disabled in Admin Social Panel.');
        }

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'location_name' => ['nullable', 'string', 'max:120'],
            'post_type' => ['nullable', 'in:product_promo,photo,album,video,announcement,tips'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:10'],
            'thumbnail_url' => ['nullable', 'string', 'max:512'],
            'media_urls' => ['nullable', 'array'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        $created = $this->feed->createActorPost(
            $actor['user'],
            $actor['type'],
            $validated,
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Post published',
            'data' => $created,
        ]);
    }

    public function customerDestroy(Request $request, $post)
    {
        return $this->actorDestroy($request, $post, SocialActorResolver::ACTOR_CUSTOMER);
    }

    public function actorDestroy(Request $request, $post, ?string $preferredType = null)
    {
        $actor = $this->requireActor($request, $preferredType);
        $this->feed->deleteActorPost($actor['type'], (int)$actor['id'], (int)$post, $actor['user']);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted',
            'data' => (object)[],
        ]);
    }

    public function toggleLike(Request $request, $post)
    {
        $actor = $this->requireActor($request);
        $reactionType = (string)$request->input('reaction_type', 'like');
        $result = $this->feed->toggleReaction((int)$post, (int)$actor['id'], $reactionType, $actor['type']);

        return response()->json([
            'success' => true,
            'message' => 'Updated',
            'data' => $result,
        ]);
    }

    public function toggleBookmark(Request $request, $post)
    {
        $actor = $this->requireActor($request);
        return response()->json([
            'success' => true,
            'message' => 'Updated',
            'data' => $this->feed->toggleBookmark((int)$post, (int)$actor['id'], $actor['type']),
        ]);
    }

    public function toggleFollow(Request $request)
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:branch,business,author'],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);
        $actor = $this->requireActor($request);

        return response()->json([
            'success' => true,
            'message' => 'Updated',
            'data' => $this->feed->toggleFollow((int)$actor['id'], $validated['target_type'], (int)$validated['target_id']),
        ]);
    }

    public function sharePost(Request $request, $post)
    {
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
        ]);
        $actor = $this->requireActor($request);
        $shared = $this->feed->sharePost((int)$post, $actor['user'], $validated['caption'] ?? null, $actor['type']);

        return response()->json([
            'success' => true,
            'message' => 'Post shared',
            'data' => $shared,
        ]);
    }

    public function storeComment(Request $request, $post)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'parent_comment_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $actor = $this->requireActor($request);
        $comment = $this->feed->addComment(
            (int)$post,
            $actor['type'],
            (int)$actor['id'],
            $actor['name'],
            $validated['content'],
            $validated['parent_comment_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Comment posted',
            'data' => $comment,
        ]);
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:post,comment'],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:64'],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $this->requireActor($request);
        $report = $this->feed->reportTarget(
            $actor['type'],
            (int)$actor['id'],
            $validated['target_type'],
            (int)$validated['target_id'],
            $validated['reason'],
            $validated['details'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Report submitted for moderation',
            'data' => $report,
        ]);
    }

    public function blockUser(Request $request)
    {
        $validated = $request->validate([
            'blocked_type' => ['nullable', 'in:customer,staff'],
            'blocked_id' => ['required', 'integer', 'min:1'],
        ]);

        $actor = $this->requireActor($request);
        $this->feed->blockActor(
            (int)$actor['id'],
            $validated['blocked_type'] ?? 'customer',
            (int)$validated['blocked_id'],
            $actor['type']
        );

        return response()->json([
            'success' => true,
            'message' => 'User blocked',
            'data' => (object)[],
        ]);
    }

    public function presignUpload(Request $request)
    {
        $validated = $request->validate([
            'extension' => ['required', 'string', 'max:12'],
            'mime_type' => ['required', 'string', 'max:80'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Presigned upload generated',
            'data' => $this->feed->generatePresignedUpload($validated['extension'], $validated['mime_type']),
        ]);
    }

    public function staffIndex(Request $request)
    {
        $locationId = $request->query('location_id') ? (int)$request->query('location_id') : null;
        $items = $this->feed->listStaffPosts($request->user(), $locationId);

        return response()->json([
            'success' => true,
            'message' => 'Feed posts loaded',
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'location_name' => ['nullable', 'string', 'max:120'],
            'post_type' => ['nullable', 'in:product_promo,photo,album,video,announcement,tips,share'],
            'shared_post_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:10'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'pos_product_id' => ['nullable', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'estimated_monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'cta_type' => ['nullable', 'in:chat,call,telegram,none'],
            'cta_phone' => ['nullable', 'string', 'max:40'],
            'thumbnail_url' => ['nullable', 'string', 'max:512'],
            'media_urls' => ['nullable', 'array'],
            'visibility' => ['nullable', 'in:public,followers,branch'],
            'is_pinned' => ['nullable', 'boolean'],
            'send_push' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        $created = $this->feed->createPost(
            $request->user(),
            $validated,
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Post published',
            'data' => $created,
        ]);
    }

    public function destroy(Request $request, $post)
    {
        $this->feed->deletePost($request->user(), (int)$post);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted',
            'data' => (object)[],
        ]);
    }

    public function reports(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Reports loaded',
            'data' => $this->feed->listReports($request->user()),
        ]);
    }

    public function resolveReport(Request $request, $report)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:hide_target,dismiss'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report resolved',
            'data' => $this->feed->resolveReport($request->user(), (int)$report, $validated['action']),
        ]);
    }
}
