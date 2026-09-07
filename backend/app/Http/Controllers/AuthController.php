<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'role'            => 'nullable|string|in:CUSTOMER,SELLER',
            'store_name'      => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:30',
        ]);

        $role = strtoupper($validated['role'] ?? 'CUSTOMER');

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $role,
        ]);

        // Se escolheu perfil de vendedor, cria automaticamente o perfil da loja e carteira
        if ($role === 'SELLER') {
            $storeName = ! empty($validated['store_name']) 
                ? $validated['store_name'] 
                : $validated['name'] . ' Store';

            $documentNumber = ! empty($validated['document_number'])
                ? $validated['document_number']
                : '00.' . rand(100, 999) . '.' . rand(100, 999) . '/0001-' . rand(10, 99);

            $profileId = (string) Str::uuid();

            DB::table('seller_profiles')->insert([
                'id'                => $profileId,
                'user_id'           => $user->id,
                'store_name'        => $storeName,
                'legal_name'        => $storeName . ' Comércio e Serviços LTDA',
                'document_type'     => 'CNPJ',
                'document_number'   => $documentNumber,
                'kyc_status'        => 'APPROVED',
                'reputation_score'  => 5.00,
                'total_sales_count' => 0,
                'cancellation_rate' => 0.00,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            if (DB::getSchemaBuilder()->hasTable('seller_wallets')) {
                DB::table('seller_wallets')->insert([
                    'id'                      => (string) Str::uuid(),
                    'seller_profile_id'       => $profileId,
                    'balance_available_cents' => 0,
                    'balance_escrow_cents'    => 0,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }
        }

        $token = $this->issueToken($user);

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'E-mail ou senha incorretos.'], 401);
        }

        $token = $this->issueToken($user);

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            DB::table('users')->where('id', $user->id)->update(['remember_token' => null]);
            if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();
            }
        }
        return response()->json(['message' => 'Desconectado com sucesso.']);
    }

    private function issueToken(User $user): string
    {
        $plainToken = Str::random(64);

        DB::table('users')->where('id', $user->id)->update([
            'remember_token' => $plainToken,
        ]);

        if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->insert([
                'tokenable_type' => get_class($user),
                'tokenable_id'   => $user->id,
                'name'           => 'nexus_api',
                'token'          => hash('sha256', $plainToken),
                'abilities'      => json_encode(['*']),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return $plainToken;
    }
}
