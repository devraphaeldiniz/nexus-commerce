<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'zip_code' => 'required|string',
            'items'    => 'nullable|array',
        ]);

        $rawZip = preg_replace('/\D/', '', $request->input('zip_code'));

        if (strlen($rawZip) !== 8) {
            return response()->json(['message' => 'CEP inválido. O CEP deve conter 8 dígitos.'], 422);
        }

        $items = $request->input('items', []);
        $totalWeightGrams = 0;
        $totalVolumeCm3 = 0;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            $quantity = max((int) ($item['quantity'] ?? 1), 1);

            $product = null;
            if ($productId) {
                $product = Product::find($productId);
            }

            if ($product) {
                $weight = $product->weight_grams ?? 350;
                $length = $product->length_cm ?? 20;
                $width = $product->width_cm ?? 15;
                $height = $product->height_cm ?? 10;
            } else {
                $weight = $item['weight_grams'] ?? 350;
                $length = $item['length_cm'] ?? 20;
                $width = $item['width_cm'] ?? 15;
                $height = $item['height_cm'] ?? 10;
            }

            $totalWeightGrams += ($weight * $quantity);
            $totalVolumeCm3 += ($length * $width * $height * $quantity);
        }

        // Peso mínimo de 300g para fins de frete
        $billableWeightKg = max(ceil($totalWeightGrams / 1000), 1);

        // Fator de cubagem (densidade padrão transportadora: 6000 cm3/kg)
        $volumetricWeightKg = max(ceil($totalVolumeCm3 / 6000), 1);
        $finalWeightFactor = max($billableWeightKg, $volumetricWeightKg);

        // Tabela base de prazos e valores simulados
        $pacBaseCents = 1890;
        $sedexBaseCents = 3290;

        $pacTotalCents = (int) ($pacBaseCents + (($finalWeightFactor - 1) * 450));
        $sedexTotalCents = (int) ($sedexBaseCents + (($finalWeightFactor - 1) * 780));

        return response()->json([
            'zip_code' => $rawZip,
            'quotes'   => [
                [
                    'service'           => 'PAC',
                    'name'              => 'Correios PAC Econômico',
                    'price_cents'       => $pacTotalCents,
                    'delivery_time_days'=> 6,
                ],
                [
                    'service'           => 'SEDEX',
                    'name'              => 'Correios SEDEX Expresso',
                    'price_cents'       => $sedexTotalCents,
                    'delivery_time_days'=> 2,
                ],
            ]
        ]);
    }
}
