<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\LoanManagement\Services\LoanToPosPrefillService;
use Yajra\DataTables\Facades\DataTables;

class LoanInstallmentListController extends Controller
{
    protected static array $loanTableExistsCache = [];
    protected static array $loanColumnCache = [];

    protected function hasCol(string $col): bool
    {
        return in_array($col, $this->loanTableColumns('loans'), true);
    }

    protected function loanTableHasCol(string $table, string $col): bool
    {
        return in_array($col, $this->loanTableColumns($table), true);
    }

    protected function loanSafeColumns(string $table, array $payload): array
    {
        $columns = $this->loanTableColumns($table);
        if (empty($columns)) {
            return [];
        }

        return array_intersect_key($payload, array_flip($columns));
    }

    protected function attachLoanCustomerKhmerName(object $loanRow): object
    {
        $loanRow->customer_khmer_name = null;
        $loanRow->customer_english_name = null;

        if (empty($loanRow->customer_id)
            || ! $this->loanTableExists('loan_customers')) {
            return $loanRow;
        }

        $select = ['name'];
        if ($this->loanTableHasCol('loan_customers', 'khmer_name')) {
            $select[] = 'khmer_name';
        }

        $customer = DB::connection('mysql_loan')
            ->table('loan_customers')
            ->select($select)
            ->where('id', $loanRow->customer_id)
            ->first();

        if ($customer) {
            $loanRow->customer_khmer_name = $customer->khmer_name ?? null;
            $loanRow->customer_english_name = $customer->name ?? null;
        }

        return $loanRow;
    }

    protected function buildLoanPaymentCopyInfo(int $loan, object $loanRow): array
    {
        $customerRow = null;
        if ($this->loanTableExists('loan_customers') && ! empty($loanRow->customer_id)) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }

        $loanItems = collect();
        if ($this->loanTableExists('loan_items')) {
            $loanItemQuery = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($loanItemQuery, 'loan_items');
            $loanItems = $loanItemQuery->orderBy('id')->get();
        }

        $loanMeta = ! empty($loanRow->meta_json) ? (json_decode((string) $loanRow->meta_json, true) ?: []) : [];
        $depositAmounts = $this->loanDepositPaymentCopyAmounts($loan, $loanRow);
        $firstDueDate = $loanRow->first_due_date ?? $loanMeta['first_due_date'] ?? null;

        return [
            'invoice' => $loanRow->source_invoice_no ?? $loanRow->loan_number ?? $loanRow->id,
            'name_khmer' => trim((string) ($loanRow->customer_khmer_name ?? ''))
                ?: (trim((string) ($customerRow->khmer_name ?? '')) ?: ($loanRow->customer_name_snapshot ?? '-')),
            'phone' => $loanRow->customer_phone_snapshot ?? ($customerRow->phone ?? $customerRow->mobile ?? $customerRow->login_phone ?? '-'),
            'id_card' => $customerRow->id_card_number ?? $customerRow->national_id ?? $customerRow->id_number ?? '-',
            'village' => $customerRow->village ?? '-',
            'commune' => $customerRow->commune ?? '-',
            'district' => $customerRow->district ?? '-',
            'province' => $customerRow->province ?? '-',
            'product' => $this->loanCopyProductNames($loanItems, $loanRow),
            'serial_number' => $loanItems->map(fn ($item) => trim((string) ($item->serial_number_snapshot ?? $item->serial_number ?? '')))->filter()->implode(', ') ?: '-',
            'qty' => $loanItems->map(fn ($item) => (string) ($item->qty ?? $item->quantity ?? 1))->filter()->implode(', ') ?: '1',
            'unit_price' => $loanItems->map(fn ($item) => number_format((float) ($item->unit_price ?? 0), 2, '.', ''))->filter(fn ($value) => (float) $value > 0)->implode(', ') ?: number_format((float) ($loanRow->principal_amount ?? 0), 2, '.', ''),
            'amount_cash' => $depositAmounts['cash'],
            'amount_bank' => $depositAmounts['bank'],
            'first_due' => $firstDueDate ? \Carbon\Carbon::parse($firstDueDate)->format('Y-m-d') : '',
            'duration_m' => (int) ($loanRow->duration_months ?? $loanRow->installment_count ?? $loanMeta['duration_months'] ?? 0),
            'interest_percent' => number_format(((float) ($loanRow->interest_rate ?? $loanMeta['interest_rate'] ?? 0)) / 100, 2, '.', ''),
        ];
    }

    protected function loanCopyProductNames(\Illuminate\Support\Collection $loanItems, object $loanRow): string
    {
        $names = $loanItems->map(function ($item) {
            $serials = collect([
                $item->serial_number_snapshot ?? null,
                $item->serial_number ?? null,
                $item->imei_snapshot ?? null,
                $item->imei ?? null,
            ])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values();

            foreach ($serials as $serial) {
                $posProduct = $this->posProductNameBySerial($serial);
                if ($posProduct !== null) {
                    return $posProduct;
                }
            }

            return trim((string) ($item->product_name_snapshot ?? $item->product_name ?? ''));
        })->filter()->unique()->implode(', ');

        return $names !== '' ? $names : ($loanRow->product_name_snapshot ?? '-');
    }

    protected function posProductNameBySerial(string $serial): ?string
    {
        static $cache = [];

        $serial = trim($serial);
        if ($serial === '') {
            return null;
        }

        if (array_key_exists($serial, $cache)) {
            return $cache[$serial];
        }

        return $cache[$serial] = $this->posProductNameFromSmartImei($serial)
            ?? $this->posProductNameFromSellSerialStatus($serial)
            ?? $this->posProductNameFromSmartStockInventoryLine($serial)
            ?? $this->posProductNameFromPurchaseLot($serial)
            ?? $this->posProductNameFromVariationSku($serial);
    }

    protected function posProductNameFromSmartImei(string $serial): ?string
    {
        if (! Schema::hasTable('smart_imei_histories')) {
            return null;
        }

        $query = DB::table('smart_imei_histories as si')
            ->leftJoin('variations as v', 'v.id', '=', 'si.variation_id')
            ->leftJoin('products as vp', 'vp.id', '=', 'v.product_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.imei', $serial);

        if (Schema::hasColumn('smart_imei_histories', 'business_id') && session('user.business_id')) {
            $query->where('si.business_id', session('user.business_id'));
        }
        if (Schema::hasColumn('smart_imei_histories', 'deleted_at')) {
            $query->whereNull('si.deleted_at');
        }

        $row = $query
            ->selectRaw('COALESCE(NULLIF(p.name, ""), NULLIF(vp.name, "")) as product_name, v.name as variation_name')
            ->orderByDesc(Schema::hasColumn('smart_imei_histories', 'movement_date') ? 'si.movement_date' : 'si.id')
            ->first();

        return $this->formatPosProductName($row);
    }

    protected function posProductNameFromSellSerialStatus(string $serial): ?string
    {
        if (! Schema::hasTable('pos_sell_list_serial_statuses')) {
            return null;
        }

        $row = DB::table('pos_sell_list_serial_statuses as ps')
            ->leftJoin('transaction_sell_lines as tsl', 'tsl.id', '=', 'ps.transaction_sell_line_id')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('ps.serial_number', $serial)
            ->selectRaw('p.name as product_name, v.name as variation_name')
            ->orderByDesc('ps.id')
            ->first();

        return $this->formatPosProductName($row);
    }

    protected function posProductNameFromSmartStockInventoryLine(string $serial): ?string
    {
        if (! Schema::hasTable('smart_stock_inventory_lines')) {
            return null;
        }

        $serialColumns = collect(['imei', 'lot_number', 'sku'])
            ->filter(fn ($column) => Schema::hasColumn('smart_stock_inventory_lines', $column))
            ->values();

        if ($serialColumns->isEmpty()) {
            return null;
        }

        $query = DB::table('smart_stock_inventory_lines as sil')
            ->leftJoin('variations as v', 'v.id', '=', 'sil.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'sil.product_id')
            ->where(function ($query) use ($serial, $serialColumns) {
                foreach ($serialColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('sil.'.$column, $serial);
                }
            });

        if (Schema::hasColumn('smart_stock_inventory_lines', 'deleted_at')) {
            $query->whereNull('sil.deleted_at');
        }

        $row = $query
            ->selectRaw('COALESCE(NULLIF(sil.product_name, ""), NULLIF(p.name, "")) as product_name, COALESCE(NULLIF(sil.variation_name, ""), NULLIF(v.name, "")) as variation_name')
            ->orderByDesc('sil.id')
            ->first();

        return $this->formatPosProductName($row);
    }

    protected function posProductNameFromPurchaseLot(string $serial): ?string
    {
        if (! Schema::hasTable('purchase_lines') || ! Schema::hasColumn('purchase_lines', 'lot_number')) {
            return null;
        }

        $row = DB::table('purchase_lines as pl')
            ->leftJoin('variations as v', 'v.id', '=', 'pl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'pl.product_id')
            ->where('pl.lot_number', $serial)
            ->selectRaw('p.name as product_name, v.name as variation_name')
            ->orderByDesc('pl.id')
            ->first();

        return $this->formatPosProductName($row);
    }

    protected function posProductNameFromVariationSku(string $serial): ?string
    {
        if (! Schema::hasTable('variations') || ! Schema::hasTable('products')) {
            return null;
        }

        $row = DB::table('variations as v')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->where(function ($query) use ($serial) {
                $query->where('v.sub_sku', $serial);
                if (Schema::hasColumn('products', 'sku')) {
                    $query->orWhere('p.sku', $serial);
                }
            })
            ->selectRaw('p.name as product_name, v.name as variation_name')
            ->orderByDesc('v.id')
            ->first();

        return $this->formatPosProductName($row);
    }

    protected function formatPosProductName($row): ?string
    {
        if (empty($row)) {
            return null;
        }

        $product = trim((string) ($row->product_name ?? ''));
        if ($product === '') {
            return null;
        }

        $variation = trim((string) ($row->variation_name ?? ''));
        if ($variation !== '' && strcasecmp($variation, 'DUMMY') !== 0 && strcasecmp($variation, $product) !== 0) {
            return $product.' '.$variation;
        }

        return $product;
    }

    protected function loanDepositPaymentCopyAmounts(int $loan, object $loanRow): array
    {
        $cash = 0.0;
        $bank = 0.0;

        if ($this->loanTableExists('loan_payments')) {
            $query = DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($query, 'loan_payments');

            if ($this->loanTableHasCol('loan_payments', 'payment_type')) {
                $query->whereIn('payment_type', ['loan', 'initial', 'down_payment', 'downpayment', 'deposit']);
            } elseif ($this->loanTableHasCol('loan_payments', 'schedule_id')) {
                $query->whereNull('schedule_id');
            }

            foreach ($query->get() as $payment) {
                if ($this->loanTableHasCol('loan_payments', 'payment_type') && ! $this->isLoanPaymentRow($payment)) {
                    continue;
                }

                if (! $this->loanTableHasCol('loan_payments', 'payment_type') && ! empty($payment->schedule_id)) {
                    continue;
                }

                $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $method = strtolower(trim((string) ($payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '')));
                if ($method === 'cash') {
                    $cash += $amount;
                } else {
                    $bank += $amount;
                }
            }
        }

        if ($cash <= 0 && $bank <= 0 && (float) ($loanRow->down_payment ?? 0) > 0) {
            $cash = (float) $loanRow->down_payment;
        }

        return [
            'cash' => number_format($cash, 2, '.', ''),
            'bank' => number_format($bank, 2, '.', ''),
        ];
    }

    protected function formatLoanPaymentCopyText(array $info): string
    {
        return collect([
            $info['invoice'] ?? '',
            $info['name_khmer'] ?? '',
            $info['phone'] ?? '',
            $info['id_card'] ?? '',
            $info['village'] ?? '',
            $info['commune'] ?? '',
            $info['district'] ?? '',
            $info['province'] ?? '',
            $info['product'] ?? '',
            $info['qty'] ?? '',
            $info['unit_price'] ?? '',
            $info['amount_cash'] ?? '0.00',
            $info['amount_bank'] ?? '0.00',
            $info['duration_m'] ?? '',
            $info['interest_percent'] ?? '0.00',
            $info['first_due'] ?? '',
            $info['serial_number'] ?? '',
        ])->map(fn ($value) => trim((string) $value))->implode(',');
    }

    protected function loanTableExists(string $table): bool
    {
        if (! array_key_exists($table, self::$loanTableExistsCache)) {
            self::$loanTableExistsCache[$table] = Schema::connection('mysql_loan')->hasTable($table);
        }

        return self::$loanTableExistsCache[$table];
    }

    protected function loanTableColumns(string $table): array
    {
        if (! array_key_exists($table, self::$loanColumnCache)) {
            self::$loanColumnCache[$table] = $this->loanTableExists($table)
                ? Schema::connection('mysql_loan')->getColumnListing($table)
                : [];
        }

        return self::$loanColumnCache[$table];
    }

    protected function ensureLoanPaymentTypeColumn(): void
    {
        if (! $this->loanTableExists('loan_payments')
            || $this->loanTableHasCol('loan_payments', 'payment_type')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loan_payments', function ($table) {
            $table->string('payment_type', 20)->default('monthly')->after('loan_id');
        });
    }

    protected function assetFromPublicPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (preg_match('#^uploads/loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (preg_match('#^loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        if (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (Str::startsWith($path, 'storage/') && file_exists(storage_path('app/public/'.substr($path, 8)))) {
            return asset($path);
        }

        return null;
    }

    protected function moduleLocationAssetPath(int $location, string $filename): ?string
    {
        if (Str::contains($filename, ['/', '\\']) || $filename !== basename($filename)) {
            return null;
        }

        $path = base_path('Modules/LoanManagement/loan_location_assets/'.$location.'/'.$filename);

        return is_file($path) ? $path : null;
    }

    protected function fileDataUri(?string $path): ?string
    {
        if (empty($path) || ! is_file($path)) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;
        if (empty($mime) || ! Str::startsWith($mime, 'image/')) {
            $mime = 'image/'.strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpeg');
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    protected function firstExistingPublicAsset(array $paths): ?string
    {
        foreach ($paths as $path) {
            $asset = $this->assetFromPublicPath($path);
            if (! empty($asset)) {
                return $asset;
            }
        }

        return null;
    }

    protected function businessLogoAsset(): ?string
    {
        $logoName = session('business.logo');
        if (empty($logoName) && Schema::hasTable('business')) {
            $businessId = session('business.id') ?: (auth()->user()->business_id ?? null);
            if (! empty($businessId) && Schema::hasColumn('business', 'logo')) {
                $logoName = DB::table('business')->where('id', $businessId)->value('logo');
            }
        }

        $paths = [];
        if (! empty($logoName)) {
            $paths[] = 'uploads/business_logos/'.$logoName;
            $paths[] = 'storage/business_logos/'.$logoName;
            $paths[] = 'business_logos/'.$logoName;
            $paths[] = $logoName;
        }

        return $this->firstExistingPublicAsset(array_merge($paths, [
            'uploads/logo.png',
            'img/logo.png',
            'logo.png',
        ]));
    }

    protected function paymentsForPrintSchedules($payments, $installments)
    {
        $downOrLoanPayments = $payments
            ->filter(fn ($payment) => $this->isLoanPaymentRow($payment))
            ->map(function ($payment) {
                $payment->_print_schedule_id = null;
                return $payment;
            });

        $monthlyPayments = $payments->reject(fn ($payment) => $this->isLoanPaymentRow($payment));

        $assigned = $payments
            ->reject(fn ($payment) => $this->isLoanPaymentRow($payment))
            ->filter(fn ($payment) => ! empty($payment->schedule_id))
            ->map(function ($payment) {
                $payment->_print_schedule_id = $payment->schedule_id;
                $payment->_print_amount = (float) ($payment->total_paid_base ?? $payment->amount ?? 0);

                return $payment;
            });

        $unassigned = $monthlyPayments
            ->filter(fn ($payment) => empty($payment->schedule_id))
            ->sortBy(fn ($payment) => (($payment->paid_date ?? $payment->paid_at ?? null) ?: '9999-12-31')
                .'-'.str_pad((string) ($payment->id ?? 0), 10, '0', STR_PAD_LEFT))
            ->values();

        if ($unassigned->isEmpty()) {
            return $assigned->concat($downOrLoanPayments)->values();
        }

        $allocated = collect();
        $assignedAmounts = $assigned->groupBy('_print_schedule_id')->map(function ($rows) {
            return (float) $rows->sum(fn ($payment) => (float) ($payment->_print_amount ?? $payment->total_paid_base ?? $payment->amount ?? 0));
        });
        $scheduleRemaining = $installments->mapWithKeys(function ($row) {
            $paid = (float) ($row->paid_value ?? 0);

            return [$row->id => max(0, $paid)];
        })->map(function ($paid, $scheduleId) use ($assignedAmounts) {
            return max(0, round((float) $paid - (float) ($assignedAmounts[$scheduleId] ?? 0), 2));
        });

        foreach ($unassigned as $payment) {
            $remainingPayment = (float) ($payment->total_paid_base ?? $payment->amount ?? 0);
            if ($remainingPayment <= 0) {
                continue;
            }

            foreach ($installments as $row) {
                $remainingSchedule = (float) ($scheduleRemaining[$row->id] ?? 0);
                if ($remainingSchedule <= 0) {
                    continue;
                }

                $amount = min($remainingPayment, $remainingSchedule);
                $line = clone $payment;
                $line->_print_schedule_id = $row->id;
                $line->_print_amount = $amount;
                $allocated->push($line);

                $remainingPayment -= $amount;
                $scheduleRemaining[$row->id] = $remainingSchedule - $amount;

                if ($remainingPayment <= 0) {
                    break;
                }
            }

            if ($remainingPayment > 0) {
                $line = clone $payment;
                $line->_print_schedule_id = null;
                $line->_print_amount = $remainingPayment;
                $allocated->push($line);
            }
        }

        return $assigned->concat($allocated)->concat($downOrLoanPayments)->values();
    }

    protected function isLoanPaymentRow($payment): bool
    {
        $type = strtolower(trim((string) ($payment->payment_type ?? '')));
        if (in_array($type, ['loan', 'initial', 'down_payment', 'downpayment', 'deposit'], true)) {
            return true;
        }

        $reference = strtoupper(trim((string) (
            $payment->receipt_number
            ?? $payment->payment_ref_no
            ?? $payment->reference_number
            ?? $payment->payment_number
            ?? ''
        )));

        return strpos($reference, 'IMP-DOWN-') === 0;
    }

    protected function applyMonthlyPaymentFilter($query, string $table = 'loan_payments'): void
    {
        if ($this->loanTableHasCol($table, 'payment_type')) {
            $query->where('payment_type', 'monthly');
            return;
        }

        if ($this->loanTableHasCol($table, 'schedule_id')) {
            $query->whereNotNull('schedule_id');
        }

        foreach (['receipt_number', 'payment_ref_no', 'reference_number', 'payment_number'] as $column) {
            if ($this->loanTableHasCol($table, $column)) {
                $query->where($column, 'not like', 'IMP-DOWN-%');
            }
        }
    }

    protected function expandPaymentsWithDetailsForPrint($payments)
    {
        $paymentIds = $payments->pluck('id')->filter()->unique()->values();
        if ($paymentIds->isEmpty() || ! $this->loanTableExists('loan_payment_details')) {
            return $payments;
        }

        $detailColumns = $this->loanTableColumns('loan_payment_details');
        $selectColumns = array_values(array_intersect([
            'id',
            'payment_id',
            'payment_method_snapshot',
            'method',
            'amount_base',
            'amount',
        ], $detailColumns));

        if (! in_array('payment_id', $selectColumns, true)) {
            return $payments;
        }

        $detailsByPayment = DB::connection('mysql_loan')
            ->table('loan_payment_details')
            ->select($selectColumns)
            ->whereIn('payment_id', $paymentIds)
            ->get()
            ->groupBy('payment_id');

        if ($detailsByPayment->isEmpty()) {
            return $payments;
        }

        return $payments->flatMap(function ($payment) use ($detailsByPayment) {
            $details = $detailsByPayment->get($payment->id, collect());
            if ($details->isEmpty()) {
                return [$payment];
            }

            return $details->map(function ($detail) use ($payment) {
                $line = clone $payment;
                $method = trim((string) ($detail->payment_method_snapshot ?? $detail->method ?? ''));

                if ($method !== '' && strtolower($method) !== 'unknown') {
                    $line->payment_method_snapshot = $method;
                    $line->channel = $method;
                    $line->method = $detail->method ?? $method;
                }

                $amount = (float) ($detail->amount_base ?? $detail->amount ?? 0);
                if ($amount > 0) {
                    $line->total_paid_base = $amount;
                    $line->amount = $amount;
                }

                return $line;
            });
        })->values();
    }

    protected function coreLocationNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('business_locations')) {
            return [];
        }

        return DB::table('business_locations')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    protected function loanLocationNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! $this->loanTableExists('loan_business_locations')) {
            return [];
        }

        return DB::connection('mysql_loan')->table('loan_business_locations')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    protected function coreLocationIdsByName(string $name): array
    {
        if (! Schema::hasTable('business_locations')) {
            return [];
        }

        return DB::table('business_locations')
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function loanLocationIdsByName(string $name): array
    {
        if (! $this->loanTableExists('loan_business_locations')) {
            return [];
        }

        return DB::connection('mysql_loan')->table('loan_business_locations')
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveLocationDisplay($row): string
    {
        $snapshot = trim((string) ($row->location_name_snapshot ?? ''));
        if ($snapshot !== '' && ! preg_match('/^Location #\d+$/', $snapshot)) {
            return $snapshot;
        }

        $loanLocationId = $row->business_location_id ?? null;
        $loanNames = $this->loanLocationNames([$loanLocationId]);
        if (! empty($loanNames[$loanLocationId])) {
            return $loanNames[$loanLocationId];
        }

        $mainLocationId = $row->main_location_id ?? null;
        $coreNames = $this->coreLocationNames([$mainLocationId]);
        if (! empty($coreNames[$mainLocationId])) {
            return $coreNames[$mainLocationId];
        }

        return $loanLocationId ? 'Location #'.$loanLocationId : '-';
    }

    protected function coreUserNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->selectRaw("id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''), username) as display_name")
            ->pluck('display_name', 'id')
            ->all();
    }

    public function index()
    {
        $locations = [];
        $collectors = [];

        if ($this->loanTableExists('loans')) {
            $collectorIdCol = $this->hasCol('collector_id')
                ? 'collector_id'
                : ($this->hasCol('assigned_to') ? 'assigned_to' : null);

            if ($this->hasCol('location_name_snapshot')) {
                $locations = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('location_name_snapshot')
                    ->where('location_name_snapshot', '!=', '')
                    ->distinct()
                    ->orderBy('location_name_snapshot')
                    ->pluck('location_name_snapshot', 'location_name_snapshot')
                    ->all();
            }
            if ($this->hasCol('business_location_id')) {
                $loanLocationIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('business_location_id')
                    ->distinct()
                    ->orderBy('business_location_id')
                    ->pluck('business_location_id');
                foreach ($this->loanLocationNames($loanLocationIds) as $id => $name) {
                    $locations[$name] = $name;
                }
            }
            if ($this->hasCol('main_location_id')) {
                $mainLocationIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('main_location_id')
                    ->distinct()
                    ->orderBy('main_location_id')
                    ->pluck('main_location_id');
                foreach ($this->coreLocationNames($mainLocationIds) as $id => $name) {
                    $locations[$name] = $name;
                }
            }

            if ($this->hasCol('collector_name_snapshot')) {
                $collectors = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('collector_name_snapshot')
                    ->where('collector_name_snapshot', '!=', '')
                    ->distinct()
                    ->orderBy('collector_name_snapshot')
                    ->pluck('collector_name_snapshot', 'collector_name_snapshot')
                    ->all();
            }
            if ($collectorIdCol) {
                $collectorIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull($collectorIdCol)
                    ->distinct()
                    ->orderBy($collectorIdCol)
                    ->pluck($collectorIdCol);
                foreach ($this->coreUserNames($collectorIds) as $id => $name) {
                    $collectors[$id] = $name;
                }
            }
        }

        return view('loanmanagement::loans.index', compact('locations', 'collectors'));
    }

    public function data(Request $request)
    {
        if (! $this->loanTableExists('loans')) {
            return DataTables::of(collect([]))->make(true);
        }

        $canJoinCustomersForKhmerName = $this->hasCol('customer_id')
            && $this->loanTableExists('loan_customers')
            && $this->loanTableHasCol('loan_customers', 'khmer_name');
        $customerNameExpr = $canJoinCustomersForKhmerName
            ? 'COALESCE(NULLIF(c.khmer_name, ""), '.($this->hasCol('customer_name_snapshot') ? 'l.customer_name_snapshot' : 'NULL').')'
            : ($this->hasCol('customer_name_snapshot') ? 'l.customer_name_snapshot' : 'NULL');

        $q = DB::connection('mysql_loan')->table('loans as l')
            ->when($canJoinCustomersForKhmerName, function ($query) {
                $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
            })
            ->selectRaw(
                'l.id, '.
                ($this->hasCol('loan_number') ? 'l.loan_number' : 'CAST(l.id as CHAR)').' as loan_number, '.
                ($this->hasCol('loan_date') ? 'l.loan_date' : 'l.created_at').' as loan_date, '.
                $customerNameExpr.' as customer_name_snapshot, '.
                ($this->hasCol('customer_phone_snapshot') ? 'l.customer_phone_snapshot' : 'NULL').' as customer_phone_snapshot, '.
                ($this->hasCol('main_location_id') ? 'l.main_location_id' : 'NULL').' as main_location_id, '.
                ($this->hasCol('business_location_id') ? 'l.business_location_id' : 'NULL').' as business_location_id, '.
                ($this->hasCol('location_name_snapshot') ? 'l.location_name_snapshot' : ($this->hasCol('business_location_id') ? "CONCAT('Location #', l.business_location_id)" : 'NULL')).' as location_name_snapshot, '.
                ($this->hasCol('principal_amount') ? 'l.principal_amount' : '0').' as principal_amount, '.
                ($this->hasCol('paid_amount') ? 'l.paid_amount' : '0').' as paid_amount, '.
                ($this->hasCol('balance_amount') ? 'l.balance_amount' : '0').' as balance_amount, '.
                ($this->hasCol('status') ? 'l.status' : "'pending'").' as status, '.
                ($this->hasCol('currency') ? 'l.currency' : "'USD'").' as currency, '.
                ($this->hasCol('source_invoice_no') ? 'l.source_invoice_no' : 'NULL').' as source_invoice_no, '.
                ($this->hasCol('collector_id') ? 'l.collector_id' : 'NULL').' as collector_id, '.
                ($this->hasCol('assigned_to') ? 'l.assigned_to' : 'NULL').' as assigned_to, '.
                ($this->hasCol('collector_name_snapshot') ? 'l.collector_name_snapshot' : ($this->hasCol('collector_id') ? "CONCAT('Collector #', l.collector_id)" : 'NULL')).' as collector_name_snapshot'
            );

        if ($this->hasCol('loan_date')) {
            if ($request->filled('start_date')) $q->whereDate('l.loan_date', '>=', $request->start_date);
            if ($request->filled('end_date')) $q->whereDate('l.loan_date', '<=', $request->end_date);
        }
        if ($request->filled('status') && $this->hasCol('status')) $q->where('l.status', $request->status);
        if ($request->filled('location_name')) {
            $locationFilter = (string) $request->location_name;
            $q->where(function ($query) use ($locationFilter) {
                if ($this->hasCol('location_name_snapshot')) {
                    $query->orWhere('l.location_name_snapshot', $locationFilter);
                }
                $loanLocationIds = $this->loanLocationIdsByName($locationFilter);
                if (! empty($loanLocationIds) && $this->hasCol('business_location_id')) {
                    $query->orWhereIn('l.business_location_id', $loanLocationIds);
                }
                $coreLocationIds = $this->coreLocationIdsByName($locationFilter);
                if (! empty($coreLocationIds) && $this->hasCol('main_location_id')) {
                    $query->orWhereIn('l.main_location_id', $coreLocationIds);
                }
                if (is_numeric($locationFilter)) {
                    if ($this->hasCol('main_location_id')) $query->orWhere('l.main_location_id', (int) $locationFilter);
                    if ($this->hasCol('business_location_id')) $query->orWhere('l.business_location_id', (int) $locationFilter);
                }
            });
        }
        if ($request->filled('collector_name')) {
            $collectorFilter = (string) $request->collector_name;
            $q->where(function ($query) use ($collectorFilter) {
                if ($this->hasCol('collector_name_snapshot')) {
                    $query->orWhere('l.collector_name_snapshot', $collectorFilter);
                }
                if (is_numeric($collectorFilter)) {
                    if ($this->hasCol('collector_id')) $query->orWhere('l.collector_id', (int) $collectorFilter);
                    if ($this->hasCol('assigned_to')) $query->orWhere('l.assigned_to', (int) $collectorFilter);
                }
            });
        }
        if ($request->filled('customer')) {
            $q->where(function ($query) use ($request, $canJoinCustomersForKhmerName) {
                $like = '%'.$request->customer.'%';
                if ($this->hasCol('customer_name_snapshot')) {
                    $query->where('l.customer_name_snapshot', 'like', $like);
                }
                if ($canJoinCustomersForKhmerName) {
                    $query->orWhere('c.khmer_name', 'like', $like);
                }
            });
        }

        if ($this->hasCol('loan_date')) {
            $q->orderByDesc('l.loan_date');
        } else {
            $q->orderByDesc('l.created_at');
        }
        $q->orderByDesc('l.id');

        return DataTables::of($q)
            ->filter(function ($query) use ($request, $canJoinCustomersForKhmerName) {
                $search = trim((string) data_get($request->all(), 'search.value', ''));
                if ($search === '') {
                    return;
                }

                $like = '%'.$search.'%';
                $query->where(function ($where) use ($like, $canJoinCustomersForKhmerName) {
                    $hasCondition = false;

                    foreach ([
                        ['loan_number', 'l.loan_number'],
                        ['loan_date', 'l.loan_date'],
                        ['source_invoice_no', 'l.source_invoice_no'],
                        ['customer_name_snapshot', 'l.customer_name_snapshot'],
                        ['customer_phone_snapshot', 'l.customer_phone_snapshot'],
                        ['business_location_name_snapshot', 'l.business_location_name_snapshot'],
                        ['collector_name_snapshot', 'l.collector_name_snapshot'],
                        ['principal_amount', 'l.principal_amount'],
                        ['paid_amount', 'l.paid_amount'],
                        ['balance_amount', 'l.balance_amount'],
                        ['status', 'l.status'],
                        ['currency', 'l.currency'],
                    ] as [$column, $qualified]) {
                        if (! $this->hasCol($column)) {
                            continue;
                        }

                        $hasCondition
                            ? $where->orWhere($qualified, 'like', $like)
                            : $where->where($qualified, 'like', $like);

                        $hasCondition = true;
                    }

                    if ($canJoinCustomersForKhmerName) {
                        $hasCondition
                            ? $where->orWhere('c.khmer_name', 'like', $like)
                            : $where->where('c.khmer_name', 'like', $like);
                    }
                });
            })
            ->editColumn('principal_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->principal_amount.'</span>')
            ->editColumn('paid_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->paid_amount.'</span>')
            ->editColumn('balance_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->balance_amount.'</span>')
            ->editColumn('location_name_snapshot', function ($r) {
                return e($this->resolveLocationDisplay($r));
            })
            ->editColumn('collector_name_snapshot', function ($r) {
                $snapshot = trim((string) ($r->collector_name_snapshot ?? ''));
                if ($snapshot !== '' && ! preg_match('/^Collector #\d+$/', $snapshot)) {
                    return e($snapshot);
                }

                $id = $r->collector_id ?? $r->assigned_to ?? null;
                $names = $this->coreUserNames([$id]);

                return e($names[$id] ?? ($id ? 'Collector #'.$id : '-'));
            })
            ->editColumn('status', function ($r) {
                $map = ['draft' => 'default', 'pending' => 'warning', 'approved' => 'info', 'active' => 'primary', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'default', 'defaulted' => 'danger'];
                $status = strtolower((string) ($r->status ?? 'pending'));
                $c = $map[$status] ?? 'default';
                $user = auth()->user();
                $canApprove = $user instanceof \Illuminate\Contracts\Auth\Authenticatable
                    && \Illuminate\Support\Facades\Gate::forUser($user)->allows('loan_management.approve');

                if (! $canApprove) {
                    return '<span class="label label-'.$c.'">'.ucfirst($status).'</span>';
                }

                $options = '';
                foreach (array_keys($map) as $value) {
                    $options .= '<option value="'.e($value).'"'.($value === $status ? ' selected' : '').'>'.e(ucfirst($value)).'</option>';
                }

                return '<select class="form-control input-sm js-loan-status-select loan-status-select status-'.$c.'" data-original-status="'.e($status).'" data-url="'.route('loan-management.loans.status', $r->id).'" style="min-width:120px;">'.$options.'</select>';
            })
            ->addColumn('action', function ($r) {
                $user = auth()->user();
                $canEdit = $user instanceof \Illuminate\Contracts\Auth\Authenticatable
                    && \Illuminate\Support\Facades\Gate::forUser($user)->allows('loan_management.edit');
                $canDelete = $user instanceof \Illuminate\Contracts\Auth\Authenticatable
                    && \Illuminate\Support\Facades\Gate::forUser($user)->allows('loan_management.delete');

                $actions = '<div class="btn-group btn-group-xs">';
                $actions .= '<button type="button" class="btn btn-xs btn-primary btn-flat dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i> Action <span class="caret"></span></button>';
                $actions .= '<ul class="dropdown-menu dropdown-menu-right" role="menu">';
                $actions .= '<li><a href="'.route('loan-management.loans.view', $r->id).'"><i class="fa fa-eye"></i> View</a></li>';
                $actions .= '<li><a href="#" data-href="'.route('loan-management.loans.print-modal', $r->id).'" data-container=".view_modal" class="btn-modal"><i class="fa fa-print"></i> Print</a></li>';
                $actions .= '<li><a href="#" data-href="'.route('loan-management.loans.convert-to-pos', ['loan' => $r->id, 'modal' => 1]).'" data-container=".view_modal" class="btn-modal"><i class="fa fa-exchange"></i> POS</a></li>';
                $actions .= '<li><a href="#" data-url="'.route('loan-management.loans.payment.copy-info', $r->id).'" class="js-copy-loan-payment-info"><i class="fa fa-copy"></i> Copy</a></li>';
                if ($canEdit) {
                    $actions .= '<li><a href="'.route('loan-management.loans.edit', $r->id).'"><i class="fa fa-pencil"></i> Edit</a></li>';
                }
                if ($canDelete && in_array(strtolower((string) $r->status), ['draft', 'pending'])) {
                    $actions .= '<li><a href="#" class="btn-delete-loan text-red" data-url="'.route('loan-management.loans.destroy', $r->id).'"><i class="fa fa-trash"></i> Delete</a></li>';
                }
                $actions .= '</ul></div>';

                return $actions;
            })
            ->rawColumns(['status', 'principal_amount', 'paid_amount', 'balance_amount', 'action'])
            ->make(true);
    }

    public function printModal(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);
        $loanRow = $this->attachLoanCustomerKhmerName($loanRow);

        $printUrl = route('loan-management.loans.print', $loan);
        $autoPrintUrl = route('loan-management.loans.print', ['loan' => $loan, 'auto_print' => 1]);

        return view('loanmanagement::loans.print.modal', compact('loanRow', 'printUrl', 'autoPrintUrl'));
    }

    public function convertToPos(int $loan, Request $request, LoanToPosPrefillService $prefillService)
    {
        try {
            $payload = $prefillService->payload($loan);
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->boolean('modal')) {
                return view('loanmanagement::loans.pos_prefill_modal', [
                    'error' => $e->getMessage(),
                    'loanId' => $loan,
                    'payload' => null,
                    'posUrl' => null,
                ]);
            }

            return redirect()
                ->route('loan-management.loans.view', $loan)
                ->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        }

        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $posUrl = route('pos.create', [
            'loan_pos_prefill' => $encodedPayload,
        ]);

        if ($request->ajax() || $request->boolean('modal')) {
            return view('loanmanagement::loans.pos_prefill_modal', [
                'error' => null,
                'loanId' => $loan,
                'payload' => $payload,
                'posUrl' => $posUrl,
            ]);
        }

        return redirect()->to($posUrl);
    }

    public function print(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);
        $loanRow = $this->attachLoanCustomerKhmerName($loanRow);
        $sourceInvoiceDisplay = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotalDisplay = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaidDisplay = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDueDisplay = $loanRow->sell_due_amount_snapshot ?? null;

        $customerRow = null;
        if ($this->loanTableExists('loan_customers') && ! empty($loanRow->customer_id)) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }

        $contact = null;
        if (! empty($loanRow->main_contact_id) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')->where('id', $loanRow->main_contact_id)->first();
        }

        $customer = (object) [
            'name' => trim((string) ($loanRow->customer_khmer_name ?? ''))
                ?: (trim((string) ($customerRow->khmer_name ?? ''))
                    ?: ($loanRow->customer_name_snapshot
                        ?? ($customerRow->name ?? ($customerRow->customer_name ?? ($contact->name ?? '-'))))),
            'mobile' => $loanRow->customer_phone_snapshot
                ?? ($customerRow->phone ?? ($customerRow->mobile ?? ($customerRow->login_phone ?? ($contact->mobile ?? '-')))),
            'address_line_1' => $loanRow->customer_address_snapshot
                ?? ($customerRow->address ?? ($contact->address_line_1 ?? '-')),
            'custom_field1' => $customerRow->id_card_number ?? ($contact->custom_field1 ?? '-'),
            'co_borrower' => $customerRow->spouse_name ?? ($customerRow->family_contact_name ?? '-'),
            'co_borrower_phone' => $customerRow->spouse_phone ?? ($customerRow->family_contact_phone ?? '-'),
        ];

        $locationRow = null;
        if ($this->loanTableExists('loan_business_locations')) {
            if (! empty($loanRow->business_location_id)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')
                    ->where('id', $loanRow->business_location_id)
                    ->orWhere('main_location_id', $loanRow->business_location_id)
                    ->first();
            }
            if (! $locationRow && ! empty($loanRow->main_location_id)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $loanRow->main_location_id)->first();
            }
            if (! $locationRow && ! empty($loanRow->location_name_snapshot)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('name', $loanRow->location_name_snapshot)->first();
            }
        }

        $locationName = $loanRow->location_name_snapshot ?? ($locationRow->name ?? null);
        if (empty($locationName)) {
            $locationId = $loanRow->main_location_id ?? $loanRow->business_location_id ?? null;
            if ($locationId && Schema::hasTable('business_locations')) {
                $locationName = DB::table('business_locations')->where('id', $locationId)->value('name');
            }
        }

        if (! empty($loanRow->source_transaction_id) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions')
                ->select('id', 'invoice_no', 'final_total')
                ->where('id', $loanRow->source_transaction_id)
                ->first();

            if ($source) {
                if (empty($sourceInvoiceDisplay)) {
                    $sourceInvoiceDisplay = $source->invoice_no;
                }
                if ($sourceFinalTotalDisplay === null && isset($source->final_total)) {
                    $sourceFinalTotalDisplay = (float) $source->final_total;
                }

                if ($sourcePaidDisplay === null || $sourceDueDisplay === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaidDisplay === null) {
                        $sourcePaidDisplay = $paid;
                    }
                    if ($sourceDueDisplay === null) {
                        $sourceDueDisplay = $due;
                    }
                }
            }
        }

        $products = collect();
        if ($this->loanTableExists('loan_items')) {
            $productQuery = DB::connection('mysql_loan')->table('loan_items')
                ->where('loan_id', $loan)
                ->orderBy('id');
            $this->excludeDeletedLoanRows($productQuery, 'loan_items');

            $products = $productQuery
                ->get()
                ->map(function ($item) {
                    $qty = $item->qty ?? $item->quantity ?? 1;
                    $unitPrice = $item->unit_price ?? $item->unit_price_inc_tax ?? 0;
                    $lineTotal = $item->line_total ?? $item->total_price ?? null;
                    $subtotal = $lineTotal !== null
                        ? (float) $lineTotal
                        : ((float) $qty * (float) $unitPrice);

                    return (object) [
                        'product_sku' => $item->sku_snapshot ?? $item->product_sku ?? '-',
                        'product_name' => $item->product_name_snapshot ?? $item->product_name ?? '-',
                        'quantity' => $qty,
                        'unit_price_inc_tax' => $unitPrice,
                        'subtotal' => $subtotal,
                        'imei' => $item->imei_snapshot ?? '-',
                        'serial' => $item->serial_number_snapshot ?? '-',
                        'color' => $item->color_snapshot ?? $item->color ?? '-',
                        'storage' => $item->storage_snapshot ?? $item->storage ?? '-',
                        'lot' => $item->lot_number_snapshot ?? '-',
                    ];
                });
        }

        $installments = collect();
        if ($this->loanTableExists('loan_payment_schedules')) {
            $installmentQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'installment_no') ? 'installment_no' : 'due_date');
            $this->excludeDeletedLoanRows($installmentQuery, 'loan_payment_schedules');

            $installments = $installmentQuery
                ->get()
                ->map(function ($row, $index) {
                    $principal = (float) ($row->principal_amount ?? 0);
                    if ($principal <= 0) {
                        $principal = (float) ($row->principal_due ?? 0);
                    }
                    $interest = (float) ($row->interest_amount ?? 0);
                    $interestDue = (float) ($row->interest_due ?? 0);
                    if ($interest <= 0 && $interestDue > 0) {
                        $interest = $interestDue;
                    }
                    $calculatedDue = round($principal + $interest, 2);
                    $amountDue = (float) ($row->schedule_amount ?? 0);
                    $amountDueAlt = (float) ($row->amount_due ?? 0);
                    if ($calculatedDue > 0 && round($amountDue, 2) !== $calculatedDue) {
                        $amountDue = $calculatedDue;
                    } elseif (($amountDue <= 0 || round($amountDue, 2) === round($principal, 2)) && $amountDueAlt > $amountDue) {
                        $amountDue = $amountDueAlt;
                    }
                    if ($amountDue <= 0) {
                        $amountDue = $calculatedDue;
                    }
                    $paidAmount = (float) ($row->paid_amount ?? 0);
                    if ($paidAmount <= 0) {
                        $paidAmount = (float) ($row->amount_paid ?? 0);
                    }
                    $balance = (float) ($row->balance_amount ?? 0);
                    if ($balance <= 0) {
                        $balance = (float) ($row->amount_balance ?? 0);
                    }
                    if ($balance <= 0 && $amountDue > $paidAmount) {
                        $balance = max(0, $amountDue - $paidAmount);
                    }

                    return (object) [
                        'id' => $row->id ?? null,
                        'installment_number' => $row->installment_no ?? ($index + 1),
                        'installmentdate' => $row->due_date ?? null,
                        'installment_value' => $principal,
                        'benefit_value' => $interest,
                        'amount_due' => $amountDue,
                        'paid_value' => $paidAmount,
                        'balance_amount' => $balance,
                        'paid_at' => $row->paid_at ?? null,
                        'status' => $row->status ?? '-',
                    ];
                });
            $installments = $this->normalizeSchedulePrincipalFromDue($installments, $loanRow, $sourceDueDisplay ?? null);
        }

        $payments = collect();
        if ($this->loanTableExists('loan_payments')) {
            $payments = DB::connection('mysql_loan')->table('loan_payments')
                ->where('loan_id', $loan)
                ->orderByDesc($this->loanTableHasCol('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at')
                ->get();
        }
        $payments = $this->expandPaymentsWithDetailsForPrint($payments);
        $payments = $this->paymentsForPrintSchedules($payments, $installments);

        $createdByName = trim((string) ($loanRow->created_by_name_snapshot ?? ''));
        if (($createdByName === '' || $createdByName === '-') && ! empty($loanRow->created_by) && Schema::hasTable('users')) {
            $userColumns = Schema::getColumnListing('users');
            $selectColumns = array_values(array_intersect(['first_name', 'last_name', 'username', 'name'], $userColumns));
            if (! empty($selectColumns)) {
                $createdByUser = DB::table('users')
                    ->select($selectColumns)
                    ->where('id', $loanRow->created_by)
                    ->first();

                if ($createdByUser) {
                    $createdByName = trim(implode(' ', array_filter([
                        $createdByUser->first_name ?? null,
                        $createdByUser->last_name ?? null,
                    ])));
                    if ($createdByName === '') {
                        $createdByName = $createdByUser->username ?? ($createdByUser->name ?? '');
                    }
                }
            }
        }
        $createdByName = Str::of($createdByName !== '' ? $createdByName : '-')->squish()->value();

        $businessName = $locationRow->name ?? $locationName ?? session('business.name', config('app.name'));
        $logo = null;
        $paymentQr = null;
        $telegramQr = null;
        $telegramNumber = null;
        if ($locationRow) {
            $logo = $this->assetFromPublicPath($locationRow->logo_path ?? null);
            $paymentQr = $this->assetFromPublicPath($locationRow->payment_qr_path ?? null);
            $telegramQr = $this->assetFromPublicPath($locationRow->telegram_qr_path ?? null);
            $telegramNumber = trim((string) ($locationRow->telegram_number ?? '')) ?: null;
        }
        $logo = $logo ?: $this->businessLogoAsset();
        $telegramNumber = $telegramNumber ?: '0717221349';

        return view('loanmanagement::loans.print.loan', compact(
            'loanRow',
            'sourceInvoiceDisplay',
            'customer',
            'locationName',
            'products',
            'installments',
            'payments',
            'businessName',
            'logo',
            'paymentQr',
            'telegramQr',
            'telegramNumber',
            'createdByName'
        ));
    }

    public function createPayment(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_payments'), 404);
        $this->ensureLoanPaymentTypeColumn();

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $schedules = collect();
        if ($this->loanTableExists('loan_payment_schedules')) {
            $scheduleQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
                ->orderBy('id');
            $this->excludeDeletedLoanRows($scheduleQuery, 'loan_payment_schedules');

            $schedules = $scheduleQuery
                ->get();
        }

        $isDepositPayment = request()->boolean('deposit_payment');
        $selectedScheduleId = $isDepositPayment ? null : (request()->integer('schedule_id') ?: null);
        $selectedSchedule = $selectedScheduleId ? $schedules->firstWhere('id', $selectedScheduleId) : $schedules->first();
        $defaultAmount = $selectedSchedule
            ? (float) ($selectedSchedule->balance_amount ?? $selectedSchedule->amount_balance ?? $selectedSchedule->schedule_amount ?? $selectedSchedule->amount_due ?? 0)
            : (float) ($loanRow->balance_amount ?? 0);
        if ($isDepositPayment) {
            $defaultAmount = 0.01;
        }
        $payOffAmount = $this->calculatePayOffAmount($schedules, $loanRow);

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        return view('loanmanagement::loans.payments.create', compact(
            'loanRow',
            'schedules',
            'selectedSchedule',
            'selectedScheduleId',
            'defaultAmount',
            'payOffAmount',
            'paymentTypes',
            'defaultPaymentMethod',
        ) + [
            'copyInfo' => [],
            'isDepositPayment' => $isDepositPayment,
        ]);
    }

    public function mobileQuickPay(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_payments'), 404);
        $this->ensureLoanPaymentTypeColumn();

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $schedules = collect();
        if ($this->loanTableExists('loan_payment_schedules')) {
            $scheduleQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
                ->orderBy('id');
            $this->excludeDeletedLoanRows($scheduleQuery, 'loan_payment_schedules');

            $schedules = $scheduleQuery
                ->get();
        }

        $selectedScheduleId = request()->integer('schedule_id') ?: null;
        $selectedSchedule = $selectedScheduleId ? $schedules->firstWhere('id', $selectedScheduleId) : $schedules->first();
        $defaultAmount = $selectedSchedule
            ? (float) ($selectedSchedule->balance_amount ?? $selectedSchedule->amount_balance ?? $selectedSchedule->schedule_amount ?? $selectedSchedule->amount_due ?? 0)
            : (float) ($loanRow->balance_amount ?? 0);
        $payOffAmount = $this->calculatePayOffAmount($schedules, $loanRow);

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        return view('loanmanagement::loans.payments.mobile_quick_pay', compact(
            'loanRow',
            'schedules',
            'selectedSchedule',
            'selectedScheduleId',
            'defaultAmount',
            'payOffAmount',
            'paymentTypes',
            'defaultPaymentMethod'
        ));
    }

    protected function storeLoanPaymentDoc(Request $request, int $loan, int $paymentId, int $lineIndex): void
    {
        if (! $this->loanTableExists('loan_files')) {
            return;
        }

        $docText = trim((string) $request->input("payment_lines.$lineIndex.payment_doc_text", ''));
        if ($docText !== '') {
            $filename = 'payment-doc-'.$loan.'-'.$paymentId.'-'.Str::random(10).'.txt';
            $path = 'loan-payment-docs/'.now()->format('Y/m').'/'.$filename;
            Storage::disk('public')->put($path, $docText);

            DB::connection('mysql_loan')->table('loan_files')->insert($this->loanSafeColumns('loan_files', [
                'fileable_type' => 'loan_payment',
                'fileable_id' => $paymentId,
                'category' => 'payment_doc',
                'disk' => 'public',
                'path' => $path,
                'original_name' => 'payment-doc-note-'.$paymentId.'.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($docText),
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $files = $request->file("payment_lines.$lineIndex.payment_docs", []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $legacyFile = $request->file("payment_lines.$lineIndex.payment_doc");
        if ($legacyFile) {
            $files[] = $legacyFile;
        }

        $files = array_values(array_filter($files, fn ($file) => $file && $file->isValid()));
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'file');
            $filename = 'payment-doc-'.$loan.'-'.$paymentId.'-'.Str::random(10).'.'.$extension;
            $path = $file->storeAs('loan-payment-docs/'.now()->format('Y/m'), $filename, 'public');

            DB::connection('mysql_loan')->table('loan_files')->insert($this->loanSafeColumns('loan_files', [
                'fileable_type' => 'loan_payment',
                'fileable_id' => $paymentId,
                'category' => 'payment_doc',
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function storePayment(Request $request, int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_payments'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $payload = $request->validate([
            'schedule_id' => 'nullable|integer|min:1',
            'pay_off' => 'nullable|boolean',
            'pay_off_discount_amount' => 'nullable|numeric|min:0',
            'deposit_payment' => 'nullable|boolean',
            'paid_date' => 'required|date',
            'payment_lines' => 'required|array|min:1',
            'payment_lines.*.amount' => 'required|numeric|min:0.01',
            'payment_lines.*.method' => 'nullable|string|max:100',
            'payment_lines.*.reference_number' => 'nullable|string|max:191',
            'payment_lines.*.note' => 'nullable|string|max:1000',
            'payment_lines.*.payment_doc_text' => 'nullable|string|max:5000',
            'payment_lines.*.payment_doc' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip|max:10240',
            'payment_lines.*.payment_docs' => 'nullable|array',
            'payment_lines.*.payment_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip|max:10240',
        ]);

        $paidDate = $payload['paid_date'];
        $paidAt = $paidDate.' '.now()->format('H:i:s');
        $isPayOff = ! empty($payload['pay_off']);
        $payOffDiscountAmount = $isPayOff ? round((float) ($payload['pay_off_discount_amount'] ?? 0), 2) : 0.0;
        $isDepositPayment = ! empty($payload['deposit_payment']);
        $selectedScheduleId = ($isPayOff || $isDepositPayment) ? null : ($payload['schedule_id'] ?? null);
        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $paymentLines = collect($payload['payment_lines'])
            ->map(function ($line) use ($paymentTypes) {
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $method = trim((string) ($line['method'] ?? ''));

                if ($method === '') {
                    $method = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? 'cash');
                }

                return [
                    'amount' => $amount,
                    'method' => $method,
                    'method_name' => $this->paymentMethodName($method, $paymentTypes),
                    'reference_number' => trim((string) ($line['reference_number'] ?? '')) ?: null,
                    'note' => trim((string) ($line['note'] ?? '')) ?: null,
                ];
            })
            ->filter(fn ($line) => $line['amount'] > 0)
            ->values();

        $totalAmount = round((float) $paymentLines->sum('amount'), 2);
        $createdPaymentIds = [];
        $returnTo = $this->safeLoanReturnTo($request, '');

        if ($paymentLines->isEmpty() || $totalAmount <= 0) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Please add at least one payment line.'], 422);
            }

            return redirect()
                ->back()
                ->with('status', ['success' => 0, 'msg' => 'Please add at least one payment line.']);
        }

        try {
            DB::connection('mysql_loan')->transaction(function () use ($request, $loan, $loanRow, $isPayOff, $payOffDiscountAmount, $isDepositPayment, $selectedScheduleId, $paymentLines, $totalAmount, $paidDate, $paidAt, &$createdPaymentIds) {
                $userName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
                if ($userName === '') {
                    $userName = auth()->user()->username ?? null;
                }

                foreach ($paymentLines as $index => $line) {
                    $receipt = 'RCP-'.now()->format('YmdHis').'-'.$loan.'-'.random_int(10, 99);
                    $paymentRef = 'PMT-'.strtoupper(Str::random(10));

                    $paymentId = DB::connection('mysql_loan')->table('loan_payments')->insertGetId($this->loanSafeColumns('loan_payments', [
                        'payment_number' => $this->generateUniquePaymentNumber($loan),
                        'payment_ref_no' => $paymentRef,
                        'receipt_number' => $receipt,
                        'loan_id' => $loan,
                        'payment_type' => ($isPayOff || $isDepositPayment) ? 'loan' : 'monthly',
                        'loan_number_snapshot' => $loanRow->loan_number ?? null,
                        'customer_id' => $loanRow->customer_id ?? 0,
                        'customer_name_snapshot' => $loanRow->customer_name_snapshot ?? null,
                        'schedule_id' => $selectedScheduleId,
                        'received_by' => auth()->id(),
                        'received_by_name_snapshot' => $userName,
                        'collected_by_name_snapshot' => $userName,
                        'channel' => $line['method_name'],
                        'payment_method_snapshot' => $line['method_name'],
                        'amount' => $line['amount'],
                        'total_paid' => $line['amount'],
                        'total_paid_base' => $line['amount'],
                        'currency' => $loanRow->currency ?? 'USD',
                        'base_currency' => $loanRow->currency ?? 'USD',
                        'exchange_rate' => 1,
                        'reference_number' => $line['reference_number'],
                        'paid_date' => $paidDate,
                        'paid_at' => $paidAt,
                        'status' => 'confirmed',
                        'note' => $line['note'],
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    $createdPaymentIds[] = (int) $paymentId;
                    $this->storeLoanPaymentDoc($request, $loan, (int) $paymentId, (int) $index);

                    if ($this->loanTableExists('loan_payment_details')) {
                        DB::connection('mysql_loan')->table('loan_payment_details')->insert($this->loanSafeColumns('loan_payment_details', [
                            'payment_id' => $paymentId,
                            'payment_method_id' => null,
                            'payment_method_snapshot' => $line['method_name'],
                            'method' => $line['method'],
                            'currency' => $loanRow->currency ?? 'USD',
                            'amount' => $line['amount'],
                            'exchange_rate' => 1,
                            'amount_base' => $line['amount'],
                            'reference_number' => $line['reference_number'],
                            'transaction_no' => $line['reference_number'],
                            'note' => $line['note'],
                            'meta_json' => json_encode(['source' => 'loan_detail']),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                    }
                }

                if ($isDepositPayment) {
                    DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
                        'down_payment' => round((float) ($loanRow->down_payment ?? 0) + $totalAmount, 2),
                        'updated_at' => now(),
                    ]));
                } elseif ($isPayOff) {
                    $this->applyLoanPayOffToSchedules($loan, $totalAmount, $payOffDiscountAmount, $paidAt);
                } else {
                    $this->applyLoanPaymentToSchedules($loan, $totalAmount, $paidAt, $selectedScheduleId);
                }
                $this->refreshLoanPaymentTotals($loan, $totalAmount);
            });

            $paymentNotificationLines = $paymentLines->all();
            $paymentNotificationPhotoPaths = $this->paymentTelegramPhotoPaths($createdPaymentIds);
            app()->terminating(function () use ($loan, $totalAmount, $paymentNotificationLines, $paidDate, $paymentNotificationPhotoPaths) {
                try {
                    $this->notifyLocationTelegram($loan, 'payment', $totalAmount, $paymentNotificationLines, $paidDate, $paymentNotificationPhotoPaths);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Loan payment saved but Telegram notification failed', [
                        'loan_id' => $loan,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            if ($request->ajax() || $request->wantsJson()) {
                $paymentId = $createdPaymentIds[0] ?? null;
                $redirectUrl = $returnTo !== '' ? $returnTo : route('loan-management.dashboard');

                return response()->json([
                    'success' => true,
                    'message' => 'Payment added successfully',
                    'data' => [
                        'payment_id' => $paymentId,
                        'print_url' => null,
                        'redirect_url' => $redirectUrl,
                    ],
                ]);
            }

            return redirect()
                ->to($returnTo !== '' ? $returnTo : route('loan-management.loans.view', $loan))
                ->with('status', ['success' => 1, 'msg' => 'Payment added successfully']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Loan payment store failed', [
                'loan_id' => $loan,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: '.$e->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors(['payment_error' => 'Payment failed: '.$e->getMessage()]);
        }
    }

    protected function safeLoanReturnTo(Request $request, string $fallback): string
    {
        $returnTo = trim((string) $request->input('return_to', $request->query('return_to', '')));
        $allowedPrefix = url('/loan-management');

        if ($returnTo !== ''
            && substr($returnTo, 0, strlen($allowedPrefix)) === $allowedPrefix) {
            return $returnTo;
        }

        return $fallback;
    }

    public function editSchedule(int $loan, int $schedule)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_payment_schedules'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $scheduleRow = DB::connection('mysql_loan')
            ->table('loan_payment_schedules')
            ->where('id', $schedule)
            ->where('loan_id', $loan)
            ->first();
        abort_if(! $scheduleRow, 404);

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? 'cash');
        $schedulePayments = $this->loanTableExists('loan_payments') && $this->loanTableHasCol('loan_payments', 'schedule_id')
            ? DB::connection('mysql_loan')
                ->table('loan_payments')
                ->where('loan_id', $loan)
                ->where('schedule_id', $schedule)
                ->orderByDesc($this->loanTableHasCol('loan_payments', 'paid_date') ? 'paid_date' : 'id')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('loanmanagement::loans.partials.edit_schedule_modal', compact(
            'loanRow',
            'scheduleRow',
            'schedulePayments',
            'paymentTypes',
            'defaultPaymentMethod'
        ));
    }

    public function createItem(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_items'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $itemRow = (object) [
            'id' => null,
            'loan_id' => $loan,
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
        ];
        $isCreate = true;

        return view('loanmanagement::loans.partials.edit_item_modal', compact('loanRow', 'itemRow', 'isCreate'));
    }

    public function storeItem(Request $request, int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_items'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $payload = $this->validatedLoanItemPayload($request, null, true, $loan);

        $itemId = DB::connection('mysql_loan')->table('loan_items')->insertGetId($this->loanSafeColumns('loan_items', array_merge($payload, [
            'loan_id' => $loan,
            'created_at' => now(),
            'updated_at' => now(),
        ])));

        $this->refreshLoanItemSnapshot($loan);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Loan item added successfully',
                'data' => [
                    'item_id' => $itemId,
                    'redirect_url' => $request->input('return_to') ?: route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])),
                ],
            ]);
        }

        return redirect()
            ->route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []))
            ->with('status', ['success' => 1, 'msg' => 'Loan item added successfully']);
    }

    public function editItem(int $loan, int $item)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_items'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $itemQuery = DB::connection('mysql_loan')
            ->table('loan_items')
            ->where('id', $item)
            ->where('loan_id', $loan);
        $this->excludeDeletedLoanRows($itemQuery, 'loan_items');
        $itemRow = $itemQuery->first();
        abort_if(! $itemRow, 404);
        $isCreate = false;

        return view('loanmanagement::loans.partials.edit_item_modal', compact('loanRow', 'itemRow', 'isCreate'));
    }

    public function updateItem(Request $request, int $loan, int $item)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_items'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $itemQuery = DB::connection('mysql_loan')
            ->table('loan_items')
            ->where('id', $item)
            ->where('loan_id', $loan);
        $this->excludeDeletedLoanRows($itemQuery, 'loan_items');
        $itemRow = $itemQuery->first();
        abort_if(! $itemRow, 404);

        $payload = $this->validatedLoanItemPayload($request, $itemRow, false, $loan);

        DB::connection('mysql_loan')->table('loan_items')->where('id', $itemRow->id)->update($this->loanSafeColumns('loan_items', array_merge($payload, [
            'updated_at' => now(),
        ])));

        $this->refreshLoanItemSnapshot($loan);

        return response()->json([
            'success' => true,
            'message' => 'Loan item updated successfully',
            'data' => [
                'redirect_url' => $request->input('return_to') ?: route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])),
            ],
        ]);
    }

    public function destroyItem(Request $request, int $loan, int $item)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        abort_if(! $this->loanTableExists('loan_items'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $itemQuery = DB::connection('mysql_loan')
            ->table('loan_items')
            ->where('id', $item)
            ->where('loan_id', $loan);
        $this->excludeDeletedLoanRows($itemQuery, 'loan_items');
        $itemRow = $itemQuery->first();
        abort_if(! $itemRow, 404);

        if ($this->loanTableHasCol('loan_items', 'deleted_at')) {
            DB::connection('mysql_loan')->table('loan_items')->where('id', $itemRow->id)->update($this->loanSafeColumns('loan_items', [
                'deleted_at' => now(),
                'updated_at' => now(),
            ]));
        } else {
            DB::connection('mysql_loan')->table('loan_items')->where('id', $itemRow->id)->delete();
        }

        $this->refreshLoanItemSnapshot($loan);
        $redirectUrl = $request->input('return_to') ?: route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []));

        return response()->json([
            'success' => true,
            'message' => 'Loan item deleted successfully',
            'data' => [
                'redirect_url' => $redirectUrl,
            ],
        ]);
    }

    protected function validatedLoanItemPayload(Request $request, ?object $itemRow = null, bool $isCreate = false, int $loanId = 0): array
    {
        $payload = $request->validate([
            'product_name_snapshot' => ($isCreate ? 'required' : 'nullable').'|string|max:191',
            'sku_snapshot' => 'nullable|string|max:191',
            'imei_snapshot' => 'nullable|string|max:191',
            'serial_number_snapshot' => 'nullable|string|max:191',
            'lot_number_snapshot' => 'nullable|string|max:191',
            'brand' => 'nullable|string|max:191',
            'category' => 'nullable|string|max:191',
            'color' => 'nullable|string|max:191',
            'storage' => 'nullable|string|max:191',
            'product_photo_path' => 'nullable|string|max:1000',
            'product_photo' => 'nullable|string',
            'product_ocr_raw_text' => 'nullable|string|max:10000',
            'qty' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'line_total' => 'nullable|numeric|min:0',
        ]);

        $qty = round((float) ($payload['qty'] ?? ($itemRow->qty ?? 1)), 4);
        $unitPrice = round((float) ($payload['unit_price'] ?? ($itemRow->unit_price ?? 0)), 2);
        $discount = round((float) ($payload['discount'] ?? ($itemRow->discount ?? 0)), 2);
        $lineTotal = array_key_exists('line_total', $payload) && $payload['line_total'] !== null
            ? round((float) $payload['line_total'], 2)
            : max(0, round(($qty * $unitPrice) - $discount, 2));

        $productName = trim((string) ($payload['product_name_snapshot'] ?? ($itemRow->product_name_snapshot ?? $itemRow->product_name ?? '')));
        $sku = trim((string) ($payload['sku_snapshot'] ?? ($itemRow->sku_snapshot ?? $itemRow->sku ?? '')));
        $imei = trim((string) ($payload['imei_snapshot'] ?? ($itemRow->imei_snapshot ?? $itemRow->imei ?? '')));
        $serial = trim((string) ($payload['serial_number_snapshot'] ?? ($itemRow->serial_number_snapshot ?? $itemRow->serial_number ?? '')));
        $lot = trim((string) ($payload['lot_number_snapshot'] ?? ($itemRow->lot_number_snapshot ?? $itemRow->lot_number ?? '')));
        $brand = trim((string) ($payload['brand'] ?? ($itemRow->brand ?? '')));
        $category = trim((string) ($payload['category'] ?? ($itemRow->category ?? '')));
        $color = trim((string) ($payload['color'] ?? ($itemRow->color ?? '')));
        $storage = trim((string) ($payload['storage'] ?? ($itemRow->storage ?? '')));

        $photoPath = trim((string) ($payload['product_photo_path'] ?? ($itemRow->product_photo_path ?? '')));
        $storedPhotoPath = $this->storeLoanItemPhotoFromDataUri((string) ($payload['product_photo'] ?? ''), $loanId ?: (int) ($itemRow->loan_id ?? 0), (int) ($itemRow->id ?? 0));
        if ($storedPhotoPath !== null) {
            $photoPath = $storedPhotoPath;
        }

        return [
            'product_name_snapshot' => $productName,
            'product_name' => $productName,
            'sku_snapshot' => $sku,
            'sku' => $sku,
            'brand' => $brand,
            'category' => $category,
            'imei_snapshot' => $imei,
            'imei' => $imei,
            'serial_number_snapshot' => $serial,
            'serial_number' => $serial,
            'lot_number_snapshot' => $lot,
            'lot_number' => $lot,
            'color' => $color,
            'color_snapshot' => $color,
            'storage' => $storage,
            'storage_snapshot' => $storage,
            'product_photo_path' => $photoPath,
            'product_ocr_raw_text' => trim((string) ($payload['product_ocr_raw_text'] ?? ($itemRow->product_ocr_raw_text ?? ''))),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'line_total' => $lineTotal,
        ];
    }

    protected function storeLoanItemPhotoFromDataUri(string $dataUri, int $loanId, int $itemId = 0): ?string
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '' || $loanId <= 0) {
            return null;
        }

        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'image/jpeg';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $itemKey = $itemId > 0 ? 'item-'.$itemId : 'item-new';
        $path = 'loan-product-photos/'.$loanId.'/'.$itemKey.'-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);
        $this->storeLoanItemPhotoFile($path, $loanId, 'loan-product-'.$loanId.'-'.$itemKey.'.'.$extension);

        return $path;
    }

    protected function storeLoanItemPhotoFile(string $path, int $loanId, string $originalName): void
    {
        if (! $this->loanTableExists('loan_files')) {
            return;
        }

        $fullPath = Storage::disk('public')->path($path);
        if (! is_readable($fullPath)) {
            return;
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'image/jpeg') : 'image/jpeg';

        DB::connection('mysql_loan')->table('loan_files')->insert($this->loanSafeColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\Loan::class,
            'fileable_id' => $loanId,
            'category' => 'product_photo',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => filesize($fullPath) ?: null,
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function storeLoanCustomerImageFromDataUri(string $dataUri, int $customerId, string $category, string $namePrefix): ?int
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '' || $customerId <= 0 || ! $this->loanTableExists('loan_files')) {
            return null;
        }

        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'image/jpeg';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $path = 'loan-customers/'.$customerId.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);
        $fullPath = Storage::disk('public')->path($path);

        return (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId($this->loanSafeColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\LoanCustomer::class,
            'fileable_id' => $customerId,
            'category' => $category,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $namePrefix.'-'.$customerId.'.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => is_readable($fullPath) ? (filesize($fullPath) ?: null) : null,
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function loanFileUrlById(int $fileId): ?string
    {
        if ($fileId <= 0 || ! $this->loanTableExists('loan_files')) {
            return null;
        }

        $file = DB::connection('mysql_loan')->table('loan_files')->where('id', $fileId)->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return Storage::disk($file->disk ?? 'public')->url($file->path);
    }

    protected function loanFilesByCategory(int $loanId, string $category)
    {
        if ($loanId <= 0 || ! $this->loanTableExists('loan_files')) {
            return collect();
        }

        $query = DB::connection('mysql_loan')->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\Loan::class)
            ->where('fileable_id', $loanId)
            ->where('category', $category)
            ->orderByDesc('id');
        $this->excludeDeletedLoanRows($query, 'loan_files');

        return $query->get()->map(function ($file) {
            $file->url = ! empty($file->path) ? Storage::disk($file->disk ?? 'public')->url($file->path) : null;
            return $file;
        });
    }

    protected function latestCustomerFileUrlByCategory(int $customerId, string $category): ?string
    {
        if ($customerId <= 0 || ! $this->loanTableExists('loan_files')) {
            return null;
        }

        $query = DB::connection('mysql_loan')->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
            ->where('fileable_id', $customerId)
            ->where('category', $category)
            ->orderByDesc('id');
        $this->excludeDeletedLoanRows($query, 'loan_files');

        $file = $query->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return Storage::disk($file->disk ?? 'public')->url($file->path);
    }

    protected function updateLoanCustomerFileReference(int $customerId, string $column, int $fileId): void
    {
        if ($customerId <= 0 || $fileId <= 0 || ! $this->loanTableExists('loan_customers') || ! $this->loanTableHasCol('loan_customers', $column)) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_customers')->where('id', $customerId)->update($this->loanSafeColumns('loan_customers', [
            $column => $fileId,
            'updated_at' => now(),
        ]));
    }

    protected function storeLoanDocumentFromDataUri(string $dataUri, int $loanId, string $namePrefix): ?int
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '' || $loanId <= 0 || ! $this->loanTableExists('loan_files')) {
            return null;
        }

        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'application/octet-stream';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = str_contains($mimeType, 'pdf') ? 'pdf'
            : (str_contains($mimeType, 'png') ? 'png'
            : (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') ? 'jpg' : 'bin'));
        $path = 'loan-documents/'.$loanId.'/'.$namePrefix.'-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);
        $fullPath = Storage::disk('public')->path($path);

        return (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId($this->loanSafeColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\Loan::class,
            'fileable_id' => $loanId,
            'category' => 'document',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $namePrefix.'.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => is_readable($fullPath) ? (filesize($fullPath) ?: null) : null,
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function storeLoanTextDocument(string $text, int $loanId, string $originalName): ?int
    {
        if (trim($text) === '' || $loanId <= 0 || ! $this->loanTableExists('loan_files')) {
            return null;
        }

        $path = 'loan-documents/'.$loanId.'/'.Str::uuid().'-'.$originalName;
        Storage::disk('public')->put($path, $text);
        $fullPath = Storage::disk('public')->path($path);

        return (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId($this->loanSafeColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\Loan::class,
            'fileable_id' => $loanId,
            'category' => 'document',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => 'text/plain',
            'size_bytes' => is_readable($fullPath) ? (filesize($fullPath) ?: null) : null,
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function refreshLoanItemSnapshot(int $loan): void
    {
        if (! $this->loanTableExists('loans') || ! $this->loanTableExists('loan_items')) {
            return;
        }

        $itemQuery = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan)->orderBy('id');
        $this->excludeDeletedLoanRows($itemQuery, 'loan_items');
        $items = $itemQuery->get();
        $firstItem = $items->first();

        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
            'product_name_snapshot' => $items->map(fn ($item) => trim((string) ($item->product_name_snapshot ?? $item->product_name ?? '')))->filter()->implode(', ') ?: null,
            'sku_snapshot' => $items->map(fn ($item) => trim((string) ($item->sku_snapshot ?? $item->sku ?? '')))->filter()->implode(', ') ?: null,
            'imei_snapshot' => $items->map(fn ($item) => trim((string) ($item->imei_snapshot ?? $item->imei ?? '')))->filter()->implode(', ') ?: null,
            'serial_number_snapshot' => $items->map(fn ($item) => trim((string) ($item->serial_number_snapshot ?? $item->serial_number ?? '')))->filter()->implode(', ') ?: null,
            'source_product_id' => $firstItem ? ($firstItem->loan_product_id ?? null) : null,
            'updated_at' => now(),
        ]));
    }

    public function updateSchedule(Request $request, int $loan, int $schedule)
    {
        try {
            abort_if(! $this->loanTableExists('loans'), 404);
            abort_if(! $this->loanTableExists('loan_payment_schedules'), 404);

            $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
            abort_if(! $loanRow, 404);

            $scheduleRow = DB::connection('mysql_loan')
                ->table('loan_payment_schedules')
                ->where('id', $schedule)
                ->where('loan_id', $loan)
                ->first();
            abort_if(! $scheduleRow, 404);

            $payload = $request->validate([
                'installment_no' => 'nullable|integer|min:1',
                'due_date' => 'nullable|date',
                'principal_amount' => 'nullable|numeric|min:0',
                'interest_amount' => 'nullable|numeric|min:0',
                'schedule_amount' => 'nullable|numeric|min:0',
                'paid_amount' => 'nullable|numeric|min:0',
                'balance_amount' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|in:auto,pending,unpaid,partial,paid,late,completed,pay off,pay_off,payoff',
                'payment_action' => 'nullable|string|in:keep,sync_status,add_update,remove',
                'payment_amount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|max:100',
                'payment_paid_date' => 'nullable|date',
                'payment_reference_number' => 'nullable|string|max:191',
                'payment_note' => 'nullable|string|max:1000',
            ]);

            $principal = round((float) ($payload['principal_amount'] ?? $scheduleRow->principal_amount ?? $scheduleRow->principal_due ?? $scheduleRow->principal ?? $scheduleRow->installment_value ?? 0), 2);
            $interest = round((float) ($payload['interest_amount'] ?? $scheduleRow->interest_amount ?? $scheduleRow->interest_due ?? $scheduleRow->interest ?? 0), 2);
            $amountDue = round((float) ($payload['schedule_amount'] ?? $scheduleRow->schedule_amount ?? $scheduleRow->amount_due ?? $scheduleRow->total ?? ($principal + $interest)), 2);
            $paid = round((float) ($payload['paid_amount'] ?? $scheduleRow->paid_amount ?? $scheduleRow->amount_paid ?? 0), 2);
            $balance = array_key_exists('balance_amount', $payload) && $payload['balance_amount'] !== null
                ? round((float) $payload['balance_amount'], 2)
                : round(max(0, $amountDue - $paid), 2);

            $status = strtolower(trim((string) ($payload['status'] ?? 'auto')));
            if (in_array($status, ['pay_off', 'payoff'], true)) {
                $status = 'pay off';
            }
            if ($status === '' || $status === 'auto') {
                if ($amountDue > 0 && $balance <= 0) {
                    $status = 'paid';
                } elseif ($paid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }
            }

            DB::connection('mysql_loan')->transaction(function () use ($loan, $scheduleRow, $payload, $principal, $interest, $amountDue, $paid, $balance, $status) {
                DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $scheduleRow->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                    'installment_no' => $payload['installment_no'] ?? $scheduleRow->installment_no ?? null,
                    'due_date' => $payload['due_date'] ?? $scheduleRow->due_date ?? null,
                    'principal_amount' => $principal,
                    'principal_due' => $principal,
                    'principal' => $principal,
                    'installment_value' => $principal,
                    'interest_amount' => $interest,
                    'interest_due' => $interest,
                    'interest' => $interest,
                    'benefit_value' => $interest,
                    'schedule_amount' => $amountDue,
                    'amount_due' => $amountDue,
                    'total' => $amountDue,
                    'paid_amount' => $paid,
                    'amount_paid' => $paid,
                    'paid_value' => $paid,
                    'balance_amount' => $balance,
                    'amount_balance' => $balance,
                    'status' => $status,
                    'paid_at' => in_array($status, ['paid', 'completed', 'pay off'], true)
                        ? ($scheduleRow->paid_at ?? now())
                        : ($paid > 0 ? ($scheduleRow->paid_at ?? null) : null),
                    'paid_date' => in_array($status, ['paid', 'completed', 'pay off'], true)
                        ? now()->toDateString()
                        : ($paid > 0 ? ($scheduleRow->paid_date ?? null) : null),
                    'updated_at' => now(),
                ]));

                $this->syncSchedulePaymentAction($loan, $scheduleRow, $payload, $paid, $amountDue, $status);
                $this->refreshLoanBalanceFromSchedules($loan);
                $this->refreshLoanPaymentTotals($loan, 0);
            });

            if ($request->ajax() || $request->wantsJson()) {
                $sectionData = $this->freshLoanSectionsResponseData($request, $loan);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment schedule updated successfully',
                    'data' => [
                        'redirect_url' => $request->input('return_to') ?: $sectionData['redirect_url'],
                        'sections_html' => $sectionData['sections_html'],
                        'sections_target' => $sectionData['sections_target'],
                    ],
                ]);
            }

            return redirect()
                ->route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []))
                ->with('status', ['success' => 1, 'msg' => 'Payment schedule updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $message = 'Unable to update payment schedule: '.$e->getMessage();
            $detail = $e->getFile().':'.$e->getLine();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'detail' => $detail,
                    ],
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'schedule_error' => $message.' in '.$detail,
                ]);
        }
    }

    protected function syncSchedulePaymentAction(int $loan, object $scheduleRow, array $payload, float $paid, float $amountDue, string $status): void
    {
        if (! $this->loanTableExists('loan_payments') || ! $this->loanTableHasCol('loan_payments', 'schedule_id')) {
            return;
        }

        $action = $payload['payment_action'] ?? 'keep';
        if ($action === 'sync_status') {
            if (in_array($status, ['pending', 'unpaid'], true)) {
                $action = 'remove';
            } elseif (in_array($status, ['paid', 'completed', 'pay off'], true) || $paid > 0) {
                $action = 'add_update';
            } else {
                $action = 'keep';
            }
        }

        if ($action === 'keep') {
            return;
        }

        if ($action === 'remove') {
            $this->deleteSchedulePayments($loan, (int) $scheduleRow->id);
            DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $scheduleRow->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                'paid_amount' => 0,
                'amount_paid' => 0,
                'paid_value' => 0,
                'balance_amount' => $amountDue,
                'amount_balance' => $amountDue,
                'status' => 'unpaid',
                'paid_at' => null,
                'paid_date' => null,
                'updated_at' => now(),
            ]));
            return;
        }

        if ($action !== 'add_update') {
            return;
        }

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $amount = array_key_exists('payment_amount', $payload) && $payload['payment_amount'] !== null
            ? round((float) $payload['payment_amount'], 2)
            : ($paid > 0 ? $paid : (in_array($status, ['paid', 'completed', 'pay off'], true) ? $amountDue : 0));
        if ($amount <= 0) {
            return;
        }

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $method = trim((string) ($payload['payment_method'] ?? ''));
        if ($method === '') {
            $method = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? 'cash');
        }
        $methodName = $this->paymentMethodName($method, $paymentTypes);
        $paidDate = $payload['payment_paid_date'] ?? now()->toDateString();
        $paidAt = $paidDate.' '.now()->format('H:i:s');
        $reference = trim((string) ($payload['payment_reference_number'] ?? '')) ?: null;
        $note = trim((string) ($payload['payment_note'] ?? '')) ?: null;
        $userName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
        if ($userName === '') {
            $userName = auth()->user()->username ?? null;
        }

        $existingPayments = DB::connection('mysql_loan')
            ->table('loan_payments')
            ->where('loan_id', $loan)
            ->where('schedule_id', $scheduleRow->id)
            ->orderBy('id')
            ->get();
        $payment = $existingPayments->first();

        $paymentPayload = $this->loanSafeColumns('loan_payments', [
            'payment_number' => $payment->payment_number ?? $this->generateUniquePaymentNumber($loan),
            'payment_ref_no' => $payment->payment_ref_no ?? 'PMT-'.strtoupper(Str::random(10)),
            'receipt_number' => $payment->receipt_number ?? 'RCP-'.now()->format('YmdHis').'-'.$loan.'-'.random_int(10, 99),
            'loan_id' => $loan,
            'payment_type' => 'monthly',
            'loan_number_snapshot' => $loanRow->loan_number ?? null,
            'customer_id' => $loanRow->customer_id ?? 0,
            'customer_name_snapshot' => $loanRow->customer_name_snapshot ?? null,
            'schedule_id' => $scheduleRow->id,
            'received_by' => auth()->id(),
            'received_by_name_snapshot' => $userName,
            'collected_by_name_snapshot' => $userName,
            'channel' => $methodName,
            'payment_method_snapshot' => $methodName,
            'amount' => $amount,
            'total_paid' => $amount,
            'total_paid_base' => $amount,
            'currency' => $loanRow->currency ?? 'USD',
            'base_currency' => $loanRow->currency ?? 'USD',
            'exchange_rate' => 1,
            'reference_number' => $reference,
            'paid_date' => $paidDate,
            'paid_at' => $paidAt,
            'status' => 'confirmed',
            'note' => $note,
            'created_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        if ($payment) {
            DB::connection('mysql_loan')->table('loan_payments')->where('id', $payment->id)->update($paymentPayload);
            $paymentId = (int) $payment->id;
            $extraPaymentIds = $existingPayments->pluck('id')->filter(fn ($id) => (int) $id !== $paymentId)->values();
            if ($extraPaymentIds->isNotEmpty()) {
                $this->deletePaymentsByIds($extraPaymentIds->all());
            }
        } else {
            $paymentPayload = array_merge($paymentPayload, $this->loanSafeColumns('loan_payments', [
                'created_at' => now(),
            ]));
            $paymentId = (int) DB::connection('mysql_loan')->table('loan_payments')->insertGetId($paymentPayload);
        }

        $this->upsertSchedulePaymentDetail($paymentId, $method, $methodName, $amount, $reference, $note);

        $balance = max(0, round($amountDue - $amount, 2));
        DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $scheduleRow->id)->update($this->loanSafeColumns('loan_payment_schedules', [
            'paid_amount' => $amount,
            'amount_paid' => $amount,
            'paid_value' => $amount,
            'balance_amount' => $balance,
            'amount_balance' => $balance,
            'status' => $status === 'pay off' ? 'pay off' : ($balance <= 0 ? 'paid' : 'partial'),
            'paid_at' => $paidAt,
            'paid_date' => $paidDate,
            'updated_at' => now(),
        ]));
    }

    protected function deleteSchedulePayments(int $loan, int $schedule): void
    {
        $paymentIds = DB::connection('mysql_loan')
            ->table('loan_payments')
            ->where('loan_id', $loan)
            ->where('schedule_id', $schedule)
            ->pluck('id')
            ->all();

        $this->deletePaymentsByIds($paymentIds);
    }

    protected function deletePaymentsByIds(array $paymentIds): void
    {
        $paymentIds = array_values(array_filter(array_map('intval', $paymentIds)));
        if (empty($paymentIds)) {
            return;
        }

        if ($this->loanTableExists('loan_payment_details')) {
            DB::connection('mysql_loan')->table('loan_payment_details')->whereIn('payment_id', $paymentIds)->delete();
        }

        DB::connection('mysql_loan')->table('loan_payments')->whereIn('id', $paymentIds)->delete();
    }

    protected function upsertSchedulePaymentDetail(int $paymentId, string $method, string $methodName, float $amount, ?string $reference, ?string $note): void
    {
        if (! $this->loanTableExists('loan_payment_details')) {
            return;
        }

        $detail = DB::connection('mysql_loan')
            ->table('loan_payment_details')
            ->where('payment_id', $paymentId)
            ->orderBy('id')
            ->first();

        $payload = $this->loanSafeColumns('loan_payment_details', [
            'payment_id' => $paymentId,
            'payment_method_id' => null,
            'payment_method_snapshot' => $methodName,
            'method' => $method !== '' ? $method : $methodName,
            'currency' => 'USD',
            'amount' => $amount,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'reference_number' => $reference,
            'transaction_no' => $reference,
            'note' => $note,
            'meta_json' => json_encode(['source' => 'schedule_edit']),
            'updated_at' => now(),
        ]);

        if ($detail) {
            DB::connection('mysql_loan')->table('loan_payment_details')->where('id', $detail->id)->update($payload);
            return;
        }

        DB::connection('mysql_loan')->table('loan_payment_details')->insert(array_merge($payload, $this->loanSafeColumns('loan_payment_details', [
            'created_at' => now(),
        ])));
    }

    public function paymentCopyInfo(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $info = $this->buildLoanPaymentCopyInfo($loan, $this->attachLoanCustomerKhmerName($loanRow));

        return response()->json([
            'success' => true,
            'data' => [
                'info' => $info,
                'text' => $this->formatLoanPaymentCopyText($info),
            ],
        ]);
    }

    public function editWorkflow(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $collectionStatuses = ['new', 'active', 'follow_up', 'ptp', 'overdue', 'escalated', 'recovery', 'closed'];
        $riskLevels = ['low', 'medium', 'high', 'critical'];
        $ptpStatuses = ['open', 'kept', 'broken', 'cancelled'];
        $skipLevels = ['none', 'soft', 'medium', 'hard'];

        return view('loanmanagement::loans.partials.edit_workflow_modal', compact(
            'loanRow',
            'collectionStatuses',
            'riskLevels',
            'ptpStatuses',
            'skipLevels'
        ));
    }

    public function updateWorkflow(Request $request, int $loan)
    {
        try {
            abort_if(! $this->loanTableExists('loans'), 404);

            $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
            abort_if(! $loanRow, 404);

            $data = $request->validate([
                'source_type' => 'nullable|string|max:30',
                'source_created_at' => 'nullable|date',
                'stock_already_deducted' => 'nullable|boolean',
                'collection_status' => 'nullable|string|max:50',
                'risk_level' => 'nullable|string|max:50',
                'collection_priority' => 'nullable|integer|min:0|max:255',
                'ptp_date' => 'nullable|date',
                'ptp_amount' => 'nullable|numeric|min:0',
                'ptp_note' => 'nullable|string|max:5000',
                'ptp_status' => 'nullable|string|max:30',
                'broken_ptp_count' => 'nullable|integer|min:0',
                'last_contact_at' => 'nullable|date',
                'last_contact_result' => 'nullable|string|max:100',
                'next_followup_at' => 'nullable|date',
                'field_visit_required' => 'nullable|boolean',
                'skip_level' => 'nullable|string|max:30',
                'legal_stage' => 'nullable|string|max:100',
                'recovery_stage' => 'nullable|string|max:100',
                'repossession_status' => 'nullable|string|max:100',
                'blacklisted_at' => 'nullable|date',
                'written_off_at' => 'nullable|date',
                'assigned_collection_team' => 'nullable|string|max:100',
                'days_past_due' => 'nullable|integer|min:0',
                'overdue_bucket' => 'nullable|string|max:30',
                'contact_attempt_count' => 'nullable|integer|min:0',
                'last_payment_date' => 'nullable|date',
                'last_payment_amount' => 'nullable|numeric|min:0',
                'recovery_score' => 'nullable|integer|min:0|max:65535',
            ]);

            $data['stock_already_deducted'] = (int) $request->boolean('stock_already_deducted');
            $data['field_visit_required'] = (int) $request->boolean('field_visit_required');

            DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', array_merge($data, [
                'updated_at' => now(),
            ])));

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Source & Collection Workflow updated successfully.',
                ]);
            }

            return redirect()
                ->route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []))
                ->with('status', ['success' => 1, 'msg' => 'Source & Collection Workflow updated successfully.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update workflow: '.$e->getMessage(),
                    'data' => [
                        'detail' => $e->getFile().':'.$e->getLine(),
                    ],
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'workflow_error' => 'Unable to update workflow: '.$e->getMessage(),
                ]);
        }
    }

    public function destroySchedule(Request $request, int $loan, int $schedule)
    {
        try {
            abort_if(! $this->loanTableExists('loans'), 404);
            abort_if(! $this->loanTableExists('loan_payment_schedules'), 404);

            $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
            abort_if(! $loanRow, 404);

            $scheduleQuery = DB::connection('mysql_loan')
                ->table('loan_payment_schedules')
                ->where('id', $schedule)
                ->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($scheduleQuery, 'loan_payment_schedules');
            $scheduleRow = $scheduleQuery->first();
            abort_if(! $scheduleRow, 404);

            DB::connection('mysql_loan')->transaction(function () use ($loan, $scheduleRow) {
                $this->deleteSchedulePayments($loan, (int) $scheduleRow->id);

                if ($this->loanTableHasCol('loan_payment_schedules', 'deleted_at')) {
                    DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $scheduleRow->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]));
                } else {
                    DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $scheduleRow->id)->delete();
                }

                $this->refreshLoanBalanceFromSchedules($loan);
                $this->refreshLoanPaymentTotals($loan, 0);
            });

            if ($request->ajax() || $request->wantsJson()) {
                $sectionData = $this->freshLoanSectionsResponseData($request, $loan);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment schedule deleted successfully',
                    'data' => [
                        'redirect_url' => $sectionData['redirect_url'],
                        'sections_html' => $sectionData['sections_html'],
                        'sections_target' => $sectionData['sections_target'],
                    ],
                ]);
            }

            return redirect()
                ->route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []))
                ->with('status', ['success' => 1, 'msg' => 'Payment schedule deleted successfully']);
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to delete payment schedule: '.$e->getMessage(),
                    'data' => [
                        'detail' => $e->getFile().':'.$e->getLine(),
                    ],
                ], 500);
            }

            return back()
                ->withErrors(['schedule_error' => 'Unable to delete payment schedule: '.$e->getMessage()]);
        }
    }

    protected function freshLoanSectionsResponseData(Request $request, int $loan): array
    {
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $context = strtolower((string) $request->input('sections_context', $request->query('sections_context', 'edit')));
        $isShowContext = in_array($context, ['show', 'detail', 'loan_detail'], true);

        if ($isShowContext) {
            return [
                'sections_target' => 'loanShowSections',
                'sections_html' => view(
                    'loanmanagement::loans.partials.show_sections',
                    array_merge(['loanRow' => $loanRow], $this->loadLoanShowSectionData($loan, $loanRow, $loanRow->sell_due_amount_snapshot ?? null))
                )->render(),
                'redirect_url' => route('loan-management.loans.view', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])),
            ];
        }

        return [
            'sections_target' => 'loanEditSections',
            'sections_html' => view(
                'loanmanagement::loans.partials.edit_sections',
                array_merge([
                    'loanRow' => $loanRow,
                    'backCustomerId' => request('customer_id') ?: ($loanRow->customer_id ?? null),
                ], $this->loadLoanEditSectionData($loan))
            )->render(),
            'redirect_url' => route('loan-management.loans.edit', ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])),
        ];
    }

    public function updateSchedulesFromEdit(Request $request, int $loan)
    {
        try {
            abort_if(! $this->loanTableExists('loans'), 404);
            abort_if(! $this->loanTableExists('loan_payment_schedules'), 404);

            $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
            abort_if(! $loanRow, 404);

            $data = $request->validate([
                'principal_amount' => 'required|numeric|min:0.01',
                'interest_amount' => 'nullable|numeric|min:0',
                'installment_count' => 'required|integer|min:1|max:1000',
                'duration_months' => 'nullable|integer|min:1|max:1000',
                'interest_rate' => 'nullable|numeric|min:0',
                'interest_type' => 'nullable|in:flat,reducing_balance',
                'payment_frequency' => 'required|string|in:daily,weekly,biweekly,monthly,quarterly,yearly',
                'first_due_date' => 'required|date',
            ]);

            $data['duration_months'] = (int) ($data['duration_months'] ?? $data['installment_count']);
            $data['installment_count'] = (int) $data['installment_count'];
            $data['interest_type'] = $data['interest_type'] ?? 'flat';
            $data['interest_rate'] = (float) ($data['interest_rate'] ?? 0);
            $data = $this->recalculateEditScheduleAmounts($loan, $loanRow, $data);

            DB::connection('mysql_loan')->transaction(function () use ($loan, $data, $loanRow) {
                $meta = ! empty($loanRow->meta_json) ? (json_decode((string) $loanRow->meta_json, true) ?: []) : [];
                $meta['interest_rate'] = $data['interest_rate'];
                $meta['interest_type'] = $data['interest_type'];
                $meta['duration_months'] = $data['duration_months'];
                $meta['payment_frequency'] = $data['payment_frequency'];
                $meta['first_due_date'] = $data['first_due_date'];

                DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
                    'principal_amount' => $data['principal_amount'],
                    'interest_amount' => $data['interest_amount'] ?? ($loanRow->interest_amount ?? 0),
                    'installment_count' => $data['installment_count'],
                    'duration_months' => $data['duration_months'],
                    'interest_rate' => $data['interest_rate'],
                    'interest_type' => $data['interest_type'],
                    'payment_frequency' => $data['payment_frequency'],
                    'first_due_date' => $data['first_due_date'],
                    'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]));

                $scheduleData = $data;
                unset($scheduleData['interest_amount']);
                $this->syncLoanSchedulesFromEdit($loan, $scheduleData, $loanRow);
            });

            $freshLoanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first() ?: $loanRow;
            $sectionHtml = view(
                'loanmanagement::loans.partials.edit_sections',
                array_merge([
                    'loanRow' => $freshLoanRow,
                    'backCustomerId' => request('customer_id') ?: ($freshLoanRow->customer_id ?? null),
                ], $this->loadLoanEditSectionData($loan))
            )->render();

            return response()->json([
                'success' => true,
                'message' => 'Payment schedules updated successfully.',
                'data' => [
                    'sections_html' => $sectionHtml,
                    'loan' => [
                        'principal_amount' => (float) ($freshLoanRow->principal_amount ?? 0),
                        'interest_amount' => (float) ($freshLoanRow->interest_amount ?? 0),
                        'total_amount' => (float) ($freshLoanRow->total_amount ?? 0),
                        'paid_amount' => (float) ($freshLoanRow->paid_amount ?? 0),
                        'balance_amount' => (float) ($freshLoanRow->balance_amount ?? 0),
                        'down_payment' => (float) ($freshLoanRow->down_payment ?? 0),
                        'installment_count' => (int) ($freshLoanRow->installment_count ?? 0),
                        'duration_months' => (int) ($freshLoanRow->duration_months ?? $freshLoanRow->installment_count ?? 0),
                        'payment_frequency' => (string) ($freshLoanRow->payment_frequency ?? 'monthly'),
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to update payment schedules: '.$e->getMessage(),
                'data' => [
                    'detail' => $e->getFile().':'.$e->getLine(),
                ],
            ], 500);
        }
    }

    protected function recalculateEditScheduleAmounts(int $loan, object $loanRow, array $data): array
    {
        $productTotal = $this->loanItemsUnitPriceTotal($loan);
        if ($productTotal <= 0) {
            $productTotal = (float) ($loanRow->sell_final_total_snapshot ?? 0);
        }
        if ($productTotal <= 0) {
            $productTotal = (float) ($data['principal_amount'] ?? $loanRow->principal_amount ?? 0)
                + (float) ($loanRow->down_payment ?? 0);
        }

        $depositAmounts = $this->loanDepositPaymentCopyAmounts($loan, $loanRow);
        $depositTotal = round($depositAmounts['cash'] + $depositAmounts['bank'], 2);
        $principal = max(0.01, round($productTotal - $depositTotal, 2));
        $months = max(1, (int) ($data['duration_months'] ?? $data['installment_count'] ?? 1));
        $rate = max(0, (float) ($data['interest_rate'] ?? 0)) / 100;
        $interestType = in_array(($data['interest_type'] ?? 'flat'), ['flat', 'reducing_balance'], true)
            ? $data['interest_type']
            : 'flat';
        $interestTotal = 0.0;
        $remaining = $principal;
        $principalPer = round($principal / $months, 2);

        for ($i = 1; $i <= $months; $i++) {
            $principalPart = $i === $months ? round($remaining, 2) : $principalPer;
            $interestTotal += $interestType === 'reducing_balance'
                ? round($remaining * $rate, 2)
                : round($principal * $rate, 2);
            $remaining = max(0, round($remaining - $principalPart, 2));
        }

        $data['principal_amount'] = $principal;
        $data['interest_amount'] = round($interestTotal, 2);

        return $data;
    }

    protected function ultimatePosPaymentTypes(object $loanRow): array
    {
        $businessId = (int) (session('user.business_id') ?? 0);
        $locationId = $loanRow->main_location_id ?? null;

        $paymentTypes = app(TransactionUtil::class)->payment_types($locationId, true, $businessId);

        return ! empty($paymentTypes) ? $paymentTypes : ['cash' => 'Cash'];
    }

    protected function paymentMethodName(string $method, array $paymentTypes): string
    {
        return (string) ($paymentTypes[$method] ?? ucfirst(str_replace('_', ' ', $method)));
    }

    protected function generateUniquePaymentNumber(int $loanId): string
    {
        $prefix = 'PAY-'.now()->format('YmdHis').'-'.$loanId.'-';
        $attempt = 0;
        $referenceColumn = null;

        foreach (['payment_number', 'payment_ref_no', 'receipt_number', 'reference_number'] as $column) {
            if ($this->loanTableHasCol('loan_payments', $column)) {
                $referenceColumn = $column;
                break;
            }
        }

        do {
            $candidate = $prefix.random_int(1000, 9999);
            $exists = $referenceColumn
                ? DB::connection('mysql_loan')->table('loan_payments')
                    ->where($referenceColumn, $candidate)
                    ->exists()
                : false;
            $attempt++;
        } while ($exists && $attempt < 10);

        return $exists ? $prefix.uniqid() : $candidate;
    }

    protected function calculatePayOffAmount($schedules, object $loanRow): float
    {
        if ($schedules->isEmpty()) {
            return max(0.01, (float) ($loanRow->balance_amount ?? 0));
        }

        $remainingPrincipal = (float) $schedules->sum(function ($schedule) {
            return (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0);
        });

        $oneMonthInterest = (float) $schedules
            ->map(fn ($schedule) => (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0))
            ->first(fn ($interest) => $interest > 0, 0);

        $payOffAmount = round($remainingPrincipal + $oneMonthInterest, 2);

        return max(0.01, $payOffAmount > 0 ? $payOffAmount : (float) ($loanRow->balance_amount ?? 0));
    }

    protected function applyLoanPayOffToSchedules(int $loan, float $amount, float $discountAmount, string $paidAt): void
    {
        if (! $this->loanTableExists('loan_payment_schedules')) {
            return;
        }

        $schedules = DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->where('loan_id', $loan)
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
            ->when($this->loanTableHasCol('loan_payment_schedules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
            ->orderBy('id')
            ->get();

        $payOffSchedule = $schedules->first();
        if (! $payOffSchedule) {
            return;
        }

        $remainingPrincipal = round((float) $schedules->sum(function ($schedule) {
            return (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0);
        }), 2);
        $oneMonthInterest = round((float) $schedules
            ->map(fn ($schedule) => (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0))
            ->first(fn ($interest) => $interest > 0, 0), 2);
        $calculatedPayOffAmount = round($remainingPrincipal + $oneMonthInterest, 2);
        $discountAmount = min(max(0, round($discountAmount, 2)), $calculatedPayOffAmount);
        $paidAmount = round($amount, 2);
        $payOffAmount = max($calculatedPayOffAmount, round($paidAmount + $discountAmount, 2));

        DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $payOffSchedule->id)->update($this->loanSafeColumns('loan_payment_schedules', [
            'principal_amount' => $remainingPrincipal,
            'principal_due' => $remainingPrincipal,
            'principal' => $remainingPrincipal,
            'installment_value' => $remainingPrincipal,
            'interest_amount' => $oneMonthInterest,
            'interest_due' => $oneMonthInterest,
            'interest' => $oneMonthInterest,
            'benefit_value' => $oneMonthInterest,
            'schedule_amount' => $payOffAmount,
            'amount_due' => $payOffAmount,
            'total' => $payOffAmount,
            'amount_paid' => $paidAmount,
            'paid_amount' => $paidAmount,
            'paid_value' => $paidAmount,
            'discount_amount' => $discountAmount,
            'amount_balance' => 0,
            'balance_amount' => 0,
            'status' => 'pay off',
            'paid_at' => $paidAt,
            'paid_date' => substr($paidAt, 0, 10),
            'updated_at' => now(),
        ]));

        $futureScheduleIds = $schedules
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== (int) $payOffSchedule->id)
            ->values();

        if ($futureScheduleIds->isEmpty()) {
            return;
        }

        if ($this->loanTableHasCol('loan_payment_schedules', 'deleted_at')) {
            DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('id', $futureScheduleIds->all())->update($this->loanSafeColumns('loan_payment_schedules', [
                'deleted_at' => now(),
                'updated_at' => now(),
            ]));
            return;
        }

        DB::connection('mysql_loan')->table('loan_payment_schedules')->whereIn('id', $futureScheduleIds->all())->delete();
    }

    protected function applyLoanPaymentToSchedules(int $loan, float $amount, string $paidAt, ?int $selectedScheduleId = null): void
    {
        if (! $this->loanTableExists('loan_payment_schedules')) {
            return;
        }

        $query = DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->where('loan_id', $loan)
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late']);
        $this->excludeDeletedLoanRows($query, 'loan_payment_schedules');

        if ($selectedScheduleId) {
            $query->orderByRaw('CASE WHEN id = '.((int) $selectedScheduleId).' THEN 0 ELSE 1 END');
        }

        $schedules = $query
            ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
            ->orderBy('id')
            ->get();

        $remaining = $amount;
        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? $schedule->schedule_amount ?? $schedule->amount_due ?? 0);
            if ($due <= 0) {
                continue;
            }

            $applied = min($remaining, $due);
            $existingPaidAmount = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
            $newPaid = $existingPaidAmount + $applied;
            $newBalance = max(0, $due - $applied);

            DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $schedule->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                'amount_paid' => $newPaid,
                'paid_amount' => $newPaid,
                'amount_balance' => $newBalance,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : 'partial',
                'paid_at' => $newBalance <= 0 ? $paidAt : null,
                'updated_at' => now(),
            ]));

            $remaining -= $applied;
        }
    }

    protected function refreshLoanPaymentTotals(int $loan, float $amount): void
    {
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $paymentAmountColumn = 'total_paid_base';
        if ($this->loanTableHasCol('loan_payments', 'total_paid_base')) {
            $paymentAmountColumn = 'total_paid_base';
        } elseif ($this->loanTableHasCol('loan_payments', 'total_paid')) {
            $paymentAmountColumn = 'total_paid';
        } elseif ($this->loanTableHasCol('loan_payments', 'amount')) {
            $paymentAmountColumn = 'amount';
        }

        $newPaidAmount = (float) DB::connection('mysql_loan')
            ->table('loan_payments')
            ->where('loan_id', $loan)
            ->sum($paymentAmountColumn);

        $scheduleBalance = 0.0;
        $hasScheduleBalance = false;
        if ($this->loanTableExists('loan_payment_schedules')) {
            $scheduleBalanceQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($scheduleBalanceQuery, 'loan_payment_schedules');

            if ($this->loanTableHasCol('loan_payment_schedules', 'balance_amount')) {
                $scheduleBalance = (float) $scheduleBalanceQuery->sum('balance_amount');
                $hasScheduleBalance = true;
            } elseif ($this->loanTableHasCol('loan_payment_schedules', 'amount_balance')) {
                $scheduleBalance = (float) $scheduleBalanceQuery->sum('amount_balance');
                $hasScheduleBalance = true;
            }
        }

        if ($hasScheduleBalance) {
            $newBalanceAmount = $scheduleBalance;
        } else {
            $principal = (float) ($loanRow->principal_amount ?? $loanRow->total_payable_amount ?? 0);
            $newBalanceAmount = max(0, $principal - $newPaidAmount);
        }

        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
            'paid_amount' => $newPaidAmount,
            'balance_amount' => $newBalanceAmount,
            'status' => $newBalanceAmount <= 0 ? 'completed' : ($loanRow->status ?? 'active'),
            'updated_at' => now(),
        ]));
    }

    protected function refreshLoanBalanceFromSchedules(int $loan): void
    {
        if (! $this->loanTableExists('loans') || ! $this->loanTableExists('loan_payment_schedules')) {
            return;
        }

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $balanceColumn = $this->loanTableHasCol('loan_payment_schedules', 'balance_amount')
            ? 'balance_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'amount_balance') ? 'amount_balance' : null);

        if (! $balanceColumn) {
            return;
        }

        $scheduleBalanceQuery = DB::connection('mysql_loan')
            ->table('loan_payment_schedules')
            ->where('loan_id', $loan);
        $this->excludeDeletedLoanRows($scheduleBalanceQuery, 'loan_payment_schedules');

        $scheduleBalance = (float) $scheduleBalanceQuery->sum($balanceColumn);

        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
            'balance_amount' => $scheduleBalance,
            'status' => $scheduleBalance <= 0 ? 'completed' : (($loanRow->status ?? null) === 'completed' ? 'active' : ($loanRow->status ?? 'active')),
            'updated_at' => now(),
        ]));
    }

    protected function syncLoanSchedulesFromEdit(int $loan, array $data, object $loanRow): void
    {
        if (! $this->loanTableExists('loan_payment_schedules')) {
            return;
        }

        $months = max(1, (int) ($data['duration_months'] ?? $data['installment_count'] ?? $loanRow->duration_months ?? $loanRow->installment_count ?? 1));
        $principal = max(0, round((float) ($data['principal_amount'] ?? $loanRow->principal_amount ?? 0), 2));
        if ($principal <= 0 || $months <= 0) {
            return;
        }

        $frequency = strtolower((string) ($data['payment_frequency'] ?? $loanRow->payment_frequency ?? 'monthly'));
        $interestRate = max(0, (float) ($data['interest_rate'] ?? 0)) / 100;
        $enteredInterestTotal = max(0, round((float) ($data['interest_amount'] ?? $loanRow->interest_amount ?? 0), 2));
        $interestType = in_array(($data['interest_type'] ?? 'flat'), ['flat', 'reducing_balance'], true)
            ? $data['interest_type']
            : 'flat';
        $firstDue = \Carbon\Carbon::parse($data['first_due_date'] ?? $loanRow->first_due_date ?? now()->addMonth()->toDateString());

        $rows = [];
        $remaining = $principal;
        $principalPer = round($principal / $months, 2);
        $interestPer = round($enteredInterestTotal / $months, 2);
        $assignedInterest = 0.0;
        $flatInterestPer = round($principal * $interestRate, 2);

        for ($i = 1; $i <= $months; $i++) {
            $dueDate = match ($frequency) {
                'daily' => $firstDue->copy()->addDays($i - 1),
                'weekly' => $firstDue->copy()->addWeeks($i - 1),
                'biweekly' => $firstDue->copy()->addWeeks(($i - 1) * 2),
                'quarterly' => $firstDue->copy()->addMonths(($i - 1) * 3),
                'yearly' => $firstDue->copy()->addYears($i - 1),
                default => $firstDue->copy()->addMonths($i - 1),
            };

            $principalPart = $i === $months ? round($remaining, 2) : $principalPer;
            if (array_key_exists('interest_amount', $data)) {
                $interest = $i === $months
                    ? round($enteredInterestTotal - $assignedInterest, 2)
                    : $interestPer;
                $assignedInterest = round($assignedInterest + $interest, 2);
            } else {
                $interest = $interestType === 'reducing_balance'
                    ? round($remaining * $interestRate, 2)
                    : $flatInterestPer;
            }
            $amountDue = round($principalPart + $interest, 2);
            $remaining = max(0, round($remaining - $principalPart, 2));

            $rows[] = [
                'installment_no' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal' => $principalPart,
                'interest' => $interest,
                'amount_due' => $amountDue,
            ];
        }

        $existing = DB::connection('mysql_loan')
            ->table('loan_payment_schedules')
            ->where('loan_id', $loan)
            ->when($this->loanTableHasCol('loan_payment_schedules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'installment_no') ? 'installment_no' : 'id')
            ->orderBy('id')
            ->get()
            ->values();

        foreach ($rows as $index => $row) {
            $schedule = $existing->get($index);
            $paid = $schedule
                ? round((float) ($schedule->paid_amount ?? $schedule->amount_paid ?? $schedule->paid_value ?? 0), 2)
                : 0.0;
            $balance = max(0, round($row['amount_due'] - $paid, 2));
            $status = $balance <= 0 && $row['amount_due'] > 0
                ? 'paid'
                : ($paid > 0 ? 'partial' : 'unpaid');

            $payload = $this->loanSafeColumns('loan_payment_schedules', [
                'loan_id' => $loan,
                'installment_no' => $row['installment_no'],
                'due_date' => $row['due_date'],
                'principal_amount' => $row['principal'],
                'principal_due' => $row['principal'],
                'principal' => $row['principal'],
                'installment_value' => $row['principal'],
                'interest_amount' => $row['interest'],
                'interest_due' => $row['interest'],
                'interest' => $row['interest'],
                'benefit_value' => $row['interest'],
                'schedule_amount' => $row['amount_due'],
                'amount_due' => $row['amount_due'],
                'total' => $row['amount_due'],
                'paid_amount' => $paid,
                'amount_paid' => $paid,
                'paid_value' => $paid,
                'balance_amount' => $balance,
                'amount_balance' => $balance,
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($schedule->paid_at ?? now()) : ($paid > 0 ? ($schedule->paid_at ?? null) : null),
                'paid_date' => $status === 'paid' ? ($schedule->paid_date ?? now()->toDateString()) : ($paid > 0 ? ($schedule->paid_date ?? null) : null),
                'updated_at' => now(),
            ]);

            if ($schedule) {
                DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $schedule->id)->update($payload);
            } else {
                DB::connection('mysql_loan')->table('loan_payment_schedules')->insert(array_merge($payload, $this->loanSafeColumns('loan_payment_schedules', [
                    'created_at' => now(),
                ])));
            }
        }

        $extraSchedules = $existing->slice(count($rows));
        foreach ($extraSchedules as $extraSchedule) {
            $paid = round((float) ($extraSchedule->paid_amount ?? $extraSchedule->amount_paid ?? $extraSchedule->paid_value ?? 0), 2);
            if ($paid > 0) {
                continue;
            }

            if ($this->loanTableHasCol('loan_payment_schedules', 'deleted_at')) {
                DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $extraSchedule->id)->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $extraSchedule->id)->delete();
            }
        }

        $scheduleInterestTotal = round(collect($rows)->sum('interest'), 2);
        $scheduleAmountTotal = round(collect($rows)->sum('amount_due'), 2);
        $balanceColumn = $this->loanTableHasCol('loan_payment_schedules', 'balance_amount')
            ? 'balance_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'amount_balance') ? 'amount_balance' : null);
        $scheduleBalanceTotal = $balanceColumn
            ? (float) DB::connection('mysql_loan')
                ->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->when($this->loanTableHasCol('loan_payment_schedules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->sum($balanceColumn)
            : $scheduleAmountTotal;

        $penaltyAmount = max(0, round((float) ($data['penalty_amount'] ?? $loanRow->penalty_amount ?? 0), 2));
        $discountAmount = max(0, round((float) ($data['discount_amount'] ?? $loanRow->discount_amount ?? 0), 2));
        $paidAmount = max(0, round((float) ($data['paid_amount'] ?? $loanRow->paid_amount ?? 0), 2));
        $loanTotalAmount = max(0, round($scheduleAmountTotal + $penaltyAmount - $discountAmount, 2));
        $loanBalanceAmount = max(0, round($loanTotalAmount - $paidAmount, 2));

        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
            'financed_amount' => $principal,
            'interest_amount' => $scheduleInterestTotal,
            'total_amount' => $loanTotalAmount,
            'total_payable_amount' => $loanTotalAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => $loanBalanceAmount,
            'installment_count' => $months,
            'duration_months' => $months,
            'updated_at' => now(),
        ]));
    }

    protected function paymentTelegramPhotoPaths(array $paymentIds): array
    {
        $paymentIds = array_values(array_filter(array_map('intval', $paymentIds)));
        if (empty($paymentIds) || ! $this->loanTableExists('loan_files')) {
            return [];
        }

        $query = DB::connection('mysql_loan')->table('loan_files')
            ->where('fileable_type', 'loan_payment')
            ->whereIn('fileable_id', $paymentIds)
            ->where('category', 'payment_doc');

        if ($this->loanTableHasCol('loan_files', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('id')
            ->get()
            ->filter(fn ($file) => $this->isTelegramPhotoFile($file))
            ->map(fn ($file) => $this->loanFileAbsolutePath($file))
            ->filter(fn ($path) => is_string($path) && is_readable($path))
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    protected function isTelegramPhotoFile(object $file): bool
    {
        $mime = strtolower((string) ($file->mime_type ?? ''));
        if (Str::startsWith($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) ($file->original_name ?? $file->path ?? ''), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    protected function loanFileAbsolutePath(object $file): ?string
    {
        $path = trim((string) ($file->path ?? ''));
        if ($path === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $disk = trim((string) ($file->disk ?? 'public')) ?: 'public';
        try {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public function notifyLocationTelegram(int $loan, string $event, ?float $amount = null, array $paymentLines = [], ?string $paidDate = null, array $photoPaths = []): void
    {
        if (! $this->loanTableExists('loans') || ! $this->loanTableExists('loan_business_locations')) {
            return;
        }

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $location = null;
        if (! empty($loanRow->business_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loanRow->business_location_id)->first();
        }
        if (! $location && ! empty($loanRow->main_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $loanRow->main_location_id)->first();
        }

        if (! $location) {
            return;
        }

        if ($event === 'payment' && empty($location->telegram_notify_payment)) {
            return;
        }
        if ($event === 'installment' && empty($location->telegram_notify_installment)) {
            return;
        }

        $chatId = $this->telegramChatIdForEvent($location, $event);
        if ($chatId === '') {
            return;
        }

        $message = $event === 'payment'
            ? $this->paymentTelegramMessage($loan, $loanRow, $paymentLines, $paidDate)
            : "Installment loan created\nLoan: ".($loanRow->loan_number ?? $loanRow->id)."\nCustomer: ".($loanRow->customer_name_snapshot ?? '-')."\nLocation: ".($location->name ?? '-')."\nTotal: ".number_format((float) ($loanRow->principal_amount ?? $loanRow->total_payable_amount ?? 0), 2).' '.($loanRow->currency ?? 'USD');

        $moduleType = $event === 'payment' ? 'loan_payment' : 'loan_installment';
        $sendResult = null;

        if ($event === 'payment' && ! empty($photoPaths)) {
            $sendResult = $this->sendPaymentTelegramPhotos($chatId, $message, $photoPaths);
        }

        if (empty($sendResult['success'])) {
            $sendResult = app(\Modules\NotificationCenter\Services\NotificationService::class)->sendToChat(
                $moduleType,
                $chatId,
                $message,
                ['loan_id' => $loan, 'event' => $event, 'amount' => $amount]
            );
        }

        $this->logTelegramNotification($loanRow, $location, $event, $message, 'sent', null, $chatId);
    }

    protected function sendPaymentTelegramPhotos(string $chatId, string $message, array $photoPaths): array
    {
        $telegram = app(\Modules\NotificationCenter\Services\TelegramService::class);
        $sent = 0;
        $errors = [];

        foreach (array_values($photoPaths) as $index => $photoPath) {
            $caption = $index === 0 ? $this->telegramPhotoCaption($message) : null;
            $result = $telegram->sendPhoto($chatId, $photoPath, $caption, basename($photoPath));

            if (! empty($result['success'])) {
                $sent++;
                continue;
            }

            $errors[] = $result['error'] ?? $result['msg'] ?? 'Photo send failed';
        }

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'errors' => $errors,
        ];
    }

    protected function telegramPhotoCaption(string $message): string
    {
        $message = trim($message);

        return mb_strlen($message) > 1000 ? mb_substr($message, 0, 997).'...' : $message;
    }

    protected function paymentTelegramMessage(int $loan, object $loanRow, array $paymentLines = [], ?string $paidDate = null): string
    {
        return implode("\n", $this->paymentTelegramMessageLines($loan, $loanRow, $paymentLines, $paidDate, true));
    }

    protected function paymentTelegramMessageLines(int $loan, object $loanRow, array $paymentLines = [], ?string $paidDate = null, bool $includePrintUrl = false): array
    {
        $loanRow = $this->attachLoanCustomerKhmerName($loanRow);
        $scheduleCounts = $this->loanPaymentScheduleCounts($loan, $loanRow);
        $customerName = trim((string) ($loanRow->customer_khmer_name ?? '')) ?: ($loanRow->customer_name_snapshot ?? '-');
        $collectorName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
        if ($collectorName === '') {
            $collectorName = auth()->user()->username ?? auth()->user()->name ?? '-';
        }

        $paymentText = $this->paymentTelegramAmountLines($paymentLines);

        $lines = [
            'កាលបរិច្ឆេទ'.\Carbon\Carbon::parse($paidDate ?: now())->format('m/d/Y'),
            '',
            'វិក័យប័ត្រ        :'.($loanRow->source_invoice_no ?? $loanRow->loan_number ?? $loanRow->id),
            'អតិថិជនឈ្មោះ :'.$customerName,
            'ចំនួនខែកម្ចី       :'.$scheduleCounts['total'],
            'ចំនួនខែបានបង់ :'.$scheduleCounts['paid'],
            'ចំនួនខែនៅខ្វះ   :'.$scheduleCounts['remaining'],
            $paymentText,
        ];

        if ($includePrintUrl) {
            $lines[] = 'វិក្កយបត្របោះពុម្ព: '.route('loan-management.loans.print', ['loan' => $loan], true);
        }

        $lines[] = 'ដោយ:'.$collectorName;

        return collect($lines)->flatMap(function ($line) {
            return explode("\n", (string) $line);
        })->values()->all();
    }

    protected function loanPaymentScheduleCounts(int $loan, ?object $loanRow = null): array
    {
        $total = 0;
        $paid = 0;

        if ($this->loanTableExists('loan_payment_schedules')) {
            $query = DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($query, 'loan_payment_schedules');

            $schedules = $query->get();
            $total = $schedules->count();
            $paid = $schedules->filter(function ($schedule) {
                $status = strtolower((string) ($schedule->status ?? ''));
                $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0);

                return in_array($status, ['paid', 'completed'], true) || ($status !== '' && $balance <= 0);
            })->count();
        }

        return [
            'total' => $total ?: (int) ($loanRow->installment_count ?? $loanRow->duration_months ?? 0),
            'paid' => $paid,
            'remaining' => max(0, ($total ?: (int) ($loanRow->installment_count ?? $loanRow->duration_months ?? 0)) - $paid),
        ];
    }

    protected function paymentTelegramAmountLines(array $paymentLines): string
    {
        $cash = 0.0;
        $banks = [];

        foreach ($paymentLines as $line) {
            $amount = (float) ($line['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $method = trim((string) ($line['method_name'] ?? $line['method'] ?? ''));
            $methodKey = strtolower($method);
            if ($methodKey === 'cash') {
                $cash += $amount;
                continue;
            }

            $displayMethod = $method !== '' ? $method : 'Bank';
            $banks[$displayMethod] = ($banks[$displayMethod] ?? 0) + $amount;
        }

        $lines = [];
        if ($cash > 0) {
            $lines[] = 'លុយ '.$this->khmerDollarCentText($cash);
        }

        foreach ($banks as $method => $amount) {
            $lines[] = 'និង'.$method.' '.$this->khmerDollarCentText($amount);
        }

        return $lines ? implode("\n", $lines) : 'លុយ 0ដុល្លា00សេន';
    }

    protected function khmerDollarCentText(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        $dollars = (int) floor($amount);
        $cents = (int) round(($amount - $dollars) * 100);

        if ($cents >= 100) {
            $dollars++;
            $cents -= 100;
        }

        return $dollars.'ដុល្លា'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).'សេន';
    }

    protected function telegramChatIdForEvent(object $location, string $event): string
    {
        $chatId = $event === 'payment'
            ? ($location->telegram_payment_chat_id ?? null)
            : ($location->telegram_installment_chat_id ?? null);

        return trim((string) ($chatId ?: ($location->telegram_chat_id ?? '')));
    }

    protected function logTelegramNotification(object $loanRow, object $location, string $event, string $message, string $status, ?string $error = null, ?string $chatId = null): void
    {
        if (! $this->loanTableExists('loan_telegram_notifications')) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_telegram_notifications')->insert($this->loanSafeColumns('loan_telegram_notifications', [
            'customer_id' => $loanRow->customer_id ?? null,
            'loan_id' => $loanRow->id ?? null,
            'event_code' => $event,
            'chat_id' => $chatId ?: $this->telegramChatIdForEvent($location, $event),
            'message' => $error ? ($message."\n\nError: ".$error) : $message,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function countLoanRelatedRows(string $table, int $loan, string $loanColumn = 'loan_id'): int
    {
        if (! $this->loanTableExists($table) || ! $this->loanTableHasCol($table, $loanColumn)) {
            return 0;
        }

        $query = DB::connection('mysql_loan')->table($table)->where($loanColumn, $loan);
        $this->excludeDeletedLoanRows($query, $table);

        return (int) $query->count();
    }

    protected function excludeDeletedLoanRows($query, string $table)
    {
        if ($this->loanTableHasCol($table, 'deleted_at')) {
            $query->whereNull($table.'.deleted_at');
        }

        return $query;
    }

    protected function countLoanPayments(int $loan): int
    {
        if (! $this->loanTableExists('loan_payments')) {
            return 0;
        }

        $query = DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loan);
        $this->applyMonthlyPaymentFilter($query);

        return (int) $query->count();
    }

    protected function scheduleSummaryForLoan(int $loan): array
    {
        if (! $this->loanTableExists('loan_payment_schedules')) {
            return [
                'count' => 0,
                'principal_total' => 0.0,
                'interest_total' => 0.0,
                'amount_total' => 0.0,
                'paid_total' => 0.0,
                'balance_total' => 0.0,
            ];
        }

        $principalColumn = $this->loanTableHasCol('loan_payment_schedules', 'principal_amount')
            ? 'principal_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'principal_due') ? 'principal_due' : null);
        $interestColumn = $this->loanTableHasCol('loan_payment_schedules', 'interest_amount')
            ? 'interest_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'interest_due')
                ? 'interest_due'
                : ($this->loanTableHasCol('loan_payment_schedules', 'benefit_value') ? 'benefit_value' : null));
        $amountColumn = $this->loanTableHasCol('loan_payment_schedules', 'schedule_amount')
            ? 'schedule_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'amount_due') ? 'amount_due' : null);
        $paidColumn = $this->loanTableHasCol('loan_payment_schedules', 'paid_amount')
            ? 'paid_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'amount_paid')
                ? 'amount_paid'
                : ($this->loanTableHasCol('loan_payment_schedules', 'paid_value') ? 'paid_value' : null));
        $balanceColumn = $this->loanTableHasCol('loan_payment_schedules', 'balance_amount')
            ? 'balance_amount'
            : ($this->loanTableHasCol('loan_payment_schedules', 'amount_balance') ? 'amount_balance' : null);

        $summaryQuery = DB::connection('mysql_loan')
            ->table('loan_payment_schedules')
            ->where('loan_id', $loan);
        $this->excludeDeletedLoanRows($summaryQuery, 'loan_payment_schedules');

        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw(($principalColumn ? 'COALESCE(SUM('.$principalColumn.'), 0)' : '0').' as principal_total')
            ->selectRaw(($interestColumn ? 'COALESCE(SUM('.$interestColumn.'), 0)' : '0').' as interest_total')
            ->selectRaw(($amountColumn ? 'COALESCE(SUM('.$amountColumn.'), 0)' : '0').' as amount_total')
            ->selectRaw(($paidColumn ? 'COALESCE(SUM('.$paidColumn.'), 0)' : '0').' as paid_total')
            ->selectRaw(($balanceColumn ? 'COALESCE(SUM('.$balanceColumn.'), 0)' : '0').' as balance_total')
            ->first();

        return [
            'count' => (int) ($summary->aggregate_count ?? 0),
            'principal_total' => (float) ($summary->principal_total ?? 0),
            'interest_total' => (float) ($summary->interest_total ?? 0),
            'amount_total' => (float) ($summary->amount_total ?? 0),
            'paid_total' => (float) ($summary->paid_total ?? 0),
            'balance_total' => (float) ($summary->balance_total ?? 0),
        ];
    }

    protected function buildScheduleTotals($schedules): array
    {
        return [
            'principal_total' => (float) collect($schedules)->sum(fn ($schedule) => (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0)),
            'interest_total' => (float) collect($schedules)->sum(fn ($schedule) => (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0)),
            'amount_total' => (float) collect($schedules)->sum(fn ($schedule) => (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0)),
            'paid_total' => (float) collect($schedules)->sum(fn ($schedule) => (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0)),
            'balance_total' => (float) collect($schedules)->sum(fn ($schedule) => (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0)),
        ];
    }

    protected function loadLoanShowSectionData(int $loan, object $loanRow, $sourceDue = null): array
    {
        $items = collect();
        if ($this->loanTableExists('loan_items')) {
            $itemsQuery = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($itemsQuery, 'loan_items');
            $items = $itemsQuery->orderBy('id')->get();
        }

        $productItems = collect();
        if ($this->loanTableExists('loan_product_items')) {
            $productItemsQuery = DB::connection('mysql_loan')->table('loan_product_items');
            $this->excludeDeletedLoanRows($productItemsQuery, 'loan_product_items');
            $activeItemIds = $items->pluck('id')->filter()->values();

            if ($this->loanTableHasCol('loan_product_items', 'loan_id')) {
                $productItemsQuery->where('loan_id', $loan);
                if ($this->loanTableHasCol('loan_product_items', 'loan_item_id') && $activeItemIds->isNotEmpty()) {
                    $productItemsQuery->whereIn('loan_item_id', $activeItemIds);
                } elseif ($this->loanTableHasCol('loan_product_items', 'loan_item_id') && $items->isEmpty()) {
                    $productItemsQuery->whereRaw('1 = 0');
                }
                $productItems = $productItemsQuery->get();
            } elseif ($this->loanTableHasCol('loan_product_items', 'loan_item_id') && $items->count() > 0) {
                $productItems = $activeItemIds->isEmpty() ? collect() : $productItemsQuery->whereIn('loan_item_id', $activeItemIds)->get();
            }
        }

        $schedules = collect();
        if ($this->loanTableExists('loan_payment_schedules')) {
            $scheduleQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($scheduleQuery, 'loan_payment_schedules');
            $schedules = $scheduleQuery->orderBy('due_date')->get();
        }
        $schedules = $this->normalizeSchedulePrincipalFromDue($schedules, $loanRow, $sourceDue);

        $payments = collect();
        if ($this->loanTableExists('loan_payments')) {
            $paymentsQuery = DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loan);
            $this->applyMonthlyPaymentFilter($paymentsQuery);
            $payments = $paymentsQuery
                ->orderByDesc($this->loanTableHasCol('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at')
                ->get();
        }

        $statusLogs = $this->loanTableExists('loan_status_logs')
            ? DB::connection('mysql_loan')->table('loan_status_logs')->where('loan_id', $loan)->orderByDesc('created_at')->get()
            : collect();

        return [
            'items' => $items,
            'productItems' => $productItems,
            'schedules' => $schedules,
            'payments' => $payments,
            'statusLogs' => $statusLogs,
            'scheduleTotals' => $this->buildScheduleTotals($schedules),
        ];
    }

    protected function loadLoanEditSectionData(int $loan): array
    {
        $loanItems = collect();
        if ($this->loanTableExists('loan_items')) {
            $loanItemsQuery = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($loanItemsQuery, 'loan_items');
            $loanItems = $loanItemsQuery->orderBy('id')->get();
        }

        $schedules = collect();
        if ($this->loanTableExists('loan_payment_schedules')) {
            $scheduleQuery = DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($scheduleQuery, 'loan_payment_schedules');
            if ($this->loanTableHasCol('loan_payment_schedules', 'installment_no')) {
                $scheduleQuery->orderBy('installment_no');
            } elseif ($this->loanTableHasCol('loan_payment_schedules', 'due_date')) {
                $scheduleQuery->orderBy('due_date');
            }
            $schedules = $scheduleQuery->orderBy('id')->get();
        }

        $payments = collect();
        if ($this->loanTableExists('loan_payments')) {
            $paymentQuery = DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loan);
            if ($this->loanTableHasCol('loan_payments', 'paid_date')) {
                $paymentQuery->orderByDesc('paid_date');
            } elseif ($this->loanTableHasCol('loan_payments', 'paid_at')) {
                $paymentQuery->orderByDesc('paid_at');
            }
            $payments = $paymentQuery->orderByDesc('id')->limit(20)->get();
        }

        $schedulePayments = $this->loadLoanSchedulePayments($loan);
        $depositPayments = $this->expandPaymentsWithDetailsForPrint(
            $payments->filter(fn ($payment) => $this->isLoanPaymentRow($payment))->values()
        );
        $payments = $this->expandPaymentsWithDetailsForPrint($payments);
        $schedules = $this->attachPaidPaymentsToSchedules($schedules, $schedulePayments);

        return compact('loanItems', 'schedules', 'payments', 'depositPayments');
    }

    protected function loadLoanSchedulePayments(int $loan)
    {
        if (! $this->loanTableExists('loan_payments')) {
            return collect();
        }

        $query = DB::connection('mysql_loan')->table('loan_payments')->where('loan_id', $loan);
        if ($this->loanTableHasCol('loan_payments', 'schedule_id')) {
            $query->whereNotNull('schedule_id');
            if ($this->loanTableHasCol('loan_payments', 'payment_type')) {
                $query->where(function ($paymentTypeQuery) {
                    $paymentTypeQuery->whereNull('payment_type')
                        ->orWhere('payment_type', '')
                        ->orWhere('payment_type', 'monthly');
                });
            }
        } else {
            $this->applyMonthlyPaymentFilter($query);
        }
        if ($this->loanTableHasCol('loan_payments', 'paid_date')) {
            $query->orderByDesc('paid_date');
        } elseif ($this->loanTableHasCol('loan_payments', 'paid_at')) {
            $query->orderByDesc('paid_at');
        }

        return $this->expandPaymentsWithDetailsForPrint($query->orderByDesc('id')->get());
    }

    protected function attachPaidPaymentsToSchedules($schedules, $payments)
    {
        if ($schedules->isEmpty() || $payments->isEmpty()) {
            return $this->normalizeSchedulePaymentStatus($schedules);
        }

        $paymentsBySchedule = $payments
            ->filter(fn ($payment) => ! empty($payment->schedule_id))
            ->groupBy('schedule_id');

        if ($paymentsBySchedule->isEmpty()) {
            return $this->normalizeSchedulePaymentStatus($schedules);
        }

        return $this->normalizeSchedulePaymentStatus($schedules->map(function ($schedule) use ($paymentsBySchedule) {
            $schedulePayments = $paymentsBySchedule->get($schedule->id, collect())->values();
            if ($schedulePayments->isEmpty()) {
                $schedule->paid_payments = collect();
                return $schedule;
            }

            $paidFromPayments = round((float) $schedulePayments->sum(fn ($payment) => (float) (
                $payment->amount_base
                ?? $payment->total_paid_base
                ?? $payment->total_paid
                ?? $payment->amount
                ?? 0
            )), 2);
            $storedPaid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
            $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? $schedule->total ?? 0);

            if ($paidFromPayments > $storedPaid) {
                $schedule->paid_amount = $paidFromPayments;
                $schedule->amount_paid = $paidFromPayments;
            }

            if ($paidFromPayments > 0) {
                $balance = max(0, round($due - max($storedPaid, $paidFromPayments), 2));
                $schedule->balance_amount = $balance;
                $schedule->amount_balance = $balance;
                if ($balance <= 0) {
                    $schedule->status = 'paid';
                } elseif (! in_array(strtolower((string) ($schedule->status ?? '')), ['paid', 'completed'], true)) {
                    $schedule->status = 'partial';
                }
            }

            $schedule->paid_payments = $schedulePayments;
            $schedule->paid_payment_summary = $schedulePayments
                ->map(function ($payment) {
                    $date = $payment->paid_date ?? $payment->paid_at ?? null;
                    $dateText = $date ? \Carbon\Carbon::parse($date)->format('d-m-Y') : '-';
                    $method = trim((string) ($payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? ''));
                    $reference = trim((string) ($payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ''));
                    $amount = (float) ($payment->amount_base ?? $payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);

                    return trim($dateText.' · '.($method ?: 'Payment').' · '.number_format($amount, 2).($reference !== '' ? ' · '.$reference : ''));
                })
                ->implode("\n");

            return $schedule;
        }));
    }

    protected function normalizeSchedulePaymentStatus($schedules)
    {
        return $schedules->map(function ($schedule) {
            $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? $schedule->total ?? 0);
            $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
            $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? max(0, $due - $paid));
            $status = strtolower((string) ($schedule->status ?? 'pending'));

            if ($paid > 0 && ($balance <= 0 || ($due > 0 && $paid >= $due))) {
                $schedule->status = 'paid';
            } elseif ($paid > 0 && ! in_array($status, ['paid', 'completed'], true)) {
                $schedule->status = 'partial';
            }

            return $schedule;
        });
    }

    public function show(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerRow = null;
        if ($this->loanTableExists('loan_customers') && isset($loanRow->customer_id) && $loanRow->customer_id) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }
        $customerDisplayName = $loanRow->customer_name_snapshot ?? null;
        $customerPhoneDisplay = $loanRow->customer_phone_snapshot ?? null;
        $customerAddressDisplay = $loanRow->customer_address_snapshot ?? null;
        $mainContactIdDisplay = $loanRow->main_contact_id ?? null;
        $sourceTypeDisplay = $loanRow->source_type ?? null;
        $sourceTransactionIdDisplay = $loanRow->source_transaction_id ?? null;
        $sourceInvoiceDisplay = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotalDisplay = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaidDisplay = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDueDisplay = $loanRow->sell_due_amount_snapshot ?? null;
        if ($customerRow) {
            $first = isset($customerRow->first_name) ? trim((string) $customerRow->first_name) : '';
            $last = isset($customerRow->last_name) ? trim((string) $customerRow->last_name) : '';
            $fullFromParts = trim($first.' '.$last);
            if (!empty($fullFromParts)) {
                $customerDisplayName = $fullFromParts;
            } elseif (empty($customerDisplayName)) {
                $customerDisplayName = $customerRow->customer_name ?? ($customerRow->name ?? ($customerRow->full_name ?? null));
            }
        }
        if (empty($customerDisplayName)) {
            $customerDisplayName = '-';
        }
        $customerDisplayName = Str::of((string) $customerDisplayName)->squish()->value();

        // Fallback from core contact table if snapshot is incomplete.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($loanRow->main_contact_id) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')->select($selectCols)->where('id', $loanRow->main_contact_id)->first();
                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($customerPhoneDisplay) && !empty($contact->mobile)) {
                        $customerPhoneDisplay = trim((string) $contact->mobile);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        // Fallback: if contact id is missing, try resolving customer by phone from core contacts.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($customerPhoneDisplay) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')
                    ->select($selectCols)
                    ->where(function ($q) use ($customerPhoneDisplay, $contactCols) {
                        $q->where('mobile', $customerPhoneDisplay);
                        if (in_array('alternate_number', $contactCols, true)) {
                            $q->orWhere('alternate_number', $customerPhoneDisplay);
                        }
                        if (in_array('landline', $contactCols, true)) {
                            $q->orWhere('landline', $customerPhoneDisplay);
                        }
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        $locationRow = null;
        if ($this->loanTableExists('loan_business_locations') && isset($loanRow->business_location_id) && $loanRow->business_location_id) {
            $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loanRow->business_location_id)->first();
        }
        $locationDisplayName = $loanRow->location_name_snapshot ?? ($locationRow->name ?? null);
        if (empty($locationDisplayName)) {
            $mainLocationId = $loanRow->main_location_id ?? $loanRow->business_location_id ?? null;
            if (!empty($mainLocationId) && Schema::hasTable('business_locations')) {
                $blCols = Schema::getColumnListing('business_locations');
                if (in_array('name', $blCols, true)) {
                    $mainLocation = DB::table('business_locations')
                        ->select(array_intersect(['id', 'name'], $blCols))
                        ->where('id', $mainLocationId)
                        ->first();
                    if ($mainLocation) {
                        $locationDisplayName = trim((string) ($mainLocation->name ?? ''));
                    }
                }
            }
        }
        if (empty($locationDisplayName)) {
            $locationDisplayName = !empty($loanRow->main_location_id)
                ? ('Location #'.$loanRow->main_location_id)
                : (!empty($loanRow->business_location_id) ? ('Location #'.$loanRow->business_location_id) : '-');
        }
        $locationAddressDisplay = null;
        if ($locationRow) {
            $locationAddressDisplay = $locationRow->address
                ?? $locationRow->location_address_snapshot
                ?? $locationRow->landmark
                ?? null;
        }

        // Fallback from source sell when snapshot fields are missing.
        if (!empty($loanRow->source_transaction_id) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $loanRow->source_transaction_id)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, t.location_id, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();

            if ($source) {
                if (empty($sourceTypeDisplay)) {
                    $sourceTypeDisplay = $source->type;
                }
                if (empty($sourceTransactionIdDisplay)) {
                    $sourceTransactionIdDisplay = $source->id;
                }
                if (empty($sourceInvoiceDisplay)) {
                    $sourceInvoiceDisplay = $source->invoice_no;
                }
                if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($source->customer_name)) {
                    $customerDisplayName = trim((string) $source->customer_name);
                }
                if (empty($customerPhoneDisplay) && !empty($source->customer_phone)) {
                    $customerPhoneDisplay = trim((string) $source->customer_phone);
                }
                if (empty($customerAddressDisplay) && !empty($source->customer_address)) {
                    $customerAddressDisplay = trim((string) $source->customer_address);
                }
                if ((empty($locationDisplayName) || $locationDisplayName === '-') && !empty($source->location_name)) {
                    $locationDisplayName = trim((string) $source->location_name);
                }
                if (empty($locationAddressDisplay) && !empty($source->location_landmark)) {
                    $locationAddressDisplay = trim((string) $source->location_landmark);
                }
                if ($sourceFinalTotalDisplay === null && isset($source->final_total)) {
                    $sourceFinalTotalDisplay = $source->final_total;
                }
                if ($sourcePaidDisplay === null || $sourceDueDisplay === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaidDisplay === null) {
                        $sourcePaidDisplay = $paid;
                    }
                    if ($sourceDueDisplay === null) {
                        $sourceDueDisplay = $due;
                    }
                }
            }
        }

        $createdByName = $loanRow->created_by_name_snapshot ?? null;
        $collectorDisplayName = $loanRow->collector_name_snapshot ?? null;
        $collectorUserId = $loanRow->collector_id ?? ($loanRow->assigned_to ?? null);

        $userIdsToResolve = array_filter([
            (empty($createdByName) && !empty($loanRow->created_by)) ? (int) $loanRow->created_by : null,
            (empty($collectorDisplayName) && !empty($collectorUserId)) ? (int) $collectorUserId : null,
        ]);

        $resolvedUserNames = [];
        if (!empty($userIdsToResolve) && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $selectCols = array_values(array_intersect(['id', 'first_name', 'last_name', 'username', 'name'], $userCols));
            if (!empty($selectCols)) {
                $users = DB::table('users')->select($selectCols)->whereIn('id', $userIdsToResolve)->get();
                foreach ($users as $u) {
                    $pieces = [];
                    if (isset($u->first_name) || isset($u->last_name)) {
                        $pieces = [trim((string) ($u->first_name ?? '')), trim((string) ($u->last_name ?? ''))];
                    } elseif (isset($u->username)) {
                        $pieces = [trim((string) $u->username)];
                    } elseif (isset($u->name)) {
                        $pieces = [trim((string) $u->name)];
                    }
                    $resolvedUserNames[(int) $u->id] = trim(implode(' ', array_filter($pieces)));
                }
            }
        }

        if (empty($createdByName) && !empty($loanRow->created_by)) {
            $createdByName = $resolvedUserNames[(int) $loanRow->created_by] ?? null;
        }
        if (empty($createdByName)) {
            $createdByName = !empty($loanRow->created_by) ? ('User #'.$loanRow->created_by) : '-';
        }
        $createdByName = Str::of((string) $createdByName)->squish()->value();

        if (empty($collectorDisplayName) && !empty($collectorUserId)) {
            $collectorDisplayName = $resolvedUserNames[(int) $collectorUserId] ?? null;
        }
        if (empty($collectorDisplayName)) {
            $collectorDisplayName = !empty($collectorUserId) ? ('User #'.$collectorUserId) : '-';
        }
        $collectorDisplayName = Str::of((string) $collectorDisplayName)->squish()->value();

        $scheduleSummary = $this->scheduleSummaryForLoan($loan);
        $scheduleCount = $scheduleSummary['count'];
        $loanItemsCount = $this->countLoanRelatedRows('loan_items', $loan);
        $productItemsCount = $this->countLoanRelatedRows('loan_product_items', $loan);
        $paymentsCount = $this->countLoanPayments($loan);
        $statusLogsCount = $this->countLoanRelatedRows('loan_status_logs', $loan);

        return view('loanmanagement::loans.show', compact(
            'loanRow',
            'customerRow',
            'customerDisplayName',
            'customerPhoneDisplay',
            'customerAddressDisplay',
            'mainContactIdDisplay',
            'locationRow',
            'locationDisplayName',
            'locationAddressDisplay',
            'sourceTypeDisplay',
            'sourceTransactionIdDisplay',
            'sourceInvoiceDisplay',
            'sourceFinalTotalDisplay',
            'sourcePaidDisplay',
            'sourceDueDisplay',
            'createdByName',
            'collectorDisplayName',
            'scheduleSummary',
            'scheduleCount',
            'loanItemsCount',
            'productItemsCount',
            'paymentsCount',
            'statusLogsCount'
        ));
    }

    public function showSections(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        return view(
            'loanmanagement::loans.partials.show_sections',
            array_merge(['loanRow' => $loanRow], $this->loadLoanShowSectionData($loan, $loanRow, $loanRow->sell_due_amount_snapshot ?? null))
        );
    }

    protected function normalizeSchedulePrincipalFromDue($schedules, object $loanRow, $sourceDue = null)
    {
        $schedules = collect($schedules);
        $months = $schedules->count();
        if ($months <= 0) {
            return $schedules;
        }

        $duePrincipal = (float) ($loanRow->principal_amount ?? 0);
        if ($duePrincipal <= 0) {
            $duePrincipal = (float) ($sourceDue ?? 0);
        }
        if ($duePrincipal <= 0) {
            $duePrincipal = (float) ($loanRow->sell_due_amount_snapshot ?? 0);
        }
        if ($duePrincipal <= 0) {
            return $schedules;
        }

        $principalTotal = (float) $schedules->sum(function ($schedule) {
            return (float) ($schedule->principal_amount ?? $schedule->principal_due ?? $schedule->installment_value ?? 0);
        });

        if (round($principalTotal, 2) === round($duePrincipal, 2)) {
            return $schedules;
        }

        $principalPerMonth = round($duePrincipal / $months, 2);
        $assignedPrincipal = 0.0;

        return $schedules->values()->map(function ($schedule, $index) use ($months, $principalPerMonth, &$assignedPrincipal, $duePrincipal) {
            $principal = $index === ($months - 1)
                ? round($duePrincipal - $assignedPrincipal, 2)
                : $principalPerMonth;
            $assignedPrincipal = round($assignedPrincipal + $principal, 2);

            $interest = (float) ($schedule->interest_amount ?? $schedule->benefit_value ?? 0);
            $interestDue = (float) ($schedule->interest_due ?? $schedule->benefit_value ?? 0);
            if ($interest <= 0 && $interestDue > 0) {
                $interest = $interestDue;
            }
            $calculatedDue = round($principal + $interest, 2);
            $amountDue = (float) ($schedule->schedule_amount ?? 0);
            $amountDueAlt = (float) ($schedule->amount_due ?? 0);
            if ($calculatedDue > 0 && round($amountDue, 2) !== $calculatedDue) {
                $amountDue = $calculatedDue;
            } elseif (($amountDue <= 0 || round($amountDue, 2) === round($principal, 2)) && $amountDueAlt > $amountDue) {
                $amountDue = $amountDueAlt;
            }
            if ($amountDue <= 0) {
                $amountDue = $calculatedDue;
            }
            $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? $schedule->paid_value ?? 0);
            if ($paid <= 0 && in_array(strtolower((string) ($schedule->status ?? '')), ['paid', 'completed'], true)) {
                $paid = $amountDue;
            }

            $schedule->principal_amount = $principal;
            $schedule->principal_due = $principal;
            $schedule->installment_value = $principal;
            $schedule->interest_amount = $interest;
            $schedule->interest_due = $interest;
            $schedule->benefit_value = $interest;
            $schedule->schedule_amount = $amountDue;
            $schedule->amount_due = $amountDue;
            $schedule->paid_amount = $paid;
            $schedule->amount_paid = $paid;
            $schedule->paid_value = $paid;
            $schedule->balance_amount = max(0, round($amountDue - $paid, 2));
            $schedule->amount_balance = $schedule->balance_amount;

            return $schedule;
        });
    }

    public function edit(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $loanMeta = [];
        if (! empty($loanRow->meta_json)) {
            $loanMeta = json_decode((string) $loanRow->meta_json, true) ?: [];
        }
        $displayInterestRate = (float) ($loanRow->interest_rate ?? ($loanMeta['interest_rate'] ?? ($loanMeta['raw_import_row']['interest_rate'] ?? 0)));
        $displayInterestType = (string) ($loanRow->interest_type ?? ($loanMeta['interest_type'] ?? 'flat'));

        $customerName = trim((string) ($loanRow->customer_name_snapshot ?? ''));
        $customerPhone = trim((string) ($loanRow->customer_phone_snapshot ?? ''));
        $customerAddress = trim((string) ($loanRow->customer_address_snapshot ?? ''));
        $mainContactId = $loanRow->main_contact_id ?? null;

        if ((empty($customerName) || $customerName === '-') && !empty($mainContactId) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1')
                ->where('id', $mainContactId)
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($contact->mobile ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
            }
        }

        if ((empty($customerName) || $customerName === '-') && !empty($customerPhone) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline')
                ->where(function ($q) use ($customerPhone) {
                    $q->where('mobile', $customerPhone)
                        ->orWhere('alternate_number', $customerPhone)
                        ->orWhere('landline', $customerPhone);
                })
                ->orderByDesc('id')
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
                if (empty($mainContactId)) $mainContactId = (int) $contact->id;
            }
        }

        $locationName = trim((string) ($loanRow->business_location_name_snapshot ?? ($loanRow->location_name_snapshot ?? '')));
        $locationAddress = '';
        $locationId = $loanRow->main_location_id ?? null;
        $selectedBusinessLocationId = $loanRow->business_location_id ?? null;
        $selectedLoanLocation = null;

        if ($this->loanTableExists('loan_business_locations')) {
            $loanLocationSelect = ['id', 'name', 'main_location_id'];
            $hasLoanLocationAddress = $this->loanTableHasCol('loan_business_locations', 'address');
            if ($hasLoanLocationAddress) {
                $loanLocationSelect[] = 'address';
            } else {
                $loanLocationSelect[] = DB::raw('NULL as address');
            }

            if (! empty($selectedBusinessLocationId)) {
                $selectedLoanLocation = DB::connection('mysql_loan')
                    ->table('loan_business_locations')
                    ->select($loanLocationSelect)
                    ->where('id', $selectedBusinessLocationId)
                    ->first();
            }

            if (! $selectedLoanLocation && ! empty($locationId)) {
                $selectedLoanLocation = DB::connection('mysql_loan')
                    ->table('loan_business_locations')
                    ->select($loanLocationSelect)
                    ->where('main_location_id', $locationId)
                    ->orderBy('id')
                    ->first();
            }

            if ($selectedLoanLocation) {
                $selectedBusinessLocationId = $selectedLoanLocation->id;
                if (empty($locationName)) {
                    $locationName = trim((string) ($selectedLoanLocation->name ?? ''));
                }
                if (empty($locationAddress)) {
                    $locationAddress = trim((string) ($selectedLoanLocation->address ?? ''));
                }
                if (empty($locationId) && ! empty($selectedLoanLocation->main_location_id)) {
                    $locationId = $selectedLoanLocation->main_location_id;
                }
            }
        }

        $locationOptions = collect();
        $permittedLocationIds = $this->permittedMainLocationIds();
        if ($this->loanTableExists('loan_business_locations')) {
            $query = DB::connection('mysql_loan')
                ->table('loan_business_locations')
                ->select($loanLocationSelect)
                ->orderBy('name');

            if ($this->loanTableHasCol('loan_business_locations', 'deleted_at')) {
                $query->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->orWhere('deleted_at', 0);
                });
            }

            if ($permittedLocationIds !== null) {
                $query->where(function ($query) use ($permittedLocationIds) {
                    $query->whereIn('main_location_id', $permittedLocationIds)
                        ->orWhereIn('id', $permittedLocationIds);
                });
            }

            $locationOptions = $query->get();
            if ($selectedLoanLocation && ! $locationOptions->contains('id', $selectedLoanLocation->id)) {
                $locationOptions->prepend($selectedLoanLocation);
            }
        } elseif ($this->loanTableExists('loans')) {
            $loanColumns = $this->loanTableColumns('loans');
            if (in_array('business_location_name_snapshot', $loanColumns, true) && in_array('business_location_id', $loanColumns, true)) {
                $query = DB::connection('mysql_loan')
                    ->table('loans')
                    ->selectRaw('business_location_id as id, business_location_name_snapshot as name, main_location_id, NULL as address')
                    ->whereNotNull('business_location_id')
                    ->whereNotNull('business_location_name_snapshot')
                    ->where('business_location_name_snapshot', '!=', '');

                if ($permittedLocationIds !== null && in_array('main_location_id', $loanColumns, true)) {
                    $query->where(function ($query) use ($permittedLocationIds) {
                        $query->whereIn('main_location_id', $permittedLocationIds)
                            ->orWhereIn('business_location_id', $permittedLocationIds);
                    });
                }

                $locationOptions = $query
                    ->groupBy('business_location_id', 'business_location_name_snapshot', 'main_location_id')
                    ->orderBy('business_location_name_snapshot')
                    ->get();
            }
        }

        if ($locationOptions->isEmpty() && $selectedLoanLocation) {
            $locationOptions = collect([$selectedLoanLocation]);
        }

        if (!empty($locationId) && Schema::hasTable('business_locations')) {
            $loc = DB::table('business_locations')
                ->select('id', 'name', 'landmark')
                ->where('id', $locationId)
                ->first();
            if ($loc) {
                if (empty($locationName)) $locationName = trim((string) ($loc->name ?? ''));
                $locationAddress = trim((string) ($loc->landmark ?? ''));
            }
        }

        if (empty($locationName) && ! empty($selectedBusinessLocationId) && $locationOptions->isNotEmpty()) {
            $selectedLocation = $locationOptions->firstWhere('id', $selectedBusinessLocationId);
            if ($selectedLocation) {
                $locationName = trim((string) ($selectedLocation->name ?? ''));
                if (empty($locationAddress)) {
                    $locationAddress = trim((string) ($selectedLocation->address ?? ''));
                }
                if (empty($locationId) && ! empty($selectedLocation->main_location_id)) {
                    $locationId = $selectedLocation->main_location_id;
                }
            }
        }

        $sourceType = $loanRow->source_type ?? null;
        $sourceTransactionId = $loanRow->source_transaction_id ?? null;
        $sourceInvoice = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotal = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaid = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDue = $loanRow->sell_due_amount_snapshot ?? null;

        if (!empty($sourceTransactionId) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $sourceTransactionId)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();
            if ($source) {
                if (empty($sourceType)) $sourceType = $source->type;
                if (empty($sourceInvoice)) $sourceInvoice = $source->invoice_no;
                if (empty($customerName)) $customerName = trim((string) ($source->customer_name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($source->customer_phone ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($source->customer_address ?? ''));
                if (empty($locationName)) $locationName = trim((string) ($source->location_name ?? ''));
                if (empty($locationAddress)) $locationAddress = trim((string) ($source->location_landmark ?? ''));
                if ($sourceFinalTotal === null) $sourceFinalTotal = $source->final_total;
                if ($sourcePaid === null || $sourceDue === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaid === null) $sourcePaid = $paid;
                    if ($sourceDue === null) $sourceDue = $due;
                }
            }
        }

        $customerName = $customerName !== '' ? $customerName : '-';
        $customerPhone = $customerPhone !== '' ? $customerPhone : '-';
        $customerAddress = $customerAddress !== '' ? $customerAddress : '-';
        $locationName = $locationName !== '' ? $locationName : '-';
        $locationAddress = $locationAddress !== '' ? $locationAddress : '-';

        $loanItemsCount = $this->countLoanRelatedRows('loan_items', $loan);
        $schedulesCount = $this->countLoanRelatedRows('loan_payment_schedules', $loan);
        $paymentsCount = $this->countLoanPayments($loan);
        $loanItemsUnitPriceTotal = $this->loanItemsUnitPriceTotal($loan);
        $depositAmounts = $this->loanDepositPaymentCopyAmounts($loan, $loanRow);
        $customerDepositPaymentsAmount = round($depositAmounts['cash'] + $depositAmounts['bank'], 2);

        $depositPayments = collect();
        if ($this->loanTableExists('loan_payments') && $this->loanTableHasCol('loan_payments', 'payment_type')) {
            $depQuery = DB::connection('mysql_loan')->table('loan_payments')
                ->where('loan_id', $loan)
                ->whereIn('payment_type', ['loan', 'initial', 'down_payment', 'downpayment', 'deposit'])
                ->orderByDesc('id');
            $this->excludeDeletedLoanRows($depQuery, 'loan_payments');
            $depositPayments = $depQuery->get();
        }

        $loanItems = collect();
        if ($this->loanTableExists('loan_items')) {
            $loanItemsQuery = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan);
            $this->excludeDeletedLoanRows($loanItemsQuery, 'loan_items');
            $loanItems = $loanItemsQuery->orderBy('id')->get();
        }

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        $collectors = Schema::hasTable('users')
            ? DB::table('users')
                ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
                ->orderBy('first_name')
                ->get()
            : collect();

        $defaultCollectorId = auth()->id();

        $locations = $locationOptions->pluck('name', 'id');
        $loanCustomerRow = null;
        $loanCustomerId = (int) ($loanRow->customer_id ?? 0);
        if ($loanCustomerId > 0 && $this->loanTableExists('loan_customers')) {
            $loanCustomerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanCustomerId)->first();
            if ($loanCustomerRow) {
                $loanRow->customer_khmer_name = $loanCustomerRow->khmer_name ?? null;
                $loanRow->customer_english_name = $loanCustomerRow->name ?? null;
            }
        }

        $customerProfilePhotoUrl = $this->loanFileUrlById((int) ($loanRow->customer_photo_file_id ?? 0))
            ?: $this->loanFileUrlById((int) ($loanCustomerRow->customer_photo_file_id ?? 0))
            ?: $this->latestCustomerFileUrlByCategory($loanCustomerId, 'customer_photo');
        $idCardPhotoUrl = $this->loanFileUrlById((int) ($loanRow->id_front_file_id ?? 0))
            ?: $this->loanFileUrlById((int) ($loanCustomerRow->id_front_file_id ?? 0))
            ?: $this->latestCustomerFileUrlByCategory($loanCustomerId, 'id_front');
        $loanDocumentFiles = $this->loanFilesByCategory($loan, 'document');
        $editSectionData = $this->loadLoanEditSectionData($loan);

        return view('loanmanagement::loans.edit', compact(
            'loanRow',
            'displayInterestRate',
            'displayInterestType',
            'customerName',
            'customerPhone',
            'customerAddress',
            'mainContactId',
            'locationName',
            'locationAddress',
            'locationOptions',
            'selectedBusinessLocationId',
            'locationId',
            'sourceType',
            'sourceTransactionId',
            'sourceInvoice',
            'sourceFinalTotal',
            'sourcePaid',
            'sourceDue',
            'loanItemsCount',
            'loanItemsUnitPriceTotal',
            'customerDepositPaymentsAmount',
            'schedulesCount',
            'paymentsCount',
            'loanItems',
            'paymentTypes',
            'defaultPaymentMethod',
            'collectors',
            'defaultCollectorId',
            'locations',
            'depositPayments',
            'customerProfilePhotoUrl',
            'idCardPhotoUrl',
            'loanDocumentFiles'
        ) + $editSectionData);
    }

    protected function loanItemsUnitPriceTotal(int $loan): float
    {
        if (! $this->loanTableExists('loan_items')) {
            return 0.0;
        }

        return (float) DB::connection('mysql_loan')
            ->table('loan_items')
            ->where('loan_id', $loan)
            ->when($this->loanTableHasCol('loan_items', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->get()
            ->sum(function ($item) {
                $lineTotal = (float) ($item->line_total ?? $item->total_price ?? 0);
                if ($lineTotal > 0) {
                    return $lineTotal;
                }

                return (float) ($item->unit_price ?? 0) * max(1, (float) ($item->qty ?? $item->quantity ?? 1));
            });
    }

    protected function permittedMainLocationIds(): ?array
    {
        try {
            $businessId = session('user.business_id');
            $permitted = auth()->user()?->permitted_locations($businessId);

            if ($permitted === 'all') {
                return null;
            }

            return array_values(array_filter(array_map('intval', (array) $permitted)));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function editSections(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        return view(
            'loanmanagement::loans.partials.edit_sections',
            array_merge([
                'loanRow' => $loanRow,
                'backCustomerId' => request('customer_id') ?: ($loanRow->customer_id ?? null),
            ], $this->loadLoanEditSectionData($loan))
        );
    }

    public function update(Request $request, int $loan)
    {
        try {
            $data = $request->validate([
                'customer_id' => 'nullable|integer|min:0',
                'customer_name_snapshot' => 'nullable|string|max:191',
                'customer_phone_snapshot' => 'nullable|string|max:191',
                'customer_address_snapshot' => 'nullable|string|max:1000',
                'customer_khmer_name' => 'nullable|string|max:191',
                'customer_english_name' => 'nullable|string|max:191',
                'alternate_phone' => 'nullable|string|max:191',
                'customer_group_name' => 'nullable|string|max:255',
                'customer_profile_image' => 'nullable|string',
                'id_card_image' => 'nullable|string',
                'id_card_ocr_raw_text' => 'nullable|string',
                'id_card_ocr_fields' => 'nullable|array',
                'id_card_ocr_fields.id_card_number' => 'nullable|string|max:100',
                'id_card_ocr_fields.khmer_name' => 'nullable|string|max:191',
                'id_card_ocr_fields.english_name' => 'nullable|string|max:191',
                'id_card_ocr_fields.address' => 'nullable|string|max:1000',
                'documents' => 'nullable|array',
                'documents.*' => 'nullable|string',
                'document_text' => 'nullable|string|max:5000',
                'document_links' => 'nullable|array',
                'document_links.*' => 'nullable|url|max:1000',
                'province_code' => 'nullable|string|max:20',
                'province_name' => 'nullable|string|max:191',
                'district_code' => 'nullable|string|max:20',
                'district_name' => 'nullable|string|max:191',
                'commune_code' => 'nullable|string|max:20',
                'commune_name' => 'nullable|string|max:191',
                'village_code' => 'nullable|string|max:20',
                'village_name' => 'nullable|string|max:191',
                'id_card_number' => 'nullable|string|max:100',
                'occupation' => 'nullable|string|max:191',
                'guarantor_name' => 'nullable|string|max:191',
                'guarantor_phone' => 'nullable|string|max:191',
                'main_contact_id' => 'nullable|integer|min:0',
                'business_location_name_snapshot' => 'nullable|string|max:191',
                'main_location_id' => 'nullable|integer|min:0',
                'business_location_id' => 'nullable|integer|min:0',
                'assigned_collector_id' => 'nullable|integer|min:0',
                'source_type' => 'nullable|string|max:30',
                'source_invoice_no' => 'nullable|string|max:191',
                'source_created_at' => 'nullable|date',
                'stock_already_deducted' => 'nullable|boolean',
                'principal_amount' => 'nullable|numeric|min:0',
                'interest_amount' => 'nullable|numeric|min:0',
                'total_amount' => 'nullable|numeric|min:0',
                'paid_amount' => 'nullable|numeric|min:0',
                'financed_amount' => 'nullable|numeric|min:0',
                'penalty_amount' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'balance_amount' => 'nullable|numeric|min:0',
                'down_payment' => 'nullable|numeric|min:0',
                'installment_count' => 'nullable|integer|min:0|max:1000',
                'duration_months' => 'nullable|integer|min:0|max:1000',
                'interest_rate' => 'nullable|numeric|min:0',
                'interest_type' => 'nullable|in:flat,reducing_balance',
                'payment_frequency' => 'nullable|string|max:30',
                'currency' => 'nullable|string|max:10',
                'loan_date' => 'nullable|date',
                'first_due_date' => 'nullable|date',
                'maturity_date' => 'nullable|date',
                'status' => 'nullable|string|max:30',
                'approved_at' => 'nullable|date',
                'note' => 'nullable|string|max:5000',
                'collection_status' => 'nullable|string|max:50',
                'risk_level' => 'nullable|string|max:50',
                'collection_priority' => 'nullable|integer|min:0|max:255',
                'ptp_date' => 'nullable|date',
                'ptp_amount' => 'nullable|numeric|min:0',
                'ptp_note' => 'nullable|string|max:5000',
                'ptp_status' => 'nullable|string|max:30',
                'broken_ptp_count' => 'nullable|integer|min:0',
                'last_contact_at' => 'nullable|date',
                'last_contact_result' => 'nullable|string|max:100',
                'next_followup_at' => 'nullable|date',
                'field_visit_required' => 'nullable|boolean',
                'skip_level' => 'nullable|string|max:30',
                'legal_stage' => 'nullable|string|max:100',
                'recovery_stage' => 'nullable|string|max:100',
                'repossession_status' => 'nullable|string|max:100',
                'blacklisted_at' => 'nullable|date',
                'written_off_at' => 'nullable|date',
                'assigned_collection_team' => 'nullable|string|max:100',
                'days_past_due' => 'nullable|integer|min:0',
                'overdue_bucket' => 'nullable|string|max:30',
                'contact_attempt_count' => 'nullable|integer|min:0',
                'last_payment_date' => 'nullable|date',
                'last_payment_amount' => 'nullable|numeric|min:0',
                'recovery_score' => 'nullable|integer|min:0|max:65535',
                'delete_items' => 'nullable|array',
                'delete_items.*' => 'integer|min:1',
            ]);

            if (! empty($data['business_location_id']) && $this->loanTableExists('loan_business_locations')) {
                $selectedLocation = DB::connection('mysql_loan')
                    ->table('loan_business_locations')
                    ->select('id', 'name', 'main_location_id')
                    ->where('id', $data['business_location_id'])
                    ->first();

                if ($selectedLocation) {
                    $data['business_location_name_snapshot'] = $selectedLocation->name;
                    if (empty($data['main_location_id']) && ! empty($selectedLocation->main_location_id)) {
                        $data['main_location_id'] = $selectedLocation->main_location_id;
                    }
                }
            } elseif (! empty($data['business_location_id']) && $this->loanTableExists('loans')) {
                $loanColumns = $this->loanTableColumns('loans');
                if (in_array('business_location_name_snapshot', $loanColumns, true)) {
                    $selectedLocation = DB::connection('mysql_loan')
                        ->table('loans')
                        ->select('business_location_id', 'business_location_name_snapshot', 'main_location_id')
                        ->where('business_location_id', $data['business_location_id'])
                        ->whereNotNull('business_location_name_snapshot')
                        ->where('business_location_name_snapshot', '!=', '')
                        ->orderByDesc('id')
                        ->first();

                    if ($selectedLocation) {
                        $data['business_location_name_snapshot'] = $selectedLocation->business_location_name_snapshot;
                        if (empty($data['main_location_id']) && ! empty($selectedLocation->main_location_id)) {
                            $data['main_location_id'] = $selectedLocation->main_location_id;
                        }
                    }
                }
            }

            abort_if(! $this->loanTableExists('loans'), 404);
            $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
            abort_if(! $loanRow, 404);
            $loanCustomerId = (int) ($loanRow->customer_id ?? $data['customer_id'] ?? 0);

            if (! empty($data['id_card_ocr_fields']['id_card_number']) && empty($data['id_card_number'])) {
                $data['id_card_number'] = $data['id_card_ocr_fields']['id_card_number'];
            }
            if (! empty($data['id_card_ocr_fields']['khmer_name']) && empty($data['customer_khmer_name'])) {
                $data['customer_khmer_name'] = $data['id_card_ocr_fields']['khmer_name'];
            }
            if (! empty($data['id_card_ocr_fields']['english_name']) && empty($data['customer_english_name'])) {
                $data['customer_english_name'] = $data['id_card_ocr_fields']['english_name'];
            }
            if (! empty($data['id_card_ocr_fields']['address']) && empty($data['customer_address_snapshot'])) {
                $data['customer_address_snapshot'] = $data['id_card_ocr_fields']['address'];
            }

            $existingCustomerName = null;
            $existingCustomerKhmerName = null;
            if ($loanCustomerId > 0 && $this->loanTableExists('loan_customers')) {
                $existingCustomer = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanCustomerId)->first();
                if ($existingCustomer) {
                    $existingCustomerName = $existingCustomer->name ?? null;
                    $existingCustomerKhmerName = $existingCustomer->khmer_name ?? null;
                }
            }

            $customerKhmerName = trim((string) ($data['customer_khmer_name'] ?? ''));
            if ($customerKhmerName === '') {
                $customerKhmerName = collect([
                    $data['id_card_ocr_fields']['khmer_name'] ?? null,
                    $existingCustomerKhmerName,
                    $data['customer_name_snapshot'] ?? null,
                    $loanRow->customer_name_snapshot ?? null,
                    $existingCustomerName,
                    $data['customer_english_name'] ?? null,
                ])->map(fn ($value) => trim((string) $value))->first(fn ($value) => $value !== '') ?? '';
            }

            $customerEnglishName = trim((string) ($data['customer_english_name'] ?? ''));
            if ($customerEnglishName === '') {
                $customerEnglishName = collect([
                    $data['id_card_ocr_fields']['english_name'] ?? null,
                    $existingCustomerName,
                    $customerKhmerName,
                    $data['customer_name_snapshot'] ?? null,
                    $loanRow->customer_name_snapshot ?? null,
                ])->map(fn ($value) => trim((string) $value))->first(fn ($value) => $value !== '') ?? '';
            }

            $data['customer_khmer_name'] = $customerKhmerName;
            $data['customer_english_name'] = $customerEnglishName;
            if (empty($data['customer_name_snapshot'])) {
                $data['customer_name_snapshot'] = $customerKhmerName !== '' ? $customerKhmerName : $customerEnglishName;
            }

            if ($loanCustomerId > 0 && $this->loanTableExists('loan_customers')) {
                DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanCustomerId)->update($this->loanSafeColumns('loan_customers', [
                    'khmer_name' => $customerKhmerName !== '' ? $customerKhmerName : null,
                    'name' => $customerEnglishName !== '' ? $customerEnglishName : ($customerKhmerName !== '' ? $customerKhmerName : null),
                    'phone' => $data['customer_phone_snapshot'] ?? null,
                    'mobile' => $data['customer_phone_snapshot'] ?? null,
                    'address' => $data['customer_address_snapshot'] ?? null,
                    'updated_at' => now(),
                ]));
            }

            foreach ([
                'province_name' => 'customer_province_snapshot',
                'province_code' => 'customer_province_code_snapshot',
                'district_name' => 'customer_district_snapshot',
                'district_code' => 'customer_district_code_snapshot',
                'commune_name' => 'customer_commune_snapshot',
                'commune_code' => 'customer_commune_code_snapshot',
                'village_name' => 'customer_village_snapshot',
                'village_code' => 'customer_village_code_snapshot',
                'customer_group_name' => 'customer_group_name_snapshot',
            ] as $inputKey => $column) {
                if (array_key_exists($inputKey, $data)) {
                    $data[$column] = $data[$inputKey];
                }
            }

            $profileFileId = $this->storeLoanCustomerImageFromDataUri((string) ($data['customer_profile_image'] ?? ''), $loanCustomerId, 'customer_photo', 'customer-profile');
            if ($profileFileId !== null) {
                $data['customer_photo_file_id'] = $profileFileId;
                $this->updateLoanCustomerFileReference($loanCustomerId, 'customer_photo_file_id', $profileFileId);
            }
            $idCardFileId = $this->storeLoanCustomerImageFromDataUri((string) ($data['id_card_image'] ?? ''), $loanCustomerId, 'id_front', 'id-card-front');
            if ($idCardFileId !== null) {
                $data['id_front_file_id'] = $idCardFileId;
                $this->updateLoanCustomerFileReference($loanCustomerId, 'id_front_file_id', $idCardFileId);
            }
            foreach ((array) ($data['documents'] ?? []) as $index => $document) {
                $this->storeLoanDocumentFromDataUri((string) $document, $loan, 'loan-document-'.$loan.'-'.($index + 1));
            }
            $documentText = trim((string) ($data['document_text'] ?? ''));
            if ($documentText !== '') {
                $this->storeLoanTextDocument($documentText, $loan, 'loan-document-note-'.$loan.'.txt');
            }
            foreach ((array) ($data['document_links'] ?? []) as $index => $link) {
                $link = trim((string) $link);
                if ($link !== '') {
                    $this->storeLoanTextDocument($link, $loan, 'loan-document-link-'.$loan.'-'.($index + 1).'.txt');
                }
            }

            unset(
                $data['id_card_ocr_fields'],
                $data['customer_profile_image'],
                $data['id_card_image'],
                $data['documents'],
                $data['document_text'],
                $data['document_links'],
                $data['customer_group_name'],
                $data['province_name'],
                $data['district_name'],
                $data['commune_name'],
                $data['village_name']
            );

            if (! empty($data['assigned_collector_id'])) {
                $data['collector_id'] = (int) $data['assigned_collector_id'];
                if (Schema::hasTable('users')) {
                    $collector = DB::table('users')
                        ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
                        ->where('id', (int) $data['assigned_collector_id'])
                        ->first();
                    if ($collector && trim((string) $collector->name) !== '') {
                        $data['collector_name_snapshot'] = trim((string) $collector->name);
                    }
                }
            } elseif (array_key_exists('assigned_collector_id', $data)) {
                $data['collector_id'] = null;
                $data['collector_name_snapshot'] = null;
            }

            foreach ([
                'customer_id' => $loanRow->customer_id ?? 0,
                'source_type' => $loanRow->source_type ?? 'manual',
                'principal_amount' => $loanRow->principal_amount ?? 0,
                'interest_amount' => $loanRow->interest_amount ?? 0,
                'total_amount' => $loanRow->total_amount ?? 0,
                'paid_amount' => $loanRow->paid_amount ?? 0,
                'financed_amount' => $loanRow->financed_amount ?? ($loanRow->principal_amount ?? 0),
                'penalty_amount' => $loanRow->penalty_amount ?? 0,
                'discount_amount' => $loanRow->discount_amount ?? 0,
                'balance_amount' => $loanRow->balance_amount ?? 0,
                'down_payment' => $loanRow->down_payment ?? 0,
                'installment_count' => $loanRow->installment_count ?? 0,
                'duration_months' => $loanRow->duration_months ?? ($loanRow->installment_count ?? 0),
                'payment_frequency' => $loanRow->payment_frequency ?? 'monthly',
                'status' => $loanRow->status ?? 'draft',
                'currency' => $loanRow->currency ?? 'USD',
                'interest_type' => $loanRow->interest_type ?? 'flat',
            ] as $column => $fallback) {
                if (! array_key_exists($column, $data) || $data[$column] === null || $data[$column] === '') {
                    $data[$column] = $fallback;
                }
            }

            $principalAmount = max(0, round((float) ($data['principal_amount'] ?? 0), 2));
            $interestAmount = max(0, round((float) ($data['interest_amount'] ?? 0), 2));
            $penaltyAmount = max(0, round((float) ($data['penalty_amount'] ?? 0), 2));
            $discountAmount = max(0, round((float) ($data['discount_amount'] ?? 0), 2));
            $paidAmount = max(0, round((float) ($data['paid_amount'] ?? 0), 2));
            $totalAmount = max(0, round($principalAmount + $interestAmount + $penaltyAmount - $discountAmount, 2));

            $data['principal_amount'] = $principalAmount;
            $data['financed_amount'] = $principalAmount;
            $data['interest_amount'] = $interestAmount;
            $data['penalty_amount'] = $penaltyAmount;
            $data['discount_amount'] = $discountAmount;
            $data['paid_amount'] = $paidAmount;
            $data['total_amount'] = $totalAmount;
            $data['balance_amount'] = max(0, round($totalAmount - $paidAmount, 2));

            $loanMeta = ! empty($loanRow->meta_json) ? (json_decode((string) $loanRow->meta_json, true) ?: []) : [];
            if (array_key_exists('interest_rate', $data)) {
                $loanMeta['interest_rate'] = (float) $data['interest_rate'];
            }
            if (array_key_exists('interest_type', $data)) {
                $loanMeta['interest_type'] = in_array(($data['interest_type'] ?? 'flat'), ['flat', 'reducing_balance'], true)
                    ? $data['interest_type']
                    : 'flat';
            }
            $data['meta_json'] = json_encode($loanMeta, JSON_UNESCAPED_UNICODE);

            DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', array_merge($data, [
                'stock_already_deducted' => $request->has('stock_already_deducted')
                    ? (int) $request->boolean('stock_already_deducted')
                    : (int) ($loanRow->stock_already_deducted ?? 0),
                'field_visit_required' => $request->has('field_visit_required')
                    ? (int) $request->boolean('field_visit_required')
                    : (int) ($loanRow->field_visit_required ?? 0),
                'updated_at' => now(),
            ])));

            $this->syncLoanSchedulesFromEdit($loan, $data, $loanRow);

            $deleteItems = array_values(array_filter(array_map('intval', (array) $request->input('delete_items', []))));
            if (! empty($deleteItems) && $this->loanTableExists('loan_items')) {
                $deleteQuery = DB::connection('mysql_loan')
                    ->table('loan_items')
                    ->where('loan_id', $loan)
                    ->whereIn('id', $deleteItems);

                if ($this->loanTableHasCol('loan_items', 'deleted_at')) {
                    $deleteQuery->update($this->loanSafeColumns('loan_items', [
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]));
                } else {
                    $deleteQuery->delete();
                }

                $this->refreshLoanItemSnapshot($loan);
            }

            $editItems = $request->input('edit_items', []);
            if (!empty($editItems) && is_array($editItems) && $this->loanTableExists('loan_items')) {
                foreach ($editItems as $key => $itemData) {
                    if (!is_array($itemData)) continue;
                    $productName = trim((string) ($itemData['product_name_snapshot'] ?? $itemData['product_name'] ?? ''));
                    $sku = trim((string) ($itemData['sku_snapshot'] ?? $itemData['sku'] ?? ''));
                    $imei = trim((string) ($itemData['imei_snapshot'] ?? $itemData['imei'] ?? ''));
                    $serial = trim((string) ($itemData['serial_number_snapshot'] ?? $itemData['serial_number'] ?? ''));
                    $lot = trim((string) ($itemData['lot_number_snapshot'] ?? $itemData['lot_number'] ?? ''));
                    $brand = trim((string) ($itemData['brand'] ?? ''));
                    $category = trim((string) ($itemData['category'] ?? ''));
                    $color = trim((string) ($itemData['color'] ?? ''));
                    $storage = trim((string) ($itemData['storage'] ?? ''));
                    $qty = max(0, (float) ($itemData['qty'] ?? 1));
                    $unitPrice = max(0, (float) ($itemData['unit_price'] ?? 0));
                    $discount = max(0, (float) ($itemData['discount'] ?? 0));
                    $lineTotal = array_key_exists('line_total', $itemData) && $itemData['line_total'] !== null
                        ? round((float) $itemData['line_total'], 2) : max(0, round(($qty * $unitPrice) - $discount, 2));
                    $photoPath = trim((string) ($itemData['product_photo_path'] ?? ''));
                    $storedPhotoPath = $this->storeLoanItemPhotoFromDataUri((string) ($itemData['product_photo'] ?? ''), $loan, is_numeric($key) ? (int) $key : 0);
                    if ($storedPhotoPath !== null) {
                        $photoPath = $storedPhotoPath;
                    }
                    $itemPayload = $this->loanSafeColumns('loan_items', [
                        'product_name_snapshot' => $productName, 'product_name' => $productName,
                        'sku_snapshot' => $sku, 'sku' => $sku,
                        'brand' => $brand, 'category' => $category,
                        'imei_snapshot' => $imei, 'imei' => $imei,
                        'serial_number_snapshot' => $serial, 'serial_number' => $serial,
                        'lot_number_snapshot' => $lot, 'lot_number' => $lot,
                        'color' => $color, 'color_snapshot' => $color,
                        'storage' => $storage, 'storage_snapshot' => $storage,
                        'product_photo_path' => $photoPath,
                        'product_ocr_raw_text' => trim((string) ($itemData['product_ocr_raw_text'] ?? '')),
                        'qty' => $qty, 'unit_price' => $unitPrice, 'discount' => $discount, 'line_total' => $lineTotal,
                        'updated_at' => now(),
                    ]);
                    $isNew = !is_numeric($key) || str_starts_with((string) $key, 'new_');
                    if ($isNew) {
                        $itemPayload['loan_id'] = $loan;
                        $itemPayload['created_at'] = now();
                        DB::connection('mysql_loan')->table('loan_items')->insert($itemPayload);
                    } else {
                        DB::connection('mysql_loan')->table('loan_items')
                            ->where('id', (int) $key)->where('loan_id', $loan)->update($itemPayload);
                    }
                }
                if (!empty($editItems)) $this->refreshLoanItemSnapshot($loan);
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Loan updated successfully.']);
            }

            $redirectParams = ['loan' => $loan] + ($request->boolean('_lm_modal') ? ['_lm_modal' => 1] : []);
            if ($request->filled('customer_id')) {
                $redirectParams['customer_id'] = $request->input('customer_id');
            }

            return redirect()->route('loan-management.loans.edit', $redirectParams)->with('status', [
                'success' => 1,
                'msg' => 'Loan updated successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()
                ->withInput()
                ->withErrors([
                    'save_error' => $e->getMessage().' in '.$e->getFile().':'.$e->getLine(),
                ]);
        }
    }

    public function changeStatus(Request $request, int $loan)
    {
        $payload = $request->validate([
            'status' => 'required|in:draft,pending,approved,active,completed,rejected,cancelled,defaulted',
        ]);

        abort_if(! $this->loanTableExists('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        DB::connection('mysql_loan')->transaction(function () use ($loan, $loanRow, $payload) {
            DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update([
                'status' => $payload['status'],
                'updated_at' => now(),
            ]);

            if ($this->loanTableExists('loan_status_logs')) {
                $cols = $this->loanTableColumns('loan_status_logs');
                $row = [
                    'loan_id' => $loan,
                    'status' => $payload['status'],
                    'from_status' => $loanRow->status ?? null,
                    'to_status' => $payload['status'],
                    'changed_by' => auth()->id(),
                    'note' => 'Status changed from installment list',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                DB::connection('mysql_loan')->table('loan_status_logs')->insert(array_intersect_key($row, array_flip($cols)));
            }
        });

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    public function destroy(int $loan)
    {
        abort_if(! $this->loanTableExists('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->delete();
        return response()->json(['success' => true, 'message' => 'Loan deleted']);
    }
}
