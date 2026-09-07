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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rotas Autenticadas
Route::middleware('api.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Perfil e CPF
    Route::put('/user/profile', function (Request $request) {
        $user = $request->user();
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'cpf'   => 'nullable|string|max:14',
        ]);
        $user->update($validated);
        return response()->json(['message' => 'Perfil atualizado com sucesso!', 'user' => $user]);
    });

    // Alteração de Senha
    Route::put('/user/password', function (Request $request) {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'A senha atual está incorreta.'], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso!']);
    });

    // Moderação de Lojistas pelo ADMIN
    Route::get('/admin/sellers/pending', function (Request $request) {
        if ($request->user()->role !== 'ADMIN') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }
        return User::where('role', 'SELLER')
            ->where('is_approved', false)
            ->select('id', 'name', 'email', 'created_at')
            ->latest()
            ->get();
    });

    Route::post('/admin/sellers/{id}/approve', function (Request $request, $id) {
        if ($request->user()->role !== 'ADMIN') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }
        $seller = User::where('role', 'SELLER')->findOrFail($id);
        $seller->is_approved = true;
        $seller->save();

        return response()->json(['message' => "Vendedor {$seller->name} aprovado com sucesso!"]);
    });

    // Operações de Produtos e Vendas (SELLER e ADMIN)
    Route::middleware('api.auth:SELLER,ADMIN')->group(function () {
        Route::get('/seller/metrics', [SellerDashboardController::class, 'metrics']);
        Route::get('/seller/orders', [SellerDashboardController::class, 'orders']);
        Route::post('/seller/orders/{id}/dispatch', [SellerDashboardController::class, 'dispatchOrder']);

        Route::get('/seller/products', [SellerProductController::class, 'index']);
        Route::post('/seller/products', [SellerProductController::class, 'store']);
        Route::put('/seller/products/{id}', [SellerProductController::class, 'update']);
        Route::delete('/seller/products/{id}', [SellerProductController::class, 'destroy']);
    });
});
