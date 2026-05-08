<?php

namespace Modules\UserBackupRestore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UserImportService
{
    private const TEMP_DIR = 'temp/user_backup_restore';

    public function preview(string $zip_path, int $business_id): array
    {
        $warnings = [];
        $parsed = $this->readZip($zip_path, $warnings);

        $users = $parsed['users'] ?? [];
        $record_count = count($users);

        $matched = 0;
        $new = 0;
        $skipped = 0;
        $sample = [];

        foreach ($users as $idx => $u) {
            $existing = $this->matchExistingUser($u, $business_id);
            $isMatch = ! empty($existing);
            if ($isMatch) {
                $matched++;
                $skipped++; // Insert Only would skip matches
            } else {
                $new++;
            }
            if ($idx < 10) {
                $u['_match'] = $isMatch ? true : false;
                $sample[] = $u;
            }
        }

        return [
            'record_count' => $record_count,
            'matched_count' => $matched,
            'new_count' => $new,
            'skipped_count' => $skipped,
            'warnings' => $warnings,
            'sample' => $sample,
        ];
    }

    public function import(string $zip_path, int $business_id, string $mode, array $options): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $warnings = [];
        $parsed = $this->readZip($zip_path, $warnings);
        $users = $parsed['users'] ?? [];

        $result = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'warnings' => $warnings,
            'warnings_count' => count($warnings),
        ];

        if (! Schema::hasTable('users')) {
            throw new \RuntimeException('Users table not found.');
        }

        DB::beginTransaction();
        try {
            foreach ($users as $u) {
                try {
                    $existing = $this->matchExistingUser($u, $business_id);
                    $isMatch = ! empty($existing);

                    if ($isMatch && $mode === 'insert_only') {
                        $result['skipped']++;
                        continue;
                    }

                    $data = $this->prepareUserData($u, $business_id, $options, $isMatch);

                    if ($isMatch) {
                        if ($mode === 'update_existing' || $mode === 'insert_update') {
                            DB::table('users')->where('id', $existing['id'])->update($data);
                            $result['updated']++;
                            $this->restoreRolesAndLocations($existing['id'], $u, $business_id, $warnings);
                        } else {
                            $result['skipped']++;
                        }
                    } else {
                        $newId = DB::table('users')->insertGetId($data);
                        $result['inserted']++;
                        $this->restoreRolesAndLocations($newId, $u, $business_id, $warnings);
                    }
                } catch (\Throwable $e) {
                    $result['failed']++;
                    $warnings[] = 'User import failed: ' . ($u['email'] ?? ($u['username'] ?? 'unknown')) . ' | ' . $e->getMessage();
                }
            }

            $result['warnings'] = $warnings;
            $result['warnings_count'] = count($warnings);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $this->cleanupZipArtifact($zip_path);
        }

        return $result;
    }

    public function matchExistingUser(array $user_data, int $business_id): ?array
    {
        $q = DB::table('users');
        if (Schema::hasColumn('users', 'business_id')) {
            $q->where('business_id', $business_id);
        }

        $email = isset($user_data['email']) ? trim((string) $user_data['email']) : '';
        $username = isset($user_data['username']) ? trim((string) $user_data['username']) : '';
        $contact = '';
        if (! empty($user_data['contact_no'])) {
            $contact = trim((string) $user_data['contact_no']);
        } elseif (! empty($user_data['contact_number'])) {
            $contact = trim((string) $user_data['contact_number']);
        }

        if ($email !== '' && Schema::hasColumn('users', 'email')) {
            $found = (array) (clone $q)->where('email', $email)->first();
            if (! empty($found)) {
                return $found;
            }
        }

        if ($username !== '' && Schema::hasColumn('users', 'username')) {
            $found = (array) (clone $q)->where('username', $username)->first();
            if (! empty($found)) {
                return $found;
            }
        }

        if ($contact !== '' && (Schema::hasColumn('users', 'contact_no') || Schema::hasColumn('users', 'contact_number'))) {
            $found = (array) (clone $q)
                ->where(function ($w) use ($contact) {
                    if (Schema::hasColumn('users', 'contact_no')) {
                        $w->orWhere('contact_no', $contact);
                    }
                    if (Schema::hasColumn('users', 'contact_number')) {
                        $w->orWhere('contact_number', $contact);
                    }
                })
                ->first();
            if (! empty($found)) {
                return $found;
            }
        }

        return null;
    }

    public function prepareUserData(array $user_data, int $business_id, array $options, bool $isMatch): array
    {
        $columns = Schema::getColumnListing('users');
        $data = [];

        // Map backup -> current business
        if (in_array('business_id', $columns, true)) {
            $data['business_id'] = $business_id;
        }

        foreach ($user_data as $key => $value) {
            if (Str::startsWith($key, '_')) {
                continue;
            }
            if ($key === 'id') {
                continue; // never trust foreign IDs
            }
            if ($key === 'business_id') {
                continue;
            }
            if (! in_array($key, $columns, true)) {
                continue;
            }
            if ($key === 'password') {
                continue; // handled separately
            }
            $data[$key] = $value;
        }

        // Password rules
        $passwordOption = $options['password_option'] ?? 'random';
        $defaultPassword = (string) ($options['default_password'] ?? '12345678');

        if (! $isMatch) {
            if ($passwordOption === 'restore_hash' && ! empty($user_data['password']) && in_array('password', $columns, true)) {
                $data['password'] = (string) $user_data['password'];
            } else {
                $plain = $passwordOption === 'default' ? $defaultPassword : Str::random(12);
                if (in_array('password', $columns, true)) {
                    $data['password'] = Hash::make($plain);
                }
            }
        } else {
            // For existing users, never overwrite password unless explicitly restoring hash and hash exists
            if ($passwordOption === 'restore_hash' && ! empty($user_data['password']) && in_array('password', $columns, true)) {
                $data['password'] = (string) $user_data['password'];
            }
        }

        // Timestamps: keep if column exists, else ignore
        if (! in_array('created_at', $columns, true)) {
            unset($data['created_at']);
        }
        if (! in_array('updated_at', $columns, true)) {
            unset($data['updated_at']);
        }

        // Ensure updated_at set on updates when column exists
        if ($isMatch && in_array('updated_at', $columns, true)) {
            $data['updated_at'] = now();
        }

        // On insert, ensure created_at exists if column exists
        if (! $isMatch && in_array('created_at', $columns, true) && empty($data['created_at'])) {
            $data['created_at'] = now();
        }

        return $data;
    }

    private function restoreRolesAndLocations(int $userId, array $userData, int $business_id, array &$warnings): void
    {
        $this->restoreRoles($userId, $userData, $business_id, $warnings);
        $this->restoreLocations($userId, $userData, $business_id, $warnings);
    }

    private function restoreRoles(int $userId, array $userData, int $business_id, array &$warnings): void
    {
        if (empty($userData['_roles']) || ! is_array($userData['_roles'])) {
            return;
        }
        if (! (Schema::hasTable('model_has_roles') && Schema::hasTable('roles'))) {
            $warnings[] = 'Roles tables not found; skipped role restore.';
            return;
        }

        $userModel = config('permission.models.user') ?? \App\User::class;

        $roleNames = [];
        foreach ($userData['_roles'] as $r) {
            $name = is_array($r) ? ($r['name'] ?? null) : null;
            if (! empty($name)) {
                $roleNames[] = (string) $name;
            }
        }
        $roleNames = array_values(array_unique(array_filter($roleNames)));
        if (empty($roleNames)) {
            return;
        }

        $rolesQ = DB::table('roles')->whereIn('name', $roleNames);
        if (Schema::hasColumn('roles', 'business_id')) {
            $rolesQ->where('business_id', $business_id);
        }
        $roles = $rolesQ->get(['id', 'name']);
        $foundByName = [];
        foreach ($roles as $r) {
            $foundByName[(string) $r->name] = (int) $r->id;
        }

        $roleIds = [];
        foreach ($roleNames as $name) {
            if (! isset($foundByName[$name])) {
                $warnings[] = 'Role not found, skipped: ' . $name;
                continue;
            }
            $roleIds[] = $foundByName[$name];
        }

        // Sync roles (safe, only for this user)
        DB::table('model_has_roles')
            ->where('model_type', $userModel)
            ->where('model_id', $userId)
            ->delete();

        foreach ($roleIds as $rid) {
            DB::table('model_has_roles')->insert([
                'role_id' => $rid,
                'model_type' => $userModel,
                'model_id' => $userId,
            ]);
        }
    }

    private function restoreLocations(int $userId, array $userData, int $business_id, array &$warnings): void
    {
        if (empty($userData['_locations']) || ! is_array($userData['_locations'])) {
            return;
        }

        if (! (Schema::hasTable('user_location_permissions') && Schema::hasTable('business_locations'))) {
            $warnings[] = 'Location permission tables not found; skipped location restore.';
            return;
        }

        $names = array_values(array_unique(array_filter(array_map('strval', $userData['_locations']))));
        if (empty($names)) {
            return;
        }

        $locQ = DB::table('business_locations')->whereIn('name', $names);
        if (Schema::hasColumn('business_locations', 'business_id')) {
            $locQ->where('business_id', $business_id);
        }
        $locs = $locQ->get(['id', 'name']);
        $found = [];
        foreach ($locs as $l) {
            $found[(string) $l->name] = (int) $l->id;
        }

        DB::table('user_location_permissions')->where('user_id', $userId)->delete();

        foreach ($names as $name) {
            if (! isset($found[$name])) {
                $warnings[] = 'Location not found, skipped: ' . $name;
                continue;
            }
            DB::table('user_location_permissions')->insert([
                'user_id' => $userId,
                'location_id' => $found[$name],
            ]);
        }
    }

    private function readZip(string $zip_path, array &$warnings): array
    {
        if (! is_file($zip_path)) {
            throw new \RuntimeException('ZIP file not found.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new \RuntimeException('Invalid ZIP file.');
        }

        $runId = now()->format('Ymd_His') . '_' . Str::random(8);
        $extractRel = self::TEMP_DIR . '/extracts/' . $runId;
        Storage::disk('local')->makeDirectory($extractRel);
        $extractPath = storage_path('app/' . $extractRel);

        try {
            if ($zip->locateName('manifest.json') === false) {
                throw new \RuntimeException('ZIP missing manifest.json');
            }
            if ($zip->locateName('users.json') === false) {
                throw new \RuntimeException('ZIP missing users.json');
            }

            $zip->extractTo($extractPath, ['manifest.json', 'users.json']);
            $zip->close();

            $manifestRaw = file_get_contents($extractPath . '/manifest.json');
            $usersRaw = file_get_contents($extractPath . '/users.json');

            $manifest = json_decode((string) $manifestRaw, true);
            if (! is_array($manifest)) {
                throw new \RuntimeException('Invalid manifest.json format.');
            }
            if (($manifest['module'] ?? null) !== 'UserBackupRestore') {
                $warnings[] = 'Manifest module mismatch: ' . (string) ($manifest['module'] ?? '');
            }
            if (($manifest['data_type'] ?? null) !== 'users') {
                $warnings[] = 'Manifest data_type is not "users".';
            }

            $users = json_decode((string) $usersRaw, true);
            if (! is_array($users)) {
                throw new \RuntimeException('Invalid users.json format.');
            }

            return [
                'manifest' => $manifest,
                'users' => $users,
            ];
        } finally {
            Storage::disk('local')->deleteDirectory($extractRel);
        }
    }

    private function cleanupZipArtifact(string $zip_path): void
    {
        // Best-effort cleanup for uploaded zip under storage/app/temp/...
        $real = realpath($zip_path);
        if (! $real) {
            return;
        }
        $tempRoot = realpath(storage_path('app/' . self::TEMP_DIR));
        if ($tempRoot && Str::startsWith($real, $tempRoot)) {
            @unlink($real);
        }
    }
}
