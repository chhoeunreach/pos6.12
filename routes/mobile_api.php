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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
|
| Routes for Flutter mobile app. All authenticated routes use Passport
| token auth. Session data is set via SetMobileApiSession middleware.
|
*/

// Public routes
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Mobile API is reachable',
        'data' => [
            'service' => 'Ultimate POS Mobile API',
            'version' => '1.0',
        ],
    ]);
});
Route::get('ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'pong',
        'data' => [
            'service' => 'Ultimate POS Mobile API',
        ],
    ]);
});
Route::post('login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware(['auth:api', 'SetMobileApiSession'])->group(function () {

    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('permissions', [AuthController::class, 'permissions']);
    Route::get('locations', [AuthController::class, 'locations']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // POS
    Route::prefix('pos')->group(function () {
        Route::get('settings', [PosController::class, 'settings']);
        Route::get('products', [PosController::class, 'products']);
        Route::post('validate-cart', [PosController::class, 'validateCart']);
        Route::post('sales', [PosController::class, 'sales']);
        Route::get('receipt/{transaction_id}', [PosController::class, 'receipt']);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('{id}', [ProductController::class, 'show']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{id}', [ProductController::class, 'update']);
        Route::post('{id}/image', [ProductController::class, 'imageUpload']);
        Route::get('{id}/stock', [ProductController::class, 'stock']);
    });

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('{id}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('{id}', [CustomerController::class, 'update']);
        Route::get('{id}/ledger', [CustomerController::class, 'ledger']);
        Route::get('{id}/payments', [CustomerController::class, 'payments']);
        Route::post('{id}/pay-due', [CustomerController::class, 'payDue']);
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('{id}', [SupplierController::class, 'show']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::put('{id}', [SupplierController::class, 'update']);
        Route::get('{id}/ledger', [SupplierController::class, 'ledger']);
        Route::post('{id}/pay-due', [SupplierController::class, 'payDue']);
    });

    // Sales
    Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index']);
        Route::get('{id}', [SaleController::class, 'show']);
        Route::post('{id}/payment', [SaleController::class, 'payment']);
        Route::post('{id}/return', [SaleController::class, 'sellReturn']);
        Route::delete('{id}', [SaleController::class, 'destroy']);
    });

    // Purchases
    Route::prefix('purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::get('{id}', [PurchaseController::class, 'show']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::post('{id}/payment', [PurchaseController::class, 'payment']);
        Route::post('{id}/return', [PurchaseController::class, 'purchaseReturn']);
    });

    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::get('categories', [ExpenseController::class, 'categories']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::put('{id}', [ExpenseController::class, 'update']);
        Route::delete('{id}', [ExpenseController::class, 'destroy']);
    });

    // Stock
    Route::prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('low', [StockController::class, 'lowStock']);
        Route::get('adjustments', [StockController::class, 'adjustments']);
        Route::post('adjustments', [StockController::class, 'storeAdjustment']);
        Route::get('transfers', [StockController::class, 'transfers']);
        Route::post('transfers', [StockController::class, 'storeTransfer']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('{id}', [PaymentController::class, 'show']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('{id}', [PaymentController::class, 'update']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
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

    // Categories & Brands (for select/dropdown)
    Route::get('categories', function () {
        $business_id = auth()->user()->business_id;
        $categories = \App\Category::where('business_id', $business_id)->get(['id', 'name', 'short_code']);
        return response()->json(['success' => true, 'data' => $categories]);
    });
    Route::get('brands', function () {
        $business_id = auth()->user()->business_id;
        $brands = \App\Brands::where('business_id', $business_id)->get(['id', 'name']);
        return response()->json(['success' => true, 'data' => $brands]);
    });

    // Settings
    Route::get('settings', [SettingsController::class, 'index']);
    Route::get('payment-methods', [SettingsController::class, 'paymentMethods']);
    Route::get('business', [SettingsController::class, 'business']);
});
