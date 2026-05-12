<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanCustomerService
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_customers';

    public function create(array $data, array $contactSnapshot = []): int
    {
        return DB::connection($this->connection)->transaction(function () use ($data, $contactSnapshot) {
            $payload = $this->buildPayload($data, $contactSnapshot, true);
            return (int) DB::connection($this->connection)->table($this->table)->insertGetId($this->filterColumns($payload));
        });
    }

    public function update(int $id, array $data): void
    {
        DB::connection($this->connection)->transaction(function () use ($id, $data) {
            $payload = $this->buildPayload($data, [], false);
            DB::connection($this->connection)->table($this->table)->where('id', $id)->update($this->filterColumns($payload));
        });
    }

    protected function buildPayload(array $data, array $snapshot, bool $isCreate): array
    {
        $name = trim((string) ($data['name'] ?? $snapshot['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? $snapshot['phone'] ?? ''));
        $payload = [
            'main_contact_id' => $data['main_contact_id'] ?? ($snapshot['main_contact_id'] ?? null),
            'business_location_id' => $data['business_location_id'] ?? ($snapshot['business_location_id'] ?? null),
            'business_location_name_snapshot' => $snapshot['business_location_name_snapshot'] ?? null,
            'name' => $name,
            'customer_name' => $name,
            'phone' => $phone,
            'alternate_phone' => $data['alternate_phone'] ?? ($snapshot['alternate_phone'] ?? null),
            'login_phone' => $data['login_phone'] ?? null,
            'username' => $data['username'] ?? null,
            'can_login' => (int) ($data['can_login'] ?? 0),
            'telegram' => $data['telegram'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'email' => $data['email'] ?? ($snapshot['email'] ?? null),
            'gender' => $data['gender'] ?? ($snapshot['gender'] ?? null),
            'date_of_birth' => $data['date_of_birth'] ?? ($snapshot['date_of_birth'] ?? null),
            'id_card_number' => $data['id_card_number'] ?? ($snapshot['id_card_number'] ?? null),
            'passport_number' => $data['passport_number'] ?? null,
            'address' => $data['address'] ?? ($snapshot['address'] ?? null),
            'khmer_name' => $data['khmer_name'] ?? null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'commune' => $data['commune'] ?? null,
            'village' => $data['village'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'family_contact_name' => $data['family_contact_name'] ?? null,
            'family_contact_phone' => $data['family_contact_phone'] ?? null,
            'spouse_name' => $data['spouse_name'] ?? null,
            'spouse_phone' => $data['spouse_phone'] ?? null,
            'workplace' => $data['workplace'] ?? null,
            'monthly_income' => $data['monthly_income'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'customer_photo_file_id' => $data['customer_photo_file_id'] ?? null,
            'id_front_file_id' => $data['id_front_file_id'] ?? null,
            'id_back_file_id' => $data['id_back_file_id'] ?? null,
            'blacklist_status' => (int) ($data['blacklist_status'] ?? 0),
            'blacklist_reason' => $data['blacklist_reason'] ?? null,
            'blacklist_date' => !empty($data['blacklist_status']) ? now() : null,
            'blacklist_by' => !empty($data['blacklist_status']) ? auth()->id() : null,
            'note' => $data['note'] ?? null,
            'allow_gps_tracking' => (int) ($data['allow_gps_tracking'] ?? 0),
            'gps_tracking_note' => $data['gps_tracking_note'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $isCreate ? auth()->id() : ($data['created_by'] ?? null),
            'created_by_name_snapshot' => $isCreate ? $this->resolveCreatedByName() : null,
            'synced_at' => !empty($snapshot) ? now() : null,
            'updated_at' => now(),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make((string) $data['password']);
        }
        if ($isCreate) {
            $payload['customer_code'] = $data['customer_code'] ?? $this->generateCustomerCode();
            $payload['created_at'] = now();
        }
        return $payload;
    }

    protected function filterColumns(array $payload): array
    {
        $columns = Schema::connection($this->connection)->getColumnListing($this->table);
        return Arr::only($payload, $columns);
    }

    protected function generateCustomerCode(): string
    {
        do {
            $code = 'LC-'.strtoupper(Str::random(8));
            $exists = DB::connection($this->connection)->table($this->table)->where('customer_code', $code)->exists();
        } while ($exists);
        return $code;
    }

    protected function resolveCreatedByName(): ?string
    {
        $user = auth()->user();
        if (! $user) return null;
        $full = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));
        return $full !== '' ? $full : ($user->username ?? null);
    }
}

