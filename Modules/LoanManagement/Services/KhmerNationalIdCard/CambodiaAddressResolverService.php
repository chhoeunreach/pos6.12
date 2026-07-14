<?php

namespace Modules\LoanManagement\Services\KhmerNationalIdCard;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CambodiaAddressResolverService
{
    private const CACHE_KEY = 'cambodia_standard_addresses:v1';
    private const SYNC_PROGRESS_KEY = 'cambodia_standard_addresses:sync_progress:v1';
    private const TABLE = 'cambodia_standard_addresses';

    public function standardize(array $fields): array
    {
        try {
            $addressRows = $this->addresses();
            $match = $this->bestMatch($addressRows, $fields);

            if (! $match) {
                return $fields;
            }

            $fields['province'] = $match['province_kh'] ?: $match['province_en'];
            $fields['district'] = $match['district_kh'] ?: $match['district_en'];
            $fields['commune'] = $match['commune_kh'] ?: $match['commune_en'];
            $fields['village'] = $match['village_kh'] ?: $match['village_en'];
            $fields['province_code'] = $match['province_code'] ?? null;
            $fields['district_code'] = $match['district_code'] ?? null;
            $fields['commune_code'] = $match['commune_code'] ?? null;
            $fields['village_code'] = $match['village_code'] ?? null;

            return $fields;
        } catch (\Throwable $exception) {
            Log::warning('Cambodia address standardization failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $fields;
        }
    }

    public function provinces(): array
    {
        if ($this->needsSync()) {
            return [];
        }

        return $this->localOptions('province');
    }

    public function districts(string $provinceCode): array
    {
        if ($this->needsSync()) {
            return [];
        }

        return $this->localOptions('district', 'province_code', $provinceCode);
    }

    public function communes(string $districtCode): array
    {
        if ($this->needsSync()) {
            return [];
        }

        return $this->localOptions('commune', 'district_code', $districtCode);
    }

    public function villages(string $communeCode): array
    {
        if ($this->needsSync()) {
            return [];
        }

        return $this->localOptions('village', 'commune_code', $communeCode);
    }

    public function needsSync(): bool
    {
        $this->ensureLocalTable();

        $progress = Cache::get(self::SYNC_PROGRESS_KEY);

        if ($this->hasLocalAddresses() && is_array($progress) && isset($progress['complete'])) {
            return ! (bool) $progress['complete'];
        }

        return ! $this->hasLocalAddresses();
    }

    public function syncBatch(?int $page = null, int $pagesPerBatch = 25): array
    {
        $this->ensureLocalTable();

        $endpoint = config('loanmanagement.cambodia_address.endpoint');
        $pageSize = (int) config('loanmanagement.cambodia_address.page_size', 200);
        $timeout = (int) config('loanmanagement.cambodia_address.timeout', 30);
        $verifySsl = (bool) config('loanmanagement.cambodia_address.verify_ssl', true);
        $progress = Cache::get(self::SYNC_PROGRESS_KEY, []);
        $currentPage = max(1, (int) ($page ?: ($progress['next_page'] ?? 1)));
        $totalPages = max(1, (int) ($progress['total_pages'] ?? 1));
        $syncedRows = 0;

        for ($index = 0; $index < $pagesPerBatch && $currentPage <= $totalPages; $index++) {
            $response = $this->requestAddressPage($endpoint, $timeout, $verifySsl, $currentPage, $pageSize);

            if (! $response->successful()) {
                $status = $response->status();

                Log::warning('Cambodia address dataset returned an error.', [
                    'status' => $status,
                    'page' => $currentPage,
                ]);

                if ($status === 429) {
                    Cache::put(self::SYNC_PROGRESS_KEY, [
                        'next_page' => $currentPage,
                        'total_pages' => $totalPages,
                        'complete' => false,
                    ], now()->addDay());

                    return [
                        'complete' => false,
                        'next_page' => $currentPage,
                        'total_pages' => $totalPages,
                        'synced_rows' => $syncedRows,
                        'cached_rows' => DB::table(self::TABLE)->count(),
                        'retry_after' => (int) config('loanmanagement.cambodia_address.retry_after_seconds', 10),
                        'message' => 'Cambodia address server is busy. Retrying...',
                    ];
                }

                throw new \RuntimeException('Unable to download Cambodia address page '.$currentPage.'. Status: '.$status);
            }

            $payload = $response->json();
            $totalPages = max(1, (int) ($payload['total_pages'] ?? $totalPages));
            $syncedRows += $this->upsertRows(Arr::wrap($payload['items'] ?? []));
            $currentPage++;
        }

        $complete = $currentPage > $totalPages;
        $nextPage = $complete ? null : $currentPage;

        Cache::put(self::SYNC_PROGRESS_KEY, [
            'next_page' => $nextPage,
            'total_pages' => $totalPages,
            'complete' => $complete,
        ], now()->addDay());
        Cache::forget(self::CACHE_KEY);

        return [
            'complete' => $complete,
            'next_page' => $nextPage,
            'total_pages' => $totalPages,
            'synced_rows' => $syncedRows,
            'cached_rows' => DB::table(self::TABLE)->count(),
        ];
    }

    public function syncToDatabase(): int
    {
        $this->ensureLocalTable();

        $rows = $this->fetchAddresses();

        if (! Schema::hasTable(self::TABLE) || empty($rows)) {
            return 0;
        }

        $count = $this->upsertRows($rows);

        Cache::forget(self::CACHE_KEY);
        Cache::put(self::SYNC_PROGRESS_KEY, [
            'next_page' => null,
            'total_pages' => 1,
            'complete' => true,
        ], now()->addDay());

        return $count;
    }

    private function upsertRows(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $now = now();
        $count = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as $row) {
                if (empty($row['village_code'])) {
                    continue;
                }

                DB::table(self::TABLE)->updateOrInsert(
                    ['village_code' => $row['village_code']],
                    [
                        'province_code' => $row['province_code'] ?? '',
                        'province_kh' => $row['province_kh'] ?? null,
                        'province_en' => $row['province_en'] ?? null,
                        'district_code' => $row['district_code'] ?? '',
                        'district_kh' => $row['district_kh'] ?? null,
                        'district_en' => $row['district_en'] ?? null,
                        'commune_code' => $row['commune_code'] ?? '',
                        'commune_kh' => $row['commune_kh'] ?? null,
                        'commune_en' => $row['commune_en'] ?? null,
                        'village_kh' => $row['village_kh'] ?? null,
                        'village_en' => $row['village_en'] ?? null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $count++;
            }
        }

        return $count;
    }

    private function uniqueOptions(array $rows, string $level): array
    {
        $options = [];
        $codeKey = $level.'_code';
        $khKey = $level.'_kh';
        $enKey = $level.'_en';

        foreach ($rows as $row) {
            $code = $row[$codeKey] ?? null;

            if (empty($code) || isset($options[$code])) {
                continue;
            }

            $khmer = $row[$khKey] ?? '';
            $english = $row[$enKey] ?? '';

            $options[$code] = [
                'code' => $code,
                'kh' => $khmer,
                'en' => $english,
                'label' => $this->formatLabel($khmer, $english),
            ];
        }

        return array_values($options);
    }

    private function localOptions(string $level, ?string $parentCodeKey = null, ?string $parentCode = null): array
    {
        $this->ensureLocalTable();

        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $codeKey = $level.'_code';
        $khKey = $level.'_kh';
        $enKey = $level.'_en';

        $query = DB::table(self::TABLE)
            ->select([$codeKey.' as code', $khKey.' as kh', $enKey.' as en'])
            ->whereNotNull($codeKey)
            ->where($codeKey, '!=', '');

        if ($parentCodeKey && $parentCode !== null && $parentCode !== '') {
            $query->where($parentCodeKey, $parentCode);
        }

        return $query
            ->groupBy($codeKey, $khKey, $enKey)
            ->orderBy($codeKey)
            ->get()
            ->map(function ($row) {
                $khmer = (string) ($row->kh ?? '');
                $english = (string) ($row->en ?? '');

                return [
                    'code' => (string) $row->code,
                    'kh' => $khmer,
                    'en' => $english,
                    'label' => $this->formatLabel($khmer, $english),
                ];
            })
            ->values()
            ->all();
    }

    private function formatLabel(?string $khmer, ?string $english): string
    {
        $khmer = trim((string) $khmer);
        $english = trim((string) $english);

        if ($khmer !== '' && $english !== '') {
            return $khmer.' ('.$english.')';
        }

        return $khmer !== '' ? $khmer : $english;
    }

    private function addresses(): array
    {
        if ($this->hasLocalAddresses()) {
            return $this->localRows();
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addDays(config('loanmanagement.cambodia_address.cache_days', 7)),
            function () {
                return $this->fetchAddresses();
            }
        );
    }

    private function syncIfEmpty(): void
    {
        $this->ensureLocalTable();

        if ($this->hasLocalAddresses()) {
            return;
        }

        try {
            $this->syncToDatabase();
        } catch (\Throwable $exception) {
            Log::warning('Cambodia address database sync failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hasLocalAddresses(): bool
    {
        return Schema::hasTable(self::TABLE) && DB::table(self::TABLE)->exists();
    }

    private function localRows(): array
    {
        $this->ensureLocalTable();

        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)
            ->select([
                'province_code',
                'province_kh',
                'province_en',
                'district_code',
                'district_kh',
                'district_en',
                'commune_code',
                'commune_kh',
                'commune_en',
                'village_code',
                'village_kh',
                'village_en',
            ])
            ->orderBy('province_code')
            ->orderBy('district_code')
            ->orderBy('commune_code')
            ->orderBy('village_code')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    private function ensureLocalTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 20)->index();
            $table->string('province_kh')->nullable();
            $table->string('province_en')->nullable();
            $table->string('district_code', 20)->index();
            $table->string('district_kh')->nullable();
            $table->string('district_en')->nullable();
            $table->string('commune_code', 20)->index();
            $table->string('commune_kh')->nullable();
            $table->string('commune_en')->nullable();
            $table->string('village_code', 20)->unique();
            $table->string('village_kh')->nullable();
            $table->string('village_en')->nullable();
            $table->timestamps();
        });
    }

    private function fetchAddresses(): array
    {
        $endpoint = config('loanmanagement.cambodia_address.endpoint');
        $pageSize = config('loanmanagement.cambodia_address.page_size', 200);
        $timeout = config('loanmanagement.cambodia_address.timeout', 30);
        $verifySsl = config('loanmanagement.cambodia_address.verify_ssl', true);
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->requestAddressPage($endpoint, $timeout, $verifySsl, $page, $pageSize);

            if (! $response->successful()) {
                Log::warning('Cambodia address dataset returned an error.', [
                    'status' => $response->status(),
                    'page' => $page,
                ]);

                break;
            }

            $payload = $response->json();
            $items = array_merge($items, Arr::wrap($payload['items'] ?? []));
            $totalPages = (int) ($payload['total_pages'] ?? $totalPages);
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    private function requestAddressPage(string $endpoint, int $timeout, bool $verifySsl, int $page, int $pageSize)
    {
        try {
            return Http::timeout($timeout)
                ->withOptions(['verify' => $verifySsl])
                ->get($endpoint, [
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);
        } catch (\Throwable $exception) {
            if (! $verifySsl) {
                throw $exception;
            }

            Log::warning('Cambodia address dataset SSL verification failed; retrying without SSL verification.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'page' => $page,
            ]);

            return Http::timeout($timeout)
                ->withOptions(['verify' => false])
                ->get($endpoint, [
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);
        }
    }

    private function bestMatch(array $addressRows, array $fields): ?array
    {
        $haystack = $this->normalize(implode(' ', array_filter([
            $fields['id_card_address'] ?? null,
            $fields['province'] ?? null,
            $fields['district'] ?? null,
            $fields['commune'] ?? null,
            $fields['village'] ?? null,
        ])));

        if ($haystack === '') {
            return null;
        }

        $bestRow = null;
        $bestScore = 0;

        foreach ($addressRows as $row) {
            $score = $this->scoreRow($row, $fields, $haystack);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row;
            }
        }

        return $bestScore >= 6 ? $bestRow : null;
    }

    private function scoreRow(array $row, array $fields, string $haystack): int
    {
        $score = 0;

        $fieldWeights = [
            'province' => 4,
            'district' => 5,
            'commune' => 6,
            'village' => 7,
        ];

        foreach ($fieldWeights as $field => $weight) {
            $fieldValue = $this->normalize($fields[$field] ?? '');

            if ($fieldValue === '') {
                continue;
            }

            if (
                $fieldValue === $this->normalize($row[$field.'_kh'] ?? '')
                || $fieldValue === $this->normalize($row[$field.'_en'] ?? '')
            ) {
                $score += $weight * 2;
            }
        }

        foreach (['province', 'district', 'commune', 'village'] as $field) {
            if ($this->containsAny($haystack, [
                $row[$field.'_kh'] ?? '',
                $row[$field.'_en'] ?? '',
            ])) {
                $score += $field === 'village' ? 4 : 3;
            }
        }

        return $score;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = $this->normalize($needle);

            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/u', '', $value);

        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}
