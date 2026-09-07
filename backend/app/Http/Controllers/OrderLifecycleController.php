<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderLifecycleController extends Controller
{
    public function markAsDelivered(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'DELIVERED') {
            return response()->json(['message' => 'Pedido já se encontra como entregue.'], 400);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'DELIVERED']);

            // Busca todas as custódias vinculadas a esse pedido
            $escrows = DB::table('order_escrows')
                ->where('order_id', $order->id)
                ->where('status', 'HELD')
                ->get();

            foreach ($escrows as $escrow) {
                // Atualiza status do escrow para liberado
                DB::table('order_escrows')
                    ->where('id', $escrow->id)
                    ->update([
                        'status'      => 'RELEASED',
                        'released_at' => now(),
                        'updated_at'  => now(),
                    ]);

                // Atualiza saldo da carteira do vendedor
                $seller = Seller::where('id', $escrow->seller_id)->lockForUpdate()->first();
                if ($seller) {
                    $newBalance = $seller->available_balance_cents + $escrow->net_seller_cents;
                    $seller->update(['available_balance_cents' => $newBalance]);

                    // Grava no extrato financeiro (Ledger)
                    DB::table('seller_ledgers')->insert([
                        'id'                  => (string) Str::uuid(),
                        'seller_id'           => $seller->id,
                        'operation_type'      => 'ESCROW_RELEASE',
                        'amount_cents'        => $escrow->net_seller_cents,
                        'balance_after_cents' => $newBalance,
                        'description'         => "Liberação de custódia do Pedido #{$order->id}",
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Pedido marcado como ENTREGUE. Saldo dos vendedores liquidado com sucesso!',
            'order'   => $order->fresh(),
        ]);
    }
}
