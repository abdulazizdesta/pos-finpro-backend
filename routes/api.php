<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TaxSettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Superadmin & Owner only
    Route::middleware('role:superadmin,owner')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('outlets', OutletController::class);
        Route::apiResource('tax-settings', TaxSettingController::class);
        Route::apiResource('discounts', DiscountController::class);
    });

    // Categories
    // Read: semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
    });
    // Write: admin ke atas
    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    });

    // Products
    // Read: semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
    });
    // Write:
    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::delete('products/bulk', [ProductController::class, 'bulkDelete']);
        Route::post('products/bulk-import', [ProductController::class, 'bulkImport']);
        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])->withTrashed();
    });

    // Stocks
    // Read: semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('stocks', [StockController::class, 'index']);
        Route::get('stocks/{stock}', [StockController::class, 'show']);
        Route::get('stock-mutations', [StockController::class, 'mutations']);
    });
    // Write: admin ke atas
    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::post('stocks', [StockController::class, 'store']);
        Route::put('stocks/{stock}/restock', [StockController::class, 'restock']);
        Route::put('stocks/{stock}/adjust', [StockController::class, 'adjust']);
    });

    // Shifts: semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index']);
        Route::post('shifts', [ShiftController::class, 'open']);
        Route::get('shifts/active', [ShiftController::class, 'active']);
        Route::get('shifts/{shift}', [ShiftController::class, 'show']);
        Route::put('shifts/{shift}/close', [ShiftController::class, 'close']);
    });

    // Transactions
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    });

    // Buat transaksi — khusus kasir
    Route::middleware('role:cashier')->group(function () {
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::put('transactions/{transaction}/confirm-payment', [TransactionController::class, 'confirmPayment']);
        Route::put('transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::post('transactions/{transaction}/refund', [RefundController::class, 'store']);
    });

    // Reports: admin ke atas
    Route::prefix('reports')->middleware('role:superadmin,owner,admin')->group(function () {
        Route::get('sales', [ReportController::class, 'getSales']);
        Route::get('sales/export', [ReportController::class, 'exportSales']);
    });

});