<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Auth 
Route::prefix('auth')->group(function () {
    Route::post('login',     [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

// ─── Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Users — superadmin & owner only
    Route::middleware('role:superadmin,owner')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Categories & Products — superadmin, owner & admin
    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::delete('products/bulk', [ProductController::class, 'bulkDelete']);
        Route::post('products/bulk-import', [ProductController::class, 'bulkImport']);
        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])
             ->withTrashed();
        Route::apiResource('products', ProductController::class);
    });

});