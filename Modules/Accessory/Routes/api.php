<?php

use App\Http\Controllers\MobileApi\AuthController;
use App\Http\Controllers\MobileApi\CustomerController;
use App\Http\Controllers\MobileApi\DashboardController;
use App\Http\Controllers\MobileApi\ExpenseController;
use App\Http\Controllers\MobileApi\PaymentController;
use App\Http\Controllers\MobileApi\PosController;
use App\Http\Controllers\MobileApi\ProductController;
use App\Http\Controllers\MobileApi\PurchaseController;
use App\Http\Controllers\MobileApi\ReportController;
use App\Http\Controllers\MobileApi\SaleController;
use App\Http\Controllers\MobileApi\SettingsController;
use App\Http\Controllers\MobileApi\StockController;
use App\Http\Controllers\MobileApi\SupplierController;
use Modules\Accessory\Http\Controllers\MobileAccessoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accessory Module Mobile API Routes
|--------------------------------------------------------------------------
|
| All routes here use the accessory database via the accessory.database
| middleware (applied in RouteServiceProvider). Public routes exclude
| the auth middleware for login.
|
*/

// Public routes (no auth required)
Route::post('mobile/login', [AuthController::class, 'login'])
    ->withoutMiddleware(['auth:api', 'accessory.database']);

// Authenticated routes
Route::group([], function () {

    // Auth
    Route::post('mobile/logout', [AuthController::class, 'logout']);
    Route::get('mobile/me', [AuthController::class, 'me']);
    Route::get('mobile/permissions', [AuthController::class, 'permissions']);
    Route::get('mobile/locations', [AuthController::class, 'locations']);

    // Dashboard
    Route::get('mobile/dashboard', [DashboardController::class, 'index']);

    // POS
    Route::prefix('mobile/pos')->group(function () {
        Route::get('settings', [PosController::class, 'settings']);
        Route::get('products', [PosController::class, 'products']);
        Route::post('validate-cart', [PosController::class, 'validateCart']);
        Route::post('sales', [PosController::class, 'sales']);
        Route::get('receipt/{transaction_id}', [PosController::class, 'receipt']);
    });

    // Products
    Route::prefix('mobile/products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('{id}', [ProductController::class, 'show']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{id}', [ProductController::class, 'update']);
        Route::post('{id}/image', [ProductController::class, 'imageUpload']);
        Route::get('{id}/stock', [ProductController::class, 'stock']);
    });

    // Customers
    Route::prefix('mobile/customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('{id}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('{id}', [CustomerController::class, 'update']);
        Route::get('{id}/ledger', [CustomerController::class, 'ledger']);
        Route::get('{id}/payments', [CustomerController::class, 'payments']);
        Route::post('{id}/pay-due', [CustomerController::class, 'payDue']);
    });

    // Suppliers
    Route::prefix('mobile/suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('{id}', [SupplierController::class, 'show']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::put('{id}', [SupplierController::class, 'update']);
        Route::get('{id}/ledger', [SupplierController::class, 'ledger']);
        Route::post('{id}/pay-due', [SupplierController::class, 'payDue']);
    });

    // Sales
    Route::prefix('mobile/sales')->group(function () {
        Route::get('/', [SaleController::class, 'index']);
        Route::get('{id}', [SaleController::class, 'show']);
        Route::post('{id}/payment', [SaleController::class, 'payment']);
        Route::post('{id}/return', [SaleController::class, 'sellReturn']);
        Route::delete('{id}', [SaleController::class, 'destroy']);
    });

    // Purchases
    Route::prefix('mobile/purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::get('{id}', [PurchaseController::class, 'show']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::put('{id}', [PurchaseController::class, 'update']);
        Route::delete('{id}', [PurchaseController::class, 'destroy']);
        Route::post('{id}/payment', [PurchaseController::class, 'payment']);
        Route::post('{id}/return', [PurchaseController::class, 'purchaseReturn']);
    });

    // Expenses
    Route::prefix('mobile/expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::get('categories', [ExpenseController::class, 'categories']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::put('{id}', [ExpenseController::class, 'update']);
        Route::delete('{id}', [ExpenseController::class, 'destroy']);
    });

    // Stock
    Route::prefix('mobile/stock')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('low', [StockController::class, 'lowStock']);
        Route::get('adjustments', [StockController::class, 'adjustments']);
        Route::post('adjustments', [StockController::class, 'storeAdjustment']);
        Route::get('transfers', [StockController::class, 'transfers']);
        Route::post('transfers', [StockController::class, 'storeTransfer']);
    });

    // Payments
    Route::prefix('mobile/payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('{id}', [PaymentController::class, 'show']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('{id}', [PaymentController::class, 'update']);
    });

    // Reports
    Route::prefix('mobile/reports')->group(function () {
        Route::get('sales', [ReportController::class, 'sales']);
        Route::get('products', [ReportController::class, 'products']);
        Route::get('customers-due', [ReportController::class, 'customersDue']);
        Route::get('suppliers-due', [ReportController::class, 'suppliersDue']);
        Route::get('stock', [ReportController::class, 'stock']);
        Route::get('payments', [ReportController::class, 'payments']);
        Route::get('expenses', [ReportController::class, 'expenses']);
        Route::get('purchases', [ReportController::class, 'purchases']);
        Route::get('profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('local-cashier', [ReportController::class, 'localCashier']);
    });

    // Categories & Brands
    Route::get('mobile/categories', function () {
        $business_id = auth()->user()->business_id;
        $categories = \App\Category::catAndSubCategories($business_id);
        return response()->json(['success' => true, 'data' => $categories]);
    });
    Route::get('mobile/brands', function () {
        $business_id = auth()->user()->business_id;
        $brands = \App\Brands::where('business_id', $business_id)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
        return response()->json(['success' => true, 'data' => $brands]);
    });

    // Settings
    Route::get('mobile/settings', [SettingsController::class, 'index']);
    Route::get('mobile/payment-methods', [SettingsController::class, 'paymentMethods']);
    Route::get('mobile/business', [SettingsController::class, 'business']);

    // Accessories CRUD (main database, bypass accessory.database middleware)
    Route::prefix('mobile/accessories')->withoutMiddleware('accessory.database')->group(function () {
        Route::get('/', [MobileAccessoryController::class, 'index']);
        Route::get('{id}', [MobileAccessoryController::class, 'show']);
        Route::post('/', [MobileAccessoryController::class, 'store']);
        Route::put('{id}', [MobileAccessoryController::class, 'update']);
        Route::delete('{id}', [MobileAccessoryController::class, 'destroy']);
        Route::post('{id}/image', [MobileAccessoryController::class, 'imageUpload']);
    });
});
