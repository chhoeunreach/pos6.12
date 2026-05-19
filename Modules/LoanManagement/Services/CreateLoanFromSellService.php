<?php

namespace Modules\LoanManagement\Services;

use App\Services\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreateLoanFromSellService
{
    public function searchSales(array $filters)
    {
        return $this->searchSells($filters);
    }

    public function searchSells(array $filters)
    {
        $paidSub = DB::table('transaction_payments')
            ->selectRaw('transaction_id, COALESCE(SUM(amount),0) as paid_amount')
            ->groupBy('transaction_id');

        $query = DB::table('transactions as t')
            ->leftJoinSub($paidSub, 'tp', function ($join) {
                $join->on('tp.transaction_id', '=', 't.id');
            })
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('customer_groups as tcg', 'tcg.id', '=', 't.customer_group_id')
            ->leftJoin('customer_groups as ccg', 'ccg.id', '=', 'c.customer_group_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->leftJoin('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('purchase_lines as pl', 'pl.id', '=', 'tsl.lot_no_line_id')
            ->where('t.type', 'sell')
            ->selectRaw("t.id, t.transaction_date, t.invoice_no, c.name as customer_name, c.mobile as customer_phone, COALESCE(tcg.name, ccg.name) as customer_group_name, bl.name as location_name, GROUP_CONCAT(DISTINCT NULLIF(COALESCE(v.sub_sku, p.sku), '') SEPARATOR ' | ') as skus, GROUP_CONCAT(DISTINCT NULLIF(p.name, '') SEPARATOR ' | ') as product_names, GROUP_CONCAT(DISTINCT NULLIF(COALESCE(pl.lot_number, tsl.sell_line_note), '') SEPARATOR ' | ') as lots, t.final_total, COALESCE(tp.paid_amount,0) as paid_amount, (t.final_total - COALESCE(tp.paid_amount,0)) as due_amount, t.payment_status, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.username) as created_by_name")
            ->groupBy('t.id');

        if (! empty($filters['invoice_no'])) $query->where('t.invoice_no', 'like', '%'.$filters['invoice_no'].'%');
        if (! empty($filters['customer_name'])) $query->where('c.name', 'like', '%'.$filters['customer_name'].'%');
        if (! empty($filters['customer_phone'])) $query->where('c.mobile', 'like', '%'.$filters['customer_phone'].'%');
        $customerGroupName = trim((string) ($filters['customer_group_name'] ?? 'រំលស់'));
        if ($customerGroupName !== '') {
            $query->where(function ($q) use ($customerGroupName) {
                $q->where('tcg.name', $customerGroupName)
                    ->orWhere(function ($fallback) use ($customerGroupName) {
                        $fallback->whereNull('tcg.id')
                            ->where('ccg.name', $customerGroupName);
                    });
            });
        }
        if (! empty($filters['location_id'])) $query->where('t.location_id', $filters['location_id']);
        if (! empty($filters['payment_status'])) $query->where('t.payment_status', $filters['payment_status']);
        if (! empty($filters['sale_status'])) $query->where('t.status', $filters['sale_status']);
        if (empty($filters['sale_status'])) $query->where('t.status', 'final');
        if (! empty($filters['final_total'])) $query->where('t.final_total', $filters['final_total']);
        if (! empty($filters['imei_or_lot'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('tsl.lot_no_line_id', 'like', '%'.$filters['imei_or_lot'].'%')
                    ->orWhere('pl.lot_number', 'like', '%'.$filters['imei_or_lot'].'%')
                    ->orWhere('tsl.sell_line_note', 'like', '%'.$filters['imei_or_lot'].'%');
            });
        }
        if (! empty($filters['product_name_sku'])) {
            $query->where(function ($q) use ($filters) {
                $keyword = '%'.$filters['product_name_sku'].'%';
                $q->where('p.name', 'like', $keyword)
                    ->orWhere('p.sku', 'like', $keyword)
                    ->orWhere('v.sub_sku', 'like', $keyword);
            });
        }
        if (! empty($filters['start_date'])) $query->whereDate('t.transaction_date', '>=', $filters['start_date']);
        if (! empty($filters['end_date'])) $query->whereDate('t.transaction_date', '<=', $filters['end_date']);

        $rows = $query->orderByDesc('t.id')->limit(200)->get();

        return $rows->map(function ($row) {
            $row->is_converted = $this->preventDuplicateLoan((int) $row->id);
            $row->loan_id = $row->is_converted ? $this->getLoanIdBySourceTransactionId((int) $row->id) : null;
            return $row;
        });
    }

    public function getSaleFullData($transactionId): array
    {
        return $this->getSellFullData($transactionId);
    }

    public function getSellFullData($transactionId): array
    {
        $transaction = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.id', $transactionId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw("t.*, c.id as contact_id, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.id as main_location_id, bl.name as location_name_snapshot, bl.landmark as location_address_snapshot, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.username) as created_by_name_snapshot")
            ->first();

        if (! $transaction) {
            throw new \RuntimeException('Sell transaction not found.');
        }

        $paidAmount = (float) DB::table('transaction_payments')->where('transaction_id', $transactionId)->sum('amount');
        $defaultPaymentMethod = DB::table('transaction_payments')
            ->where('transaction_id', $transactionId)
            ->orderByDesc('id')
            ->value('method');
        $paymentRows = DB::table('transaction_payments')
            ->where('transaction_id', $transactionId)
            ->select('id', 'paid_on', 'payment_ref_no', 'amount', 'method', 'note')
            ->orderBy('id')
            ->get();
        if ($paymentRows->isEmpty()) {
            $paymentRows = DB::table('transaction_payments')
                ->whereIn('parent_id', function ($q) use ($transactionId) {
                    $q->from('transaction_payments')
                        ->where('transaction_id', $transactionId)
                        ->select('id');
                })
                ->select('id', 'paid_on', 'payment_ref_no', 'amount', 'method', 'note')
                ->orderBy('id')
                ->get();
        }
        if ($paymentRows->isEmpty() && $paidAmount > 0) {
            $paymentRows = collect([
                (object) [
                    'id' => 1,
                    'paid_on' => $transaction->transaction_date ?? null,
                    'payment_ref_no' => $transaction->invoice_no ?? null,
                    'amount' => $paidAmount,
                    'method' => $defaultPaymentMethod ?: 'cash',
                    'note' => null,
                ],
            ]);
        }
        $dueAmount = max(0, (float) $transaction->final_total - $paidAmount);

        $lines = DB::table('transaction_sell_lines as tsl')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->where('tsl.transaction_id', $transactionId)
            ->selectRaw('tsl.*, p.name as product_name_snapshot, p.sku as product_sku_snapshot, p.id as product_id, v.id as variation_id, v.sub_sku as variation_sku_snapshot')
            ->get()
            ->map(function ($line) {
                $line->sku_snapshot = $line->variation_sku_snapshot ?: $line->product_sku_snapshot;
                $line->imei_snapshot = $line->sell_line_note;
                $line->serial_number_snapshot = $line->sell_line_note;
                $line->line_total = (float) $line->quantity * (float) $line->unit_price_inc_tax;
                return $line;
            });

        $defaults = $this->calculateLoanDefaults((object) [
            'final_total' => $transaction->final_total,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ]);
        $defaults['loan_number'] = $this->generateUniqueLoanNumber(null, $transaction->main_location_id ?? null);

        return [
            'transaction' => $transaction,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'default_payment_method' => $defaultPaymentMethod,
            'payment_rows' => $paymentRows,
            'products' => $lines,
            'defaults' => $defaults,
        ];
    }

    public function cloneSaleToLoanFormData($transaction): array
    {
        $full = is_array($transaction) ? $transaction : $this->getSellFullData((int) $transaction);
        $sale = $full['transaction'];

        return [
            'source_type' => 'ultimate_pos_sell',
            'source_transaction_id' => $sale->id,
            'source_invoice_no' => $sale->invoice_no,
            'stock_already_deducted' => true,
            'transaction' => $sale,
            'customer_snapshot' => $this->cloneCustomerSnapshot($sale),
            'location_snapshot' => $this->cloneLocationSnapshot($sale),
            'payment_summary' => [
                'final_total' => (float) $sale->final_total,
                'paid_amount' => (float) $full['paid_amount'],
                'due_amount' => (float) $full['due_amount'],
                'payment_status' => $sale->payment_status ?? null,
            ],
            'sell_lines' => $this->cloneProductSnapshots($full),
            'defaults' => $full['defaults'],
        ];
    }

    public function prepareLoanDefaults($transaction): array
    {
        $full = is_array($transaction) ? $transaction : $this->getSellFullData((int) $transaction);

        return $full['defaults'] ?? $this->calculateLoanDefaults((object) [
            'final_total' => $full['transaction']->final_total ?? 0,
            'paid_amount' => $full['paid_amount'] ?? 0,
            'due_amount' => $full['due_amount'] ?? 0,
        ]);
    }

    public function cloneCustomerSnapshot($transaction): array
    {
        $resolved = $this->resolveCustomerSnapshot($transaction);

        return array_merge([
            'main_contact_id' => $resolved['contact_id'],
            'name' => $resolved['name'],
            'phone' => $resolved['phone'] !== '' ? $resolved['phone'] : '-',
            'address' => $resolved['address'],
        ], $this->mapResolvedCustomerToLoanCustomerPayload($resolved));
    }

    public function cloneLocationSnapshot($transaction): array
    {
        return [
            'main_location_id' => $transaction->main_location_id,
            'name' => $transaction->location_name_snapshot,
            'address' => $transaction->location_address_snapshot,
        ];
    }

    public function cloneProductSnapshots($transaction): array
    {
        return $transaction['products']->map(function ($line) {
            return [
                'main_product_id' => $line->product_id,
                'main_variation_id' => $line->variation_id,
                'product_name_snapshot' => $line->product_name_snapshot,
                'sku_snapshot' => $line->sku_snapshot,
                'imei_snapshot' => $line->imei_snapshot,
                'serial_number_snapshot' => $line->serial_number_snapshot,
                'qty' => $line->quantity,
                'unit_price' => (float) $line->unit_price_inc_tax,
                'total_price' => (float) $line->line_total,
                'discount' => (float) ($line->line_discount_amount ?? 0),
                'tax' => (float) ($line->item_tax ?? 0),
            ];
        })->all();
    }

    public function calculateLoanDefaults($transaction): array
    {
        $principal = (float) ($transaction->due_amount > 0 ? $transaction->due_amount : $transaction->final_total - $transaction->paid_amount);

        return [
            'principal_amount' => max(0, $principal),
            'down_payment' => (float) $transaction->paid_amount,
            'interest_rate' => 0,
            'interest_type' => 'flat',
            'duration_months' => 12,
            'payment_frequency' => 'monthly',
            'first_due_date' => Carbon::today()->addMonth()->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => 1,
            'penalty_type' => 'fixed',
            'penalty_amount' => 0,
        ];
    }

    public function preventDuplicateLoan($transactionId): bool
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')
            || ! Schema::connection('mysql_loan')->hasColumn('loans', 'source_transaction_id')) {
            return false;
        }

        return DB::connection('mysql_loan')->table('loans')
            ->where('source_transaction_id', $transactionId)
            ->exists();
    }

    public function getLoanIdBySourceTransactionId(int $transactionId): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')
            || ! Schema::connection('mysql_loan')->hasColumn('loans', 'source_transaction_id')) {
            return null;
        }

        $id = DB::connection('mysql_loan')->table('loans')
            ->where('source_transaction_id', $transactionId)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function createLoanFromSell(array $data): int
    {
        $transactionId = (int) $data['transaction_id'];
        if ($this->preventDuplicateLoan($transactionId)) {
            throw new \RuntimeException('This sell already has a loan.');
        }

        $full = $this->getSellFullData($transactionId);
        $transaction = $full['transaction'];

        $loanId = DB::connection('mysql_loan')->transaction(function () use ($data, $full, $transaction, $transactionId) {
            $effectiveDownPayment = (float) ($data['payment']['amount'] ?? ($data['down_payment'] ?? 0));
            $resolvedCustomer = $this->resolveCustomerSnapshot($transaction);
            $customerId = $this->createLoanCustomerSnapshot($transaction, $resolvedCustomer, $data);
            $locationId = $this->upsertSnapshot('loan_business_locations', 'main_location_id', $transaction->main_location_id, $this->cloneLocationSnapshot($transaction));

            $requestedLoanNumber = trim((string) ($data['loan_number'] ?? ''));
            if ($requestedLoanNumber !== '' && $this->loanNumberExists($requestedLoanNumber)) {
                throw new \RuntimeException('Loan invoice number already exists.');
            }

            $loanPayload = $this->filterColumns('loans', array_merge([
                'loan_number' => $requestedLoanNumber !== '' ? $requestedLoanNumber : $this->generateUniqueLoanNumber($locationId, $transaction->main_location_id ?? null),
                'customer_id' => $customerId,
                'main_contact_id' => $resolvedCustomer['contact_id'],
                'customer_name_snapshot' => $resolvedCustomer['name'],
                'customer_phone_snapshot' => $resolvedCustomer['phone'],
                'customer_address_snapshot' => $resolvedCustomer['address'],
                'customer_group_name_snapshot' => $this->resolveCustomerGroupName($data),
                'business_location_id' => $locationId,
                'main_location_id' => $transaction->main_location_id,
                'location_name_snapshot' => $transaction->location_name_snapshot,
                'loan_date' => $data['loan_date'],
                'principal_amount' => $data['principal_amount'],
                'down_payment' => $effectiveDownPayment,
                'paid_amount' => $effectiveDownPayment,
                'balance_amount' => max(0, (float) $data['principal_amount'] - $effectiveDownPayment),
                'total_payable_amount' => $data['principal_amount'],
                'interest_rate' => $data['interest_rate'] ?? 0,
                'interest_type' => $data['interest_type'],
                'duration_months' => $data['duration_months'],
                'payment_frequency' => $data['payment_frequency'],
                'first_due_date' => $data['first_due_date'],
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'penalty_type' => $data['penalty_type'] ?? null,
                'penalty_amount' => $data['penalty_amount'] ?? 0,
                'assigned_to' => $data['assigned_collector_id'] ?? null,
                'collector_id' => $data['assigned_collector_id'] ?? null,
                'source_type' => 'ultimate_pos_sell',
                'source_transaction_id' => $transactionId,
                'source_invoice_no' => $transaction->invoice_no,
                'source_created_at' => $transaction->transaction_date ?? null,
                'stock_already_deducted' => 1,
                'sell_final_total_snapshot' => $transaction->final_total,
                'sell_paid_amount_snapshot' => $full['paid_amount'],
                'sell_due_amount_snapshot' => $full['due_amount'],
                'status' => $data['action_type'] === 'create_approve' ? 'active' : ($data['action_type'] === 'draft' ? 'draft' : 'pending'),
                'created_by' => auth()->id(),
                'created_by_name_snapshot' => auth()->user()->first_name.' '.auth()->user()->last_name,
                'note' => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->mapResolvedCustomerToLoanSnapshotPayload($resolvedCustomer)));

            try {
                $loanId = (int) DB::connection('mysql_loan')->table('loans')->insertGetId($loanPayload);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($requestedLoanNumber !== '') {
                    throw $e;
                }

                // Retry once with a fresh loan number if unique conflict happens.
                $loanPayload['loan_number'] = $this->generateUniqueLoanNumber($locationId, $transaction->main_location_id ?? null);
                $loanId = (int) DB::connection('mysql_loan')->table('loans')->insertGetId($loanPayload);
            }

            foreach ($full['products'] as $line) {
                $productId = $this->upsertSnapshot('loan_products', 'main_product_id', $line->product_id, [
                    'main_product_id' => $line->product_id,
                    'main_variation_id' => $line->variation_id,
                    'name' => $line->product_name_snapshot,
                    'sku' => $line->sku_snapshot,
                ], 'main_variation_id', $line->variation_id);

                if (Schema::connection('mysql_loan')->hasTable('loan_items')) {
                    DB::connection('mysql_loan')->table('loan_items')->insert($this->filterColumns('loan_items', [
                        'loan_id' => $loanId,
                        'loan_product_id' => $productId,
                        'main_product_id' => $line->product_id,
                        'main_variation_id' => $line->variation_id,
                        'product_name_snapshot' => $line->product_name_snapshot,
                        'sku_snapshot' => $line->sku_snapshot,
                        'imei_snapshot' => $line->imei_snapshot,
                        'serial_number_snapshot' => $line->serial_number_snapshot,
                        'qty' => $line->quantity,
                        'unit_price' => $line->unit_price_inc_tax,
                        'total_price' => $line->line_total,
                        'discount' => $line->line_discount_amount ?? 0,
                        'tax' => $line->item_tax ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }

                if (Schema::connection('mysql_loan')->hasTable('loan_product_items')) {
                    DB::connection('mysql_loan')->table('loan_product_items')->insert($this->filterColumns('loan_product_items', [
                        'loan_id' => $loanId,
                        'loan_product_id' => $productId,
                        'main_product_id' => $line->product_id,
                        'main_variation_id' => $line->variation_id,
                        'imei_no' => $line->imei_snapshot,
                        'serial_no' => $line->serial_number_snapshot,
                        'location_name_snapshot' => $transaction->location_name_snapshot,
                        'unit_price' => $line->unit_price_inc_tax,
                        'total_price' => $line->line_total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            $schedule = $this->previewSchedule($data);
            if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
                foreach ($schedule as $row) {
                    DB::connection('mysql_loan')->table('loan_payment_schedules')->insert($this->filterColumns('loan_payment_schedules', [
                        'loan_id' => $loanId,
                        'due_date' => $row['due_date'],
                        'schedule_amount' => $row['total'],
                        'principal_amount' => $row['principal'],
                        'interest_amount' => $row['interest'],
                        'balance_amount' => $row['balance'],
                        'status' => 'unpaid',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
                DB::connection('mysql_loan')->table('loan_status_logs')->insert($this->filterColumns('loan_status_logs', [
                    'loan_id' => $loanId,
                    'status' => $loanPayload['status'] ?? 'pending',
                    'changed_by' => auth()->id(),
                    'note' => $data['action_type'] === 'create_approve'
                        ? 'Loan created from existing Ultimate POS sell. Stock already deducted by sell transaction.'
                        : 'Loan created from existing Ultimate POS sell.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $this->storeInitialPaymentInfo($loanId, $loanPayload, $data);

            return $loanId;
        });

        $this->notifyLocationTelegram($loanId, 'installment');

        return $loanId;
    }

    public function previewSchedule(array $data): array
    {
        $principal = (float) ($data['principal_amount'] ?? 0);
        $months = max(1, (int) ($data['duration_months'] ?? 1));
        $rate = (float) ($data['interest_rate'] ?? 0) / 100;
        $firstDue = Carbon::parse($data['first_due_date'] ?? Carbon::today()->addMonth()->toDateString());
        $frequency = $data['payment_frequency'] ?? 'monthly';

        $rows = [];
        $remaining = $principal;
        $principalPer = round($principal / $months, 2);
        $flatInterestPer = round($principal * $rate, 2);

        for ($i = 1; $i <= $months; $i++) {
            if ($frequency === 'weekly') {
                $dueDate = $firstDue->copy()->addWeeks($i - 1)->toDateString();
            } elseif ($frequency === 'daily') {
                $dueDate = $firstDue->copy()->addDays($i - 1)->toDateString();
            } else {
                $dueDate = $firstDue->copy()->addMonths($i - 1)->toDateString();
            }

            $principalPart = ($i === $months) ? round($remaining, 2) : $principalPer;
            $interest = ($data['interest_type'] ?? 'flat') === 'reducing'
                ? round($remaining * $rate, 2)
                : $flatInterestPer;
            $total = round($principalPart + $interest, 2);
            $remaining = max(0, round($remaining - $principalPart, 2));

            $rows[] = [
                'schedule_no' => $i,
                'due_date' => $dueDate,
                'principal' => $principalPart,
                'interest' => $interest,
                'total' => $total,
                'balance' => $remaining,
            ];
        }

        return $rows;
    }

    protected function upsertSnapshot(string $table, string $mainKey, $mainValue, array $payload, ?string $secondKey = null, $secondValue = null): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable($table)) return null;

        $query = DB::connection('mysql_loan')->table($table)->where($mainKey, $mainValue);
        if ($secondKey) $query->where($secondKey, $secondValue);
        $existing = $query->first();
        if ($existing) return (int) $existing->id;

        $payload['created_at'] = now();
        $payload['updated_at'] = now();
        return (int) DB::connection('mysql_loan')->table($table)->insertGetId($this->filterColumns($table, $payload));
    }

    protected function createLoanCustomerSnapshot($transaction, array $resolvedCustomer, array $data = []): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            return null;
        }

        $payload = $this->cloneCustomerSnapshot($transaction);
        $payload['main_contact_id'] = $resolvedCustomer['contact_id'] ?? ($payload['main_contact_id'] ?? null);
        $payload['name'] = $resolvedCustomer['name'] ?? ($payload['name'] ?? null);
        $payload['phone'] = $resolvedCustomer['phone'] !== '' ? $resolvedCustomer['phone'] : ($payload['phone'] ?? '-');
        $payload['address'] = $resolvedCustomer['address'] ?? ($payload['address'] ?? null);
        $payload['customer_group_name_snapshot'] = $this->resolveCustomerGroupName($data);
        $payload['business_location_id'] = $transaction->main_location_id ?? ($payload['business_location_id'] ?? null);
        $payload['created_by'] = auth()->id() ?? ($payload['created_by'] ?? null);
        $payload['status'] = $payload['status'] ?? 'active';
        $payload['blacklist_status'] = $payload['blacklist_status'] ?? 0;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        return (int) DB::connection('mysql_loan')->table('loan_customers')->insertGetId(
            $this->filterColumns('loan_customers', $payload)
        );
    }

    protected function filterColumns(string $table, array $payload): array
    {
        if (! Schema::connection('mysql_loan')->hasTable($table)) {
            return [];
        }

        $columns = Schema::connection('mysql_loan')->getColumnListing($table);

        return Arr::only($payload, $columns);
    }

    protected function resolveCustomerGroupName(array $data): string
    {
        $groupName = trim((string) ($data['customer_group_name'] ?? ''));

        return $groupName !== '' ? $groupName : 'រំលស់';
    }

    protected function resolveCustomerSnapshot($transaction): array
    {
        $contactId = !empty($transaction->contact_id) ? (int) $transaction->contact_id : null;
        $name = trim((string) ($transaction->customer_name ?? ''));
        $phone = trim((string) ($transaction->customer_phone ?? ''));
        $address = trim((string) ($transaction->customer_address ?? ''));
        $contactData = [];

        if ($contactId && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            if ($contact) {
                $contactData = (array) $contact;
                if ($name === '') {
                    $name = trim((string) ($contact->name ?? $contact->supplier_business_name ?? ''));
                }
                if ($phone === '') {
                    $phone = trim((string) ($contact->mobile ?? ''));
                }
                if ($address === '') {
                    $address = trim(implode(' ', array_filter([
                        $contact->address_line_1 ?? null,
                        $contact->address_line_2 ?? null,
                        $contact->city ?? null,
                        $contact->state ?? null,
                        $contact->country ?? null,
                        $contact->zip_code ?? null,
                    ])));
                }
            }
        }

        if ($name === '' && $phone !== '') {
            $name = 'Customer '.$phone;
        }

        return [
            'contact_id' => $contactId,
            'name' => $name !== '' ? $name : '-',
            'phone' => $phone,
            'address' => $address,
            'contact_data' => $contactData,
        ];
    }

    protected function mapResolvedCustomerToLoanCustomerPayload(array $resolvedCustomer): array
    {
        $contact = (array) ($resolvedCustomer['contact_data'] ?? []);
        if (empty($contact)) {
            return [];
        }

        $mapping = [
            'name' => ['name', 'supplier_business_name'],
            'phone' => ['mobile', 'landline'],
            'alternate_phone' => ['alternate_number'],
            'address' => ['address_line_1'],
            'email' => ['email'],
            'date_of_birth' => ['dob', 'date_of_birth'],
            'gender' => ['gender'],
            'id_card_number' => ['id_card_number', 'custom_field1'],
        ];

        return $this->mapCustomerFields($contact, $mapping);
    }

    protected function mapResolvedCustomerToLoanSnapshotPayload(array $resolvedCustomer): array
    {
        $contact = (array) ($resolvedCustomer['contact_data'] ?? []);
        if (empty($contact)) {
            return [];
        }

        $mapping = [
            'customer_email_snapshot' => ['email'],
            'customer_gender_snapshot' => ['gender'],
            'customer_dob_snapshot' => ['dob', 'date_of_birth'],
            'customer_city_snapshot' => ['city'],
            'customer_state_snapshot' => ['state'],
            'customer_country_snapshot' => ['country'],
            'customer_zip_code_snapshot' => ['zip_code'],
            'customer_id_card_snapshot' => ['id_card_number', 'custom_field1'],
            'customer_house_no_snapshot' => ['custom_field2'],
            'customer_job_snapshot' => ['custom_field3'],
            'customer_income_snapshot' => ['custom_field4'],
            'customer_landline_snapshot' => ['landline'],
            'customer_business_name_snapshot' => ['supplier_business_name'],
        ];

        return $this->mapCustomerFields($contact, $mapping);
    }

    protected function mapCustomerFields(array $contact, array $mapping): array
    {
        $payload = [];
        foreach ($mapping as $target => $sources) {
            foreach ((array) $sources as $source) {
                if (! array_key_exists($source, $contact)) {
                    continue;
                }

                $value = $contact[$source];
                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $payload[$target] = $value;
                break;
            }
        }

        return $payload;
    }

    protected function generateUniqueLoanNumber($loanLocationId = null, $mainLocationId = null): string
    {
        $prefix = $this->normalizeLoanInvoicePrefix($this->loanInvoicePrefixForLocation($loanLocationId, $mainLocationId));
        $prefix = $prefix.Carbon::now()->format('Ymd').'-';
        $attempt = 0;

        do {
            $candidate = $prefix.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $exists = DB::connection('mysql_loan')->table('loans')->where('loan_number', $candidate)->exists();
            $attempt++;
        } while ($exists && $attempt < 10);

        if ($exists) {
            // Deterministic fallback
            $candidate = $prefix.Carbon::now()->format('His').'-'.random_int(10, 99);
        }

        return $candidate;
    }

    protected function loanInvoicePrefixForLocation($loanLocationId = null, $mainLocationId = null): ?string
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')
            || ! Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'loan_invoice_prefix')) {
            return null;
        }

        $query = DB::connection('mysql_loan')->table('loan_business_locations')
            ->whereNotNull('loan_invoice_prefix')
            ->where('loan_invoice_prefix', '!=', '');

        if (! empty($loanLocationId) || ! empty($mainLocationId)) {
            $query->where(function ($q) use ($loanLocationId, $mainLocationId) {
                if (! empty($loanLocationId)) {
                    $q->orWhere('id', (int) $loanLocationId)
                        ->orWhere('main_location_id', (int) $loanLocationId);
                }

                if (! empty($mainLocationId)) {
                    $q->orWhere('main_location_id', (int) $mainLocationId)
                        ->orWhere('id', (int) $mainLocationId);
                }
            });
        }

        return $query->value('loan_invoice_prefix');
    }

    protected function normalizeLoanInvoicePrefix(?string $prefix): string
    {
        $prefix = trim((string) $prefix);
        $prefix = preg_replace('/\s+/', '', $prefix) ?: '';

        if ($prefix === '') {
            $prefix = 'LN';
        }

        return rtrim($prefix, '-/').'-';
    }

    protected function loanNumberExists(string $loanNumber): bool
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')
            || ! Schema::connection('mysql_loan')->hasColumn('loans', 'loan_number')) {
            return false;
        }

        return DB::connection('mysql_loan')->table('loans')
            ->where('loan_number', $loanNumber)
            ->exists();
    }

    protected function storeInitialPaymentInfo(int $loanId, array $loanPayload, array $data): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return;
        }

        $payment = (array) ($data['payment'] ?? []);
        $downPayment = (float) ($data['down_payment'] ?? 0);
        $amount = (float) ($payment['amount'] ?? 0);
        if ($amount <= 0) {
            $amount = $downPayment;
        }
        if ($amount <= 0) {
            return;
        }

        $paymentMethodId = ! empty($payment['payment_method_id']) ? (int) $payment['payment_method_id'] : null;
        $paymentMethod = trim((string) ($payment['method'] ?? ''));
        $paymentMethodName = 'Unknown';

        if ($paymentMethod !== '') {
            $paymentTypes = app(\App\Utils\TransactionUtil::class)->payment_types(
                $loanPayload['main_location_id'] ?? null,
                true,
                (int) (session('user.business_id') ?? 0)
            );
            $paymentMethodName = (string) ($paymentTypes[$paymentMethod] ?? ucfirst(str_replace('_', ' ', $paymentMethod)));
        } elseif (! empty($paymentMethodId) && Schema::hasTable('payment_methods')) {
            $paymentMethodName = (string) (DB::table('payment_methods')->where('id', $paymentMethodId)->value('name') ?: 'Unknown');
        }

        $paidDate = ! empty($payment['paid_date']) ? $payment['paid_date'] : ($data['loan_date'] ?? now()->toDateString());
        $exchangeRate = (float) ($payment['exchange_rate'] ?? ($data['exchange_rate'] ?? 1));
        if ($exchangeRate <= 0) {
            $exchangeRate = 1;
        }
        $currency = (string) ($payment['currency'] ?? ($data['currency'] ?? 'USD'));
        $paymentStatus = (string) ($payment['status'] ?? 'completed');
        $metaText = trim(implode(' | ', array_filter([
            ! empty($payment['channel']) ? 'Channel: '.$payment['channel'] : null,
            ! empty($payment['account_name']) ? 'Account Name: '.$payment['account_name'] : null,
            ! empty($payment['account_number']) ? 'Account Number: '.$payment['account_number'] : null,
            ! empty($payment['transaction_id']) ? 'Transaction ID: '.$payment['transaction_id'] : null,
        ])));
        $combinedNote = trim(implode("\n", array_filter([
            $payment['note'] ?? null,
            $metaText ?: null,
        ])));

        $paymentPayload = $this->filterColumns('loan_payments', [
            'loan_id' => $loanId,
            'payment_number' => $this->generateUniquePaymentNumber($loanId),
            'receipt_number' => 'RCP-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-'.random_int(10, 99),
            'loan_number_snapshot' => $loanPayload['loan_number'] ?? null,
            'customer_name_snapshot' => $loanPayload['customer_name_snapshot'] ?? null,
            'customer_phone_snapshot' => $loanPayload['customer_phone_snapshot'] ?? null,
            'payment_method_snapshot' => $paymentMethodName,
            'paid_date' => $paidDate,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'total_paid' => $amount,
            'total_paid_base' => round($amount * $exchangeRate, 4),
            'reference_number' => $payment['reference_number'] ?? null,
            'note' => $combinedNote ?: null,
            'status' => $paymentStatus,
            'received_by' => auth()->id(),
            'received_by_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (empty($paymentPayload)) {
            return;
        }

        $paymentId = 0;
        $attempt = 0;
        do {
            try {
                if ($attempt > 0) {
                    if (array_key_exists('payment_number', $paymentPayload)) {
                        $paymentPayload['payment_number'] = $this->generateUniquePaymentNumber($loanId);
                    }
                    if (array_key_exists('receipt_number', $paymentPayload)) {
                        $paymentPayload['receipt_number'] = 'RCP-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-'.random_int(10, 99);
                    }
                }
                $paymentId = (int) DB::connection('mysql_loan')->table('loan_payments')->insertGetId($paymentPayload);
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                $attempt++;
                if ($attempt >= 3) {
                    throw $e;
                }
                usleep(150000);
            }
        } while ($attempt < 3);

        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_details')) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_payment_details')->insert($this->filterColumns('loan_payment_details', [
            'payment_id' => $paymentId,
            'payment_method_id' => $paymentMethodId,
            'payment_method_snapshot' => $paymentMethodName,
            'method' => $paymentMethod !== '' ? $paymentMethod : $paymentMethodName,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => $exchangeRate,
            'amount_base' => round($amount * $exchangeRate, 4),
            'reference_number' => $payment['reference_number'] ?? null,
            'note' => $combinedNote ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function generateUniquePaymentNumber(int $loanId): string
    {
        $prefix = 'PAY-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-';
        $attempt = 0;

        do {
            $candidate = $prefix.random_int(1000, 9999);
            $exists = DB::connection('mysql_loan')->table('loan_payments')
                ->where('payment_number', $candidate)
                ->exists();
            $attempt++;
        } while ($exists && $attempt < 10);

        if ($exists) {
            $candidate = $prefix.uniqid();
        }

        return $candidate;
    }

    protected function notifyLocationTelegram(int $loanId, string $event): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans') || ! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return;
        }

        $loan = DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }

        $location = null;
        if (! empty($loan->business_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loan->business_location_id)->first();
        }
        if (! $location && ! empty($loan->main_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $loan->main_location_id)->first();
        }

        if (! $location || empty($location->telegram_notify_installment)) {
            return;
        }

        $chatId = $this->telegramChatIdForEvent($location, $event);
        if ($chatId === '') {
            return;
        }

        $message = "Installment loan created\nLoan: ".($loan->loan_number ?? $loan->id)."\nCustomer: ".($loan->customer_name_snapshot ?? '-')."\nLocation: ".($location->name ?? '-')."\nTotal: ".number_format((float) ($loan->principal_amount ?? $loan->total_payable_amount ?? 0), 2).' '.($loan->currency ?? 'USD');

        try {
            app(TelegramBotService::class)->sendMessageToChat($chatId, $message);
            $this->logTelegramNotification($loan, $location, $event, $message, 'sent', $chatId);
        } catch (\Throwable $e) {
            Log::warning('LoanManagement installment Telegram notification failed', [
                'loan_id' => $loanId,
                'message' => $e->getMessage(),
            ]);
            $this->logTelegramNotification($loan, $location, $event, $message."\n\nError: ".$e->getMessage(), 'failed', $chatId);
        }
    }

    protected function telegramChatIdForEvent(object $location, string $event): string
    {
        $chatId = $event === 'payment'
            ? ($location->telegram_payment_chat_id ?? null)
            : ($location->telegram_installment_chat_id ?? null);

        return trim((string) ($chatId ?: ($location->telegram_chat_id ?? '')));
    }

    protected function logTelegramNotification(object $loan, object $location, string $event, string $message, string $status, ?string $chatId = null): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_telegram_notifications')) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_telegram_notifications')->insert($this->filterColumns('loan_telegram_notifications', [
            'loan_id' => $loan->id ?? null,
            'customer_id' => $loan->customer_id ?? null,
            'event_code' => $event,
            'chat_id' => $chatId ?: $this->telegramChatIdForEvent($location, $event),
            'message' => $message,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
