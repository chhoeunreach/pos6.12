<?php

namespace Modules\UserBackupRestore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UserExportService
{
    private const TEMP_DIR = 'temp/user_backup_restore';

    private const SAFE_FIELDS = [
        'id',
        'surname',
        'first_name',
        'last_name',
        'username',
        'email',
        'language',
        'contact_no',
        'address',
        'business_id',
        'status',
        'is_cmmsn_agnt',
        'cmmsn_percent',
        'selected_contacts',
        'dob',
        'gender',
        'marital_status',
        'blood_group',
        'contact_number',
        'alt_number',
        'family_number',
        'fb_link',
        'twitter_link',
        'social_media_1',
        'social_media_2',
        'permanent_address',
        'current_address',
        'guardian_name',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'bank_details',
        'id_proof_name',
        'id_proof_number',
        'essentials_salary',
        'essentials_pay_period',
        'essentials_pay_cycle',
        'essentials_sales_target',
        'allow_login',
        'created_at',
        'updated_at',
        // Optional (only when enabled): password (hashed)
        'password',
    ];

    public function exportUsers(int $business_id, array $options): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $disk = Storage::disk('local');
        $runId = now()->format('Ymd_His') . '_' . Str::random(8);
        $baseDir = self::TEMP_DIR . '/runs/' . $runId;
        $disk->makeDirectory($baseDir);

        try {
            $users = $this->fetchUsers($business_id, $options);

            $manifest = [
                'module' => 'UserBackupRestore',
                'created_at' => now()->format('Y-m-d H:i:s'),
                'business_id' => $business_id,
                'record_count' => count($users),
                'ultimatepos_version' => config('app.version') ?? null,
                'data_type' => 'users',
            ];

            $payload = [
                'manifest' => $manifest,
                'users' => $users,
            ];

            $zipPath = $this->createZip($payload, $baseDir);
        } finally {
            // Keep zip file, remove temp run directory
            $disk->deleteDirectory($baseDir);
        }

        return $zipPath;
    }

    private function fetchUsers(int $business_id, array $options): array
    {
        if (! Schema::hasTable('users')) {
            throw new \RuntimeException('Users table not found.');
        }

        $columns = Schema::getColumnListing('users');
        $allowed = array_values(array_intersect(self::SAFE_FIELDS, $columns));

        // Password export only when explicitly enabled
        if (empty($options['include_passwords'])) {
            $allowed = array_values(array_diff($allowed, ['password']));
        }

        $query = DB::table('users')->select($allowed);

        if (in_array('business_id', $allowed, true)) {
            $query->where('business_id', $business_id);
        }

        $userIds = $options['user_ids'] ?? [];
        if (! empty($userIds) && in_array('id', $allowed, true)) {
            $query->whereIn('id', array_map('intval', $userIds));
        }

        $activeOnly = ! empty($options['active_only']);
        $includeInactive = ! empty($options['include_inactive']);
        if ($activeOnly && ! $includeInactive && in_array('status', $allowed, true)) {
            $query->whereIn('status', [1, '1', 'active', 'Active', 'ACTIVE']);
        }

        $orderColumn = 'id';
        if (! in_array('id', $allowed, true)) {
            $orderColumn = in_array('email', $allowed, true) ? 'email' : (in_array('created_at', $allowed, true) ? 'created_at' : $allowed[0] ?? 'email');
        }
        $rows = $query->orderBy($orderColumn)->get();
        $users = json_decode(json_encode($rows), true) ?: [];

        if (! empty($options['include_roles'])) {
            $this->attachRoles($users, $business_id);
        }

        if (! empty($options['include_location_permissions'])) {
            $this->attachLocationPermissions($users, $business_id);
        }

        return $users;
    }

    private function attachRoles(array &$users, int $business_id): void
    {
        if (empty($users)) {
            return;
        }

        if (! (Schema::hasTable('model_has_roles') && Schema::hasTable('roles'))) {
            return;
        }

        $userIds = array_values(array_filter(array_map(static fn ($u) => $u['id'] ?? null, $users)));
        if (empty($userIds)) {
            return;
        }

        $userModel = config('permission.models.user') ?? \App\User::class;

        $rolesQuery = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', $userModel)
            ->whereIn('model_has_roles.model_id', $userIds)
            ->select([
                'model_has_roles.model_id as user_id',
                'roles.name as role_name',
            ]);

        if (Schema::hasColumn('roles', 'business_id')) {
            $rolesQuery->addSelect('roles.business_id as role_business_id');
        }

        $mappings = $rolesQuery->get();
        $map = [];
        foreach ($mappings as $m) {
            $uid = (int) $m->user_id;
            $entry = ['name' => (string) $m->role_name];
            if (property_exists($m, 'role_business_id')) {
                $entry['business_id'] = (int) ($m->role_business_id ?? 0);
            }
            $map[$uid][] = $entry;
        }

        foreach ($users as &$u) {
            $uid = (int) ($u['id'] ?? 0);
            $u['_roles'] = $map[$uid] ?? [];
        }
        unset($u);
    }

    private function attachLocationPermissions(array &$users, int $business_id): void
    {
        if (empty($users)) {
            return;
        }

        if (! (Schema::hasTable('user_location_permissions') && Schema::hasTable('business_locations'))) {
            return;
        }

        $userIds = array_values(array_filter(array_map(static fn ($u) => $u['id'] ?? null, $users)));
        if (empty($userIds)) {
            return;
        }

        $q = DB::table('user_location_permissions')
            ->join('business_locations', 'user_location_permissions.location_id', '=', 'business_locations.id')
            ->whereIn('user_location_permissions.user_id', $userIds)
            ->select([
                'user_location_permissions.user_id as user_id',
                'business_locations.name as location_name',
            ]);

        if (Schema::hasColumn('business_locations', 'business_id')) {
            $q->where('business_locations.business_id', $business_id);
        }

        $rows = $q->get();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->user_id][] = (string) $r->location_name;
        }

        foreach ($users as &$u) {
            $uid = (int) ($u['id'] ?? 0);
            $u['_locations'] = $map[$uid] ?? [];
        }
        unset($u);
    }

    public function createZip(array $data, string $baseDir): string
    {
        $disk = Storage::disk('local');

        $manifest = $data['manifest'] ?? [];
        $users = $data['users'] ?? [];

        $disk->put($baseDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $disk->put($baseDir . '/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zipName = 'user_backup_restore_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
        $zipRelative = self::TEMP_DIR . '/exports/' . $zipName;
        $disk->makeDirectory(self::TEMP_DIR . '/exports');

        $zipFull = $this->localDiskPath($disk, $zipRelative);
        $zipDir = dirname($zipFull);
        if (! is_dir($zipDir)) {
            @mkdir($zipDir, 0775, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create ZIP file.');
        }

        $manifestRel = $baseDir . '/manifest.json';
        $usersRel = $baseDir . '/users.json';
        try {
            if (! $disk->exists($manifestRel) || ! $disk->exists($usersRel)) {
                throw new \RuntimeException('Temporary export files not found. Please check storage permissions.');
            }

            $zip->addFromString('manifest.json', (string) $disk->get($manifestRel));
            $zip->addFromString('users.json', (string) $disk->get($usersRel));
        } finally {
            if (! $zip->close()) {
                throw new \RuntimeException('ZIP creation failed. Please check PHP temp directory permissions.');
            }
        }

        $this->assertZipIsValid($zipFull);
        return $zipFull;
    }

    private function localDiskPath($disk, string $relativePath): string
    {
        if (is_object($disk) && method_exists($disk, 'path')) {
            return $disk->path($relativePath);
        }

        return storage_path('app/' . ltrim($relativePath, '/'));
    }

    private function assertZipIsValid(string $zipFullPath): void
    {
        clearstatcache(true, $zipFullPath);
        $size = @filesize($zipFullPath);
        if ($size === false || $size < 4) {
            throw new \RuntimeException('Generated ZIP file is empty or missing.');
        }

        $fh = @fopen($zipFullPath, 'rb');
        if (! $fh) {
            throw new \RuntimeException('Unable to read generated ZIP file.');
        }
        try {
            $sig = fread($fh, 2);
        } finally {
            fclose($fh);
        }

        if ($sig !== 'PK') {
            throw new \RuntimeException('Generated file is not a valid ZIP (missing PK signature).');
        }
    }
}
