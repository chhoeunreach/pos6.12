<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialCategory;

class CategoryController extends Controller
{
    public function index()
    {
        $businessId = session('business.id', 1);

        $categories = FinancialCategory::with('parent')
            ->where('business_id', $businessId)
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(50);

        $parentCategories = FinancialCategory::where('business_id', $businessId)
            ->whereNull('parent_id')
            ->get();

        return view('dailyfinancialmanagement::category.index', compact('categories', 'parentCategories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string|max:50',
            'name_en' => 'required|string|max:191',
            'name_kh' => 'nullable|string|max:191',
            'type' => 'required|string|in:income,operating_expense,cost_of_goods_sold,non_operating_income,non_operating_expense,asset,liability,equity',
            'parent_id' => 'nullable|integer',
            'is_operating' => 'nullable|boolean',
        ]);

        $businessId = session('business.id', 1);

        try {
            $category = FinancialCategory::create([
                'business_id' => $businessId,
                'code' => $request->code,
                'name_en' => $request->name_en,
                'name_kh' => $request->name_kh,
                'type' => $request->type,
                'parent_id' => $request->parent_id ?: null,
                'is_operating' => (bool) $request->input('is_operating', true),
                'is_active' => true,
                'description' => $request->description,
                'created_by' => auth()->id(),
            ]);

            FinancialAuditLog::record(
                $businessId,
                'category_created',
                'FinancialCategory',
                $category->id,
                null,
                $category->toArray(),
                "Created financial category: {$category->name_en}"
            );

            return back()->with('status', ['success' => 1, 'msg' => 'Category created successfully!']);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        $businessId = session('business.id', 1);
        $category = FinancialCategory::where('business_id', $businessId)->findOrFail($id);

        $request->validate([
            'code' => 'nullable|string|max:50',
            'name_en' => 'required|string|max:191',
            'name_kh' => 'nullable|string|max:191',
            'type' => 'required|string',
        ]);

        $old = $category->toArray();

        $category->update([
            'code' => $request->code,
            'name_en' => $request->name_en,
            'name_kh' => $request->name_kh,
            'type' => $request->type,
            'parent_id' => $request->parent_id ?: null,
            'is_operating' => (bool) $request->input('is_operating', true),
            'is_active' => (bool) $request->input('is_active', true),
            'description' => $request->description,
        ]);

        FinancialAuditLog::record(
            $businessId,
            'category_updated',
            'FinancialCategory',
            $category->id,
            $old,
            $category->toArray(),
            "Updated financial category #{$category->id}"
        );

        return back()->with('status', ['success' => 1, 'msg' => 'Category updated successfully!']);
    }

    public function destroy($id)
    {
        $businessId = session('business.id', 1);
        $category = FinancialCategory::where('business_id', $businessId)->findOrFail($id);

        if ($category->transactions()->exists()) {
            return back()->with('status', ['success' => 0, 'msg' => 'Cannot delete category that contains transactions. Deactivate it instead.']);
        }

        $category->delete();

        return back()->with('status', ['success' => 1, 'msg' => 'Category deleted.']);
    }
}

