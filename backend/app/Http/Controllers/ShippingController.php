<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ShippingCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShippingController extends Controller
{
    public function __construct(private ShippingCalculatorService $shippingService) {}

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip_code'         => 'required|string|min:8',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $quote = $this->shippingService->calculate($validated['zip_code'], $validated['items']);

        return response()->json($quote);
    }

    /**
     * Atualização de status logístico: SHIP (Despachar com geração de rastreio)
     */
    public function shipOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        if ($order->status !== 'PAID' && $order->status !== 'IN_PREPARATION') {
            return response()->json(['message' => "Pedido em status {$order->status} não pode ser despachado."], 400);
        }

        // Gera código de rastreamento no padrão de marketplace (Ex: NX-123456789-BR)
        $trackingCode = 'NX-' . strtoupper(Str::random(9)) . '-BR';

        $order->update([
            'status'        => 'SHIPPED',
            'tracking_code' => $trackingCode,
            'shipped_at'    => now(),
        ]);

        return response()->json([
            'message'       => 'Pedido despachado e código de rastreio emitido.',
            'tracking_code' => $trackingCode,
            'order'         => $order,
        ]);
    }
}
