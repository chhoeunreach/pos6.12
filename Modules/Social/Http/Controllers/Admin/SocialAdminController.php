<?php

namespace Modules\Social\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Social\Entities\SocialComment;
use Modules\Social\Entities\SocialPost;
use Modules\Social\Entities\SocialReaction;
use Modules\Social\Entities\SocialReport;
use Modules\Social\Services\SocialFeedService;
use Modules\Social\Services\SocialSettingsService;

class SocialAdminController extends Controller
{
    public function __construct(
        protected SocialFeedService $feed,
    ) {
    }

    protected function authorizePanel(string $permission): void
    {
        $user = auth()->user();
        $allowed = $user && (
            $user->can($permission)
            || $user->can('superadmin')
            || $user->can('business_settings.access')
        );
        abort_unless($allowed, 403, 'Unauthorized action.');
    }

    public function postsIndex(Request $request)
    {
        $this->authorizePanel('social.view');

        $settings = SocialSettingsService::get();
        $posts = SocialPost::query()
            ->with('media')
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $stats = [
            'total_posts' => SocialPost::query()->count(),
            'published_posts' => SocialPost::query()->where('status', 'published')->count(),
            'total_reactions' => SocialReaction::query()->count(),
            'total_comments' => SocialComment::query()->count(),
            'open_reports' => SocialReport::query()->where('status', 'open')->count(),
        ];

        return view('social::admin.posts', compact('settings', 'posts', 'stats'));
    }

    public function storePost(Request $request)
    {
        $this->authorizePanel('social.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'post_type' => ['nullable', 'in:product_promo,photo,album,video,announcement,tips'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'estimated_monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'cta_type' => ['nullable', 'in:chat,call,telegram,none'],
            'cta_phone' => ['nullable', 'string', 'max:40'],
            'thumbnail_url' => ['nullable', 'string', 'max:512'],
            'status' => ['nullable', 'in:published,draft,archived'],
            'is_pinned' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        if (!empty($validated['thumbnail_url'])) {
            $validated['media_urls'] = [$validated['thumbnail_url']];
        }

        $this->feed->createPost($request->user(), $validated, $request->file('file'));

        return redirect()
            ->route('social.admin.posts.index')
            ->with('status', ['success' => true, 'msg' => 'Social post created and published.']);
    }

    public function seedSamplePosts(Request $request)
    {
        $this->authorizePanel('social.create');

        $samples = [
            [
                'post_type' => 'product_promo',
                'title' => 'iPhone 16 Pro Max 256GB — បង់រំលស់ 0% មុន',
                'content' => "ទទួលបានទូរស័ព្ទថ្មីភ្លាមៗ ឯកសារងាយស្រួល ត្រឹមអត្តសញ្ញាណប័ណ្ណ។\n• អាចបង់ប្រចាំខែចាប់ពី \$58/ខែ\n• ធានាផ្លូវការ 1 ឆ្នាំពេញ",
                'price' => 1199,
                'currency' => 'USD',
                'estimated_monthly_payment' => 58,
                'cta_type' => 'chat',
                'is_pinned' => true,
            ],
            [
                'post_type' => 'product_promo',
                'title' => 'Honda PCX 160 ABS 2026 — ដកម៉ូតូជិះភ្លាម មិនបាច់ទ្រព្យធានា',
                'content' => 'អត្រាការប្រាក់ពិសេសសម្រាប់អតិថិជនថ្មី និងអតិថិជនចាស់។ ចុចជជែកជាមួយភ្នាក់ងារ ឬចូលតាម Telegram ឥឡូវនេះ!',
                'price' => 3850,
                'currency' => 'USD',
                'estimated_monthly_payment' => 145,
                'cta_type' => 'telegram',
                'is_pinned' => false,
            ],
            [
                'post_type' => 'announcement',
                'title' => 'សូមស្វាគមន៍មកកាន់ KNEAYERNG Social Feed (Sample Mode)',
                'content' => 'នេះជាមុខងារ Sample News Feed ដំបូងដែលគ្រប់គ្រង API ផ្ទាល់ពី POS Admin Social Panel មុនពេលពង្រីកទៅជា Platform សង្គមពេញលេញ។',
                'cta_type' => 'chat',
                'is_pinned' => false,
            ],
        ];

        $user = $request->user();
        $created = 0;

        foreach ($samples as $sample) {
            $exists = SocialPost::query()->where('title', $sample['title'])->exists();
            if ($exists) {
                continue;
            }

            SocialPost::create([
                'uuid' => (string)Str::uuid(),
                'business_id' => $user->business_id ?? null,
                'author_type' => 'staff',
                'author_id' => (int)($user->id ?? 0),
                'author_name' => (string)($user->first_name ?? $user->username ?? 'KNEAYERNG Official'),
                'post_type' => $sample['post_type'],
                'title' => $sample['title'],
                'content' => $sample['content'],
                'language' => 'km',
                'country_code' => 'KH',
                'price' => $sample['price'] ?? null,
                'currency' => $sample['currency'] ?? 'USD',
                'estimated_monthly_payment' => $sample['estimated_monthly_payment'] ?? null,
                'cta_type' => $sample['cta_type'],
                'visibility' => 'public',
                'is_pinned' => (bool)$sample['is_pinned'],
                'status' => 'published',
                'published_at' => now(),
            ]);
            $created++;
        }

        $this->bumpCache();

        return redirect()
            ->route('social.admin.posts.index')
            ->with('status', [
                'success' => true,
                'msg' => $created > 0
                    ? "Seeded {$created} sample post(s) into social DB."
                    : 'Sample posts already exist.',
            ]);
    }

    public function togglePin(int $post)
    {
        $this->authorizePanel('social.create');
        $row = SocialPost::query()->findOrFail($post);
        $row->is_pinned = !$row->is_pinned;
        $row->save();
        $this->bumpCache();

        return redirect()
            ->route('social.admin.posts.index')
            ->with('status', ['success' => true, 'msg' => $row->is_pinned ? 'Post pinned.' : 'Post unpinned.']);
    }

    public function toggleStatus(int $post)
    {
        $this->authorizePanel('social.create');
        $row = SocialPost::query()->findOrFail($post);
        $row->status = $row->status === 'published' ? 'draft' : 'published';
        if ($row->status === 'published' && !$row->published_at) {
            $row->published_at = now();
        }
        $row->save();
        $this->bumpCache();

        return redirect()
            ->route('social.admin.posts.index')
            ->with('status', ['success' => true, 'msg' => "Post status updated to {$row->status}."]);
    }

    public function destroyPost(int $post)
    {
        $this->authorizePanel('social.create');
        SocialPost::query()->findOrFail($post)->delete();
        $this->bumpCache();

        return redirect()
            ->route('social.admin.posts.index')
            ->with('status', ['success' => true, 'msg' => 'Post removed.']);
    }

    public function reportsIndex()
    {
        $this->authorizePanel('social.moderate');

        $reports = SocialReport::query()
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('social::admin.reports', compact('reports'));
    }

    public function resolveReport(Request $request, int $report)
    {
        $this->authorizePanel('social.moderate');

        $action = $request->input('action') === 'hide_target' ? 'hide_target' : 'dismiss';
        $this->feed->resolveReport($request->user(), $report, $action);

        return redirect()
            ->route('social.admin.reports.index')
            ->with('status', ['success' => true, 'msg' => 'Moderation report updated.']);
    }

    public function settingsIndex()
    {
        $this->authorizePanel('social.settings');

        $settings = SocialSettingsService::get();
        $dbStatus = ['connected' => false, 'database' => null, 'posts_count' => 0];
        try {
            $conn = DB::connection('mysql_feed');
            $conn->getPdo();
            $dbStatus = [
                'connected' => true,
                'database' => $conn->getDatabaseName(),
                'posts_count' => SocialPost::query()->count(),
            ];
        } catch (\Throwable $e) {
            $dbStatus['connected'] = false;
        }

        return view('social::admin.settings', compact('settings', 'dbStatus'));
    }

    public function settingsUpdate(Request $request)
    {
        $this->authorizePanel('social.settings');

        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:120'],
            'mode' => ['required', 'in:sample,live,external'],
            'per_page_limit' => ['required', 'integer', 'min:5', 'max:40'],
            'cache_ttl_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'default_language' => ['nullable', 'string', 'max:10'],
            'default_country_code' => ['nullable', 'string', 'max:4'],
            'external_api_base_url' => ['nullable', 'url', 'max:512'],
            'external_api_key' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        SocialSettingsService::save(array_merge($validated, [
            'api_enabled' => $request->boolean('api_enabled'),
            'public_feed_enabled' => $request->boolean('public_feed_enabled'),
            'customer_posting_enabled' => $request->boolean('customer_posting_enabled'),
            'allow_video_transcode' => $request->boolean('allow_video_transcode'),
        ]));

        return redirect()
            ->route('social.admin.settings.index')
            ->with('status', ['success' => true, 'msg' => 'Social Platform API settings saved.']);
    }

    protected function bumpCache(): void
    {
        try {
            $current = (int)Cache::get('social_feed:version', 1);
            Cache::put('social_feed:version', $current + 1, now()->addDays(7));
        } catch (\Throwable $e) {
            // Ignore cache errors.
        }
    }
}
