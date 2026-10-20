<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\OrderLifecycleController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\ShippingController;
use App\Models\Category;
use App\Models\User;
use App\Models\PaymentWebhookEvent;
use App\Services\AsaasService;
use App\Services\PaymentService;
use App\Services\OrderTransitionService;
use App\Enums\OrderStatus;
use App\Services\ResendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

// Token CSRF para clientes SPA (webhooks permanecem fora deste fluxo)
Route::get('/csrf-token', function (Request $request) {
    return \App\Http\Middleware\VerifyApiCsrf::issue(response()->json(['csrf_token' => $request->cookie('nexus_csrf') ?: '']));
});

// Rotas Públicas
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'service' => 'nexus-commerce']);
});
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', function () {
    return response()->json(Category::orderBy('name')->get());
});
Route::post('/shipping/quote', [ShippingController::class, 'calculate']);
Route::middleware('api.auth')->group(function () {
    Route::get('/customer/orders', [CustomerOrderController::class, 'index']);
    Route::get('/customer/orders/{id}', [CustomerOrderController::class, 'show']);
});

// Autenticação & OTP
Route::middleware('throttle:10,1')->group(function () {
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyCode']);

Route::post('/auth/resend-code', [AuthController::class, 'resendCode']);
});

// CHECKOUT ASAAS (PIX & CARTÃO)
Route::post('/checkout', [CheckoutController::class, 'process'])
    ->middleware(['api.auth', 'throttle:20,1']);
Route::middleware('api.auth')->post('/checkout/asaas/pix', function (Request $request) {
    $validated = $request->validate([
        'order_id' => 'required|uuid|exists:orders,id',
        'name'   => 'required|string|max:255',
        'email'  => 'required|email',
        'cpf'    => 'required|string',
    ]);
    $order = \App\Models\Order::whereKey($validated['order_id'])->where('user_id', $request->user()->id)->firstOrFail();
    if ($order->gateway_payment_id) return response()->json(['payment_id' => $order->gateway_payment_id, 'status' => 'PENDING']);

    try {
        return response()->json(app(PaymentService::class)->createPix($order, $validated));
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
});

Route::middleware('api.auth')->post('/checkout/asaas/card', function (Request $request) {
    $validated = $request->validate([
        'order_id'    => 'required|uuid|exists:orders,id',
        'name'        => 'required|string|max:255',
        'email'       => 'required|email',
        'cpf'         => 'required|string',
        'card_token'       => 'required|string|max:255',
        'card'             => 'prohibited',
        'card.postal_code'  => 'nullable|string',
    ]);

    $order = \App\Models\Order::whereKey($validated['order_id'])->where('user_id', $request->user()->id)->firstOrFail();
    if ($order->gateway_payment_id) return response()->json(['payment_id' => $order->gateway_payment_id, 'status' => 'PENDING', 'order_id' => $order->id]);

    try {
        return response()->json(app(PaymentService::class)->createCard($order, $validated));
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
});

// WEBHOOK DO ASAAS
Route::post('/webhooks/asaas', function (Request $request) {
    $configuredToken = config('services.asaas.webhook_token') ?: env('ASAAS_WEBHOOK_TOKEN');
    $providedToken = $request->header('asaas-access-token');
    if (! $configuredToken || ! $providedToken || ! hash_equals($configuredToken, $providedToken)) {
        return response()->json(['message' => 'Não autorizado.'], 401);
    }

    $event = $request->input('event');
    $payment = $request->input('payment');
    if (! is_string($event) || ! is_array($payment) || empty($payment['id'])) {
        return response()->json(['message' => 'Payload de webhook inválido.'], 422);
    }

    $paymentId = $payment['id'] ?? null;
    $eventKey = hash('sha256', 'asaas|' . ($request->header('asaas-event-id') ?: ($event . '|' . ($paymentId ?: hash('sha256', $request->getContent())))));
    $order = $paymentId ? \App\Models\Order::where('gateway_payment_id', $paymentId)->first() : null;
    // Claim the event before any external call: retries of a completed event must
    // be safe when Asaas is unavailable, while new events still require reconciliation.
    try {
        $webhook = PaymentWebhookEvent::firstOrCreate(
            ['event_key' => $eventKey],
            ['event' => $event, 'payment_id' => $paymentId, 'order_id' => $order?->id, 'payload' => $request->all()]
        );
    } catch (\Illuminate\Database\QueryException $e) {
        $webhook = PaymentWebhookEvent::where('event_key', $eventKey)->firstOrFail();
    }
    if ($webhook->processed_at) {
        return response()->json(['status' => 'ALREADY_PROCESSED']);
    }

    // Never trust the webhook alone: reconcile new events directly with Asaas.
    try {
        $remotePayment = AsaasService::getPayment($paymentId);
    } catch (\Throwable $e) {
        Log::warning('Falha na reconciliação Asaas', ['payment_id' => $paymentId]);
        return response()->json(['message' => 'Reconciliação indisponível.'], 503);
    }
    $remoteStatus = $remotePayment['status'] ?? null;
    Log::info('Webhook Asaas recebido', ['event' => $event, 'payment_id' => $paymentId]);

    if (in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)
        && in_array($remoteStatus, ['RECEIVED', 'CONFIRMED'], true)) {
        Log::info("Pagamento {$paymentId} CONFIRMADO no Asaas! Liberando pedido em custódia (Escrow).");
        // Atualiza status caso esteja atrelado à tabela orders
        if ($paymentId && Schema::hasTable('orders')) {
            DB::transaction(function () use ($paymentId) {
                $order = \App\Models\Order::where('gateway_payment_id', $paymentId)
                    ->where('status', 'PENDING')->lockForUpdate()->first();
                if ($order) {
                    app(OrderTransitionService::class)->transition($order, OrderStatus::PAID);
                    $order->update(['paid_at' => now()]);
                }
            });
        }
    }

    $webhook->update(['processed_at' => now()]);
    return response()->json(['status' => 'SUCCESS']);
});

// Rotas Autenticadas
Route::middleware(['api.auth', 'api.csrf'])->group(function () {
    Route::get('/auth/me', fn(Request $r) => response()->json($r->user()));
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::get('/profile/cards', [ProfileController::class, 'listSavedCards']);
    Route::post('/profile/cards', [ProfileController::class, 'storeSavedCard']);
    Route::delete('/profile/cards/{cardId}', [ProfileController::class, 'deleteSavedCard']);
    Route::post('/profile/2fa/setup', [ProfileController::class, 'setupTwoFactor']);
    Route::post('/profile/2fa/enable', [ProfileController::class, 'enableTwoFactor']);
    Route::post('/profile/2fa/disable', [ProfileController::class, 'disableTwoFactor']);
    Route::post('/disputes/open', [DisputeController::class, 'open']);
    Route::post('/disputes/{id}/messages', [DisputeController::class, 'postMessage']);
    Route::post('/disputes/{id}/resolve', [DisputeController::class, 'resolve']);
    Route::post('/orders/{id}/deliver', [OrderLifecycleController::class, 'markAsDelivered']);
    Route::post('/auth/logout', function (Request $r) {
        $u = $r->user();
        if ($u) {
            $u->remember_token = null;
            $u->api_token = null;
            $u->api_token_expires_at = null;
            $u->save();
        }
        return response()->json(['message' => 'Logout concluído'])
            ->withCookie(cookie()->forget('nexus_auth', '/', config('session.domain')));
    });

    Route::post('/user/request-email-change', function (Request $request) {
        $request->validate(['new_email' => 'required|email|unique:users,email']);
        ResendService::generateOtp($request->new_email);
        return response()->json(['message' => "Código enviado para {$request->new_email}."]);
    });

    Route::post('/user/confirm-email-change', function (Request $request) {
        $request->validate([
            'new_email' => 'required|email|unique:users,email',
            'code'      => 'required|string|size:6',
        ]);

        $record = DB::table('email_verifications')
            ->where('email', $request->new_email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) return response()->json(['message' => 'Código inválido ou expirado.'], 422);

        $user = $request->user();
        $user->email = $request->new_email;
        $user->email_verified_at = now();
        $user->save();

        DB::table('email_verifications')->where('email', $request->new_email)->delete();

        return response()->json(['message' => 'E-mail alterado com sucesso!', 'user' => $user]);
    });

    Route::put('/user/profile', function (Request $request) {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cpf'  => 'nullable|string|max:14',
        ]);
        $user->name = $validated['name'];
        if (isset($validated['cpf'])) $user->cpf = $validated['cpf'];
        $user->save();
        return response()->json(['message' => 'Perfil atualizado com sucesso!', 'user' => $user]);
    });

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

    Route::get('/admin/sellers/pending', function (Request $request) {
        if ($request->user()->role !== 'ADMIN') return response()->json(['message' => 'Acesso negado.'], 403);
        return User::where('role', 'SELLER')->where('is_approved', false)->select('id', 'name', 'email', 'created_at')->latest()->get();
    });

    Route::post('/admin/sellers/{id}/approve', function (Request $request, $id) {
        if ($request->user()->role !== 'ADMIN') return response()->json(['message' => 'Acesso negado.'], 403);
        $seller = User::where('role', 'SELLER')->findOrFail($id);
        $seller->is_approved = true;
        $seller->save();
        return response()->json(['message' => "Vendedor {$seller->name} aprovado!"]);
    });

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
