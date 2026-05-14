<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Auth
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// ─── Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Users — superadmin & owner only
    Route::middleware('role:superadmin,owner')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Categories, Products, Stocks — superadmin, owner & admin
    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::apiResource('categories', CategoryController::class);

        Route::delete('products/bulk', [ProductController::class, 'bulkDelete']);
        Route::post('products/bulk-import', [ProductController::class, 'bulkImport']);
        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])->withTrashed();
        Route::apiResource('products', ProductController::class);

        Route::get('stocks', [StockController::class, 'index']);
        Route::post('stocks', [StockController::class, 'store']);
        Route::get('stocks/{stock}', [StockController::class, 'show']);
        Route::put('stocks/{stock}/restock', [StockController::class, 'restock']);
        Route::put('stocks/{stock}/adjust', [StockController::class, 'adjust']);
        Route::get('stock-mutations', [StockController::class, 'mutations']);
    });

    // Shifts — semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index']);
        Route::post('shifts', [ShiftController::class, 'open']);
        Route::get('shifts/active', [ShiftController::class, 'active']);
        Route::get('shifts/{shift}', [ShiftController::class, 'show']);
        Route::put('shifts/{shift}/close', [ShiftController::class, 'close']);
    });

    // Transactions — semua role
    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::put('transactions/{transaction}/confirm-payment', [TransactionController::class, 'confirmPayment']);
    });

});
