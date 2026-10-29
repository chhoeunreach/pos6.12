<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\LoanManagement\Helpers\LoanMenuHelper;
use Modules\LoanManagement\Entities\LoanBusinessLocation;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanProduct;
use Modules\LoanManagement\Entities\LoanQuotation;
use Modules\LoanManagement\Services\LoanQuotationService;

class LoanQuotationController extends Controller
{
    use ApiResponseTrait;

    protected LoanQuotationService $service;

    public function __construct(LoanQuotationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->authorizeAction('loan_management.loans.view|loan_management.view');
        $query = LoanQuotation::with(['customer', 'location', 'convertedLoan'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $s = trim($request->query('search'));
            $query->where(function ($q) use ($s) {
                $q->where('quotation_no', 'like', "%{$s}%")
                    ->orWhere('customer_name_snapshot', 'like', "%{$s}%")
                    ->orWhere('customer_phone_snapshot', 'like', "%{$s}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('quotation_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('quotation_date', '<=', $request->query('date_to'));
        }

        $quotations = $query->paginate(20)->appends($request->query());

        $summary = [
            'total' => LoanQuotation::count(),
            'draft' => LoanQuotation::where('status', 'draft')->count(),
            'sent' => LoanQuotation::where('status', 'sent')->count(),
            'accepted' => LoanQuotation::where('status', 'accepted')->count(),
            'converted' => LoanQuotation::where('status', 'converted')->count(),
            'total_amount' => LoanQuotation::sum('total_amount'),
        ];

        return view('loanmanagement::quotations.index', compact('quotations', 'summary'));
    }

    public function create()
    {
        $this->authorizeAction('loan_management.loans.create|loan_management.create');
        $quotationNo = $this->service->generateQuotationNumber();
        $locations = LoanBusinessLocation::orderBy('name')->get();
        $customers = LoanCustomer::where('status', 'active')->orderBy('name')->limit(50)->get();
        $products = LoanProduct::orderBy('name')->get();

        return view('loanmanagement::quotations.create', compact('quotationNo', 'locations', 'customers', 'products'));
    }

    public function store(Request $request)
    {
        $this->authorizeAction('loan_management.loans.create|loan_management.create');
        $data = $this->validatedData($request);

        $quotation = $this->service->create($data, auth()->id());

        return redirect()->route('loan-management.quotations.show', $quotation->id)
            ->with('status', ['success' => 1, 'msg' => 'Quotation created successfully!']);
    }

    public function edit(int $id)
    {
        $this->authorizeAction('loan_management.loans.edit|loan_management.edit');
        $quotation = LoanQuotation::with('items')->findOrFail($id);
        abort_if($quotation->status === 'converted' || $quotation->converted_loan_id, 409, 'Converted quotations cannot be edited.');
        $locations = LoanBusinessLocation::orderBy('name')->get();
        $customers = LoanCustomer::where('status', 'active')
            ->orWhere('id', $quotation->customer_id)->orderBy('name')->get();
        $quotationNo = $quotation->quotation_no;

        return view('loanmanagement::quotations.create', compact('quotation', 'quotationNo', 'locations', 'customers'));
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeAction('loan_management.loans.edit|loan_management.edit');
        $quotation = LoanQuotation::findOrFail($id);
        abort_if($quotation->status === 'converted' || $quotation->converted_loan_id, 409, 'Converted quotations cannot be edited.');
        $this->service->update($quotation, $this->validatedData($request), auth()->id());

        return redirect()->route('loan-management.quotations.show', $id)
            ->with('status', ['success' => 1, 'msg' => 'Quotation updated successfully.']);
    }

    protected function validatedData(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => 'nullable|integer',
            'customer_name_snapshot' => 'required|string|max:191',
            'customer_phone_snapshot' => 'required|string|max:50',
            'customer_address_snapshot' => 'nullable|string',
            'business_location_id' => 'nullable|integer',
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'interest_type' => 'required|string|in:flat_rate,declining_balance',
            'duration_months' => 'required|integer|min:1|max:600',
            'payment_frequency' => 'required|string|in:daily,weekly,monthly',
            'first_due_date' => 'required|date',
            'installment_amount' => 'nullable|numeric|min:0',
            'total_interest' => 'nullable|numeric|min:0',
            'total_payable' => 'nullable|numeric|min:0',
            'terms' => 'nullable|string',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:191',
            'items.*.product_id' => 'nullable|integer',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.serial_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.photo_path' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.line_total' => 'nullable|numeric|min:0',
        ]);

        if (! empty($data['business_location_id'])) {
            $loc = LoanBusinessLocation::find($data['business_location_id']);
            $data['location_name_snapshot'] = $loc ? $loc->name : '';
        }

        return $data;
    }

    public function show(int $id)
    {
        $this->authorizeAction('loan_management.loans.view|loan_management.view');
        $quotation = LoanQuotation::with(['items', 'schedules', 'customer', 'location', 'convertedLoan'])->findOrFail($id);

        return view('loanmanagement::quotations.show', compact('quotation'));
    }

    public function print(int $id)
    {
        $this->authorizeAction('loan_management.loans.view|loan_management.view');
        $quotation = LoanQuotation::with(['items', 'schedules', 'customer', 'location'])->findOrFail($id);

        return view('loanmanagement::quotations.print', compact('quotation'));
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $this->authorizeAction('loan_management.loans.create|loan_management.create|loan_management.loans.edit|loan_management.edit');
        $principal = (float) $request->input('principal', $request->input('loan_amount', 0));
        $interestRate = (float) $request->input('interest_rate', 0);
        $interestType = (string) $request->input('interest_type', 'flat_rate');
        $duration = (int) $request->input('duration', $request->input('duration_months', 12));
        $frequency = (string) $request->input('frequency', $request->input('payment_frequency', 'monthly'));
        $firstDueDate = (string) $request->input('first_due_date', now()->addMonth()->toDateString());

        $schedules = $this->service->calculateSchedule($principal, $interestRate, $interestType, $duration, $frequency, $firstDueDate);

        $totalPrincipal = array_sum(array_column($schedules, 'principal_amount'));
        $totalInterest = array_sum(array_column($schedules, 'interest_amount'));
        $totalPayable = array_sum(array_column($schedules, 'schedule_amount'));

        return response()->json([
            'success' => true,
            'schedules' => $schedules,
            'summary' => [
                'total_principal' => round($totalPrincipal, 2),
                'total_interest' => round($totalInterest, 2),
                'total_payable' => round($totalPayable, 2),
                'installment_amount' => count($schedules) > 0 ? $schedules[0]['schedule_amount'] : 0,
            ],
        ]);
    }

    public function convertToLoan(int $id)
    {
        $this->authorizeAction('loan_management.loans.create|loan_management.create');
        $quotation = LoanQuotation::with(['items', 'schedules'])->findOrFail($id);

        try {
            $loan = $this->service->convertToLoan($quotation, auth()->id());

            return redirect()->route('loan-management.loans.view', $loan->id)
                ->with('status', [
                    'success' => 1,
                    'msg' => "Quotation #{$quotation->quotation_no} successfully converted to Loan #{$loan->loan_number}! / បានបម្លែងសម្រង់តម្លៃទៅជាកម្ចីជោគជ័យ!",
                ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Failed to convert quotation: ' . $e->getMessage(),
            ]);
        }
    }

    public function duplicate(int $id)
    {
        $this->authorizeAction('loan_management.loans.create|loan_management.create');
        $quotation = LoanQuotation::with(['items', 'schedules'])->findOrFail($id);
        $newQuote = $this->service->duplicate($quotation, auth()->id());

        return redirect()->route('loan-management.quotations.show', $newQuote->id)
            ->with('status', ['success' => 1, 'msg' => "Quotation duplicated as #{$newQuote->quotation_no}!"]);
    }

    public function changeStatus(Request $request, int $id)
    {
        $this->authorizeAction('loan_management.loans.edit|loan_management.edit');
        $status = $request->input('status');

        if (in_array($status, ['draft', 'sent', 'accepted', 'rejected', 'cancelled'], true)) {
            DB::connection('mysql_loan')->transaction(function () use ($id, $status) {
                $quotation = LoanQuotation::lockForUpdate()->findOrFail($id);
                abort_if($quotation->status === 'converted' || $quotation->converted_loan_id, 409, 'Converted quotations cannot change status.');
                $quotation->status = $status;
                $quotation->updated_by = auth()->id();
                if ($status === 'sent') $quotation->sent_at = now();
                if ($status === 'accepted') $quotation->accepted_at = now();
                if ($status === 'rejected') $quotation->rejected_at = now();
                $quotation->save();
            });

            return redirect()->back()->with('status', ['success' => 1, 'msg' => "Status changed to {$status}."]);
        }

        return redirect()->back()->with('status', ['success' => 0, 'msg' => 'Invalid status.']);
    }

    public function destroy(int $id)
    {
        $this->authorizeAction('loan_management.loans.delete|loan_management.delete');
        DB::connection('mysql_loan')->transaction(function () use ($id) {
            $quotation = LoanQuotation::lockForUpdate()->findOrFail($id);
            abort_if($quotation->status === 'converted' || $quotation->converted_loan_id, 409, 'Converted quotations cannot be deleted.');
            $quotation->delete();
        });

        return redirect()->route('loan-management.quotations.index')
            ->with('status', ['success' => 1, 'msg' => 'Quotation deleted successfully.']);
    }

    protected function authorizeAction(string $permissions): void
    {
        abort_unless(LoanMenuHelper::loanUserCan($permissions), 403, 'Unauthorized action.');
    }
}
