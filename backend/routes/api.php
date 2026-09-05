<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\LogisticsHubController;
use App\Http\Controllers\OrderLifecycleController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerOnboardingController;
use App\Http\Controllers\ShippingController;
use Illuminate\Support\Facades\Route;

// 1. Autenticação & Cadastro
Route::post('/auth/register', [AuthController::class, 'registerCustomer']);
Route::post('/auth/login', [AuthController::class, 'login']);

// 2. Catálogo Público
Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/products/{id}', [CatalogController::class, 'showProduct']);
Route::post('/shipping/quote', [ShippingController::class, 'quote']);
Route::post('/checkout', [CheckoutController::class, 'process']);
Route::post('/sellers/register', [SellerOnboardingController::class, 'register']);

// 3. Rotas Autenticadas com RBAC
Route::middleware('api.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/auth/resend-code', [AuthController::class, 'resendVerificationCode']);

    // Gestão de Perfil, Senha, 2FA e Cartões Salvos
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/2fa/setup', [ProfileController::class, 'setupTwoFactor']);
    Route::post('/profile/2fa/enable', [ProfileController::class, 'enableTwoFactor']);
    Route::post('/profile/2fa/disable', [ProfileController::class, 'disableTwoFactor']);
    Route::get('/profile/cards', [ProfileController::class, 'listSavedCards']);
    Route::post('/profile/cards', [ProfileController::class, 'storeSavedCard']);
    Route::delete('/profile/cards/{id}', [ProfileController::class, 'deleteSavedCard']);

    // Vendedor & Admin
    Route::middleware('api.auth:SELLER,ADMIN')->group(function () {
        Route::get('/sellers/{id}/dashboard', [SellerDashboardController::class, 'show']);
        Route::post('/sellers/{id}/payout', [PayoutController::class, 'requestPayout']);
    });

    // Logística & Admin
    Route::middleware('api.auth:LOGISTICS,ADMIN')->group(function () {
        Route::get('/logistics/queue', [LogisticsHubController::class, 'queue']);
        Route::post('/logistics/orders/{id}/prepare', [LogisticsHubController::class, 'markInPreparation']);
        Route::post('/orders/{id}/ship', [ShippingController::class, 'shipOrder']);
        Route::post('/orders/{id}/deliver', [OrderLifecycleController::class, 'markAsDelivered']);
    });

    // Admin
    Route::middleware('api.auth:ADMIN')->group(function () {
        Route::get('/sellers', [SellerDashboardController::class, 'listSellers']);
        Route::post('/sellers/{id}/kyc-transition', [SellerOnboardingController::class, 'transitionKyc']);
        Route::post('/disputes/{id}/resolve', [DisputeController::class, 'resolve']);
    });

    // Mediação & Disputas
    Route::post('/disputes/open', [DisputeController::class, 'open']);
    Route::get('/disputes/{id}', [DisputeController::class, 'show']);
    Route::post('/disputes/{id}/messages', [DisputeController::class, 'postMessage']);
});
