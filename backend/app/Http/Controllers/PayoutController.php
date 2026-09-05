<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function requestPayout(Request $request, string $sellerId): JsonResponse
    {
        $validated = $request->validate([
            'pix_key_type' => ['required', Rule::in(['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'RANDOM'])],
            'pix_key'      => 'required|string|max:100',
            'amount_cents' => 'required|integer|min:1000', // Saque mínimo de R$ 10,00 (1000 cents)
        ]);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? 'payout-' . (string) Str::uuid();

        // Idempotência: caso a mesma chave já tenha sido enviada, retorna o comprovante existente
        $existing = PayoutRequest::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        $seller = SellerProfile::find($sellerId);
        if (! $seller) {
            return response()->json(['message' => 'Vendedor não encontrado.'], 404);
        }

        if (! $seller->isOperational()) {
            return response()->json(['message' => 'Seller suspenso ou com KYC pendente não pode realizar saques.'], 403);
        }

        try {
            $payout = DB::transaction(function () use ($seller, $validated, $idempotencyKey) {
                // Lock pessimista para impedir saques concorrentes excedendo o saldo
                $wallet = SellerWallet::where('seller_profile_id', $seller->id)->lockForUpdate()->first();

                if (! $wallet) {
                    throw new \Exception('Carteira contábil não encontrada.', 404);
                }

                if ($wallet->balance_available_cents < $validated['amount_cents']) {
                    $disponivel = number_format($wallet->balance_available_cents / 100, 2, ',', '.');
                    throw new \Exception("Saldo disponível insuficiente (Disponível: R$ {$disponivel}).", 422);
                }

                // 1. Débito no saldo disponível
                $wallet->decrement('balance_available_cents', $validated['amount_cents']);

                // 2. Criação do protocolo Pix (Simulando EndToEndId do Bacen: E + ISPB + Data + Hash)
                $e2eId = 'E' . '18236120' . date('YmdHi') . strtoupper(Str::random(11));

                $payout = PayoutRequest::create([
                    'seller_id'          => $seller->id,
                    'wallet_id'          => $wallet->id,
                    'pix_key_type'       => $validated['pix_key_type'],
                    'pix_key'            => $validated['pix_key'],
                    'amount_cents'       => $validated['amount_cents'],
                    'fee_cents'          => 0,
                    'status'             => 'COMPLETED',
                    'idempotency_key'    => $idempotencyKey,
                    'bank_end_to_end_id' => $e2eId,
                    'processed_at'       => now(),
                ]);

                // 3. Lançamento no livro-razão (Ledger)
                WalletTransaction::create([
                    'wallet_id'    => $wallet->id,
                    'order_id'     => null,
                    'type'         => 'PAYOUT',
                    'amount_cents' => $validated['amount_cents'],
                    'description'  => "Saque via Pix ({$validated['pix_key_type']}: {$validated['pix_key']}) - E2E: {$e2eId}",
                ]);

                return $payout;
            });

            return response()->json([
                'message' => 'Saque via Pix liquidado com sucesso.',
                'payout'  => $payout,
            ], 201);
        } catch (\Exception $e) {
            $status = in_array($e->getCode(), [403, 404, 422]) ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
