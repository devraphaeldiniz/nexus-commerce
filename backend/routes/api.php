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
use App\Services\ResendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

// Rotas Públicas
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
Route::post('/legacy/register', function (Request $request) {
    $validated = $request->validate([
        'name'       => 'required|string|max:255',
        'email'      => 'required|email|unique:users,email',
        'password'   => 'required|string|min:6',
        'role'       => 'nullable|string|in:CUSTOMER,SELLER',
        'store_name' => 'nullable|string|max:255',
    ]);

    $role = $validated['role'] ?? 'CUSTOMER';
    $isApproved = ($role === 'CUSTOMER');

    $user = User::create([
        'name'              => $validated['name'],
        'email'             => $validated['email'],
        'password'          => Hash::make($validated['password']),
        'role'              => $role,
        'is_approved'       => $isApproved,
        'email_verified_at' => null,
    ]);

    if ($role === 'SELLER') {
        \App\Models\SellerProfile::create([
            'user_id'    => $user->id,
            'store_name' => $validated['store_name'] ?? "Loja de {$user->name}",
            'status'     => 'PENDING_APPROVAL',
        ]);
    }

    ResendService::generateOtp($user->email);

    return response()->json([
        'message'               => 'Conta criada! Enviamos um código de verificação para o seu e-mail.',
        'requires_verification' => true,
        'email'                 => $user->email,
    ], 201);
});

Route::post('/auth/verify-email', function (Request $request) {
    return app(AuthController::class)->verifyCode($request);
});

Route::post('/legacy/auth/verify-code', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'code'  => 'required|string|size:6',
    ]);

    $record = DB::table('email_verifications')
        ->where('email', $request->email)
        ->where('code', $request->code)
        ->where('expires_at', '>', now())
        ->first();

    if (!$record) {
        return response()->json(['message' => 'Código inválido ou expirado.'], 422);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $user->email_verified_at = now();
    $user->save();

    DB::table('email_verifications')->where('email', $request->email)->delete();

    $token = bin2hex(random_bytes(32));
    $user->remember_token = $token;
    $user->save();

    return response()->json([
        'message' => 'E-mail verificado com sucesso!',
        'user'    => $user,
        'token'   => $token,
    ]);
});

Route::post('/auth/resend-code', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $user = User::where('email', $request->email)->firstOrFail();
    ResendService::generateOtp($user->email);
    return response()->json(['message' => 'Novo código de verificação enviado!']);
});

Route::post('/legacy/login', function (Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Credenciais inválidas.'], 401);
    }

    if ($user->role !== 'ADMIN' && !$user->email_verified_at) {
        ResendService::generateOtp($user->email);
        return response()->json([
            'message'               => 'Seu e-mail ainda não foi verificado. Enviamos um novo código.',
            'requires_verification' => true,
            'email'                 => $user->email,
        ], 403);
    }

    $token = bin2hex(random_bytes(32));
    $user->remember_token = $token;
    $user->save();

    if ($user->role === 'ADMIN') {
        $ip = $request->ip();
        $date = now()->format('d/m/Y H:i:s');
        $htmlAlert = "
        <div style='font-family: Arial, sans-serif; background-color: #0c1222; color: #f8fafc; padding: 25px; border-radius: 10px; max-width: 500px;'>
            <h3 style='color: #f43f5e;'>🛡️ Alerta de Segurança Nexus Commerce</h3>
            <p>Um login administrativo foi realizado na sua conta:</p>
            <ul>
                <li><strong>E-mail:</strong> {$user->email}</li>
                <li><strong>Data/Hora:</strong> {$date}</li>
                <li><strong>IP de Origem:</strong> {$ip}</li>
            </ul>
        </div>";
        ResendService::sendEmail($user->email, '⚠️ Alerta de Segurança: Login de Administrador', $htmlAlert);
    }

    return response()->json([
        'message' => 'Autenticado com sucesso!',
        'user'    => $user,
        'token'   => $token,
    ]);
});
});

// CHECKOUT ASAAS (PIX & CARTÃO)
Route::post('/checkout', [CheckoutController::class, 'process'])
    ->middleware(['api.auth', 'throttle:20,1']);
Route::middleware('api.auth')->post('/checkout/asaas/pix', function (Request $request) {
    $validated = $request->validate([
        'amount' => 'required|numeric|min:1',
        'name'   => 'required|string',
        'email'  => 'required|email',
        'cpf'    => 'required|string',
    ]);

    try {
        $pix = AsaasService::createPixPayment(
            $validated['amount'],
            $validated['name'],
            $validated['email'],
            $validated['cpf'],
            'Compra Nexus Marketplace'
        );
        return response()->json($pix);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
});

Route::middleware('api.auth')->post('/checkout/asaas/card', function (Request $request) {
    $validated = $request->validate([
        'amount'      => 'required|numeric|min:1',
        'name'        => 'required|string',
        'email'       => 'required|email',
        'cpf'         => 'required|string',
        'card_token'       => 'required|string|max:255',
        'card'             => 'prohibited',
        'card.postal_code'  => 'nullable|string',
    ]);

    try {
        $result = AsaasService::createCreditCardPayment(
            $validated['amount'],
            $validated['name'],
            $validated['email'],
            $validated['cpf'],
            ['token' => $validated['card_token']]
        );
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
});

// WEBHOOK DO ASAAS
Route::post('/webhooks/asaas', function (Request $request) {
    $configuredToken = config('services.asaas.webhook_token') ?: env('ASAAS_WEBHOOK_TOKEN');
    $providedToken = $request->header('asaas-access-token');
    if ($configuredToken && (! $providedToken || ! hash_equals($configuredToken, $providedToken))) {
        return response()->json(['message' => 'Não autorizado.'], 401);
    }

    $event = $request->input('event');
    $payment = $request->input('payment');

    $paymentId = $payment['id'] ?? null;
    $eventKey = hash('sha256', 'asaas|' . ($request->header('asaas-event-id') ?: ($event . '|' . ($paymentId ?: hash('sha256', $request->getContent())))));
    $webhook = PaymentWebhookEvent::firstOrCreate(
        ['event_key' => $eventKey],
        ['event' => $event, 'payment_id' => $paymentId, 'payload' => $request->all()]
    );

    if ($webhook->processed_at) {
        return response()->json(['status' => 'ALREADY_PROCESSED']);
    }

    Log::info('Webhook Asaas recebido', ['event' => $event, 'payment_id' => $paymentId]);

    if (in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
        Log::info("Pagamento {$paymentId} CONFIRMADO no Asaas! Liberando pedido em custódia (Escrow).");
        // Atualiza status caso esteja atrelado à tabela orders
        if ($paymentId && Schema::hasTable('orders')) {
            DB::transaction(function () use ($paymentId) {
                DB::table('orders')
                    ->where('gateway_payment_id', $paymentId)
                    ->whereIn('status', ['PENDING', 'PROCESSING'])
                    ->update(['status' => 'PAID', 'paid_at' => now(), 'updated_at' => now()]);
            });
        }
    }

    $webhook->update(['processed_at' => now()]);
    return response()->json(['status' => 'SUCCESS']);
});

// Rotas Autenticadas
Route::middleware('api.auth')->group(function () {
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
