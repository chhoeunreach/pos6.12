<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanSellConversionService;
use Carbon\Carbon;

class LoanSellListController extends Controller
{
    public function __construct(protected LoanSellConversionService $service)
    {
    }

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($request->filled('sell_list_filter_date_range')) {
            $parts = preg_split('/\s*~\s*/', (string) $request->input('sell_list_filter_date_range'));
            if (is_array($parts) && count($parts) === 2) {
                try {
                    $startDate = \Carbon\Carbon::parse(trim($parts[0]))->format('Y-m-d');
                    $endDate = \Carbon\Carbon::parse(trim($parts[1]))->format('Y-m-d');
                } catch (\Throwable $e) {
                    // keep existing start/end fallback
                }
            }
        }

        if (empty($startDate)) {
            $startDate = \Carbon\Carbon::now()->subDays(29)->format('Y-m-d');
        }
        if (empty($endDate)) {
            $endDate = \Carbon\Carbon::now()->format('Y-m-d');
        }

        $request->merge([
            'location_id' => $request->input('location_id', $request->input('sell_list_filter_location_id')),
            'customer_id' => $request->input('customer_id', $request->input('sell_list_filter_customer_id')),
            'payment_status' => $request->input('payment_status', $request->input('sell_list_filter_payment_status')),
            'created_by' => $request->input('created_by'),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $rows = $this->service->getSellList($request);
        $filterData = $this->service->getFilterData();
        $business_locations = $filterData['businessLocations'];
        $customers = $filterData['customers'];
        $sales_representative = $filterData['salesRepresentative'];
        $shipping_statuses = $filterData['shippingStatuses'];
        $payment_types = $filterData['paymentTypes'];

        return view('loanmanagement::sell-list.index', compact('rows', 'business_locations', 'customers', 'sales_representative', 'shipping_statuses', 'payment_types'));
    }

    public function view(int $transaction)
    {
        $sell = $this->service->getSellDetails($transaction);
        abort_if(empty($sell), 404, 'Sell transaction not found.');

        return view('loanmanagement::sell-list.view', compact('sell'));
    }

    public function createFromSell(int $transaction)
    {
        abort_if($this->service->isConverted($transaction), 422, 'This sale was already converted to installment.');
        $sell = $this->service->getSellDetails($transaction);
        abort_if(empty($sell), 404, 'Sell transaction not found.');
        $preview = $this->service->buildLoanPreview($sell);

        return view('loanmanagement::sell-list.add-to-installment', compact('sell', 'preview'));
    }

    public function storeFromSell(Request $request, int $transaction)
    {
        $payload = $request->validate([
            'loan_date' => 'nullable|date',
            'term_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $loanId = $this->service->convertTransactionToInstallment($transaction, auth()->id(), $payload);

        return redirect()->route('loan-management.sell-list')->with('status', 'Converted to installment successfully. Loan ID: '.$loanId);
    }
}
