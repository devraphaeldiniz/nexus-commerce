<?php

namespace App\Http\Controllers;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    private const DEFAULT_TAKE_RATE_BPS = 1300; // 13.00%

    public function process(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_email'          => 'required|email',
            'shipping_zip_code'       => 'nullable|string|max:9',
            'shipping_service'        => 'nullable|string|in:STANDARD,EXPRESS',
            'shipping_cost_cents'     => 'nullable|integer|min:0',
            'estimated_delivery_days' => 'nullable|integer|min:1',
            'items'                   => 'required|array|min:1',
            'items.*.id'              => 'required|uuid',
            'items.*.quantity'        => 'required|integer|min:1',
        ]);

        $idempotencyKey = $request->header('X-Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json(['message' => 'Header X-Idempotency-Key é obrigatório'], 400);
        }

        $existingOrder = Order::with('items.product')->where('idempotency_key', $idempotencyKey)->first();
        if ($existingOrder) {
            return response()->json($existingOrder, 200);
        }

        try {
            $order = DB::transaction(function () use ($data, $idempotencyKey) {
                $itemsSubtotalCents = 0;
                $itemsToCreate = [];
                $sellerEarnings = [];

                foreach ($data['items'] as $itemData) {
                    $product = Product::with('seller.wallet')
                        ->where('id', $itemData['id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        throw new \Exception("Produto ID {$itemData['id']} não encontrado.", 404);
                    }

                    if (! $product->seller || ! $product->seller->isOperational()) {
                        throw new \Exception("Vendedor do produto {$product->name} não está habilitado para vendas.", 422);
                    }

                    if ($product->stock_quantity < $itemData['quantity']) {
                        throw new \Exception("Estoque insuficiente para {$product->name}.", 422);
                    }

                    $product->decrement('stock_quantity', $itemData['quantity']);

                    $subtotal = $product->price_cents * $itemData['quantity'];
                    $itemsSubtotalCents += $subtotal;

                    // Split: 13% plataforma, 87% seller
                    $platformFee = (int) round(($subtotal * self::DEFAULT_TAKE_RATE_BPS) / 10000);
                    $netSeller = $subtotal - $platformFee;

                    $itemsToCreate[] = [
                        'product_id'          => $product->id,
                        'seller_id'           => $product->seller_id,
                        'unit_price_cents'    => $product->price_cents,
                        'quantity'            => $itemData['quantity'],
                        'commission_rate_bps' => self::DEFAULT_TAKE_RATE_BPS,
                        'fee_platform_cents'  => $platformFee,
                        'net_seller_cents'    => $netSeller,
                    ];

                    $sId = $product->seller_id;
                    $sellerEarnings[$sId] = ($sellerEarnings[$sId] ?? 0) + $netSeller;
                }

                $shippingCents = $data['shipping_cost_cents'] ?? 0;
                $grandTotalCents = $itemsSubtotalCents + $shippingCents;

                $order = Order::create([
                    'customer_email'          => $data['customer_email'],
                    'shipping_zip_code'       => $data['shipping_zip_code'] ?? null,
                    'shipping_service'        => $data['shipping_service'] ?? 'STANDARD',
                    'shipping_cost_cents'     => $shippingCents,
                    'estimated_delivery_days' => $data['estimated_delivery_days'] ?? null,
                    'total_cents'             => $grandTotalCents,
                    'status'                  => 'PAID',
                    'idempotency_key'         => $idempotencyKey,
                ]);

                foreach ($itemsToCreate as $item) {
                    $item['order_id'] = $order->id;
                    OrderItem::create($item);
                }

                // Retenção em custódia (Escrow)
                foreach ($sellerEarnings as $sellerId => $netCents) {
                    $wallet = SellerWallet::where('seller_profile_id', $sellerId)->lockForUpdate()->first();
                    if ($wallet) {
                        $wallet->increment('balance_escrow_cents', $netCents);

                        WalletTransaction::create([
                            'wallet_id'    => $wallet->id,
                            'order_id'     => $order->id,
                            'type'         => 'ESCROW_HOLD',
                            'amount_cents' => $netCents,
                            'description'  => "Retenção de custódia do pedido #{$order->id}",
                        ]);
                    }
                }

                return $order->load('items.product');
            });

            Cache::forget('catalog:products:available');
            foreach ($data['items'] as $item) {
                Cache::forget("catalog:product:{$item['id']}");
            }

            SendOrderConfirmationEmail::dispatch($order);

            return response()->json($order, 201);
        } catch (\Exception $e) {
            $status = in_array($e->getCode(), [404, 422]) ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
