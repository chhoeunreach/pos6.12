<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LoanToPosPrefillService
{
    protected string $connection = 'mysql_loan';

    public function payload(int $loanId): array
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            throw new RuntimeException('Loan table is not available.');
        }

        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            throw new RuntimeException('Loan not found.');
        }

        $customer = $this->customer($loan);
        $serials = $this->serials($loan);

        if (empty($serials)) {
            throw new RuntimeException('This loan does not have serial or IMEI data to search in POS.');
        }

        $contactId = (int) ($customer->main_contact_id ?? $loan->main_contact_id ?? 0);
        $contactId = $this->ensurePosCustomer($loan, $customer, $contactId);
        $contactName = $this->contactName($contactId);
        $phone = trim((string) ($loan->customer_phone_snapshot ?? ($customer->phone ?? ($customer->login_phone ?? ''))));
        $userId = $contactId > 0 ? (string) $contactId : trim((string) ($customer->customer_code ?? ''));
        if ($userId === '') {
            $userId = (string) ($contactId ?: ($customer->id ?? $loan->customer_id ?? ''));
        }
        $sellNote = $this->collectorSellNote($loan) ?: $userId;

        return [
            'source' => 'loan_management',
            'loan_id' => (int) $loan->id,
            'loan_number' => (string) ($loan->loan_number ?? $loan->id),
            'customer_id' => $contactId ?: null,
            'customer_name' => $contactName,
            'sell_note' => $sellNote,
            'staff_note' => $phone,
            'lines' => array_map(fn ($serial) => [
                'serial' => $serial,
                'sell_note' => $sellNote,
                'staff_note' => $phone,
            ], $serials),
        ];
    }

    protected function customer(object $loan): ?object
    {
        if (empty($loan->customer_id) || ! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return null;
        }

        return DB::connection($this->connection)->table('loan_customers')->where('id', $loan->customer_id)->first();
    }

    protected function serials(object $loan): array
    {
        $serials = [];

        if (! empty($loan->imei_snapshot)) {
            $serials[] = (string) $loan->imei_snapshot;
        }

        if (Schema::connection($this->connection)->hasTable('loan_items')) {
            $items = DB::connection($this->connection)
                ->table('loan_items')
                ->where('loan_id', $loan->id)
                ->when(Schema::connection($this->connection)->hasColumn('loan_items', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->get();

            foreach ($items as $item) {
                foreach (['serial_number_snapshot', 'serial_number', 'imei_snapshot', 'imei', 'sku_snapshot', 'sku'] as $column) {
                    $value = trim((string) ($item->{$column} ?? ''));
                    if ($value !== '') {
                        $serials[] = $value;
                        break;
                    }
                }
            }
        }

        return collect($serials)
            ->map(fn ($serial) => preg_replace('/[^a-zA-Z0-9]/', '', $serial))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function contactName(int $contactId): ?string
    {
        if ($contactId <= 0 || ! Schema::hasTable('contacts')) {
            return null;
        }

        $contact = DB::table('contacts')->where('id', $contactId)->first();
        if (! $contact) {
            return null;
        }

        return trim((string) ($contact->supplier_business_name ?? '')) ?: trim((string) ($contact->name ?? ''));
    }

    protected function collectorSellNote(object $loan): ?string
    {
        $collectorId = 0;
        foreach (['assigned_collector_id', 'collector_id', 'assigned_to', 'staff_id', 'created_by'] as $field) {
            if (! empty($loan->{$field})) {
                $collectorId = (int) $loan->{$field};
                break;
            }
        }

        $username = '';
        if ($collectorId > 0 && Schema::hasTable('users')) {
            $username = (string) DB::table('users')->where('id', $collectorId)->value('username');
        }

        return $this->normalizeCollectorCode($username !== '' ? $username : (string) ($collectorId ?: ''));
    }

    protected function normalizeCollectorCode(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+)$/', $value, $match)) {
            $number = ltrim($match[1], '0');

            return $number !== '' ? $number : '0';
        }

        return $value;
    }

    protected function ensurePosCustomer(object $loan, ?object $customer, int $contactId = 0): int
    {
        if (! Schema::hasTable('contacts')) {
            return $contactId;
        }

        $businessId = $this->currentBusinessId($loan, $customer);
        if ($businessId <= 0) {
            return $contactId;
        }

        $groupId = $this->ensureInstallmentCustomerGroup($businessId);
        $contact = $contactId > 0 ? $this->findContactById($contactId, $businessId) : null;
        if (! $contact) {
            $contact = $this->findExistingPosCustomer($loan, $customer, $businessId);
        }

        if ($contact) {
            $this->updatePosCustomerGroup((int) $contact->id, $groupId);
            $this->syncLoanMainContactId($loan, $customer, (int) $contact->id);

            return (int) $contact->id;
        }

        $createdContactId = $this->createPosCustomer($loan, $customer, $businessId, $groupId);
        if ($createdContactId > 0) {
            $this->syncLoanMainContactId($loan, $customer, $createdContactId);

            return $createdContactId;
        }

        return $contactId;
    }

    protected function currentBusinessId(object $loan, ?object $customer): int
    {
        $businessId = (int) (session('user.business_id') ?? 0);
        if ($businessId > 0) {
            return $businessId;
        }

        $businessId = (int) (auth()->user()->business_id ?? 0);
        if ($businessId > 0) {
            return $businessId;
        }

        foreach ([$loan->business_id ?? null, $customer->business_id ?? null, $loan->business_location_id ?? null, $customer->business_location_id ?? null] as $candidate) {
            if ((int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    protected function ensureInstallmentCustomerGroup(int $businessId): ?int
    {
        if (! Schema::hasTable('customer_groups')) {
            return null;
        }

        $name = 'រំលស់';
        $query = DB::table('customer_groups')
            ->where('business_id', $businessId)
            ->where('name', $name);

        if (Schema::hasColumn('customer_groups', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $existingId = $query->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        $now = now();
        $payload = $this->onlyExistingMainColumns('customer_groups', [
            'business_id' => $businessId,
            'name' => $name,
            'amount' => 0,
            'price_calculation_type' => 'percentage',
            'selling_price_group_id' => null,
            'created_by' => (int) (auth()->id() ?: (session('user.id') ?? 1)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('customer_groups')->insertGetId($payload);
    }

    protected function findContactById(int $contactId, int $businessId): ?object
    {
        $query = DB::table('contacts')->where('id', $contactId);

        if (Schema::hasColumn('contacts', 'business_id')) {
            $query->where('business_id', $businessId);
        }
        if (Schema::hasColumn('contacts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    protected function findExistingPosCustomer(object $loan, ?object $customer, int $businessId): ?object
    {
        $phone = $this->customerPhone($loan, $customer);
        $customerCode = $this->posCustomerCode($loan, $customer);
        $canSearchPhone = $phone !== '' && Schema::hasColumn('contacts', 'mobile');
        $canSearchCode = $customerCode !== '' && Schema::hasColumn('contacts', 'contact_id');

        if (! $canSearchPhone && ! $canSearchCode) {
            return null;
        }

        $query = DB::table('contacts')->where('business_id', $businessId);
        if (Schema::hasColumn('contacts', 'type')) {
            $query->whereIn('type', ['customer', 'both']);
        }
        if (Schema::hasColumn('contacts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->where(function ($q) use ($phone, $customerCode, $canSearchPhone, $canSearchCode) {
            if ($canSearchPhone) {
                $q->orWhere('mobile', $phone);
            }
            if ($canSearchCode) {
                $q->orWhere('contact_id', $customerCode);
            }
        });

        return $query->orderByDesc('id')->first();
    }

    protected function createPosCustomer(object $loan, ?object $customer, int $businessId, ?int $groupId): int
    {
        $now = now();
        $name = $this->customerDisplayName($loan, $customer);
        $phone = $this->customerPhone($loan, $customer);
        $address = trim((string) ($loan->customer_address_snapshot ?? ($customer->address ?? '')));
        $customerCode = $this->posCustomerCode($loan, $customer);

        $payload = $this->onlyExistingMainColumns('contacts', [
            'business_id' => $businessId,
            'type' => 'customer',
            'name' => $name,
            'first_name' => $name,
            'supplier_business_name' => null,
            'mobile' => $phone,
            'alternate_number' => trim((string) ($customer->alternate_phone ?? $loan->alternate_phone ?? '')) ?: null,
            'email' => trim((string) ($customer->email ?? $loan->customer_email_snapshot ?? '')) ?: null,
            'address_line_1' => $address ?: null,
            'landmark' => $address ?: null,
            'contact_id' => $customerCode,
            'customer_group_id' => $groupId,
            'contact_status' => 'active',
            'created_by' => (int) (auth()->id() ?: (session('user.id') ?? 1)),
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('contacts')->insertGetId($payload);
    }

    protected function updatePosCustomerGroup(int $contactId, ?int $groupId): void
    {
        if (! $groupId || ! Schema::hasColumn('contacts', 'customer_group_id')) {
            return;
        }

        DB::table('contacts')->where('id', $contactId)->update($this->onlyExistingMainColumns('contacts', [
            'customer_group_id' => $groupId,
            'updated_at' => now(),
        ]));
    }

    protected function syncLoanMainContactId(object $loan, ?object $customer, int $contactId): void
    {
        if ($contactId <= 0) {
            return;
        }

        if (! empty($customer->id)
            && Schema::connection($this->connection)->hasTable('loan_customers')
            && Schema::connection($this->connection)->hasColumn('loan_customers', 'main_contact_id')) {
            DB::connection($this->connection)
                ->table('loan_customers')
                ->where('id', $customer->id)
                ->update($this->onlyExistingLoanColumns('loan_customers', [
                    'main_contact_id' => $contactId,
                    'updated_at' => now(),
                ]));
        }

        if (! empty($loan->id)
            && Schema::connection($this->connection)->hasTable('loans')
            && Schema::connection($this->connection)->hasColumn('loans', 'main_contact_id')) {
            DB::connection($this->connection)
                ->table('loans')
                ->where('id', $loan->id)
                ->update($this->onlyExistingLoanColumns('loans', [
                    'main_contact_id' => $contactId,
                    'updated_at' => now(),
                ]));
        }
    }

    protected function customerDisplayName(object $loan, ?object $customer): string
    {
        foreach ([
            $customer->khmer_name ?? null,
            $loan->customer_khmer_name_snapshot ?? null,
            $loan->khmer_name_snapshot ?? null,
            $customer->name ?? null,
            $loan->customer_name_snapshot ?? null,
        ] as $value) {
            $name = trim((string) $value);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Loan Customer '.$loan->id;
    }

    protected function customerPhone(object $loan, ?object $customer): string
    {
        foreach ([
            $loan->customer_phone_snapshot ?? null,
            $customer->phone ?? null,
            $customer->login_phone ?? null,
            $customer->alternate_phone ?? null,
        ] as $value) {
            $phone = trim((string) $value);
            if ($phone !== '') {
                return $phone;
            }
        }

        return '';
    }

    protected function posCustomerCode(object $loan, ?object $customer): string
    {
        $customerCode = trim((string) ($customer->customer_code ?? $loan->customer_code ?? ''));
        if ($customerCode !== '') {
            return $customerCode;
        }

        foreach ([$customer->id ?? null, $loan->customer_id ?? null, $loan->id ?? null] as $value) {
            if ((int) $value > 0) {
                return 'LM'.(int) $value;
            }
        }

        return '';
    }

    protected function onlyExistingMainColumns(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }

    protected function onlyExistingLoanColumns(string $table, array $payload): array
    {
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return [];
        }

        $columns = Schema::connection($this->connection)->getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }
}
