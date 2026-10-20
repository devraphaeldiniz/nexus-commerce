<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerificationOtpMail;
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
            'otp_attempts'                  => 0,
            'otp_last_sent_at'              => now(),
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

        if (!$user || !$user->email_verification_expires_at || $user->email_verification_expires_at->isPast()) {
            return response()->json(['message' => 'Código de verificação incorreto ou expirado'], 422);
        }

        if ((int) ($user->otp_attempts ?? 0) >= 5) {
            return response()->json(['message' => 'Muitas tentativas. Solicite um novo código.'], 429);
        }
        if (! Hash::check((string) $request->code, (string) $user->email_verification_code)) {
            $user->increment('otp_attempts');
            return response()->json(['message' => 'Código de verificação incorreto ou expirado'], 422);
        }

        $token = Str::random(64);

        $user->update([
            'email_verification_code'       => null,
            'email_verification_expires_at' => null,
            'otp_attempts'                 => 0,
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

        if ($user->otp_last_sent_at && $user->otp_last_sent_at->gt(now()->subMinute())) {
            return response()->json(['message' => 'Aguarde um minuto antes de solicitar outro código.'], 429);
        }
        $code = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'email_verification_code'       => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
            'otp_attempts'                  => 0,
            'otp_last_sent_at'              => now(),
            'otp_attempts'                  => 0,
            'otp_last_sent_at'              => now(),
        ]);

        $this->sendOtpEmail($user->email, $code, 'Reenvio de Código');

        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, um novo código será enviado.',
        ]);
    }

    protected function sendOtpEmail(string $recipient, string $code, string $actionContext = 'Nexus Commerce'): void
    {
        Log::info('Solicitação de OTP processada', ['context' => $actionContext]);

        try {
            Mail::to($recipient)->send(new EmailVerificationOtpMail($recipient, $code));
            Log::info('E-mail OTP enviado via mailer configurado', ['recipient' => $recipient]);
        } catch (\Throwable $e) {
            Log::error('Falha no envio do e-mail OTP: '.$e->getMessage());
        }
    }
}
