<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $isSeller = $request->input('role') === 'SELLER';

        $rules = [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:6',
            'role'       => 'nullable|string|in:CUSTOMER,SELLER',
        ];

        if ($isSeller) {
            $rules['document']   = 'required|string|min:14|max:18';
            $rules['store_name'] = 'required|string|max:255';
        } else {
            $rules['document']   = 'required|string|min:11|max:14';
        }

        $validated = $request->validate($rules);
        $role = $validated['role'] ?? 'CUSTOMER';
        $code = sprintf('%06d', random_int(100000, 999999));
        $cleanDoc = preg_replace('/\D/', '', $validated['document']);

        $user = User::create([
            'name'                          => $validated['name'],
            'email'                         => $validated['email'],
            'password'                      => Hash::make($validated['password']),
            'role'                          => $role,
            'cpf'                           => !$isSeller ? $cleanDoc : null,
            'email_verification_code'       => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
            'is_approved'                   => $role === 'CUSTOMER',
        ]);

        if ($role === 'SELLER') {
            SellerProfile::create([
                'user_id'         => $user->id,
                'store_name'      => $validated['store_name'],
                'legal_name'      => $validated['name'],
                'document_type'   => 'CNPJ',
                'document_number' => $cleanDoc,
            ]);
        }

        $this->sendOtpEmail($user->email, $code, 'Cadastro Nexus');

        return response()->json([
            'requires_verification' => true,
            'email'                 => $user->email,
            'message'               => 'Conta criada! Digite o código de 6 dígitos enviado ao seu e-mail.',
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $code = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'email_verification_code'       => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendOtpEmail($user->email, $code, 'Login Nexus');

        return response()->json([
            'requires_verification' => true,
            'email'                 => $user->email,
            'message'               => 'Código enviado ao seu e-mail.',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || (string)$user->email_verification_code !== (string)$request->code || !$user->email_verification_expires_at || $user->email_verification_expires_at->isPast()) {
            return response()->json(['message' => 'Código de verificação incorreto ou expirado'], 422);
        }

        $token = Str::random(64);

        $user->update([
            'email_verification_code'       => null,
            'email_verification_expires_at' => null,
            'email_verified_at'             => $user->email_verified_at ?? now(),
            'api_token'                     => hash('sha256', $token),
            'api_token_expires_at'          => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Autenticação concluída com sucesso!',
            'user'    => $user,
        ])->withCookie(cookie('nexus_auth', $token, 60 * 24 * 30, '/', config('session.domain'), true, true, false, config('session.same_site', 'lax')));
    }

    public function resendCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Se o e-mail estiver cadastrado, um novo código será enviado.']);
        }

        $code = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'email_verification_code'       => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendOtpEmail($user->email, $code, 'Reenvio de Código');

        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, um novo código será enviado.',
        ]);
    }

    protected function sendOtpEmail(string $recipient, string $code, string $actionContext = 'Nexus Commerce'): void
    {
        Log::info('Solicitação de OTP processada', ['context' => $actionContext]);

        $apiKey = env('BREVO_API_KEY');
        $senderEmail = 'fareusaloisio@gmail.com';

        if (!$apiKey) {
            Log::warning('BREVO_API_KEY não configurada.');
            return;
        }

        try {
            $response = Http::withHeaders([
                'api-key'      => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender'      => ['name' => 'Nexus Commerce', 'email' => $senderEmail],
                'to'          => [['email' => $recipient]],
                'subject'     => "Nexus Commerce: Código de Acesso [{$code}]",
                'htmlContent' => "
                    <div style='font-family: Arial, sans-serif; background: #0f172a; color: #f8fafc; padding: 28px; border-radius: 12px; max-width: 480px;'>
                        <h2 style='color: #818cf8; margin-bottom: 8px;'>Nexus Commerce</h2>
                        <p style='color: #cbd5e1; font-size: 14px;'>Seu código de acesso para <strong>{$actionContext}</strong> é:</p>
                        <div style='background: #1e293b; padding: 16px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #38bdf8;'>{$code}</span>
                        </div>
                        <p style='color: #94a3b8; font-size: 12px;'>Este código é válido por 15 minutos.</p>
                    </div>",
            ]);

            if ($response->failed()) {
                Log::error('Falha no envio via Brevo: ' . $response->body());
            } else {
                Log::info('E-mail OTP enviado com sucesso via Brevo para: ' . $recipient);
            }
        } catch (\Throwable $e) {
            Log::error('Exceção ao disparar via Brevo: ' . $e->getMessage());
        }
    }
}
