<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Inventory Sync API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// Full REST API for products
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::get('/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::put('/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
});

// Inventory logs
Route::prefix('inventory-logs')->group(function () {
    Route::get('/', [InventoryLogController::class, 'index'])->name('inventory-logs.index');
    Route::get('/product/{product_id}', [InventoryLogController::class, 'byProduct'])->name('inventory-logs.by-product');
    Route::get('/statistics', [InventoryLogController::class, 'statistics'])->name('inventory-logs.statistics');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
