<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderLifecycleController extends Controller
{
    public function markAsDelivered(string $id): JsonResponse
    {
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        if ($order->status === 'DELIVERED') {
            return response()->json(['message' => 'Pedido já foi finalizado e entregue.'], 400);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status'       => 'DELIVERED',
                'delivered_at' => now(),
            ]);

            // Agrupa os valores líquidos por seller do pedido
            $sellerEarnings = [];
            foreach ($order->items as $item) {
                $sellerEarnings[$item->seller_id] = ($sellerEarnings[$item->seller_id] ?? 0) + $item->net_seller_cents;
            }

            // Transfere o valor de custódia para saldo disponível
            foreach ($sellerEarnings as $sellerId => $amountCents) {
                $wallet = SellerWallet::where('seller_profile_id', $sellerId)->lockForUpdate()->first();
                if ($wallet) {
                    $wallet->decrement('balance_escrow_cents', $amountCents);
                    $wallet->increment('balance_available_cents', $amountCents);

                    WalletTransaction::create([
                        'wallet_id'    => $wallet->id,
                        'order_id'     => $order->id,
                        'type'         => 'ESCROW_RELEASE',
                        'amount_cents' => $amountCents,
                        'description'  => "Liberação de custódia pós-entrega do pedido #{$order->id}",
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Pedido entregue com sucesso e saldo liquidado para o vendedor.',
            'order'   => $order->fresh(),
        ]);
    }
}
