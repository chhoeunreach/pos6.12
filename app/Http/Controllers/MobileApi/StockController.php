<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\StockAdjustmentRequest;
use App\Http\Requests\MobileApi\StockTransferRequest;
use App\Product;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Stock
 * Stock management, adjustments and transfers
 */
class StockController extends BaseController
{
    protected $productUtil;
    protected $transactionUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $query = Product::where('products.business_id', $business_id)
            ->where('products.enable_stock', 1)
            ->active()
            ->with(['variations.variation_location_details' => function ($q) use ($location_id) {
                if ($location_id) {
                    $q->where('location_id', $location_id);
                }
            }, 'category', 'unit']);

        if ($location_id) {
            $query->forLocation($location_id);
        }
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $products = $query->orderBy('products.name')->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            $total_stock = 0;
            foreach ($product->variations as $variation) {
                foreach ($variation->variation_location_details as $detail) {
                    $total_stock += $detail->qty_available;
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'total_stock' => $total_stock,
                'alert_quantity' => $product->alert_quantity,
                'enable_stock' => $product->enable_stock,
                'category_name' => $product->category->name ?? '',
                'unit_name' => $product->unit->short_name ?? '',
                'stock_details' => $product->variations->map(function ($v) {
                    return [
                        'variation_id' => $v->id,
                        'variation_name' => $v->name,
                        'sub_sku' => $v->sub_sku,
                        'locations' => $v->variation_location_details->map(function ($d) {
                            return [
                                'location_id' => $d->location_id,
                                'qty_available' => $d->qty_available,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return $this->success($products);
    }

    public function lowStock(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $query = Product::where('products.business_id', $business_id)
            ->where('products.enable_stock', 1)
            ->active()
            ->where('products.is_inactive', 0)
            ->with(['variations.variation_location_details' => function ($q) use ($location_id) {
                if ($location_id) {
                    $q->where('location_id', $location_id);
                }
            }]);

        if ($location_id) {
            $query->forLocation($location_id);
        }

        $products = $query->get();
        $low_stock_products = [];

        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                foreach ($variation->variation_location_details as $detail) {
                    if ($detail->qty_available <= $product->alert_quantity) {
                        $low_stock_products[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'variation_id' => $variation->id,
                            'variation_name' => $variation->name,
                            'location_id' => $detail->location_id,
                            'qty_available' => $detail->qty_available,
                            'alert_quantity' => $product->alert_quantity,
                        ];
                    }
                }
            }
        }

        return $this->success($low_stock_products);
    }

    public function adjustments(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'stock_adjustment')
            ->with(['location', 'createdByUser', 'stock_adjustment_lines']);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $adjustments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $adjustments->getCollection()->transform(function ($adj) {
            return [
                'id' => $adj->id,
                'ref_no' => $adj->ref_no,
                'transaction_date' => $adj->transaction_date,
                'location_id' => $adj->location_id,
                'location_name' => $adj->location->name ?? '',
                'final_total' => $adj->final_total,
                'additional_notes' => $adj->additional_notes,
                'created_by' => $adj->createdByUser->user_full_name ?? '',
                'lines' => $adj->stock_adjustment_lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'variation_id' => $line->variation_id,
                        'quantity' => $line->quantity,
                        'type' => $line->type,
                        'unit_cost' => $line->unit_cost,
                        'reason' => $line->reason,
                    ];
                }),
            ];
        });

        return $this->success($adjustments);
    }

    public function storeAdjustment(StockAdjustmentRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('Invalid location');
        }

        try {
            DB::beginTransaction();

            $ref_count = $this->productUtil->setAndGetReferenceCount('stock_adjustment');
            $ref_no = $this->productUtil->generateReferenceNumber('stock_adjustment', $ref_count);

            $adjustment = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $input['location_id'],
                'type' => 'stock_adjustment',
                'status' => 'received',
                'transaction_date' => $input['transaction_date'],
                'additional_notes' => $input['additional_notes'] ?? null,
                'total_before_tax' => $input['total_amount'] ?? 0,
                'final_total' => $input['total_amount'] ?? 0,
                'ref_no' => $ref_no,
                'created_by' => $user_id,
            ]);

            $lines = [];
            foreach ($input['products'] as $product) {
                $this->assertStockProduct($product, $business_id);
                $lines[] = new \App\StockAdjustmentLine([
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_cost'] ?? 0,
                    'lot_no_line_id' => $product['lot_no_line_id'] ?? null,
                ]);

                // Normal and abnormal both classify stock losses.
                $this->productUtil->decreaseProductQuantity($product['product_id'], $product['variation_id'],
                    $input['location_id'], $product['quantity']);
            }

            $adjustment->stock_adjustment_lines()->saveMany($lines);
            $adjustment->update(['adjustment_type' => collect($input['products'])->contains('type', 'abnormal') ? 'abnormal' : 'normal']);
            $this->transactionUtil->mapPurchaseSell([
                'id' => $business_id, 'location_id' => $input['location_id'],
                'accounting_method' => \App\Business::findOrFail($business_id)->accounting_method ?: 'fifo',
                'pos_settings' => ['allow_overselling' => false],
            ], $adjustment->stock_adjustment_lines, 'stock_adjustment');

            \App\Events\StockAdjustmentCreatedOrModified::dispatch($adjustment, 'added');

            DB::commit();

            return $this->success([
                'id' => $adjustment->id,
                'ref_no' => $adjustment->ref_no,
            ], 'Stock adjustment created successfully', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create adjustment: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }

    public function transfers(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['sell_transfer', 'purchase_transfer'])
            ->with([
                'location',
                'transferParent.location',
                'createdByUser',
                'sell_lines.product',
                'sell_lines.variations',
                'purchase_lines.product',
                'purchase_lines.variations',
            ]);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('location_to_id')) {
            $query->whereHas('transferParent', function ($q) use ($request) {
                $q->where('location_id', $request->location_to_id);
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            if ($request->status == 'completed') {
                $query->whereIn('status', ['final', 'completed']);
            } else {
                $query->where('status', $request->status);
            }
        }
        if ($request->filled('product_id')) {
            $query->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))
                    ->from('transaction_sell_lines as tsl')
                    ->whereColumn('tsl.transaction_id', 'transactions.id')
                    ->where('tsl.product_id', $request->product_id);
            });
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $transfers->getCollection()->transform(function ($transfer) {
            $lines = $transfer->type == 'sell_transfer'
                ? $transfer->sell_lines
                : $transfer->purchase_lines;

            $destination = $transfer->transferParent;
            $total_qty = $lines->sum('quantity');
            $status = in_array($transfer->status, ['final', 'completed']) ? 'completed' : $transfer->status;

            return [
                'id' => $transfer->id,
                'type' => $transfer->type,
                'ref_no' => $transfer->ref_no,
                'transaction_date' => $transfer->transaction_date,
                'location_id' => $transfer->location_id,
                'location' => $transfer->location,
                'location_from' => $transfer->location->name ?? '',
                'location_to_id' => $destination->location_id ?? null,
                'location_to' => $destination->location->name ?? '',
                'transfer_parent_id' => $transfer->transfer_parent_id,
                'transfer_parent' => $destination,
                'status' => $status,
                'total_qty' => $total_qty,
                'final_total' => $transfer->final_total,
                'shipping_charges' => $transfer->shipping_charges,
                'additional_notes' => $transfer->additional_notes,
                'created_by_user' => $transfer->createdByUser,
                'created_by_name' => $transfer->createdByUser->user_full_name ?? '',
                'lines' => $lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'variation_id' => $line->variation_id,
                        'quantity' => $line->quantity,
                        'product' => $line->product,
                        'variations' => $line->variations,
                    ];
                })->values(),
            ];
        });

        return $this->success($transfers);
    }

    private function assertStockProduct(array $product, int $businessId): void
    {
        \App\Variation::where('product_id', $product['product_id'])->whereHas('product', function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->findOrFail($product['variation_id']);
    }

    public function storeTransfer(StockTransferRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('Invalid source location');
        }
        if (!$this->checkLocationAccess($input['transfer_location_id'])) {
            return $this->unauthorized('Invalid destination location');
        }

        try {
            DB::beginTransaction();

            $ref_count = $this->productUtil->setAndGetReferenceCount('stock_transfer');
            $ref_no = !empty($input['ref_no'])
                ? $input['ref_no']
                : $this->productUtil->generateReferenceNumber('stock_transfer', $ref_count);
            $status = $input['status'] ?? 'completed';
            $sell_status = in_array($status, ['completed', 'final']) ? 'final' : $status;
            $purchase_status = in_array($status, ['completed', 'final']) ? 'received' : $status;
            $line_total = collect($input['products'])->sum(function ($product) {
                return ($product['quantity'] ?? 0) * ($product['unit_cost'] ?? 0);
            });
            $shipping_charges = $input['shipping_charges'] ?? 0;
            $final_total = $line_total + $shipping_charges;

            $sell_transfer = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $input['location_id'],
                'type' => 'sell_transfer',
                'status' => $sell_status,
                'transaction_date' => $input['transaction_date'],
                'additional_notes' => $input['additional_notes'] ?? null,
                'total_before_tax' => $line_total,
                'final_total' => $final_total,
                'ref_no' => $ref_no,
                'created_by' => $user_id,
                'shipping_charges' => $shipping_charges,
                'payment_status' => 'paid',
            ]);

            foreach ($input['products'] as $product) {
                $this->assertStockProduct($product, $business_id);
                $sell_line = new \App\TransactionSellLine([
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_cost'] ?? 0,
                    'unit_price_inc_tax' => $product['unit_cost'] ?? 0,
                    'unit_price_before_discount' => $product['unit_cost'] ?? 0,
                    'lot_no_line_id' => $product['lot_no_line_id'] ?? null,
                    'item_tax' => 0,
                ]);
                $sell_transfer->sell_lines()->save($sell_line);

            }

            $purchase_transfer = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $input['transfer_location_id'],
                'type' => 'purchase_transfer',
                'status' => $purchase_status,
                'transaction_date' => $input['transaction_date'],
                'additional_notes' => $input['additional_notes'] ?? null,
                'total_before_tax' => $line_total,
                'final_total' => $final_total,
                'ref_no' => $ref_no,
                'created_by' => $user_id,
                'transfer_parent_id' => $sell_transfer->id,
                'shipping_charges' => $shipping_charges,
                'payment_status' => 'paid',
            ]);

            foreach ($input['products'] as $product) {
                $purchase_line = new \App\PurchaseLine([
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $product['quantity'],
                    'purchase_price' => $product['unit_cost'] ?? 0,
                    'purchase_price_inc_tax' => $product['unit_cost'] ?? 0,
                    'item_tax' => 0,
                ]);
                $purchase_transfer->purchase_lines()->save($purchase_line);

            }

            if (in_array($status, ['completed', 'final'], true)) {
                app(\App\Services\StockTransferStockService::class)->apply($sell_transfer, $purchase_transfer,
                    \App\Business::findOrFail($business_id)->accounting_method ?: 'fifo');
            }
            $this->transactionUtil->activityLog($sell_transfer, 'added');
            DB::commit();
            \App\Events\StockTransferCreatedOrModified::dispatch($sell_transfer, 'added');

            return $this->success([
                'sell_transfer_id' => $sell_transfer->id,
                'purchase_transfer_id' => $purchase_transfer->id,
                'ref_no' => $ref_no,
            ], 'Stock transfer created successfully', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create transfer: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }
}






