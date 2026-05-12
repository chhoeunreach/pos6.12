<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanSellConversionService
{
    public function getFilterData(): array
    {
        $businessLocations = DB::table('business_locations')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $customers = DB::table('contacts')
            ->where('type', 'customer')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $salesRepresentative = DB::table('users')
            ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as full_name, username")
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(function ($u) {
                $name = trim((string) $u->full_name);
                return [$u->id => ($name !== '' ? $name : (string) $u->username)];
            })
            ->toArray();
        $salesRepresentative = ['' => __('lang_v1.all')] + $salesRepresentative;

        $shippingStatuses = [
            'ordered' => __('lang_v1.ordered'),
            'packed' => __('lang_v1.packed'),
            'shipped' => __('lang_v1.shipped'),
            'delivered' => __('lang_v1.delivered'),
            'cancelled' => __('restaurant.cancelled'),
        ];

        $paymentTypes = DB::table('transaction_payments')
            ->whereNotNull('method')
            ->distinct()
            ->orderBy('method')
            ->pluck('method', 'method')
            ->toArray();

        return compact('businessLocations', 'customers', 'salesRepresentative', 'shippingStatuses', 'paymentTypes');
    }

    public function getSellList(Request $request)
    {
        $paidSub = DB::table('transaction_payments')
            ->selectRaw('transaction_id, COALESCE(SUM(amount),0) as paid_amount')
            ->groupBy('transaction_id');

        $query = DB::table('transactions as t')
            ->leftJoinSub($paidSub, 'tp', function ($join) {
                $join->on('tp.transaction_id', '=', 't.id');
            })
            ->leftJoin('transaction_payments as tpay', 'tpay.transaction_id', '=', 't.id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw("t.id, t.invoice_no, t.transaction_date as sale_date, t.final_total, COALESCE(tp.paid_amount,0) as paid_amount, (t.final_total - COALESCE(tp.paid_amount,0)) as due_amount, t.payment_status, c.name as customer_name, c.mobile as customer_phone, bl.name as location_name, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.username) as created_by_name");

        if (Schema::connection('mysql_loan')->hasTable('loan_sell_transaction_links')) {
            $converted = DB::connection('mysql_loan')->table('loan_sell_transaction_links')->pluck('transaction_id');
            if ($converted->isNotEmpty()) {
                $query->whereNotIn('t.id', $converted->all());
            }
        }

        if (! empty($request->invoice_no)) {
            $query->where('t.invoice_no', 'like', '%'.$request->invoice_no.'%');
        }
        if (! empty($request->location_id)) {
            $query->where('t.location_id', $request->location_id);
        }
        if (! empty($request->customer_id)) {
            $query->where('t.contact_id', $request->customer_id);
        }
        if (! empty($request->payment_status)) {
            $query->where('t.payment_status', $request->payment_status);
        }
        if (! empty($request->created_by)) {
            $query->where('t.created_by', $request->created_by);
        }
        if (! empty($request->shipping_status)) {
            $query->where('t.shipping_status', $request->shipping_status);
        }
        if (! empty($request->payment_method)) {
            $query->where('tpay.method', $request->payment_method);
        }
        if (! empty($request->only_subscriptions)) {
            $query->where(function ($q) {
                $q->where('t.is_recurring', 1)->orWhere('t.subscription_repeat_on', '>', 0);
            });
        }
        if (! empty($request->start_date)) {
            $query->whereDate('t.transaction_date', '>=', $request->start_date);
        }
        if (! empty($request->end_date)) {
            $query->whereDate('t.transaction_date', '<=', $request->end_date);
        }

        $rows = $query->groupBy('t.id')->orderByDesc('t.id')->limit(200)->get();

        return $rows->map(function ($r) {
            $r->installment_status = $this->isConverted((int) $r->id) ? 'Already Added' : 'Pending';
            return $r;
        });
    }

    public function getSellDetails(int $transactionId): ?array
    {
        $locationCodeSelect = Schema::hasColumn('business_locations', 'location_id') ? 'bl.location_id as location_code' : 'NULL as location_code';
        $locationInvoiceSchemeSelect = Schema::hasColumn('business_locations', 'invoice_scheme_id') ? 'bl.invoice_scheme_id as location_invoice_scheme_id' : 'NULL as location_invoice_scheme_id';
        $locationPhoneSelect = Schema::hasColumn('business_locations', 'mobile') ? 'bl.mobile as location_phone' : 'NULL as location_phone';
        $locationAltPhoneSelect = Schema::hasColumn('business_locations', 'alternate_number') ? 'bl.alternate_number as location_alt_phone' : 'NULL as location_alt_phone';
        $customerGenderSelect = Schema::hasColumn('contacts', 'gender') ? 'c.gender as customer_gender' : 'NULL as customer_gender';
        $customerDobSelect = Schema::hasColumn('contacts', 'dob') ? 'c.dob as customer_dob' : 'NULL as customer_dob';
        $customerDateOfBirthSelect = Schema::hasColumn('contacts', 'date_of_birth') ? 'c.date_of_birth as customer_date_of_birth' : 'NULL as customer_date_of_birth';
        $customerIdCardSelect = Schema::hasColumn('contacts', 'id_card_number') ? 'c.id_card_number as customer_id_card_number' : 'NULL as customer_id_card_number';
        $customerCustomField1Select = Schema::hasColumn('contacts', 'custom_field1') ? 'c.custom_field1 as customer_custom_field1' : 'NULL as customer_custom_field1';
        $customerAddressLine2Select = Schema::hasColumn('contacts', 'address_line_2') ? 'c.address_line_2 as customer_address_line_2' : 'NULL as customer_address_line_2';
        $customerCitySelect = Schema::hasColumn('contacts', 'city') ? 'c.city as customer_city' : 'NULL as customer_city';
        $customerStateSelect = Schema::hasColumn('contacts', 'state') ? 'c.state as customer_state' : 'NULL as customer_state';
        $customerCountrySelect = Schema::hasColumn('contacts', 'country') ? 'c.country as customer_country' : 'NULL as customer_country';
        $customerZipSelect = Schema::hasColumn('contacts', 'zip_code') ? 'c.zip_code as customer_zip_code' : 'NULL as customer_zip_code';

        $head = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.id', $transactionId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw("t.*, c.name as customer_name, c.mobile as customer_phone, c.alternate_number as customer_alternate_phone, c.email as customer_email, {$customerGenderSelect}, {$customerDobSelect}, {$customerDateOfBirthSelect}, {$customerIdCardSelect}, {$customerCustomField1Select}, c.address_line_1 as customer_address, {$customerAddressLine2Select}, {$customerCitySelect}, {$customerStateSelect}, {$customerCountrySelect}, {$customerZipSelect}, bl.name as location_name, {$locationCodeSelect}, {$locationInvoiceSchemeSelect}, bl.landmark as location_address, {$locationPhoneSelect}, {$locationAltPhoneSelect}, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.username) as created_by_name")
            ->first();

        if (! $head) {
            return null;
        }

        $head->customer_dob = $head->customer_dob ?? $head->customer_date_of_birth ?? null;
        $head->customer_id_card_number = $head->customer_id_card_number ?? $head->customer_custom_field1 ?? null;
        $head->customer_address = trim(implode(' ', array_filter([
            $head->customer_address ?? null,
            $head->customer_address_line_2 ?? null,
            $head->customer_city ?? null,
            $head->customer_state ?? null,
            $head->customer_country ?? null,
            $head->customer_zip_code ?? null,
        ])));

        $paidAmount = (float) DB::table('transaction_payments')->where('transaction_id', $transactionId)->sum('amount');

        $lines = DB::table('transaction_sell_lines as tsl')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->where('tsl.transaction_id', $transactionId)
            ->selectRaw('tsl.*, p.id as product_id, p.name as product_name, p.sku as product_sku, v.id as main_variation_id, v.sub_sku as variation_sku')
            ->get();

        return [
            'header' => $head,
            'paid_amount' => $paidAmount,
            'due_amount' => max(0, (float) $head->final_total - $paidAmount),
            'lines' => $lines,
            'converted' => $this->isConverted($transactionId),
        ];
    }

    public function buildLoanPreview(array $sell): array
    {
        $h = $sell['header'];
        return [
            'principal_amount' => (float) $h->final_total,
            'down_payment' => (float) $sell['paid_amount'],
            'balance_amount' => (float) $sell['due_amount'],
            'customer_name' => (string) ($h->customer_name ?? ''),
            'invoice_no' => (string) ($h->invoice_no ?? ''),
            'location_name' => (string) ($h->location_name ?? ''),
        ];
    }

    public function isConverted(int $transactionId): bool
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_sell_transaction_links')) {
            return false;
        }

        return DB::connection('mysql_loan')->table('loan_sell_transaction_links')
            ->where('transaction_id', $transactionId)
            ->exists();
    }

    public function convertTransactionToInstallment(int $transactionId, int $userId, array $data = []): int
    {
        if ($this->isConverted($transactionId)) {
            throw new \RuntimeException('This sale is already converted.');
        }

        $sell = $this->getSellDetails($transactionId);
        if (empty($sell)) {
            throw new \RuntimeException('Sell transaction not found.');
        }

        return DB::connection('mysql_loan')->transaction(function () use ($sell, $transactionId, $userId, $data) {
            $h = $sell['header'];
            $loanDate = ! empty($data['loan_date']) ? Carbon::parse($data['loan_date'])->toDateString() : Carbon::today()->toDateString();

            $customerId = $this->upsertCustomerSnapshot($h);
            $locationId = $this->upsertLocationSnapshot($h);

            $loanId = $this->insertLoan($h, $sell, $customerId, $locationId, $loanDate, $userId, $data);

            foreach ($sell['lines'] as $line) {
                $loanProductId = $this->upsertProductSnapshot($line);
                $this->insertLoanItem($loanId, $loanProductId, $line);
                $this->insertLoanProductItem($loanId, $loanProductId, $line);
            }

            $this->insertSchedule($loanId, $sell, $loanDate, (int) ($data['term_months'] ?? 6));
            $this->insertLink($loanId, $h, $sell, $transactionId, $userId);
            $this->insertStatusLog($loanId, 'created_from_sell', $userId, 'Created from sell transaction #'.$transactionId);

            return $loanId;
        });
    }

    protected function insertLoan($h, array $sell, ?int $customerId, ?int $locationId, string $loanDate, int $userId, array $data): int
    {
        $payload = [
            'customer_id' => $customerId,
            'customer_name_snapshot' => $h->customer_name,
            'customer_phone_snapshot' => $h->customer_phone,
            'main_contact_id' => $h->contact_id,
            'business_location_id' => $locationId,
            'location_name_snapshot' => $h->location_name,
            'main_location_id' => $h->location_id,
            'principal_amount' => (float) $h->final_total,
            'total_payable_amount' => (float) $h->final_total,
            'down_payment' => (float) $sell['paid_amount'],
            'paid_amount' => (float) $sell['paid_amount'],
            'balance_amount' => (float) $sell['due_amount'],
            'currency' => 'USD',
            'status' => 'active',
            'loan_date' => $loanDate,
            'created_by' => $userId,
            'created_by_name_snapshot' => auth()->user()->username ?? auth()->user()->first_name ?? 'User',
            'source_type' => 'sell_transaction',
            'source_transaction_id' => $h->id,
            'source_invoice_no' => $h->invoice_no,
            'source_transaction_snapshot' => $h->invoice_no,
            'sell_final_total_snapshot' => (float) $h->final_total,
            'sell_paid_amount_snapshot' => (float) $sell['paid_amount'],
            'sell_due_amount_snapshot' => (float) $sell['due_amount'],
            'note' => $data['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $payload = $this->filterColumns('loans', $payload);
        return (int) DB::connection('mysql_loan')->table('loans')->insertGetId($payload);
    }

    protected function upsertCustomerSnapshot($h): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) return null;

        $payload = $this->filterColumns('loan_customers', [
            'main_contact_id' => $h->contact_id,
            'business_location_id' => $h->location_id ?? null,
            'name' => $h->customer_name ?: ('Customer #'.$h->contact_id),
            'phone' => $h->customer_phone ?: '-',
            'alternate_phone' => $h->customer_alternate_phone ?? null,
            'email' => $h->customer_email ?? null,
            'gender' => $h->customer_gender ?? null,
            'date_of_birth' => $h->customer_dob ?? null,
            'id_card_number' => $h->customer_id_card_number ?? null,
            'address' => $h->customer_address,
            'status' => 'active',
            'blacklist_status' => 0,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('mysql_loan')->table('loan_customers')->insertGetId($payload);
    }

    protected function upsertLocationSnapshot($h): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) return null;

        $existing = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $h->location_id)->first();
        if ($existing) return (int) $existing->id;

        $payload = $this->filterColumns('loan_business_locations', [
            'main_business_id' => $h->business_id ?? null,
            'main_location_id' => $h->location_id,
            'name' => $h->location_name,
            'location_code' => $h->location_code ?? null,
            'address' => $h->location_address,
            'phone' => $h->location_phone ?? ($h->location_alt_phone ?? null),
            'invoice_scheme_id' => $h->location_invoice_scheme_id ?? null,
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('mysql_loan')->table('loan_business_locations')->insertGetId($payload);
    }

    protected function upsertProductSnapshot($line): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_products')) return null;

        $existing = DB::connection('mysql_loan')->table('loan_products')->where('main_product_id', $line->product_id)->where('main_variation_id', $line->main_variation_id)->first();
        if ($existing) return (int) $existing->id;

        $payload = $this->filterColumns('loan_products', [
            'main_product_id' => $line->product_id,
            'main_variation_id' => $line->main_variation_id,
            'name' => $line->product_name,
            'sku' => $line->variation_sku ?: $line->product_sku,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('mysql_loan')->table('loan_products')->insertGetId($payload);
    }

    protected function insertLoanItem(int $loanId, ?int $loanProductId, $line): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_items')) return;

        $payload = $this->filterColumns('loan_items', [
            'loan_id' => $loanId,
            'loan_product_id' => $loanProductId,
            'main_product_id' => $line->product_id,
            'main_variation_id' => $line->main_variation_id,
            'product_name_snapshot' => $line->product_name,
            'sku_snapshot' => $line->variation_sku ?: $line->product_sku,
            'qty' => $line->quantity,
            'unit_price' => $line->unit_price_inc_tax,
            'total_price' => $line->quantity * $line->unit_price_inc_tax,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mysql_loan')->table('loan_items')->insert($payload);
    }

    protected function insertLoanProductItem(int $loanId, ?int $loanProductId, $line): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_product_items')) return;

        $payload = $this->filterColumns('loan_product_items', [
            'loan_id' => $loanId,
            'loan_product_id' => $loanProductId,
            'main_product_id' => $line->product_id,
            'main_variation_id' => $line->main_variation_id,
            'serial_no' => $line->sell_line_note ?? null,
            'imei_no' => $line->sell_line_note ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mysql_loan')->table('loan_product_items')->insert($payload);
    }

    protected function insertSchedule(int $loanId, array $sell, string $loanDate, int $termMonths): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) return;

        $balance = (float) $sell['due_amount'];
        if ($balance <= 0) return;

        $monthly = round($balance / max(1, $termMonths), 2);

        for ($i = 1; $i <= $termMonths; $i++) {
            $dueDate = Carbon::parse($loanDate)->addMonths($i)->toDateString();
            $amount = ($i === $termMonths) ? round($balance - ($monthly * ($termMonths - 1)), 2) : $monthly;
            $payload = $this->filterColumns('loan_payment_schedules', [
                'loan_id' => $loanId,
                'due_date' => $dueDate,
                'schedule_amount' => $amount,
                'paid_amount' => 0,
                'balance_amount' => $amount,
                'status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::connection('mysql_loan')->table('loan_payment_schedules')->insert($payload);
        }
    }

    protected function insertLink(int $loanId, $h, array $sell, int $transactionId, int $userId): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_sell_transaction_links')) return;

        DB::connection('mysql_loan')->table('loan_sell_transaction_links')->insert($this->filterColumns('loan_sell_transaction_links', [
            'transaction_id' => $transactionId,
            'loan_id' => $loanId,
            'invoice_no_snapshot' => $h->invoice_no,
            'customer_name_snapshot' => $h->customer_name,
            'location_name_snapshot' => $h->location_name,
            'final_total_snapshot' => (float) $h->final_total,
            'paid_amount_snapshot' => (float) $sell['paid_amount'],
            'due_amount_snapshot' => (float) $sell['due_amount'],
            'converted_by' => $userId,
            'converted_by_name_snapshot' => auth()->user()->username ?? auth()->user()->first_name ?? 'User',
            'converted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function insertStatusLog(int $loanId, string $status, int $userId, string $note): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_status_logs')) return;

        DB::connection('mysql_loan')->table('loan_status_logs')->insert($this->filterColumns('loan_status_logs', [
            'loan_id' => $loanId,
            'status' => $status,
            'changed_by' => $userId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function filterColumns(string $table, array $payload): array
    {
        $columns = Schema::connection('mysql_loan')->getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }
}
