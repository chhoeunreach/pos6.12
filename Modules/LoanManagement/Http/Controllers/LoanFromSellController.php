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
        $locations = DB::table('business_locations')->orderBy('name')->pluck('name', 'id');
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
        $customerGroups = collect(['រំលស់' => 'រំលស់'],['អ៊ីអន' => 'អ៊ីអន']);
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
