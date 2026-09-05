<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationOtpMail;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Rules\ValidTaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function registerCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'cpf'        => ['required', 'string', new ValidTaxDocument('CPF'), 'unique:customer_profiles,cpf'],
            'phone'      => 'required|string|min:10|max:20',
            'birth_date' => 'nullable|date',
        ]);

        $cleanCpf = preg_replace('/\D/', '', $validated['cpf']);
        $cleanPhone = preg_replace('/\D/', '', $validated['phone']);
        $otpCode = (string) random_int(100000, 999999);

        // Remove registros pendentes expirados ou anteriores para o mesmo e-mail/CPF
        DB::table('pending_registrations')
            ->where('email', $validated['email'])
            ->orWhere('cpf', $cleanCpf)
            ->delete();

        // 1. Grava os dados temporariamente com expiração em 10 minutos
        $pendingId = (string) Str::uuid();
        DB::table('pending_registrations')->insert([
            'id'                => $pendingId,
            'email'             => $validated['email'],
            'name'              => $validated['name'],
            'cpf'               => $cleanCpf,
            'phone'             => $cleanPhone,
            'birth_date'        => $validated['birth_date'] ?? null,
            'password'          => Hash::make($validated['password']),
            'verification_code' => $otpCode,
            'expires_at'        => now()->addMinutes(10),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 2. Dispara e-mail real via SMTP para a caixa postal
        try {
            Mail::to($validated['email'])->send(new EmailVerificationOtpMail($validated['name'], $otpCode));
        } catch (\Exception $e) {
            // Em caso de falha de conexão SMTP
        }

        return response()->json([
            'message'    => 'Código de verificação enviado para seu e-mail. Você tem 10 minutos para concluir.',
            'pending_id' => $pendingId,
            'email'      => $validated['email'],
            'expires_in' => 600, // 10 minutos em segundos
        ], 200);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $pending = DB::table('pending_registrations')->where('email', $validated['email'])->first();

        if (! $pending) {
            return response()->json([
                'message' => 'Nenhum cadastro pendente encontrado para este e-mail. Por favor, cadastre-se novamente.',
            ], 404);
        }

        // Validação de expiração estrita de 10 minutos
        if (now()->isAfter($pending->expires_at)) {
            DB::table('pending_registrations')->where('id', $pending->id)->delete();
            return response()->json([
                'message' => 'O prazo de 10 minutos expirou. Por segurança, seus dados foram descartados. Cadastre-se novamente.',
                'expired' => true,
            ], 410);
        }

        if ($pending->verification_code !== $validated['code']) {
            return response()->json(['message' => 'Código de verificação incorreto.'], 422);
        }

        // 3. Código válido dentro do prazo: Efetiva a criação da conta definitiva
        $plainToken = Str::random(64);

        $user = DB::transaction(function () use ($pending, $plainToken) {
            $user = User::create([
                'name'                 => $pending->name,
                'email'                => $pending->email,
                'email_verified_at'    => now(),
                'role'                 => 'CUSTOMER',
                'password'             => $pending->password,
                'api_token'            => hash('sha256', $plainToken),
                'api_token_expires_at' => now()->addHours(8),
            ]);

            CustomerProfile::create([
                'user_id'         => $user->id,
                'full_name'       => $pending->name,
                'document_number' => $pending->cpf,
                'cpf'             => $pending->cpf,
                'phone'           => $pending->phone,
                'birth_date'      => $pending->birth_date,
            ]);

            // Remove o registro temporário
            DB::table('pending_registrations')->where('id', $pending->id)->delete();

            return $user->load('customerProfile');
        });

        return response()->json([
            'message' => 'Conta criada e ativada com sucesso!',
            'token'   => $plainToken,
            'user'    => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'email_verified'   => true,
                'role'             => $user->role,
                'customer_profile' => $user->customerProfile,
            ],
        ], 201);
    }

    public function resendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $pending = DB::table('pending_registrations')->where('email', $validated['email'])->first();

        if (! $pending) {
            return response()->json(['message' => 'Cadastro pendente não encontrado ou já expirado.'], 404);
        }

        $newCode = (string) random_int(100000, 999999);

        DB::table('pending_registrations')->where('id', $pending->id)->update([
            'verification_code' => $newCode,
            'expires_at'        => now()->addMinutes(10), // Renova mais 10 minutos
            'updated_at'        => now(),
        ]);

        try {
            Mail::to($pending->email)->send(new EmailVerificationOtpMail($pending->name, $newCode));
        } catch (\Exception $e) {}

        return response()->json([
            'message'    => 'Novo código de ativação enviado para sua caixa de entrada.',
            'expires_in' => 600,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
            'totp_code' => 'nullable|string|size:6',
        ]);

        $user = User::with(['sellerProfile', 'customerProfile'])->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'E-mail ou senha incorretos.'], 401);
        }

        if ($user->two_factor_enabled) {
            if (empty($validated['totp_code'])) {
                return response()->json([
                    'requires_two_factor' => true,
                    'message'             => 'Digite o código de 6 dígitos do seu app autenticador.',
                ], 200);
            }

            if (! self::verifyTotp($user->two_factor_secret, $validated['totp_code'])) {
                return response()->json(['message' => 'Código de autenticação inválido.'], 422);
            }
        }

        $plainToken = Str::random(64);
        $user->forceFill([
            'api_token'            => hash('sha256', $plainToken),
            'api_token_expires_at' => now()->addHours(8),
        ])->save();

        return response()->json([
            'token'      => $plainToken,
            'expires_in' => 28800,
            'user'       => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'email_verified'     => $user->isEmailVerified(),
                'role'               => $user->role,
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
                'seller_profile'     => $user->sellerProfile,
                'customer_profile'   => $user->customerProfile,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->forceFill([
                'api_token'            => null,
                'api_token_expires_at' => null,
            ])->save();
        }

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['sellerProfile', 'customerProfile', 'savedCards']);

        return response()->json([
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'email_verified'     => $user->isEmailVerified(),
            'role'               => $user->role,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'seller_profile'     => $user->sellerProfile,
            'customer_profile'   => $user->customerProfile,
            'saved_cards'        => $user->savedCards,
        ]);
    }

    public static function verifyTotp(?string $secret, string $code): bool
    {
        if (! $secret) return false;
        $timeSlice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $calc = self::calculateTotp($secret, $timeSlice + $i);
            if (hash_equals($calc, str_pad($code, 6, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    public static function calculateTotp(string $secret, int $timeSlice): string
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $binary = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($base32Chars, $char);
            if ($pos !== false) {
                $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $secretKey = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $secretKey .= chr(bindec($byte));
            }
        }
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $value = unpack('N', substr($hmac, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
