<?php

namespace Modules\LoanManagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Utils\TransactionUtil;
use App\BusinessLocation;
use Modules\LoanManagement\Http\Requests\StoreLoanFromSellRequest;
use Modules\LoanManagement\Services\CreateLoanFromSellService;

class LoanFromSellController extends Controller
{
    protected array $installmentCustomerGroups = ['រំលស់', 'អ៊ីអន'];

    public function __construct(protected CreateLoanFromSellService $service)
    {
    }

    public function index()
    {
        $locations = BusinessLocation::forDropdown((int) session('user.business_id'), false, false, true, true);
        $customerNames = DB::table('contacts')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->distinct()
            ->limit(500)
            ->pluck('name', 'name');
        $customerPhones = DB::table('contacts')
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->orderBy('mobile')
            ->distinct()
            ->limit(500)
            ->pluck('mobile', 'mobile');
        $customerGroups = collect(['រំលស់' => 'រំលស់', 'អ៊ីអន' => 'អ៊ីអន']);
        if (Schema::hasTable('customer_groups')) {
            $customerGroups = $customerGroups->merge(
                DB::table('customer_groups')
                    ->whereNotNull('name')
                    ->where('name', '!=', '')
                    ->orderBy('name')
                    ->pluck('name', 'name')
            );
        }

        $customerGroups = collect($this->installmentCustomerGroups)
            ->filter(fn ($name) => $customerGroups->has($name))
            ->mapWithKeys(fn ($name) => [$name => $name]);

        if ($customerGroups->isEmpty()) {
            $customerGroups = collect($this->installmentCustomerGroups)
                ->mapWithKeys(fn ($name) => [$name => $name]);
        }

        return view('loanmanagement::loans.create_from_sell.index', [
            'locations' => $locations,
            'customerNames' => $customerNames,
            'customerPhones' => $customerPhones,
            'customerGroups' => $customerGroups,
            'paymentStatuses' => ['paid' => 'Paid', 'due' => 'Due', 'partial' => 'Partial', 'overdue' => 'Overdue'],
        ]);
    }

    public function calculator()
    {
        return view('loanmanagement::loans.calculator', [
            'defaults' => [
                'total_price' => 0,
                'down_payment' => 0,
                'interest_rate' => 4,
                'duration_months' => 12,
                'first_due_date' => Carbon::today()->addMonth()->toDateString(),
                'currency' => session('currency.code', 'USD'),
            ],
        ]);
    }

    public function calculatorPrint(Request $request)
    {
        $data = $request->validate([
            'total_price' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'duration_months' => 'nullable|integer|min:1|max:360',
            'first_due_date' => 'nullable|date',
        ]);

        $totalPrice = round((float) ($data['total_price'] ?? 0), 2);
        $downPayment = min($totalPrice, round((float) ($data['down_payment'] ?? 0), 2));
        $principal = max(0, round($totalPrice - $downPayment, 2));
        $durationMonths = max(1, (int) ($data['duration_months'] ?? 12));
        $interestRate = (float) ($data['interest_rate'] ?? 4);
        $firstDueDate = $data['first_due_date'] ?? Carbon::today()->addMonth()->toDateString();

        $scheduleRows = $principal > 0
            ? $this->service->previewSchedule([
                'principal_amount' => $principal,
                'interest_rate' => $interestRate,
                'interest_type' => 'flat',
                'duration_months' => $durationMonths,
                'payment_frequency' => 'monthly',
                'first_due_date' => $firstDueDate,
            ])
            : collect(range(1, $durationMonths))->map(function ($month) use ($firstDueDate) {
                return [
                    'schedule_no' => $month,
                    'due_date' => Carbon::parse($firstDueDate)->addMonths($month - 1)->toDateString(),
                    'principal' => 0,
                    'interest' => 0,
                    'total' => 0,
                    'balance' => 0,
                ];
            })->all();

        $installments = collect($scheduleRows)->map(function ($row) {
            return (object) [
                'installment_number' => $row['schedule_no'],
                'installmentdate' => $row['due_date'],
                'installment_value' => $row['principal'],
                'benefit_value' => $row['interest'],
                'amount_due' => $row['total'],
                'balance' => $row['balance'],
            ];
        });

        $businessName = session('business.name', 'Loan Management');
        $printedAt = Carbon::now()->format('d-M-Y H:i:s');
        $loanDate = Carbon::today()->format('m-d-Y');
        $loanDateTitle = Carbon::today()->format('d-M-Y');
        $lastDueDate = optional($installments->last())->installmentdate;
        $schedulePrincipalTotal = round($installments->sum('installment_value'), 2);
        $scheduleInterestTotal = round($installments->sum('benefit_value'), 2);
        $scheduleTotalAmount = round($installments->sum('amount_due'), 2);
        $downPercent = $totalPrice > 0 ? round($downPayment / $totalPrice * 100, 2) : 0;

        return view('loanmanagement::loans.print.calculator', compact(
            'businessName',
            'totalPrice',
            'downPayment',
            'principal',
            'durationMonths',
            'interestRate',
            'firstDueDate',
            'lastDueDate',
            'installments',
            'printedAt',
            'loanDate',
            'loanDateTitle',
            'schedulePrincipalTotal',
            'scheduleInterestTotal',
            'scheduleTotalAmount',
            'downPercent'
        ));
    }
    public function search(Request $request): JsonResponse
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        if ($request->filled('date_range') && (! $startDate || ! $endDate)) {
            $parts = preg_split('/\s+(?:to|–|—)\s+/i', trim((string) $request->date_range));
            if (count($parts) >= 2) {
                $startDate = trim($parts[0]);
                $endDate = trim($parts[1]);
            }
        }

        $filters = [
            'invoice_no' => $request->invoice_no,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_group_name' => $request->input('customer_group_name', 'រំលស់'),
            'location_id' => $request->location_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_status' => $request->payment_status,
            'sale_status' => $request->sale_status,
            'final_total' => $request->final_total,
            'imei_or_lot' => $request->imei_or_lot,
            'product_name_sku' => $request->product_name_sku,
            'permitted_location_ids' => $this->permittedLocationIds(),
        ];

        if (blank($request->input('customer_group_name'))) {
            $filters['customer_group_name'] = null;
        }
        $filters['customer_group_names'] = $this->installmentCustomerGroups;

        $rows = $this->service->searchSales($filters);

        return response()->json([
            'success' => true,
            'message' => 'Sells loaded successfully',
            'data' => $rows,
        ]);
    }

    public function searchSales(Request $request): JsonResponse
    {
        return $this->search($request);
    }

    protected function permittedLocationIds(): ?array
    {
        try {
            $permitted = auth()->user()?->permitted_locations(session('user.business_id'));
            if ($permitted === 'all') {
                return null;
            }

            return array_values(array_filter(array_map('intval', (array) $permitted)));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clone(Request $request, $transaction_id)
    {
        if ($this->service->preventDuplicateLoan((int) $transaction_id)) {
            $loanId = $this->service->getLoanIdBySourceTransactionId((int) $transaction_id);
            if (! $request->ajax() && ! $request->wantsJson()) {
                return redirect()->route('loan-management.loans.create-from-sell')
                    ->with('duplicate_installment_warning', 'This sell already has installment loan.')
                    ->with('duplicate_loan_url', ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null);
            }
            return response()->json([
                'success' => false,
                'message' => 'This sell already has a loan.',
                'data' => [
                'loan_id' => $loanId,
                'loan_url' => ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null,
            ],
        ]);
        }

        $sell = $this->service->getSellFullData((int) $transaction_id);
        $loanLocation = $this->resolveLoanLocationForSell($sell['transaction']);
        $collectors = DB::table('users')->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")->orderBy('first_name')->get();
        $paymentTypes = $this->ultimatePosPaymentTypes($sell);
        $defaultPaymentMethod = $this->defaultPaymentMethod($sell, $paymentTypes);
        $html = view('loanmanagement::loans.create_from_sell.form', compact('sell', 'loanLocation', 'collectors', 'paymentTypes', 'defaultPaymentMethod'))->render();

        if (! $request->ajax() && ! $request->wantsJson()) {
            return view('loanmanagement::loans.create_from_sell.clone', compact('sell', 'loanLocation', 'collectors', 'paymentTypes', 'defaultPaymentMethod'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Sell cloned successfully',
            'data' => [
                'sell' => $sell,
                'clone' => $this->service->cloneSaleToLoanFormData($sell),
                'form_html' => $html,
                'loan_url' => null,
            ],
        ]);
    }

    protected function ultimatePosPaymentTypes(array $sell): array
    {
        $businessId = (int) (session('user.business_id') ?? 0);
        $locationId = $sell['transaction']->location_id ?? null;

        return app(TransactionUtil::class)->payment_types($locationId, true, $businessId);
    }

    protected function defaultPaymentMethod(array $sell, array $paymentTypes): string
    {
        $method = (string) ($sell['default_payment_method'] ?? '');

        if ($method !== '' && array_key_exists($method, $paymentTypes)) {
            return $method;
        }

        return array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');
    }

    protected function resolveLoanLocationForSell(object $transaction): ?object
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return null;
        }

        $candidates = DB::connection('mysql_loan')->table('loan_business_locations')
            ->when(! empty($transaction->main_location_id) || ! empty($transaction->location_name_snapshot), function ($query) use ($transaction) {
                $query->where(function ($q) use ($transaction) {
                    if (! empty($transaction->main_location_id)) {
                        $q->orWhere('main_location_id', $transaction->main_location_id);
                    }
                    if (! empty($transaction->location_name_snapshot)) {
                        $q->orWhere('name', $transaction->location_name_snapshot);
                    }
                });
            })
            ->get();

        $location = collect($candidates)
            ->sortByDesc(function ($row) use ($transaction) {
                $score = 0;
                if (! empty($transaction->location_name_snapshot) && (string) $row->name === (string) $transaction->location_name_snapshot) {
                    $score += 8;
                }
                if (! empty($transaction->main_location_id) && (int) ($row->main_location_id ?? 0) === (int) $transaction->main_location_id) {
                    $score += 4;
                }
                if (! empty($row->payment_qr_path)) {
                    $score += 2;
                }
                if (! empty($row->telegram_qr_path)) {
                    $score += 1;
                }

                return $score;
            })
            ->first();

        if (! $location) {
            return null;
        }

        $location->payment_qr_asset_url = $this->loanLocationAssetUrl($location->payment_qr_path ?? null);
        $location->telegram_qr_asset_url = $this->loanLocationAssetUrl($location->telegram_qr_path ?? null);
        $location->logo_asset_url = $this->loanLocationAssetUrl($location->logo_path ?? null);

        return $location;
    }

    protected function loanLocationAssetUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (preg_match('#^(?:uploads/)?loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return route('loan-management.locations.assets.show', [
                'location' => (int) $matches[1],
                'filename' => $matches[2],
            ]);
        }

        if (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return route('loan-management.locations.assets.show', [
                'location' => (int) $matches[1],
                'filename' => $matches[2],
            ]);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    public function cloneData(Request $request, $transaction_id): JsonResponse
    {
        return $this->clone($request, $transaction_id);
    }

    public function checkDuplicateLoan($transaction_id): JsonResponse
    {
        $exists = $this->service->preventDuplicateLoan((int) $transaction_id);
        $loanId = $exists ? $this->service->getLoanIdBySourceTransactionId((int) $transaction_id) : null;

        return response()->json([
            'success' => true,
            'message' => $exists ? 'This sell already has installment loan.' : 'Sell can be converted.',
            'data' => [
                'exists' => $exists,
                'loan_id' => $loanId,
                'loan_url' => ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null,
                'clone_url' => url('/loan-management/loans/sell/'.$transaction_id.'/clone'),
            ],
        ]);
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'principal_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'required|in:flat',
            'duration_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:monthly,weekly,daily',
            'first_due_date' => 'required|date',
        ]);

        $rows = $this->service->previewSchedule($payload);

        return response()->json([
            'success' => true,
            'message' => 'Schedule preview generated',
            'data' => $rows,
        ]);
    }

    public function store(StoreLoanFromSellRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $transactionId = (int) $validated['transaction_id'];
            if ($this->service->preventDuplicateLoan($transactionId)) {
                $loanId = $this->service->getLoanIdBySourceTransactionId($transactionId);

                return response()->json([
                    'success' => false,
                    'message' => 'This sale already has installment loan.',
                    'data' => [
                        'loan_id' => $loanId,
                        'loan_url' => ! empty($loanId) ? route('loan-management.loans.view', $loanId) : null,
                    ],
                ], 422);
            }

            $loanId = $this->service->createLoanFromSell($validated);

            return response()->json([
                'success' => true,
                'message' => 'Loan created from sell successfully',
                'data' => ['loan_id' => $loanId],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }

    public function storeFromSell(StoreLoanFromSellRequest $request): JsonResponse
    {
        return $this->store($request);
    }
}
