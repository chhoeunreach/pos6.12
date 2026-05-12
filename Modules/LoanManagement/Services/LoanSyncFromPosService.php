<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanSyncFromPosService
{
    protected string $loanConnection = 'mysql_loan';

    public function syncBusinessLocations(?int $businessId = null, ?int $userId = null): array
    {
        if (! Schema::hasTable('business_locations')) {
            return ['synced' => 0, 'skipped' => 0, 'reason' => 'business_locations table not found'];
        }
        if (! Schema::connection($this->loanConnection)->hasTable('loan_business_locations')) {
            return ['synced' => 0, 'skipped' => 0, 'reason' => 'loan_business_locations table not found'];
        }

        $required = ['id', 'name', 'location_id'];
        foreach ($required as $column) {
            if (! Schema::hasColumn('business_locations', $column)) {
                return ['synced' => 0, 'skipped' => 0, 'reason' => "Missing required column: {$column}"];
            }
        }

        $select = ['id', 'name', 'location_id'];
        foreach (['invoice_scheme_id', 'receipt_printer_type', 'mobile', 'alternate_number', 'landmark'] as $optionalColumn) {
            if (Schema::hasColumn('business_locations', $optionalColumn)) {
                $select[] = $optionalColumn;
            }
        }
        if ($businessId !== null && Schema::hasColumn('business_locations', 'business_id')) {
            $select[] = 'business_id';
        }

        $query = DB::table('business_locations')->select($select)->orderBy('id');
        if ($businessId !== null && Schema::hasColumn('business_locations', 'business_id')) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();
        $synced = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $payload = [
                'main_business_id' => $row->business_id ?? null,
                'main_location_id' => $row->id,
                'name' => $row->name,
                'location_code' => $row->location_id ?? null,
                'address' => $row->landmark ?? null,
                'phone' => $row->mobile ?? ($row->alternate_number ?? null),
                'invoice_scheme_id' => $row->invoice_scheme_id ?? null,
                'status' => 'active',
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::connection($this->loanConnection)->hasColumn('loan_business_locations', 'created_by') && $userId !== null) {
                $payload['created_by'] = $userId;
            }

            $filtered = $this->filterColumns('loan_business_locations', $payload);
            if (! isset($filtered['main_location_id']) || ! isset($filtered['name'])) {
                $skipped++;
                continue;
            }

            $existing = DB::connection($this->loanConnection)->table('loan_business_locations')
                ->where('main_location_id', $row->id)
                ->first();

            if ($existing) {
                DB::connection($this->loanConnection)->table('loan_business_locations')->where('id', $existing->id)->update($filtered);
            } else {
                if (Schema::connection($this->loanConnection)->hasColumn('loan_business_locations', 'created_at')) {
                    $filtered['created_at'] = now();
                }
                DB::connection($this->loanConnection)->table('loan_business_locations')->insert($filtered);
            }
            $synced++;
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'reason' => null];
    }

    public function syncCustomers(?int $businessId = null, ?int $userId = null): array
    {
        if (! Schema::hasTable('contacts')) {
            return ['synced' => 0, 'skipped' => 0, 'reason' => 'contacts table not found'];
        }
        if (! Schema::connection($this->loanConnection)->hasTable('loan_customers')) {
            return ['synced' => 0, 'skipped' => 0, 'reason' => 'loan_customers table not found'];
        }

        $required = ['id', 'name', 'mobile', 'alternate_number', 'email', 'address_line_1', 'contact_id', 'supplier_business_name'];
        foreach ($required as $column) {
            if (! Schema::hasColumn('contacts', $column)) {
                return ['synced' => 0, 'skipped' => 0, 'reason' => "Missing required contact column: {$column}"];
            }
        }

        $select = [
            'id',
            'name',
            'mobile',
            'alternate_number',
            'email',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'country',
            'zip_code',
            'contact_id',
            'supplier_business_name',
        ];
        foreach (['gender', 'dob', 'date_of_birth', 'id_card_number', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'business_id'] as $optionalColumn) {
            if (Schema::hasColumn('contacts', $optionalColumn)) {
                $select[] = $optionalColumn;
            }
        }

        $query = DB::table('contacts')->where('type', 'customer')->select($select)->orderBy('id');
        if ($businessId !== null && Schema::hasColumn('contacts', 'business_id')) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();
        $synced = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $address = trim(implode(' ', array_filter([
                $row->address_line_1 ?? null,
                $row->address_line_2 ?? null,
                $row->city ?? null,
                $row->state ?? null,
                $row->country ?? null,
                $row->zip_code ?? null,
            ])));

            $payload = [
                'main_contact_id' => $row->id,
                'business_location_id' => $row->business_id ?? null,
                'name' => $row->name,
                'phone' => $row->mobile,
                'alternate_phone' => $row->alternate_number ?? null,
                'email' => $row->email ?? null,
                'gender' => $row->gender ?? null,
                'date_of_birth' => $row->dob ?? ($row->date_of_birth ?? null),
                'id_card_number' => $row->id_card_number ?? ($row->custom_field1 ?? null),
                'address' => $address !== '' ? $address : null,
                'business_name_snapshot' => $row->supplier_business_name ?? null,
                'status' => 'active',
                'synced_at' => now(),
                'updated_at' => now(),
            ];
            if ($userId !== null && Schema::connection($this->loanConnection)->hasColumn('loan_customers', 'created_by')) {
                $payload['created_by'] = $userId;
            }

            $filtered = $this->filterColumns('loan_customers', $payload);
            if (! isset($filtered['main_contact_id']) || ! isset($filtered['name'])) {
                $skipped++;
                continue;
            }

            $existing = DB::connection($this->loanConnection)->table('loan_customers')
                ->where('main_contact_id', $row->id)
                ->first();
            if ($existing) {
                DB::connection($this->loanConnection)->table('loan_customers')->where('id', $existing->id)->update($filtered);
            } else {
                if (Schema::connection($this->loanConnection)->hasColumn('loan_customers', 'created_at')) {
                    $filtered['created_at'] = now();
                }
                DB::connection($this->loanConnection)->table('loan_customers')->insert($filtered);
            }
            $synced++;
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'reason' => null];
    }

    protected function filterColumns(string $table, array $payload): array
    {
        $columns = Schema::connection($this->loanConnection)->getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }
}
