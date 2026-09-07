<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\ShippingController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

// Rotas Públicas
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', function () {
    return response()->json(Category::orderBy('name')->get());
});
Route::post('/shipping/quote', [ShippingController::class, 'calculate']);
Route::post('/checkout', [CheckoutController::class, 'process']);
Route::get('/customer/orders', [CustomerOrderController::class, 'index']);
Route::get('/customer/orders/{id}', [CustomerOrderController::class, 'show']);

// Autenticação
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rotas Autenticadas
Route::middleware('api.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Portal do Vendedor (SELLER / ADMIN)
    Route::middleware('api.auth:SELLER,ADMIN')->group(function () {
        Route::get('/seller/metrics', [SellerDashboardController::class, 'metrics']);
        Route::get('/seller/orders', [SellerDashboardController::class, 'orders']);
        Route::post('/seller/orders/{id}/dispatch', [SellerDashboardController::class, 'dispatchOrder']);

        // Gestão de Produtos
        Route::get('/seller/products', [SellerProductController::class, 'index']);
        Route::post('/seller/products', [SellerProductController::class, 'store']);
        Route::put('/seller/products/{id}', [SellerProductController::class, 'update']);
        Route::delete('/seller/products/{id}', [SellerProductController::class, 'destroy']);
    });
});
