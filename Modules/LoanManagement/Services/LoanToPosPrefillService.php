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
        $contactName = $this->contactName($contactId);
        $phone = trim((string) ($loan->customer_phone_snapshot ?? ($customer->phone ?? ($customer->login_phone ?? ''))));
        $userId = $contactId > 0 ? (string) $contactId : trim((string) ($customer->customer_code ?? ''));
        if ($userId === '') {
            $userId = (string) ($contactId ?: ($customer->id ?? $loan->customer_id ?? ''));
        }

        return [
            'source' => 'loan_management',
            'loan_id' => (int) $loan->id,
            'loan_number' => (string) ($loan->loan_number ?? $loan->id),
            'customer_id' => $contactId ?: null,
            'customer_name' => $contactName,
            'sell_note' => $userId,
            'staff_note' => $phone,
            'lines' => array_map(fn ($serial) => [
                'serial' => $serial,
                'sell_note' => $userId,
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
}
