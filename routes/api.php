<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Public storefront. The callers are the Next.js server components (one shared IP,
// results cached 60 s), so the limit is generous: it only stops direct abuse of the API.
Route::middleware('throttle:300,1')->group(function () {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('brands', [BrandController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id');
});

// Back office: the Next.js proxy forwards /api/admin/* to /admin/* here
Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    // One call that loads the whole dashboard
    Route::get('overview', [DashboardController::class, 'index']);

    Route::get('categories', [CategoryController::class, 'adminIndex']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('products', [ProductController::class, 'adminIndex']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::post('products/{product}/stock', [StockController::class, 'adjust']);

    Route::post('uploads', [UploadController::class, 'store']);

    Route::get('admins', [AdminController::class, 'index']);
    Route::post('admins', [AdminController::class, 'store']);
    Route::put('admins/{admin}', [AdminController::class, 'update']);
    Route::delete('admins/{admin}', [AdminController::class, 'destroy']);

    Route::get('sales', [SaleController::class, 'index']);
    Route::post('sales', [SaleController::class, 'store']);

    Route::get('logs', [LogController::class, 'index']);

    Route::get('brands', [BrandController::class, 'adminIndex']);
    Route::post('brands', [BrandController::class, 'store']);
    Route::put('brands/{brand}', [BrandController::class, 'update']);
    Route::delete('brands/{brand}', [BrandController::class, 'destroy']);

    // Developer dashboard: super admin only
    Route::prefix('system')->middleware('super')->group(function () {
        Route::get('overview', [SystemController::class, 'overview']);
        Route::post('prune-images', [SystemController::class, 'pruneImages']);
        Route::delete('errors', [SystemController::class, 'clearErrors']);
    });
});