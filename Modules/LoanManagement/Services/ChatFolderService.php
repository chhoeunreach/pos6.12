<?php

namespace Modules\LoanManagement\Services;

class ChatFolderService
{
    protected static function path(): string
    {
        $dir = storage_path('app/loan-management');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/chat_folders.json';
    }

    public static function all(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            $defaults = self::defaultFolders();
            self::save($defaults);
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || empty($decoded)) {
            $defaults = self::defaultFolders();
            self::save($defaults);
            return $defaults;
        }

        // Ensure every folder has standard fields
        $folders = array_map(function ($f) {
            $f['icon'] = $f['icon'] ?? 'fa-folder';
            $f['color'] = $f['color'] ?? '#3390ec';
            $f['customer_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($f['customer_ids'] ?? [])))));
            return $f;
        }, array_values($decoded));

        return $folders;
    }

    protected static function defaultFolders(): array
    {
        return [
            [
                'id' => 'personal',
                'name' => 'Personal',
                'icon' => 'fa-user',
                'color' => '#3b82f6',
                'type' => 'system',
                'customer_ids' => [],
            ],
            [
                'id' => 'invoices',
                'name' => 'វិក្កយបត្រ',
                'icon' => 'fa-file-invoice-dollar',
                'color' => '#10b981',
                'type' => 'system',
                'customer_ids' => [],
            ],
            [
                'id' => 'installments',
                'name' => 'រំលស់',
                'icon' => 'fa-calendar-check',
                'color' => '#f59e0b',
                'type' => 'system',
                'customer_ids' => [],
            ],
        ];
    }

    public static function save(array $folders): void
    {
        @file_put_contents(self::path(), json_encode(array_values($folders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $folder) {
            if ((string) ($folder['id'] ?? '') === (string) $id) {
                return $folder;
            }
        }

        return null;
    }

    public static function create($nameOrData, array $customerIds = []): array
    {
        if (is_array($nameOrData)) {
            $name = trim((string) ($nameOrData['name'] ?? 'New Folder'));
            $customerIds = (array) ($nameOrData['customer_ids'] ?? []);
            $icon = (string) ($nameOrData['icon'] ?? 'fa-folder');
            $color = (string) ($nameOrData['color'] ?? '#3390ec');
        } else {
            $name = trim((string) $nameOrData);
            $icon = 'fa-folder';
            $color = '#3390ec';
        }

        if ($name === '') {
            $name = 'New Folder';
        }

        $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));
        $folders = self::all();

        $id = 'folder_' . time() . '_' . rand(100, 999);
        $folder = [
            'id' => $id,
            'name' => $name,
            'icon' => $icon,
            'color' => $color,
            'type' => 'custom',
            'customer_ids' => $customerIds,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $folders[] = $folder;
        self::save($folders);

        return $folder;
    }

    public static function update(string $id, array $data): ?array
    {
        $folders = self::all();
        $updated = null;

        foreach ($folders as $key => $folder) {
            if ((string) ($folder['id'] ?? '') === (string) $id) {
                if (isset($data['name']) && trim((string) $data['name']) !== '') {
                    $folder['name'] = trim((string) $data['name']);
                }
                if (isset($data['customer_ids']) && is_array($data['customer_ids'])) {
                    $folder['customer_ids'] = array_values(array_unique(array_filter(array_map('intval', $data['customer_ids']))));
                }
                if (isset($data['color'])) {
                    $folder['color'] = (string) $data['color'];
                }
                if (isset($data['icon'])) {
                    $folder['icon'] = (string) $data['icon'];
                }
                $folder['updated_at'] = now()->toIso8601String();
                $folders[$key] = $folder;
                $updated = $folder;
                break;
            }
        }

        if ($updated) {
            self::save($folders);
        }

        return $updated;
    }

    public static function delete(string $id): bool
    {
        $folders = self::all();
        $filtered = array_filter($folders, function ($f) use ($id) {
            return (string) ($f['id'] ?? '') !== (string) $id;
        });

        if (count($filtered) !== count($folders)) {
            self::save(array_values($filtered));
            return true;
        }

        return false;
    }

    public static function addCustomer(string $folderId, int $customerId): bool
    {
        $folders = self::all();
        $modified = false;

        foreach ($folders as $key => $folder) {
            if ((string) ($folder['id'] ?? '') === (string) $folderId) {
                $ids = array_map('intval', (array) ($folder['customer_ids'] ?? []));
                if (! in_array($customerId, $ids, true)) {
                    $ids[] = $customerId;
                    $folder['customer_ids'] = array_values(array_unique($ids));
                    $folder['updated_at'] = now()->toIso8601String();
                    $folders[$key] = $folder;
                    $modified = true;
                }
                break;
            }
        }

        if ($modified) {
            self::save($folders);
        }

        return $modified;
    }

    public static function removeCustomer(string $folderId, int $customerId): bool
    {
        $folders = self::all();
        $modified = false;

        foreach ($folders as $key => $folder) {
            if ((string) ($folder['id'] ?? '') === (string) $folderId) {
                $ids = array_map('intval', (array) ($folder['customer_ids'] ?? []));
                $newIds = array_values(array_diff($ids, [$customerId]));
                if (count($newIds) !== count($ids)) {
                    $folder['customer_ids'] = $newIds;
                    $folder['updated_at'] = now()->toIso8601String();
                    $folders[$key] = $folder;
                    $modified = true;
                }
                break;
            }
        }

        if ($modified) {
            self::save($folders);
        }

        return $modified;
    }

    public static function setCustomerFolders(int $customerId, array $folderIds): void
    {
        $folders = self::all();
        $folderIds = array_map('strval', $folderIds);

        foreach ($folders as $key => $folder) {
            $fid = (string) ($folder['id'] ?? '');
            $ids = array_map('intval', (array) ($folder['customer_ids'] ?? []));

            if (in_array($fid, $folderIds, true)) {
                if (! in_array($customerId, $ids, true)) {
                    $ids[] = $customerId;
                    $folder['customer_ids'] = array_values(array_unique($ids));
                    $folders[$key] = $folder;
                }
            } else {
                if (in_array($customerId, $ids, true)) {
                    $folder['customer_ids'] = array_values(array_diff($ids, [$customerId]));
                    $folders[$key] = $folder;
                }
            }
        }

        self::save($folders);
    }

    public static function getCustomerFolderIds(int $customerId): array
    {
        $result = [];
        foreach (self::all() as $folder) {
            $ids = array_map('intval', (array) ($folder['customer_ids'] ?? []));
            if (in_array($customerId, $ids, true)) {
                $result[] = (string) $folder['id'];
            }
        }

        return $result;
    }
}
