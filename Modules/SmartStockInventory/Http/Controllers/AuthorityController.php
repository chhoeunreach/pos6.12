<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exceptions\PurchaseSellMismatch;
use App\Services\AuthorityTransferReceiptService;
use App\Services\StockTransferReconciliationService;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorityController extends BaseSmartStockController
{
    public function details(Request $request, \App\Services\StockAuthorityReconciliationService $service)
    {
        $this->authorizeAuthority($request);
        $input = $request->validate([
            'category' => ['required', \Illuminate\Validation\Rule::in(array_keys($service::DETAIL_CATEGORIES))],
            'reference' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1',
        ]);
        $category = $input['category'];
        $title = $service::DETAIL_CATEGORIES[$category];
        $reference = trim($input['reference'] ?? '');
        $rows = $service->mismatchDetails($this->businessId(), $category, $reference);

        return view($request->ajax() ? 'smartstockinventory::authority.details_content' : 'smartstockinventory::authority.details',
            compact('rows', 'category', 'title', 'reference'));
    }

    private function authorizeAuthority(Request $request): void
    {
        $user = $request->user() ?? auth()->user();
        abort_unless($user && ($user->can('stock_inventory.authority') || $user->can('superadmin') || $this->isSuperAdminOrAdmin($user)), 403);
        $locations = $this->permittedLocationIds($this->businessId());
        if (! $this->isSuperAdminOrAdmin($user)) {
            abort_if(DB::table('business_locations')->where('business_id', $this->businessId())
                ->whereNotIn('id', $locations)->exists(), 403, 'Authority requires access to all locations in this business.');
        }
    }

    private function transfer(int $id): Transaction
    {
        return Transaction::where('business_id', $this->businessId())->where('type', 'sell_transfer')->findOrFail($id);
    }

    public function index(Request $request)
    {
        $this->authorizeAuthority($request);
        $request->validate([
            'reference' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'transfer_id' => 'nullable|integer|min:1',
            'mismatch_status' => 'nullable|string|in:all_mismatches,receipt_mismatch,allocation_mismatch,consistent,all',
            'from_location_id' => 'nullable|integer',
            'to_location_id' => 'nullable|integer',
            'start_date' => 'nullable|string|max:30',
            'end_date' => 'nullable|string|max:30',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $reference = trim((string) ($request->input('reference') ?? $request->input('search') ?? ''));
        $mismatchStatus = $request->input('mismatch_status');
        if ($mismatchStatus === null || $mismatchStatus === '') {
            $mismatchStatus = 'all_mismatches';
        }

        $fromLocationId = $request->filled('from_location_id') ? (int) $request->input('from_location_id') : null;
        $toLocationId = $request->filled('to_location_id') ? (int) $request->input('to_location_id') : null;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        $reconciliationService = app(\App\Services\StockAuthorityReconciliationService::class);
        $mismatchData = $reconciliationService->getTransferMismatchMap($this->businessId());
        $allMismatchIds = $mismatchData['all_mismatch_ids'];
        $receiptMismatchIds = $mismatchData['receipt_mismatch_ids'];
        $saleLineMismatchIds = $mismatchData['sale_line_mismatch_ids'];
        $mismatchMap = $mismatchData['mismatch_map'];
        $mismatchSummary = $reconciliationService->auditMismatches($this->businessId(), $mismatchData['audit']);

        $query = Transaction::from('transactions as t')
            ->leftJoin('transactions as receipt', function ($join) {
                $join->on('receipt.transfer_parent_id', '=', 't.id')
                    ->where('receipt.type', '=', 'purchase_transfer');
            })
            ->leftJoin('business_locations as from_loc', 'from_loc.id', '=', 't.location_id')
            ->leftJoin('business_locations as to_loc', 'to_loc.id', '=', 'receipt.location_id')
            ->where('t.business_id', $this->businessId())
            ->where('t.type', 'sell_transfer')
            ->where('t.status', 'final');

        // Filter by mismatch status (defaults to all_mismatches)
        if ($mismatchStatus === 'all_mismatches') {
            if (!empty($allMismatchIds)) {
                $query->whereIn('t.id', $allMismatchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($mismatchStatus === 'receipt_mismatch') {
            if (!empty($receiptMismatchIds)) {
                $query->whereIn('t.id', $receiptMismatchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($mismatchStatus === 'allocation_mismatch') {
            if (!empty($saleLineMismatchIds)) {
                $query->whereIn('t.id', $saleLineMismatchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($mismatchStatus === 'consistent') {
            if (!empty($allMismatchIds)) {
                $query->whereNotIn('t.id', $allMismatchIds);
            }
        }

        // Location filters
        if ($fromLocationId) {
            $query->where('t.location_id', $fromLocationId);
        }
        if ($toLocationId) {
            $query->where('receipt.location_id', $toLocationId);
        }

        // Date range filters
        if (!empty($startDate)) {
            try {
                $query->whereDate('t.transaction_date', '>=', \Illuminate\Support\Carbon::parse($startDate)->toDateString());
            } catch (\Throwable $e) {}
        }
        if (!empty($endDate)) {
            try {
                $query->whereDate('t.transaction_date', '<=', \Illuminate\Support\Carbon::parse($endDate)->toDateString());
            } catch (\Throwable $e) {}
        }

        // Keyword / Reference search
        if ($reference !== '') {
            $query->where(function ($sub) use ($reference) {
                $sub->where('t.ref_no', 'like', '%' . $reference . '%')
                    ->orWhereExists(function ($lineQuery) use ($reference) {
                        $lineQuery->select(DB::raw(1))
                            ->from('transaction_sell_lines as sl')
                            ->join('products as pr', 'pr.id', '=', 'sl.product_id')
                            ->leftJoin('variations as vr', 'vr.id', '=', 'sl.variation_id')
                            ->leftJoin('purchase_lines as pl', 'pl.id', '=', 'sl.lot_no_line_id')
                            ->whereColumn('sl.transaction_id', 't.id')
                            ->where(function ($matchQ) use ($reference) {
                                $matchQ->where('pr.name', 'like', '%' . $reference . '%')
                                    ->orWhere('pr.sku', 'like', '%' . $reference . '%')
                                    ->orWhere('vr.sub_sku', 'like', '%' . $reference . '%')
                                    ->orWhere('pl.lot_number', 'like', '%' . $reference . '%');
                            });
                    });
            });
        }

        $transfers = $query->select([
            't.id',
            't.ref_no',
            't.transaction_date',
            't.location_id as from_location_id',
            'receipt.location_id as to_location_id',
            'from_loc.name as from_location_name',
            'to_loc.name as to_location_name',
            DB::raw('(SELECT COUNT(*) FROM transaction_sell_lines WHERE transaction_id = t.id) as line_count')
        ])
        ->orderByDesc('t.id')
        ->paginate($perPage)
        ->withQueryString();

        $transfer = $request->filled('transfer_id') ? $this->transfer((int) $request->input('transfer_id')) : null;
        if (! $transfer && $reference !== '' && $transfers->total() === 1) {
            $transfer = $this->transfer((int) $transfers->first()->id);
        }
        $lines = $transfer ? app(StockTransferReconciliationService::class)->manualOptions($this->businessId(), $transfer->id, true, true) : [];
        $lineIds = collect($lines)->pluck('sell_line_id');
        $businessBatches = DB::table('purchase_lines as batch')->join('transactions as receipt', 'receipt.id', '=', 'batch.transaction_id')
            ->where('receipt.business_id', $this->businessId())->select('batch.id', 'batch.lot_number');
        $allocations = DB::table('transaction_sell_lines_purchase_lines as m')
            ->leftJoinSub($businessBatches, 'p', 'p.id', '=', 'm.purchase_line_id')
            ->whereIn('m.sell_line_id', $lineIds)->orderBy('m.id')
            ->get(['m.sell_line_id', 'm.purchase_line_id', 'm.quantity', 'm.qty_returned', 'p.lot_number', 'p.id as batch_id'])->groupBy('sell_line_id');
        $products = DB::table('transaction_sell_lines as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->join('variations as v', 'v.id', '=', 's.variation_id')->where('p.business_id', $this->businessId())->whereIn('s.id', $lineIds)
            ->get(['s.id', 's.product_id', 's.variation_id', 's.quantity', 'p.name', 'v.sub_sku'])->keyBy('id');
        foreach ($lines as &$line) {
            $recordedIds = $allocations->get($line['sell_line_id'], collect())->pluck('purchase_line_id')->push($line['selected_purchase_line_id']);
            $line['recorded_source_date'] = $line['sources']->whereIn('id', $recordedIds)->max('receipt_date');
            $product = $products->get($line['sell_line_id']);
            if (!$product) {
                $line['missing_receiving_quantity'] = 0;
                continue;
            }
            $expected = $products->where('product_id', $product->product_id)->where('variation_id', $product->variation_id)->sum('quantity');
            $line['missing_receiving_quantity'] = max(0, (float) $expected - (float) $line['destinations']->sum('quantity'));
            $line['completion'] = $this->completion($line, $allocations->get($line['sell_line_id'], collect()));
        }
        unset($line);

        if ($request->boolean('modal')) {
            abort_unless($transfer, 404);

            return view('smartstockinventory::authority.correction_form', compact('transfer', 'lines', 'allocations', 'products'));
        }

        $locations = $this->locationOptions($this->businessId());
        $filters = [
            'reference' => $reference,
            'search' => $reference,
            'mismatch_status' => $mismatchStatus,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'per_page' => $perPage,
        ];

        return view('smartstockinventory::authority.index', compact(
            'reference',
            'transfers',
            'transfer',
            'lines',
            'allocations',
            'products',
            'mismatchSummary',
            'mismatchMap',
            'receiptMismatchIds',
            'saleLineMismatchIds',
            'locations',
            'filters',
            'mismatchStatus'
        ));
    }

    private function completion(array $line, $allocations): array
    {
        if (!empty($line['recommendation']['automatic_match'])) {
            return $line['recommendation']['automatic_match'];
        }
        $source = $line['sources']->firstWhere('id', $line['selected_purchase_line_id']);
        if (!$source) {
            $mappedIds = $allocations->pluck('purchase_line_id')->filter()->unique();
            if ($mappedIds->count() === 1 && abs((float) $allocations->sum(fn ($row) => $row->quantity - $row->qty_returned) - $line['quantity']) < 0.0001) {
                $mapped = $line['sources']->firstWhere('id', $mappedIds->first());
                // A recorded deduction alone cannot decide a conflicting serial.
                if ($mapped && $line['destinations']->contains(fn ($row) => (string) $row->lot_number === (string) $mapped->lot_number
                    && abs((float) $row->quantity - $line['quantity']) < 0.0001)) {
                    $source = $mapped;
                }
            }
        }
        if (!$source) {
            return [];
        }
        $destinations = $line['destinations']->filter(fn ($row) => abs((float) $row->quantity - $line['quantity']) < 0.0001);
        $matching = $destinations->filter(fn ($row) => (string) $row->lot_number === (string) $source->lot_number);
        $destination = $matching->count() === 1 ? $matching->first() : null;
        if (!$destination && $matching->isEmpty() && $destinations->count() === 1) {
            $candidate = $destinations->first();
            if (!$candidate->used || trim((string) $candidate->lot_number) === '' || trim((string) $source->lot_number) === '') {
                $destination = $candidate;
            }
        }
        $result = ['source_id' => (int) $source->id];
        if ($destination) {
            $result['destination_id'] = (int) $destination->id;
            if (trim((string) $source->lot_number) === '' && trim((string) $destination->lot_number) !== '') {
                $result['verified_serial'] = $destination->lot_number;
            }
        } elseif ($destinations->isEmpty() && $line['missing_receiving_quantity'] + 0.0001 >= $line['quantity']) {
            $result['destination_id'] = 'new';
        }

        return $result;
    }

    public function fixMismatches(Request $request, \App\Services\StockAuthorityReconciliationService $service)
    {
        $this->authorizeAuthority($request);
        $request->validate(['reason' => 'nullable|string|max:500']);
        $reason = $request->input('reason') ?: 'Stock Inventory Authority Full Mismatch Fix & Data Regeneration';

        $result = $service->reconcileAll($this->businessId(), $reason, $request->user()->id, $request->ip());

        return redirect(ssi_route('ssi.authority.index'))
            ->with('status', [
                'success' => 1,
                'msg' => $result['message'],
                'authority_result' => $result,
            ]);
    }

    private function correctionInput(Request $request): array
    {
        $request->validate(['corrections' => 'required|array|max:5000']);
        $request->merge(['corrections' => collect($request->input('corrections'))->filter(fn ($row) => is_array($row)
            && (! empty($row['source_id']) || ! empty($row['destination_id'])))->values()->all()]);

        $input = $request->validate([
            'corrections' => 'required|array|min:1|max:200',
            'corrections.*.sell_line_id' => 'required|integer|min:1|distinct',
            'corrections.*.source_id' => ['required', 'regex:/^(new|[1-9][0-9]*)$/'],
            'corrections.*.destination_id' => ['required', 'regex:/^(new|[1-9][0-9]*)$/'],
            'corrections.*.verified_serial' => 'nullable|string|max:191',
            'corrections.*.new_source' => 'nullable|array:serial,quantity,date,cost',
            'corrections.*.new_source.serial' => 'nullable|string|max:191',
            'corrections.*.new_source.quantity' => 'required_if:corrections.*.source_id,new|nullable|numeric|gt:0|max:1000000000',
            'corrections.*.new_source.date' => 'required_if:corrections.*.source_id,new|nullable|date_format:Y-m-d H:i:s',
            'corrections.*.new_source.cost' => 'required_if:corrections.*.source_id,new|nullable|numeric|min:0|max:1000000000',
            'evidence' => 'required|string|min:10|max:2000',
            'transaction_date' => 'nullable|date_format:Y-m-d H:i:s',
            'date_basis' => 'nullable|in:receipt_dates,recorded_receiving_date',
        ]);
        $input['transaction_date'] = ($input['transaction_date'] ?? null) ?: null;

        return $input;
    }

    public function preview(Request $request, int $id)
    {
        $this->authorizeAuthority($request);
        $transfer = $this->transfer($id);
        $input = $this->correctionInput($request);
        try {
            $plan = AuthorityTransferReceiptService::needsStaging($input)
                ? app(AuthorityTransferReceiptService::class)->preview($this->businessId(), $id, $input, $request->user()->id)
                : app(StockTransferReconciliationService::class)->previewManual($this->businessId(), $id,
                    $input['corrections'], $input['evidence'], $input['transaction_date'] ?? null, true, true, true);

            if ($request->expectsJson()) {
                return response()->json(['success' => 1, 'html' => view('smartstockinventory::authority.preview_content', compact('transfer', 'input', 'plan'))->render()]);
            }

            return view('smartstockinventory::authority.preview', compact('transfer', 'input', 'plan'));
        } catch (PurchaseSellMismatch $exception) {
            if ($request->expectsJson()) {
                return response()->json($exception->responseData(), 422);
            }
            return redirect(ssi_route('ssi.authority.index', ['transfer_id' => $id]))->withInput()
                ->with('status', $exception->responseData());
        }
    }

    public function apply(Request $request, int $id)
    {
        $this->authorizeAuthority($request);
        $transfer = $this->transfer($id);
        $input = $this->correctionInput($request);
        $request->validate(['token' => 'required|string|size:64']);
        try {
            DB::transaction(function () use ($request, $id, $transfer, $input) {
                $plan = AuthorityTransferReceiptService::needsStaging($input)
                    ? app(AuthorityTransferReceiptService::class)->apply($this->businessId(), $id, $input, $request->user()->id, $request->input('token'))
                    : app(StockTransferReconciliationService::class)->applyManual($this->businessId(), $id,
                        $input['corrections'], $input['evidence'], $request->input('token'), $input['transaction_date'] ?? null, true, true, true);
                app(TransactionUtil::class)->activityLog($transfer, 'stock_transfer_corrected', null,
                    array_merge($plan, ['mode' => 'authority', 'authorized_by' => $request->user()->id, 'date_basis' => $input['date_basis'] ?? null]));
            });

            return redirect(ssi_route('ssi.authority.index', ['transfer_id' => $id]))
                ->with('status', ['success' => 1, 'msg' => 'Authority correction applied. Source deductions, selected serials and receiving records were rechecked. Review any remaining stock shortages in Mismatch Detector.']);
        } catch (PurchaseSellMismatch $exception) {
            return redirect(ssi_route('ssi.authority.index', ['transfer_id' => $id]))->withInput()
                ->with('status', $exception->responseData());
        }
    }
}
