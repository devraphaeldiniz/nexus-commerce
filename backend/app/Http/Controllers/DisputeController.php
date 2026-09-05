<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\DisputeMessage;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'       => 'required|uuid|exists:orders,id',
            'customer_email' => 'required|email',
            'reason'         => ['required', Rule::in(['PRODUCT_NOT_RECEIVED', 'PRODUCT_DEFECTIVE', 'PRODUCT_DIFFERENT', 'REGRET_OF_PURCHASE'])],
            'description'    => 'required|string|min:10',
        ]);

        $order = Order::with('items')->find($validated['order_id']);

        if (strtolower($order->customer_email) !== strtolower($validated['customer_email'])) {
            return response()->json(['message' => 'O e-mail informado não corresponde ao comprador deste pedido.'], 403);
        }

        $activeDispute = OrderDispute::where('order_id', $order->id)
            ->whereNotIn('status', ['RESOLVED_REFUNDED', 'RESOLVED_FAVOR_SELLER', 'CLOSED'])
            ->first();

        if ($activeDispute) {
            return response()->json(['message' => 'Já existe uma disputa em aberto para este pedido.'], 400);
        }

        $sellerId = $order->items->first()?->seller_id;
        if (! $sellerId) {
            return response()->json(['message' => 'Não foi possível identificar o vendedor deste pedido.'], 422);
        }

        $dispute = DB::transaction(function () use ($order, $sellerId, $validated) {
            $order->update(['status' => 'DISPUTED']);

            $dispute = OrderDispute::create([
                'order_id'              => $order->id,
                'seller_id'             => $sellerId,
                'customer_email'        => $validated['customer_email'],
                'reason'                => $validated['reason'],
                'description'           => $validated['description'],
                'status'                => 'OPEN',
                'disputed_amount_cents' => $order->total_cents,
            ]);

            DisputeMessage::create([
                'dispute_id'  => $dispute->id,
                'sender_type' => 'CUSTOMER',
                'sender_name' => $order->customer_email,
                'message'     => $validated['description'],
            ]);

            return $dispute->load('messages');
        });

        return response()->json([
            'message' => 'Disputa aberta com sucesso. O vendedor foi notificado para mediação.',
            'dispute' => $dispute,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $dispute = OrderDispute::with(['order.items.product', 'seller', 'messages'])->find($id);

        if (! $dispute) {
            return response()->json(['message' => 'Disputa não encontrada.'], 404);
        }

        return response()->json($dispute);
    }

    public function postMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'sender_type' => ['required', Rule::in(['CUSTOMER', 'SELLER', 'MEDIATOR'])],
            'sender_name' => 'required|string|max:100',
            'message'     => 'required|string|min:2',
        ]);

        $dispute = OrderDispute::find($id);
        if (! $dispute) {
            return response()->json(['message' => 'Disputa não encontrada.'], 404);
        }

        $msg = DisputeMessage::create([
            'dispute_id'  => $dispute->id,
            'sender_type' => $validated['sender_type'],
            'sender_name' => $validated['sender_name'],
            'message'     => $validated['message'],
        ]);

        return response()->json($msg, 201);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['REFUND_BUYER', 'FAVOR_SELLER'])],
            'notes'      => 'required|string|min:5',
        ]);

        $dispute = OrderDispute::with('order.items')->find($id);
        if (! $dispute) {
            return response()->json(['message' => 'Disputa não encontrada.'], 404);
        }

        if (in_array($dispute->status, ['RESOLVED_REFUNDED', 'RESOLVED_FAVOR_SELLER'])) {
            return response()->json(['message' => 'Esta disputa já foi encerrada.'], 400);
        }

        DB::transaction(function () use ($dispute, $validated) {
            $wallet = SellerWallet::where('seller_profile_id', $dispute->seller_id)->lockForUpdate()->first();

            // Total líquido devido ao seller neste pedido
            $netSellerCents = $dispute->order->items->sum('net_seller_cents');

            if ($validated['resolution'] === 'REFUND_BUYER') {
                $dispute->update([
                    'status'           => 'RESOLVED_REFUNDED',
                    'resolution_notes' => $validated['notes'],
                    'resolved_at'      => now(),
                ]);

                $dispute->order->update(['status' => 'CANCELED']);

                // Se o valor ainda estiver em custódia (escrow), estorna da custódia; senão debita do disponível
                if ($wallet) {
                    if ($wallet->balance_escrow_cents >= $netSellerCents) {
                        $wallet->decrement('balance_escrow_cents', $netSellerCents);
                    } else {
                        $wallet->decrement('balance_available_cents', $netSellerCents);
                    }

                    WalletTransaction::create([
                        'wallet_id'    => $wallet->id,
                        'order_id'     => $dispute->order_id,
                        'type'         => 'REFUND_DEBIT',
                        'amount_cents' => $netSellerCents,
                        'description'  => "Estorno de disputa concedido ao comprador #{$dispute->id}",
                    ]);
                }
            } else {
                // Decisão favorável ao lojista: libera o saldo de custódia para saque
                $dispute->update([
                    'status'           => 'RESOLVED_FAVOR_SELLER',
                    'resolution_notes' => $validated['notes'],
                    'resolved_at'      => now(),
                ]);

                $dispute->order->update(['status' => 'DELIVERED']);

                if ($wallet && $wallet->balance_escrow_cents >= $netSellerCents) {
                    $wallet->decrement('balance_escrow_cents', $netSellerCents);
                    $wallet->increment('balance_available_cents', $netSellerCents);

                    WalletTransaction::create([
                        'wallet_id'    => $wallet->id,
                        'order_id'     => $dispute->order_id,
                        'type'         => 'ESCROW_RELEASE',
                        'amount_cents' => $netSellerCents,
                        'description'  => "Liberação de saldo retido pós-disputa ganha pelo seller #{$dispute->id}",
                    ]);
                }
            }

            DisputeMessage::create([
                'dispute_id'  => $dispute->id,
                'sender_type' => 'MEDIATOR',
                'sender_name' => 'Nexus Mediation System',
                'message'     => "Disputa resolvida com decisão [{$validated['resolution']}]. Motivo: {$validated['notes']}",
            ]);
        });

        return response()->json([
            'message' => 'Disputa finalizada com sucesso.',
            'dispute' => $dispute->fresh(['messages']),
        ]);
    }
}
