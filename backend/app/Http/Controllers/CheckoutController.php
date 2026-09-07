<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'         => 'nullable|string|max:255',
            'customer_email'        => 'required|email|max:255',
            'shipping_zip_code'     => 'required|string|max:10',
            'shipping_street'       => 'nullable|string|max:255',
            'shipping_number'       => 'required|string|max:30',
            'shipping_complement'   => 'nullable|string|max:100',
            'shipping_neighborhood' => 'nullable|string|max:100',
            'shipping_city'         => 'nullable|string|max:100',
            'shipping_state'        => 'nullable|string|max:2',
            'shipping_service'      => 'required|string|max:50',
            'shipping_cents'        => 'required|integer|min:0',
            'payment_method'        => 'nullable|string',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|string|uuid',
            'items.*.quantity'      => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = $request->user();
            $subtotalCents = 0;
            $itemsToCreate = [];

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (! $product) {
                    return response()->json([
                        'message' => 'Um produto do carrinho não foi encontrado. Esvazie o carrinho e selecione novamente.'
                    ], 422);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'message' => "Estoque insuficiente para: {$product->name}"
                    ], 422);
                }

                $product->decrement('stock_quantity', $item['quantity']);

                $lineTotalCents = $product->price_cents * $item['quantity'];
                $subtotalCents += $lineTotalCents;

                $itemsToCreate[] = [
                    'product_id'       => $product->id,
                    'seller_id'        => $product->seller_id,
                    'quantity'         => $item['quantity'],
                    'unit_price_cents' => $product->price_cents,
                ];
            }

            $totalCents = $subtotalCents + $validated['shipping_cents'];
            $cleanZip = substr(preg_replace('/\D/', '', $validated['shipping_zip_code']), 0, 9);

            $fullAddress = trim(sprintf(
                '%s, %s%s - %s, %s/%s',
                $validated['shipping_street'] ?? 'Rua',
                $validated['shipping_number'],
                $validated['shipping_complement'] ? ' (' . $validated['shipping_complement'] . ')' : '',
                $validated['shipping_neighborhood'] ?? '',
                $validated['shipping_city'] ?? '',
                $validated['shipping_state'] ?? ''
            ));

            $order = Order::create([
                'id'                  => (string) Str::uuid(),
                'user_id'             => $user ? $user->id : null,
                'customer_email'      => $validated['customer_email'],
                'total_cents'         => $totalCents,
                'idempotency_key'     => (string) Str::uuid(),
                'status'              => 'PAID',
                'shipping_zip_code'   => $cleanZip,
                'shipping_service'    => $validated['shipping_service'],
                'shipping_cost_cents' => $validated['shipping_cents'],
                'shipping_address'    => $fullAddress,
            ]);

            foreach ($itemsToCreate as $itemData) {
                $orderItem = OrderItem::create([
                    'id'               => (string) Str::uuid(),
                    'order_id'         => $order->id,
                    'product_id'       => $itemData['product_id'],
                    'seller_id'        => $itemData['seller_id'],
                    'quantity'         => $itemData['quantity'],
                    'unit_price_cents' => $itemData['unit_price_cents'],
                ]);

                // Cálculo de repasse: 13% comissão plataforma, 87% líquido vendedor
                $grossItemCents = $itemData['unit_price_cents'] * $itemData['quantity'];
                $netSellerCents = (int) round($grossItemCents * 0.87);
                $feeCents = $grossItemCents - $netSellerCents;

                if (DB::getSchemaBuilder()->hasTable('order_escrows') && $itemData['seller_id']) {
                    DB::table('order_escrows')->insert([
                        'id'                 => (string) Str::uuid(),
                        'order_id'           => $order->id,
                        'order_item_id'      => $orderItem->id,
                        'seller_id'          => $itemData['seller_id'],
                        'gross_amount_cents' => $grossItemCents,
                        'platform_fee_cents' => $feeCents,
                        'net_seller_cents'   => $netSellerCents,
                        'status'             => 'HELD',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            return response()->json([
                'message'  => 'Pedido confirmado com sucesso!',
                'order_id' => $order->id,
                'status'   => $order->status,
                'total'    => $order->total_cents,
            ], 201);
        });
    }
}
