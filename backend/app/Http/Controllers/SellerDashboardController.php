<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = SellerProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return response()->json(['message' => 'Perfil de vendedor não localizado.'], 404);
        }

        $wallet = DB::table('seller_wallets')->where('seller_profile_id', $profile->id)->first();

        $totalSalesCents = OrderItem::where('seller_id', $profile->id)
            ->sum(DB::raw('unit_price_cents * quantity'));

        $totalOrdersCount = OrderItem::where('seller_id', $profile->id)
            ->distinct('order_id')
            ->count('order_id');

        $heldBalanceCents = DB::table('order_escrows')
            ->where('seller_id', $profile->id)
            ->where('status', 'HELD')
            ->sum('net_seller_cents');

        $releasedBalanceCents = DB::table('order_escrows')
            ->where('seller_id', $profile->id)
            ->where('status', 'RELEASED')
            ->sum('net_seller_cents');

        return response()->json([
            'store_name'              => $profile->store_name,
            'document_number'         => $profile->document_number,
            'kyc_status'              => $profile->kyc_status,
            'total_sales_cents'       => (int) $totalSalesCents,
            'total_orders_count'      => $totalOrdersCount,
            'balance_escrow_cents'    => (int) ($heldBalanceCents ?: ($wallet->balance_escrow_cents ?? 0)),
            'balance_available_cents' => (int) ($releasedBalanceCents ?: ($wallet->balance_available_cents ?? 0)),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = SellerProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return response()->json(['message' => 'Perfil de vendedor não localizado.'], 404);
        }

        $orderIds = OrderItem::where('seller_id', $profile->id)
            ->pluck('order_id')
            ->unique();

        $orders = Order::whereIn('id', $orderIds)
            ->with(['items' => function ($q) use ($profile) {
                $q->where('seller_id', $profile->id)->with('product');
            }])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($order) use ($profile) {
                $sellerGrossCents = $order->items->sum(fn ($i) => $i->unit_price_cents * $i->quantity);
                $sellerNetCents = (int) round($sellerGrossCents * 0.87);
                $platformFeeCents = $sellerGrossCents - $sellerNetCents;

                return [
                    'id'                      => $order->id,
                    'status'                  => $order->status,
                    'shipping_service'        => $order->shipping_service,
                    'shipping_zip_code'       => $order->shipping_zip_code,
                    'tracking_code'           => $order->tracking_code,
                    'created_at'              => $order->created_at,
                    'items'                   => $order->items,
                    'seller_gross_cents'      => $sellerGrossCents,
                    'seller_net_cents'        => $sellerNetCents,
                    'platform_fee_cents'      => $platformFeeCents,
                ];
            });

        return response()->json($orders);
    }

    public function dispatchOrder(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'tracking_code' => 'required|string|min:6|max:40',
        ]);

        $user = $request->user();
        $profile = SellerProfile::where('user_id', $user->id)->first();

        $order = Order::findOrFail($id);

        $hasItem = OrderItem::where('order_id', $order->id)
            ->where('seller_id', $profile->id)
            ->exists();

        if (! $hasItem) {
            return response()->json(['message' => 'Você não possui permissão sobre este pedido.'], 403);
        }

        $order->update([
            'tracking_code' => strtoupper(trim($request->input('tracking_code'))),
            'status'        => 'SHIPPED',
        ]);

        return response()->json([
            'message'       => 'Pedido despachado com sucesso!',
            'status'        => $order->status,
            'tracking_code' => $order->tracking_code,
        ]);
    }
}
