<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Services\WkhtmltopdfPdfService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $locations = $this->locations($request->user());
        $defaultLocationId = ! empty($locations) ? (int) ($locations[0]->id ?? 0) : null;

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
                'business_location_id' => $defaultLocationId,
            ],
            'locations' => $locations,
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
        $collectionDue = $request->boolean('collection_due');
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
            ->when($collectionDue, function ($query) {
                $query->whereNotIn('status', ['closed', 'paid', 'completed', 'cancelled'])
                    ->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('loan_payment_schedules as s')
                            ->whereColumn('s.loan_id', 'loans.id')
                            ->whereDate('s.due_date', '<=', now()->toDateString())
                            ->whereIn('s.status', ['pending', 'unpaid', 'partial', 'late']);
                    });
            })
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

    public function print(Request $request, int $loanId, WkhtmltopdfPdfService $pdfService)
    {
        $this->useApiGuard($request);

        $loan = Loan::query()->where('id', $loanId)->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }

        // Clone the original web loan print output, then let wkhtmltopdf render
        // that same HTML for the mobile/API print preview.
        $webPrintView = app(LoanInstallmentListController::class)->print($loanId);
        $html = $webPrintView->render();
        $outputPath = storage_path('app/temp/loan-print-'.$loanId.'-'.uniqid('', true).'.pdf');

        $pdfService->saveHtmlToPdf($html, $outputPath, [
            'encoding' => 'utf-8',
            'page-size' => 'A4',
            'orientation' => 'Portrait',
            'margin-top' => '5mm',
            'margin-right' => '5mm',
            'margin-bottom' => '5mm',
            'margin-left' => '5mm',
            'enable-local-file-access' => true,
            'print-media-type' => true,
            'load-error-handling' => 'ignore',
            'load-media-error-handling' => 'ignore',
            'quiet' => true,
        ]);

        if (! File::exists($outputPath) || File::size($outputPath) === 0) {
            return $this->fail('Unable to generate loan print PDF', 500, (object) []);
        }

        $pdf = File::get($outputPath);
        File::delete($outputPath);

        $filename = str_replace(['"', "\r", "\n"], '', 'Loan '.($loan->loan_number ?: $loanId).'.pdf');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => strlen($pdf),
        ]);
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
        if (! empty($data['loan_number']) && ! empty($loan->loan_number) && (string) $data['loan_number'] !== (string) $loan->loan_number) {
            return $this->fail('Loan number does not match this loan. Please reload and try again.', 409, (object) []);
        }

        $submittedCustomerId = (int) ($data['customer_id'] ?? 0);
        $loanCustomerId = (int) ($loan->customer_id ?? 0);
        $targetCustomerId = $submittedCustomerId > 0 ? $submittedCustomerId : $loanCustomerId;
        $customerChanged = $submittedCustomerId > 0 && $submittedCustomerId !== $loanCustomerId;
        $targetCustomer = null;
        if ($submittedCustomerId > 0 && Schema::connection($this->conn)->hasTable('loan_customers')) {
            $targetCustomer = DB::connection($this->conn)
                ->table('loan_customers')
                ->where('id', $submittedCustomerId)
                ->first();
            if (! $targetCustomer) {
                return $this->fail('Selected loan customer does not exist.', 422, (object) []);
            }
        }

        if ($customerChanged && $targetCustomer) {
            $data['customer_name'] = $targetCustomer->khmer_name
                ?? $targetCustomer->name
                ?? $data['customer_name']
                ?? null;
            $data['customer_phone'] = $targetCustomer->phone
                ?? $targetCustomer->login_phone
                ?? $targetCustomer->mobile
                ?? $data['customer_phone']
                ?? null;
            $data['customer_address'] = $targetCustomer->address ?? $data['customer_address'] ?? null;
            $data['id_card_number'] = $targetCustomer->id_card_number
                ?? $targetCustomer->national_id
                ?? $data['id_card_number']
                ?? null;
        }

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
                'customer_id' => $targetCustomerId > 0 ? $targetCustomerId : null,
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
        if (! $customerChanged) {
            $this->updateLoanCustomer($targetCustomerId, $data);
        }
        $this->storeMobileCustomerDocuments($targetCustomerId, $loanId, $data);

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
            'payments' => ['nullable', 'array'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.paid_date' => ['nullable', 'date'],
            'payments.*.method' => ['nullable', 'string', 'max:100'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'payments.*.currency' => ['nullable', Rule::in(['USD', 'KHR'])],
            'payments.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'payments.*.status' => ['nullable', Rule::in(['completed', 'pending', 'failed'])],
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

        if (! empty($data['payments']) && is_array($data['payments'])) {
            $data['payments'] = collect($data['payments'])
                ->filter(fn ($payment) => is_array($payment) && (float) ($payment['amount'] ?? 0) > 0)
                ->map(function ($payment) use ($data) {
                    $payment['paid_date'] = $payment['paid_date'] ?? $data['loan_date'];
                    $payment['currency'] = $payment['currency'] ?? $data['currency'];
                    $payment['exchange_rate'] = $payment['exchange_rate'] ?? $data['exchange_rate'];
                    $payment['status'] = $payment['status'] ?? 'completed';
                    return $payment;
                })
                ->values()
                ->all();
        }

        return $data;
    }

    protected function mobileLoanPayload(Loan $loan, bool $includeDetail = false): array
    {
        $summary = (new CustomerLoanSummaryResource($this->summaryService->buildLoanSummary($loan)))->toArray(request());
        $scheduleSummary = $this->scheduleSummary((int) $loan->id);
        $payload = array_merge($summary, [
            'status' => (string) ($loan->status ?? $summary['loan_status'] ?? ''),
            'loan_date' => ! empty($loan->loan_date) ? date('Y-m-d', strtotime((string) $loan->loan_date)) : null,
            'first_due_date' => ! empty($loan->first_due_date) ? date('Y-m-d', strtotime((string) $loan->first_due_date)) : null,
            'customer_id' => (int) ($loan->customer_id ?? 0),
            'customer_name' => (string) ($loan->customer_name_snapshot ?? ''),
            'customer_phone' => (string) ($loan->customer_phone_snapshot ?? ''),
            'source_invoice_no' => (string) ($loan->source_invoice_no ?? $loan->loan_number ?? $loan->id),
            'print_pdf_url' => route('loan-management.mobile.loans.print', ['loanId' => $loan->id]),
            'location_name' => (string) ($loan->location_name_snapshot ?? ''),
            'created_by_name' => $this->createdByName($loan),
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
            'payoff_amount' => $this->money($scheduleSummary['payoff_amount'] > 0 ? $scheduleSummary['payoff_amount'] : ($loan->balance_amount ?? $summary['remaining_balance'] ?? 0)),
            'next_due_date' => $scheduleSummary['next_due_date'],
            'schedule_total' => $scheduleSummary['total'],
            'schedule_paid_total' => $scheduleSummary['paid'],
            'schedule_unpaid_total' => $scheduleSummary['unpaid'],
        ]);

        if ($includeDetail) {
            $payload['items'] = $this->loanRows('loan_items', $loan->id, 'id');
            $payload['schedules'] = $this->loanRows('loan_payment_schedules', $loan->id, 'due_date');
            $payload['payments'] = $this->loanRows('loan_payments', $loan->id, 'id', true);
            $payload['customer'] = $this->customerPayload((int) ($loan->customer_id ?? 0));
            $payload['files'] = $this->customerFiles((int) ($loan->customer_id ?? 0));
            $payload['print_assets'] = $this->printAssetsPayload($loan);
        }

        return $payload;
    }

    protected function printAssetsPayload(Loan $loan): array
    {
        $location = $this->loanLocationRow($loan);

        return [
            'business_name' => $this->printLocationName($loan, $location),
            'telegram_number' => (string) ($location->telegram_number ?? '0717221349'),
            'transfer_number' => (string) ($location->transfer_number ?? '070923681'),
            'logo_data_uri' => $this->locationAssetDataUri($location->logo_path ?? null),
            'payment_qr_data_uri' => $this->locationAssetDataUri($location->payment_qr_path ?? null),
            'telegram_qr_data_uri' => $this->locationAssetDataUri($location->telegram_qr_path ?? null),
        ];
    }

    protected function printLocationName(Loan $loan, ?object $location): string
    {
        $name = trim((string) ($location->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($loan->location_name_snapshot ?? $loan->business_location_name_snapshot ?? ''));
        if ($name !== '') {
            return $name;
        }

        return 'KNEAYERNG LOAN';
    }

    protected function createdByName(Loan $loan): string
    {
        $name = trim((string) ($loan->created_by_name_snapshot ?? ''));
        if ($name !== '' && $name !== '-') {
            return preg_replace('/\s+/', ' ', $name) ?: $name;
        }

        $userId = (int) ($loan->created_by ?? 0);
        if ($userId <= 0 || ! Schema::hasTable('users')) {
            return '-';
        }

        $columns = Schema::getColumnListing('users');
        $select = array_values(array_intersect(['first_name', 'last_name', 'username', 'name'], $columns));
        if (empty($select)) {
            return '-';
        }

        $user = DB::table('users')->select($select)->where('id', $userId)->first();
        if (! $user) {
            return '-';
        }

        $full = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));

        return $full !== '' ? $full : (string) ($user->username ?? $user->name ?? '-');
    }

    protected function loanLocationRow(Loan $loan): ?object
    {
        if (! Schema::connection($this->conn)->hasTable('loan_business_locations')) {
            return null;
        }

        $query = DB::connection($this->conn)->table('loan_business_locations')
            ->when(Schema::connection($this->conn)->hasColumn('loan_business_locations', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));
        $locationId = (int) ($loan->business_location_id ?? 0);
        $mainLocationId = (int) ($loan->main_location_id ?? 0);
        $locationName = trim((string) ($loan->location_name_snapshot ?? $loan->business_location_name_snapshot ?? ''));

        if ($locationId > 0) {
            $row = (clone $query)->where('id', $locationId)->first();
            if ($row) {
                return $row;
            }

            if (Schema::connection($this->conn)->hasColumn('loan_business_locations', 'main_location_id')) {
                $row = (clone $query)->where('main_location_id', $locationId)->first();
                if ($row) {
                    return $row;
                }
            }
        }

        if ($mainLocationId > 0 && Schema::connection($this->conn)->hasColumn('loan_business_locations', 'main_location_id')) {
            $row = (clone $query)->where('main_location_id', $mainLocationId)->first();
            if ($row) {
                return $row;
            }
        }

        if ($locationName !== '' && Schema::connection($this->conn)->hasColumn('loan_business_locations', 'name')) {
            $row = (clone $query)->where('name', $locationName)->first();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    protected function locationAssetDataUri(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        $fullPath = null;

        if (preg_match('#^(?:uploads/)?loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            $fullPath = module_path('LoanManagement', 'loan_location_assets/'.((int) $matches[1]).'/'.$matches[2]);
        } elseif (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            $fullPath = module_path('LoanManagement', 'loan_location_assets/'.((int) $matches[1]).'/'.$matches[2]);
        } elseif (File::isFile(public_path($path))) {
            $fullPath = public_path($path);
        } elseif (File::isFile(module_path('LoanManagement', $path))) {
            $fullPath = module_path('LoanManagement', $path);
        }

        if (! $fullPath || ! File::isFile($fullPath)) {
            return null;
        }

        $mime = File::mimeType($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) File::get($fullPath));
    }

    protected function updateLoanCustomer(int $customerId, array $data): void
    {
        if ($customerId <= 0 || ! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return;
        }

        $name = trim((string) ($data['customer_english_name'] ?? $data['customer_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($data['customer_name'] ?? ''));
        }

        $payload = $this->onlyExistingColumns('loan_customers', [
            'name' => $name !== '' ? $name : null,
            'khmer_name' => trim((string) ($data['customer_khmer_name'] ?? '')) ?: null,
            'phone' => trim((string) ($data['customer_phone'] ?? '')) ?: null,
            'login_phone' => trim((string) ($data['customer_phone'] ?? '')) ?: null,
            'alternate_phone' => trim((string) ($data['alternate_phone'] ?? '')) ?: null,
            'address' => trim((string) ($data['customer_address'] ?? '')) ?: null,
            'province' => trim((string) ($data['province_name'] ?? '')) ?: null,
            'province_code' => trim((string) ($data['province_code'] ?? '')) ?: null,
            'district' => trim((string) ($data['district_name'] ?? '')) ?: null,
            'district_code' => trim((string) ($data['district_code'] ?? '')) ?: null,
            'commune' => trim((string) ($data['commune_name'] ?? '')) ?: null,
            'commune_code' => trim((string) ($data['commune_code'] ?? '')) ?: null,
            'village' => trim((string) ($data['village_name'] ?? '')) ?: null,
            'village_code' => trim((string) ($data['village_code'] ?? '')) ?: null,
            'id_card_number' => trim((string) ($data['id_card_number'] ?? '')) ?: null,
            'business_location_id' => $data['business_location_id'] ?? null,
            'updated_at' => now(),
        ]);

        $payload = array_filter($payload, fn ($value) => $value !== null && $value !== '');

        if (! empty($payload)) {
            DB::connection($this->conn)->table('loan_customers')
                ->where('id', $customerId)
                ->update($payload);
        }
    }

    protected function storeMobileCustomerDocuments(int $customerId, int $loanId, array $data): void
    {
        if ($customerId <= 0 || ! Schema::connection($this->conn)->hasTable('loan_files')) {
            return;
        }

        $profileImage = trim((string) ($data['customer_profile_image'] ?? ''));
        if ($profileImage !== '') {
            $fileId = $this->storeMobileDataUriFile($profileImage, $customerId, 'customer_photo', 'customer-profile-'.$loanId.'.jpg');
            $this->updateCustomerFileReference($customerId, 'customer_photo_file_id', $fileId);
        }

        $idCardImage = trim((string) ($data['id_card_image'] ?? ''));
        if ($idCardImage !== '') {
            $fileId = $this->storeMobileDataUriFile($idCardImage, $customerId, 'id_front', 'id-card-front-'.$loanId.'.jpg');
            $this->updateCustomerFileReference($customerId, 'id_front_file_id', $fileId);
            $this->storeIdCardScan($customerId, $fileId, $data);
        }

        foreach ((array) ($data['documents'] ?? []) as $index => $document) {
            if (! is_string($document) || trim($document) === '') {
                continue;
            }

            $this->storeMobileDataUriFile(
                $document,
                $customerId,
                'document',
                'customer-document-'.$loanId.'-'.($index + 1).'.'.$this->extensionFromDataUri($document)
            );
        }

        $documentText = trim((string) ($data['document_text'] ?? ''));
        if ($documentText !== '') {
            $this->storeMobileDataUriFile(
                'data:text/plain;base64,'.base64_encode($documentText),
                $customerId,
                'document_note',
                'customer-document-note-'.$loanId.'.txt'
            );
        }

        foreach ((array) ($data['document_links'] ?? []) as $index => $link) {
            $link = trim((string) $link);
            if ($link === '') {
                continue;
            }

            $this->storeMobileDataUriFile(
                'data:text/plain;base64,'.base64_encode($link),
                $customerId,
                'document_link',
                'customer-document-link-'.$loanId.'-'.($index + 1).'.txt'
            );
        }
    }

    protected function storeMobileDataUriFile(string $dataUri, int $customerId, string $category, string $originalName): ?int
    {
        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $base64 = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'image/jpeg';
            $base64 = $dataUri;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = $this->extensionFromMimeType($mimeType);
        $path = 'loan-customers/'.$customerId.'/'.uniqid('', true).'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return (int) DB::connection($this->conn)->table('loan_files')->insertGetId($this->onlyExistingColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\LoanCustomer::class,
            'fileable_id' => $customerId,
            'category' => $category,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($binary),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function updateCustomerFileReference(int $customerId, string $column, ?int $fileId): void
    {
        if (! $fileId || ! Schema::connection($this->conn)->hasColumn('loan_customers', $column)) {
            return;
        }

        DB::connection($this->conn)->table('loan_customers')->where('id', $customerId)->update([
            $column => $fileId,
            'updated_at' => now(),
        ]);
    }

    protected function storeIdCardScan(int $customerId, ?int $fileId, array $data): void
    {
        if (! $fileId || ! Schema::connection($this->conn)->hasTable('loan_id_card_scans')) {
            return;
        }

        DB::connection($this->conn)->table('loan_id_card_scans')->insert($this->onlyExistingColumns('loan_id_card_scans', [
            'customer_id' => $customerId,
            'loan_file_id' => $fileId,
            'side' => 'front',
            'ocr_raw_text' => $data['id_card_ocr_raw_text'] ?? null,
            'ocr_structured_json' => ! empty($data['id_card_ocr_fields']) ? json_encode($data['id_card_ocr_fields'], JSON_UNESCAPED_UNICODE) : null,
            'provider' => 'tesseract',
            'status' => ! empty($data['id_card_ocr_raw_text']) ? 'completed' : 'pending',
            'scanned_at' => ! empty($data['id_card_ocr_raw_text']) ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function customerPayload(int $customerId): ?object
    {
        if ($customerId <= 0 || ! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return null;
        }

        return DB::connection($this->conn)->table('loan_customers')->where('id', $customerId)->first();
    }

    protected function customerFiles(int $customerId): array
    {
        if ($customerId <= 0 || ! Schema::connection($this->conn)->hasTable('loan_files')) {
            return [];
        }

        return DB::connection($this->conn)->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
            ->where('fileable_id', $customerId)
            ->when(Schema::connection($this->conn)->hasColumn('loan_files', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('id')
            ->get()
            ->map(function ($file) {
                $storageUrl = ! empty($file->path) ? Storage::disk($file->disk ?? 'public')->url($file->path) : null;
                $file->url = $storageUrl ? url($storageUrl) : null;
                return $file;
            })
            ->values()
            ->all();
    }

    protected function extensionFromDataUri(string $dataUri): string
    {
        if (preg_match('/^data:([^;]+);/', $dataUri, $match)) {
            return $this->extensionFromMimeType($match[1]);
        }

        return 'jpg';
    }

    protected function extensionFromMimeType(string $mimeType): string
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ][$mimeType] ?? (str_contains($mimeType, 'png') ? 'png' : (str_contains($mimeType, 'pdf') ? 'pdf' : 'jpg'));
    }

    protected function scheduleSummary(int $loanId): array
    {
        $summary = [
            'total' => 0,
            'paid' => 0,
            'unpaid' => 0,
            'payoff_amount' => 0.0,
            'next_due_date' => null,
        ];

        if (! Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
            return $summary;
        }

        $rows = DB::connection($this->conn)
            ->table('loan_payment_schedules')
            ->where('loan_id', $loanId)
            ->get();

        foreach ($rows as $row) {
            $summary['total']++;
            $status = strtolower((string) ($row->status ?? ''));
            $balance = (float) ($row->balance_amount ?? $row->amount_balance ?? $row->balance ?? 0);
            if ($status === 'paid' || $balance <= 0) {
                $summary['paid']++;
            } else {
                $summary['unpaid']++;
                $summary['payoff_amount'] += $balance;
                $dueDate = (string) ($row->due_date ?? '');
                if ($dueDate !== '' && ($summary['next_due_date'] === null || $dueDate < $summary['next_due_date'])) {
                    $summary['next_due_date'] = $dueDate;
                }
            }
        }

        $summary['payoff_amount'] = round($summary['payoff_amount'], 2);

        return $summary;
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

    protected function locations($user = null): array
    {
        if (! Schema::connection($this->conn)->hasTable('loan_business_locations')) {
            return [];
        }

        $query = DB::connection($this->conn)->table('loan_business_locations')
            ->select('id', 'name', 'main_location_id')
            ->when(Schema::connection($this->conn)->hasColumn('loan_business_locations', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

        $locationIds = $this->mobileUserLocationIds($user);
        if (! empty($locationIds)) {
            $query->whereIn('id', $locationIds);
        }

        return $query->orderBy('name')
            ->get()
            ->values()
            ->all();
    }

    protected function mobileUserLocationIds($user = null): array
    {
        if (! $user) {
            return [];
        }

        foreach (['business_location_id', 'location_id', 'default_location_id'] as $field) {
            $value = (int) ($user->{$field} ?? 0);
            if ($value > 0) {
                return [$value];
            }
        }

        if (method_exists($user, 'permitted_locations')) {
            $permitted = $user->permitted_locations($user->business_id ?? null);
            if (is_array($permitted)) {
                return array_values(array_filter(array_map('intval', $permitted)));
            }
        }

        return [];
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
