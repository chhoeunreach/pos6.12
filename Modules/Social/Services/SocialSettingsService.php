<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Social\Entities\SocialSetting;

/**
 * Resolves and persists Social Platform API settings controlled by the Admin Social Panel.
 */
class SocialSettingsService
{
    public const CACHE_KEY = 'social_settings:singleton';

    public static function defaults(): array
    {
        return [
            'platform_name' => 'KNEAYERNG Social',
            'mode' => 'sample',
            'api_enabled' => true,
            'public_feed_enabled' => true,
            'customer_posting_enabled' => true,
            'allow_video_transcode' => true,
            'per_page_limit' => 15,
            'cache_ttl_seconds' => (int) config('social.cache_ttl_seconds', 45),
            'default_language' => 'km',
            'default_country_code' => 'KH',
            'external_api_base_url' => null,
            'external_api_key' => null,
            'notes' => null,
        ];
    }

    public static function get(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, now()->addSeconds(60), function () {
                if (!Schema::connection('mysql_feed')->hasTable('social_settings')) {
                    return self::defaults();
                }

                $row = SocialSetting::query()->find(1);
                if (!$row) {
                    return self::defaults();
                }

                return array_merge(self::defaults(), [
                    'platform_name' => (string)($row->platform_name ?: 'KNEAYERNG Social'),
                    'mode' => (string)($row->mode ?: 'sample'),
                    'api_enabled' => (bool)$row->api_enabled,
                    'public_feed_enabled' => (bool)$row->public_feed_enabled,
                    'customer_posting_enabled' => (bool)$row->customer_posting_enabled,
                    'allow_video_transcode' => (bool)$row->allow_video_transcode,
                    'per_page_limit' => max(5, min((int)$row->per_page_limit, 40)),
                    'cache_ttl_seconds' => max(0, min((int)$row->cache_ttl_seconds, 3600)),
                    'default_language' => (string)($row->default_language ?: 'km'),
                    'default_country_code' => (string)($row->default_country_code ?: 'KH'),
                    'external_api_base_url' => $row->external_api_base_url ? (string)$row->external_api_base_url : null,
                    'external_api_key' => $row->external_api_key ? (string)$row->external_api_key : null,
                    'notes' => $row->notes ? (string)$row->notes : null,
                ]);
            });
        } catch (\Throwable $e) {
            return self::defaults();
        }
    }

    public static function save(array $input): SocialSetting
    {
        $payload = [
            'platform_name' => trim((string)($input['platform_name'] ?? 'KNEAYERNG Social')) ?: 'KNEAYERNG Social',
            'mode' => in_array($input['mode'] ?? 'sample', ['sample', 'live', 'external'], true)
                ? (string)$input['mode']
                : 'sample',
            'api_enabled' => !empty($input['api_enabled']),
            'public_feed_enabled' => !empty($input['public_feed_enabled']),
            'customer_posting_enabled' => !empty($input['customer_posting_enabled']),
            'allow_video_transcode' => !empty($input['allow_video_transcode']),
            'per_page_limit' => max(5, min((int)($input['per_page_limit'] ?? 15), 40)),
            'cache_ttl_seconds' => max(0, min((int)($input['cache_ttl_seconds'] ?? 45), 3600)),
            'default_language' => trim((string)($input['default_language'] ?? 'km')) ?: 'km',
            'default_country_code' => strtoupper(substr(trim((string)($input['default_country_code'] ?? 'KH')) ?: 'KH', 0, 4)),
            'external_api_base_url' => isset($input['external_api_base_url']) && trim((string)$input['external_api_base_url']) !== ''
                ? rtrim(trim((string)$input['external_api_base_url']), '/')
                : null,
            'notes' => isset($input['notes']) && trim((string)$input['notes']) !== ''
                ? trim((string)$input['notes'])
                : null,
        ];

        $existing = SocialSetting::query()->find(1);
        $rawKey = isset($input['external_api_key']) ? trim((string)$input['external_api_key']) : '';
        if ($rawKey !== '') {
            $payload['external_api_key'] = $rawKey;
        } elseif ($existing) {
            $payload['external_api_key'] = $existing->external_api_key;
        }

        $setting = SocialSetting::query()->updateOrCreate(['id' => 1], $payload);
        Cache::forget(self::CACHE_KEY);

        return $setting;
    }
}
