<?php

namespace Modules\Accessory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accessory\Entities\Accessory;
use Modules\Accessory\Entities\AccessoryPurchase;
use Modules\Accessory\Entities\AccessoryPurchaseItem;
use Modules\Accessory\Http\Requests\StoreAccessoryPurchaseRequest;
use Modules\Accessory\Http\Resources\AccessoryPurchaseResource;

class MobileAccessoryPurchaseController extends Controller
{
    protected function getBusinessId()
    {
        return request()->user()->business_id ?? session('user.business_id');
    }

    protected function getUserId()
    {
        return request()->user()->id;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = AccessoryPurchase::where('business_id', $business_id)
            ->with(['supplier', 'createdBy']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $purchases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AccessoryPurchaseResource::collection($purchases),
        ]);
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $purchase = AccessoryPurchase::where('business_id', $business_id)
            ->with(['items', 'supplier', 'createdBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new AccessoryPurchaseResource($purchase),
        ]);
    }

    public function store(StoreAccessoryPurchaseRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        try {
            DB::connection('mysql')->beginTransaction();

            $items = $request->input('items', []);
            $totalCost = 0;

            foreach ($items as &$item) {
                $accessory = Accessory::where('business_id', $business_id)
                    ->findOrFail($item['accessory_id']);
                $qty = (float) ($item['quantity'] ?? 1);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $item['name'] = $accessory->name;
                $item['sku'] = $accessory->sku;
                $item['subtotal'] = $qty * $cost;
                $totalCost += $item['subtotal'];
            }
            unset($item);

            $supplierId = $request->supplier_id;
            $supplierName = $request->supplier_name;
            if ($supplierId && !$supplierName) {
                $supplier = \App\Contact::where('business_id', $business_id)
                    ->where('id', $supplierId)
                    ->first();
                $supplierName = $supplier?->name;
            }

            $purchase = AccessoryPurchase::create([
                'business_id' => $business_id,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'reference_no' => $request->reference_no,
                'transaction_date' => $request->transaction_date,
                'status' => $request->status,
                'payment_status' => $request->payment_status ?? 'due',
                'total_cost' => $totalCost,
                'additional_notes' => $request->additional_notes,
                'created_by' => $user_id,
            ]);

            foreach ($items as $item) {
                AccessoryPurchaseItem::create([
                    'accessory_purchase_id' => $purchase->id,
                    'accessory_id' => $item['accessory_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::connection('mysql')->commit();

            $purchase->load(['items', 'supplier', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => new AccessoryPurchaseResource($purchase),
                'message' => 'Purchase created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update($id, StoreAccessoryPurchaseRequest $request)
    {
        $business_id = $this->getBusinessId();

        $purchase = AccessoryPurchase::where('business_id', $business_id)
            ->with('items')
            ->findOrFail($id);

        try {
            DB::connection('mysql')->beginTransaction();

            $items = $request->input('items', []);
            $totalCost = 0;

            foreach ($items as &$item) {
                $accessory = Accessory::where('business_id', $business_id)
                    ->findOrFail($item['accessory_id']);
                $qty = (float) ($item['quantity'] ?? 1);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $item['name'] = $accessory->name;
                $item['sku'] = $accessory->sku;
                $item['subtotal'] = $qty * $cost;
                $totalCost += $item['subtotal'];
            }
            unset($item);

            $supplierId = $request->supplier_id;
            $supplierName = $request->supplier_name;
            if ($supplierId && !$supplierName) {
                $supplier = \App\Contact::where('business_id', $business_id)
                    ->where('id', $supplierId)
                    ->first();
                $supplierName = $supplier?->name;
            }

            $purchase->update([
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'reference_no' => $request->reference_no,
                'transaction_date' => $request->transaction_date,
                'status' => $request->status,
                'payment_status' => $request->payment_status ?? 'due',
                'total_cost' => $totalCost,
                'additional_notes' => $request->additional_notes,
            ]);

            // Delete existing items and recreate
            $purchase->items()->delete();

            foreach ($items as $item) {
                AccessoryPurchaseItem::create([
                    'accessory_purchase_id' => $purchase->id,
                    'accessory_id' => $item['accessory_id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::connection('mysql')->commit();

            $purchase->load(['items', 'supplier', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => new AccessoryPurchaseResource($purchase),
                'message' => 'Purchase updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();
        $purchase = AccessoryPurchase::where('business_id', $business_id)->findOrFail($id);

        try {
            $purchase->items()->delete();
            $purchase->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete purchase: ' . $e->getMessage(),
            ], 500);
        }
    }
}
