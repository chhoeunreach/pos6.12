<?php

namespace Modules\Accessory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accessory\Entities\Accessory;
use Modules\Accessory\Http\Requests\StoreAccessoryRequest;
use Modules\Accessory\Http\Resources\MobileAccessoryResource;

class MobileAccessoryController extends Controller
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

        $query = Accessory::where('business_id', $business_id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $accessories = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MobileAccessoryResource::collection($accessories),
        ]);
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();
        $accessory = Accessory::where('business_id', $business_id)->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => new MobileAccessoryResource($accessory),
        ]);
    }

    public function store(StoreAccessoryRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        try {
            DB::connection('mysql')->beginTransaction();

            $data = $request->only(['name', 'sku', 'model', 'price', 'cost', 'description']);
            $data['business_id'] = $business_id;
            $data['created_by'] = $user_id;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/img'), $filename);
                $data['image'] = $filename;
            }

            $accessory = Accessory::create($data);

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'data' => new MobileAccessoryResource($accessory),
                'message' => 'Accessory created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create accessory: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update($id, StoreAccessoryRequest $request)
    {
        $business_id = $this->getBusinessId();
        $accessory = Accessory::where('business_id', $business_id)->findOrFail($id);

        try {
            DB::connection('mysql')->beginTransaction();

            $data = $request->only(['name', 'sku', 'model', 'price', 'cost', 'description']);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/img'), $filename);

                if ($accessory->image && file_exists(public_path('uploads/img/' . $accessory->image))) {
                    @unlink(public_path('uploads/img/' . $accessory->image));
                }

                $data['image'] = $filename;
            }

            $accessory->update($data);

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'data' => new MobileAccessoryResource($accessory->fresh()),
                'message' => 'Accessory updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update accessory: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();
        $accessory = Accessory::where('business_id', $business_id)->findOrFail($id);

        try {
            if ($accessory->image && file_exists(public_path('uploads/img/' . $accessory->image))) {
                @unlink(public_path('uploads/img/' . $accessory->image));
            }

            $accessory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Accessory deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete accessory: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function imageUpload($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $accessory = Accessory::where('business_id', $business_id)->findOrFail($id);

        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/img'), $filename);

            if ($accessory->image && file_exists(public_path('uploads/img/' . $accessory->image))) {
                @unlink(public_path('uploads/img/' . $accessory->image));
            }

            $accessory->image = $filename;
            $accessory->save();
        }

        return response()->json([
            'success' => true,
            'data' => new MobileAccessoryResource($accessory->fresh()),
            'message' => 'Image uploaded successfully',
        ]);
    }
}
