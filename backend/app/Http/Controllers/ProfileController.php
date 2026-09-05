<?php

namespace App\Http\Controllers;

use App\Models\UserSavedCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // 1. Atualização de Dados Pessoais & Senha
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'current_password' => 'nullable|string|required_with:new_password',
            'new_password'     => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $validated['name'];

        if (! empty($validated['new_password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return response()->json(['message' => 'A senha atual informada está incorreta.'], 422);
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user'    => $user->fresh(['sellerProfile', 'customerProfile']),
        ]);
    }

    // 2. Geração da chave 2FA para escaneamento no App
    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Gera segredo Base32 de 16 caracteres
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $base32Chars[random_int(0, 31)];
        }

        // Gera 6 códigos de recuperação descartáveis
        $recoveryCodes = [];
        for ($i = 0; $i < 6; $i++) {
            $recoveryCodes[] = strtoupper(Str::random(10));
        }

        // Salva temporariamente
        $user->update([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        // URL padrão otpauth:// para escaneamento com apps autenticadores
        $otpUrl = "otpauth://totp/NexusCommerce:{$user->email}?secret={$secret}&issuer=NexusCommerce";
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpUrl);

        return response()->json([
            'secret'         => $secret,
            'qr_code_url'    => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    // 3. Confirmar e Ativar 2FA
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! AuthController::verifyTotp($user->two_factor_secret, $validated['code'])) {
            return response()->json(['message' => 'Código de 6 dígitos inválido ou expirado.'], 422);
        }

        $user->update(['two_factor_enabled' => true]);

        return response()->json([
            'message'            => 'Autenticação em dois fatores (2FA) ativada com sucesso!',
            'two_factor_enabled' => true,
        ]);
    }

    // 4. Desativar 2FA
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Senha incorreta para desativar o 2FA.'], 422);
        }

        $user->update([
            'two_factor_enabled'        => false,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'message'            => 'Autenticação em dois fatores desativada.',
            'two_factor_enabled' => false,
        ]);
    }

    // 5. Gestão de Cartões Cadastrados (Wallet do Usuário)
    public function listSavedCards(Request $request): JsonResponse
    {
        return response()->json($request->user()->savedCards);
    }

    public function storeSavedCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_holder_name' => 'required|string|max:100',
            'card_number'      => 'required|string|min:13|max:19',
            'exp_month'        => 'required|string|size:2',
            'exp_year'         => 'required|string|min:2|max:4',
            'card_brand'       => ['required', Rule::in(['VISA', 'MASTERCARD', 'ELO', 'AMEX', 'HIPERCARD'])],
        ]);

        $cleanNumber = preg_replace('/\D/', '', $validated['card_number']);
        $lastFour = substr($cleanNumber, -4);

        $user = $request->user();
        $isFirstCard = $user->savedCards()->count() === 0;

        $savedCard = UserSavedCard::create([
            'user_id'            => $user->id,
            'card_holder_name'   => strtoupper($validated['card_holder_name']),
            'card_brand'         => $validated['card_brand'],
            'last_four'          => $lastFour,
            'exp_month'          => str_pad($validated['exp_month'], 2, '0', STR_PAD_LEFT),
            'exp_year'           => strlen($validated['exp_year']) === 2 ? '20' . $validated['exp_year'] : $validated['exp_year'],
            'gateway_card_token' => 'tok_' . Str::uuid(),
            'is_default'         => $isFirstCard,
        ]);

        return response()->json([
            'message' => 'Cartão cadastrado com sucesso!',
            'card'    => $savedCard,
        ], 201);
    }

    public function deleteSavedCard(Request $request, string $cardId): JsonResponse
    {
        $card = $request->user()->savedCards()->where('id', $cardId)->first();

        if (! $card) {
            return response()->json(['message' => 'Cartão não encontrado.'], 404);
        }

        $card->delete();

        return response()->json(['message' => 'Cartão removido com sucesso.']);
    }
}
