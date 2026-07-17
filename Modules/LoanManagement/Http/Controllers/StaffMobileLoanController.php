<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\LoanManagement\Entities\Loan;
use Modules\LoanManagement\Http\Resources\CustomerLoanSummaryResource;
use Modules\LoanManagement\Services\CreateStandaloneLoanService;
use Modules\LoanManagement\Services\CustomerLoanSummaryService;
use Modules\LoanManagement\Services\KhmerNationalIdCard\CambodiaAddressResolverService;

class StaffMobileLoanController extends Controller
{
    use ApiResponseTrait;

    protected string $conn = 'mysql_loan';

    public function __construct(
        protected CreateStandaloneLoanService $createService,
        protected CustomerLoanSummaryService $summaryService,
        protected CambodiaAddressResolverService $addressResolver
    ) {
    }

    public function options(Request $request)
    {
        $this->useApiGuard($request);

        return $this->ok('Loan form options loaded', [
            'defaults' => [
                'loan_date' => now()->toDateString(),
                'first_due_date' => now()->addMonth()->toDateString(),
                'currency' => 'USD',
                'exchange_rate' => 1,
                'interest_type' => 'flat',
                'payment_frequency' => 'monthly',
                'duration_months' => 12,
                'interest_rate' => 0,
                'assigned_collector_id' => optional($request->user())->id,
            ],
            'locations' => $this->locations(),
            'collectors' => $this->collectors(),
            'currencies' => ['USD', 'KHR'],
            'interest_types' => ['flat', 'reducing_balance'],
            'payment_frequencies' => ['monthly', 'weekly', 'daily'],
            'actions' => ['draft', 'create', 'create_approve'],
            'groups' => ['រំលស់'],
        ]);
    }

    public function addressOptions(Request $request, string $level)
    {
        $this->useApiGuard($request);

        $items = match ($level) {
            'provinces' => $this->addressResolver->provinces(),
            'districts' => $this->addressResolver->districts((string) $request->query('province_code', '')),
            'communes' => $this->addressResolver->communes((string) $request->query('district_code', '')),
            'villages' => $this->addressResolver->villages((string) $request->query('commune_code', '')),
            default => [],
        };

        return $this->ok('Address options loaded', [
            'items' => $items,
            'needs_sync' => $this->addressResolver->needsSync(),
        ]);
    }

    public function addressSync(Request $request)
    {
        $this->useApiGuard($request);

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return $this->ok('Address sync completed', [
                'sync' => $this->addressResolver->syncBatch(
                    (int) $request->query('page', $request->input('page')) ?: null,
                    (int) config('loanmanagement.cambodia_address.pages_per_request', 25)
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->fail('Unable to prepare Cambodia address list.', 422, [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $this->useApiGuard($request);

        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return $this->ok('Loans loaded', []);
        }

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = Loan::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('loan_number', 'like', "%{$search}%")
                        ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('customer_phone_snapshot', 'like', "%{$search}%")
                        ->orWhere('product_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('imei_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($status !== '' && strtolower($status) !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $data = $rows
            ->map(fn (Loan $loan) => $this->mobileLoanPayload($loan))
            ->values()
            ->all();

        return $this->ok('Loans loaded', $data);
    }

    public function show(Request $request, int $loanId)
    {
        $this->useApiGuard($request);

        $loan = Loan::query()->where('id', $loanId)->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        return $this->ok('Loan loaded', $this->mobileLoanPayload($loan, true));
    }

    public function destroy(Request $request, int $loanId)
    {
        $this->useApiGuard($request);

        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        $loan = Loan::query()->where('id', $loanId)->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        $loan->delete();

        return $this->ok('Loan deleted', ['id' => $loanId]);
    }

    public function searchCustomers(Request $request)
    {
        $this->useApiGuard($request);

        $keyword = trim((string) $request->query('q', $request->input('q', '')));
        if (mb_strlen($keyword) < 2) {
            return $this->ok('Customers loaded', []);
        }

        return $this->ok('Customers loaded', $this->createService->searchCustomers($keyword)->values()->all());
    }

    public function previewSchedule(Request $request)
    {
        $this->useApiGuard($request);
        $data = $this->validatedLoanData($request, false);

        return $this->ok('Schedule preview loaded', $this->createService->previewSchedule($data));
    }

    public function store(Request $request)
    {
        $this->useApiGuard($request);
        $data = $this->validatedLoanData($request, true);

        try {
            $loanId = $this->createService->createStandaloneLoan($data);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, (object) []);
        }

        $loan = Loan::query()->where('id', $loanId)->first();

        return $this->ok('Loan created', [
            'id' => $loanId,
            'loan' => $loan ? $this->mobileLoanPayload($loan, true) : null,
        ]);
    }

    public function update(Request $request, int $loanId)
    {
        $this->useApiGuard($request);

        $loan = Loan::query()->where('id', $loanId)->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        $data = $this->validatedLoanData($request, false);
        $items = (array) ($data['items'] ?? []);
        $firstItem = is_array($items[0] ?? null) ? $items[0] : [];
        $qty = max(1, (int) ($firstItem['qty'] ?? 1));
        $unitPrice = max(0, (float) ($firstItem['unit_price'] ?? $data['principal_amount']));
        $productTotal = round($qty * $unitPrice, 2);
        $downPayment = max(0, (float) ($data['down_payment'] ?? 0));
        $principal = max(0, round(($productTotal > 0 ? $productTotal : (float) $data['principal_amount']) - $downPayment, 2));
        $interestRate = max(0, (float) ($data['interest_rate'] ?? 0));
        $interestAmount = round($principal * ($interestRate / 100) * max(1, (int) ($data['duration_months'] ?? 1)), 2);
        $totalAmount = round($principal + $interestAmount, 2);
        $paidAmount = (float) ($loan->paid_amount ?? 0);

        DB::connection($this->conn)->table('loans')->where('id', $loanId)->update(
            $this->onlyExistingColumns('loans', [
                'customer_name_snapshot' => $data['customer_name'] ?? null,
                'customer_phone_snapshot' => $data['customer_phone'] ?? null,
                'business_location_id' => $data['business_location_id'] ?? null,
                'collector_id' => $data['assigned_collector_id'] ?? null,
                'product_name_snapshot' => $firstItem['product_name'] ?? null,
                'imei_snapshot' => $firstItem['imei'] ?? null,
                'principal_amount' => $principal,
                'down_payment' => $downPayment,
                'interest_rate' => $interestRate,
                'interest_amount' => $interestAmount,
                'total_amount' => $totalAmount,
                'total_payable_amount' => $totalAmount,
                'balance_amount' => max(0, round($totalAmount - $paidAmount, 2)),
                'installment_count' => (int) ($data['duration_months'] ?? 1),
                'duration_months' => (int) ($data['duration_months'] ?? 1),
                'payment_frequency' => $data['payment_frequency'] ?? 'monthly',
                'interest_type' => $data['interest_type'] ?? 'flat',
                'loan_date' => $data['loan_date'] ?? null,
                'first_due_date' => $data['first_due_date'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'penalty_type' => $data['penalty_type'] ?? null,
                'penalty_amount' => (float) ($data['penalty_amount'] ?? 0),
                'note' => $data['note'] ?? null,
                'updated_at' => now(),
            ])
        );

        $this->upsertFirstLoanItem($loanId, $firstItem, $unitPrice, $qty);

        $fresh = Loan::query()->where('id', $loanId)->first();

        return $this->ok('Loan updated', [
            'id' => $loanId,
            'loan' => $fresh ? $this->mobileLoanPayload($fresh, true) : null,
        ]);
    }

    protected function validatedLoanData(Request $request, bool $creating): array
    {
        $rules = [
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => [$creating ? 'required' : 'nullable', 'string', 'max:191'],
            'customer_english_name' => ['nullable', 'string', 'max:191'],
            'customer_khmer_name' => ['nullable', 'string', 'max:191'],
            'customer_phone' => ['nullable', 'string', 'max:191'],
            'alternate_phone' => ['nullable', 'string', 'max:191'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'province_name' => ['nullable', 'string', 'max:191'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'district_name' => ['nullable', 'string', 'max:191'],
            'commune_code' => ['nullable', 'string', 'max:20'],
            'commune_name' => ['nullable', 'string', 'max:191'],
            'village_code' => ['nullable', 'string', 'max:20'],
            'village_name' => ['nullable', 'string', 'max:191'],
            'customer_group_name' => ['nullable', 'string', 'max:255'],
            'id_card_number' => ['nullable', 'string', 'max:100'],
            'loan_number' => ['nullable', 'string', 'max:191'],
            'loan_date' => [$creating ? 'required' : 'nullable', 'date'],
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'interest_type' => ['required', Rule::in(['flat', 'reducing_balance'])],
            'duration_months' => ['required', 'integer', 'min:1', 'max:360'],
            'payment_frequency' => ['required', Rule::in(['monthly', 'weekly', 'daily'])],
            'first_due_date' => [$creating ? 'required' : 'nullable', 'date'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'penalty_type' => ['nullable', 'string', 'max:50'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'assigned_collector_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'business_location_id' => ['nullable', 'integer'],
            'action_type' => ['required', Rule::in(['draft', 'create', 'create_approve'])],
            'items' => ['nullable', 'array'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.imei' => ['nullable', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:255'],
            'items.*.storage' => ['nullable', 'string', 'max:255'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.product_photo' => ['nullable', 'string'],
            'items.*.product_ocr_raw_text' => ['nullable', 'string'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.paid_date' => ['nullable', 'date'],
            'payment.method' => ['nullable', 'string', 'max:100'],
            'payment.reference_number' => ['nullable', 'string', 'max:255'],
            'payment.currency' => ['nullable', Rule::in(['USD', 'KHR'])],
            'payment.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'payment.status' => ['nullable', Rule::in(['completed', 'pending', 'failed'])],
            'customer_profile_image' => ['nullable', 'string'],
            'id_card_image' => ['nullable', 'string'],
            'id_card_ocr_raw_text' => ['nullable', 'string'],
            'id_card_ocr_fields' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'string'],
            'document_text' => ['nullable', 'string', 'max:5000'],
            'document_links' => ['nullable', 'array'],
            'document_links.*' => ['nullable', 'url', 'max:1000'],
        ];

        $data = $request->validate($rules);
        $data['loan_date'] = $data['loan_date'] ?? now()->toDateString();
        $data['first_due_date'] = $data['first_due_date'] ?? now()->addMonth()->toDateString();
        $data['interest_rate'] = $data['interest_rate'] ?? 0;
        $data['down_payment'] = $data['down_payment'] ?? 0;
        $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
        $data['items'] = $data['items'] ?? [];

        if (empty($data['items']) && $request->filled('product_name')) {
            $data['items'] = [[
                'product_name' => $request->input('product_name'),
                'imei' => $request->input('imei'),
                'serial_number' => $request->input('serial_number'),
                'qty' => 1,
                'unit_price' => $data['principal_amount'],
            ]];
        }

        if (! empty($data['payment']['amount'])) {
            $data['payment']['paid_date'] = $data['payment']['paid_date'] ?? $data['loan_date'];
            $data['payment']['currency'] = $data['payment']['currency'] ?? $data['currency'];
            $data['payment']['exchange_rate'] = $data['payment']['exchange_rate'] ?? $data['exchange_rate'];
            $data['payment']['status'] = $data['payment']['status'] ?? 'completed';
        }

        return $data;
    }

    protected function mobileLoanPayload(Loan $loan, bool $includeDetail = false): array
    {
        $summary = (new CustomerLoanSummaryResource($this->summaryService->buildLoanSummary($loan)))->toArray(request());
        $payload = array_merge($summary, [
            'status' => (string) ($loan->status ?? $summary['loan_status'] ?? ''),
            'loan_date' => ! empty($loan->loan_date) ? date('Y-m-d', strtotime((string) $loan->loan_date)) : null,
            'first_due_date' => ! empty($loan->first_due_date) ? date('Y-m-d', strtotime((string) $loan->first_due_date)) : null,
            'customer_id' => (int) ($loan->customer_id ?? 0),
            'customer_name' => (string) ($loan->customer_name_snapshot ?? ''),
            'customer_phone' => (string) ($loan->customer_phone_snapshot ?? ''),
            'business_location_id' => (int) ($loan->business_location_id ?? 0),
            'assigned_collector_id' => (int) ($loan->collector_id ?? $loan->assigned_collector_id ?? 0),
            'principal_amount' => $this->money($loan->principal_amount ?? $summary['product_price'] ?? 0),
            'down_payment' => $this->money($loan->down_payment ?? 0),
            'interest_rate' => $this->money($loan->interest_rate ?? 0),
            'interest_type' => (string) ($loan->interest_type ?? 'flat'),
            'duration_months' => (int) ($loan->duration_months ?? $loan->installment_count ?? 0),
            'payment_frequency' => (string) ($loan->payment_frequency ?? 'monthly'),
            'penalty_type' => (string) ($loan->penalty_type ?? ''),
            'penalty_amount' => $this->money($loan->penalty_amount ?? 0),
            'note' => (string) ($loan->note ?? ''),
            'paid_amount' => $this->money($loan->paid_amount ?? $summary['total_paid_amount'] ?? 0),
            'balance_amount' => $this->money($loan->balance_amount ?? $summary['remaining_balance'] ?? 0),
            'total_payable' => $this->money($loan->total_amount ?? $summary['total_loan_amount'] ?? 0),
        ]);

        if ($includeDetail) {
            $payload['items'] = $this->loanRows('loan_items', $loan->id, 'id');
            $payload['schedules'] = $this->loanRows('loan_payment_schedules', $loan->id, 'due_date');
            $payload['payments'] = $this->loanRows('loan_payments', $loan->id, 'id', true);
        }

        return $payload;
    }

    protected function upsertFirstLoanItem(int $loanId, array $item, float $unitPrice, int $qty): void
    {
        if (! Schema::connection($this->conn)->hasTable('loan_items') || empty($item)) {
            return;
        }

        $lineTotal = round($unitPrice * max(1, $qty), 2);
        $payload = $this->onlyExistingColumns('loan_items', [
            'loan_id' => $loanId,
            'product_name_snapshot' => $item['product_name'] ?? null,
            'product_name' => $item['product_name'] ?? null,
            'sku_snapshot' => $item['sku'] ?? null,
            'sku' => $item['sku'] ?? null,
            'imei_snapshot' => $item['imei'] ?? null,
            'color_snapshot' => $item['color'] ?? null,
            'color' => $item['color'] ?? null,
            'storage_snapshot' => $item['storage'] ?? null,
            'storage' => $item['storage'] ?? null,
            'serial_number_snapshot' => $item['serial_number'] ?? null,
            'serial_number' => $item['serial_number'] ?? null,
            'qty' => max(1, $qty),
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'total_price' => $lineTotal,
            'updated_at' => now(),
        ]);

        $existingId = DB::connection($this->conn)
            ->table('loan_items')
            ->where('loan_id', $loanId)
            ->orderBy('id')
            ->value('id');

        if ($existingId) {
            DB::connection($this->conn)->table('loan_items')->where('id', $existingId)->update($payload);
            return;
        }

        $payload = $this->onlyExistingColumns('loan_items', $payload + ['created_at' => now()]);
        DB::connection($this->conn)->table('loan_items')->insert($payload);
    }

    protected function onlyExistingColumns(string $table, array $payload): array
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return [];
        }

        $columns = Schema::connection($this->conn)->getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }

    protected function loanRows(string $table, int $loanId, string $orderColumn, bool $desc = false): array
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return [];
        }

        $query = DB::connection($this->conn)->table($table)->where('loan_id', $loanId);
        $desc ? $query->orderByDesc($orderColumn) : $query->orderBy($orderColumn);

        return $query->get()->values()->all();
    }

    protected function locations(): array
    {
        if (! Schema::connection($this->conn)->hasTable('loan_business_locations')) {
            return [];
        }

        return DB::connection($this->conn)->table('loan_business_locations')
            ->select('id', 'name', 'main_location_id')
            ->when(Schema::connection($this->conn)->hasColumn('loan_business_locations', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('name')
            ->get()
            ->values()
            ->all();
    }

    protected function collectors(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name, username")
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $row->name = trim((string) $row->name) !== '' ? $row->name : $row->username;
                return $row;
            })
            ->values()
            ->all();
    }

    protected function useApiGuard(Request $request): void
    {
        Auth::shouldUse('api');
        if ($request->user()) {
            Auth::setUser($request->user());
        }
    }
}
