<?php

namespace App\Services;

use App\Models\Product;

class ShippingCalculatorService
{
    /**
     * Calcula frete com base no maior peso entre o real e o cúbico total.
     */
    public function calculate(string $zipCode, array $items): array
    {
        $cleanZip = preg_replace('/\D/', '', $zipCode);
        
        $totalWeightGrams = 0;
        $totalCubedGrams = 0;

        foreach ($items as $item) {
            $product = Product::find($item['id']);
            if (! $product) continue;

            $qty = $item['quantity'];
            $totalWeightGrams += $product->weight_grams * $qty;
            
            // Cubagem agrupada
            $cubedItemKg = ($product->height_cm * $product->width_cm * $product->length_cm) / 6000;
            $totalCubedGrams += (int) round($cubedItemKg * 1000) * $qty;
        }

        // Peso taxável é o maior entre peso físico e peso cúbico
        $chargeableWeightKg = max($totalWeightGrams, $totalCubedGrams) / 1000;

        // Fator de distância regional a partir do primeiro dígito do CEP (0xxxx = SP, 3xxxx = MG, etc.)
        $regionPrefix = (int) substr($cleanZip, 0, 1);
        $distanceMultiplier = match (true) {
            $regionPrefix <= 2 => 1.0, // Sudeste (SP, RJ, ES)
            $regionPrefix === 3 => 1.1, // MG
            $regionPrefix <= 5 => 1.3, // Sul (PR, SC, RS)
            $regionPrefix <= 7 => 1.6, // Centro-Oeste / DF
            default => 1.9,            // Nordeste e Norte
        };

        // Modalidade 1: Nexus Standard (Econômico)
        $standardBaseCents = 1890;
        $standardCost = (int) round(($standardBaseCents + ($chargeableWeightKg * 450)) * $distanceMultiplier);
        $standardDays = max(2, (int) round(3 * $distanceMultiplier));

        // Modalidade 2: Nexus Full / Express (Prioritário)
        $expressBaseCents = 3290;
        $expressCost = (int) round(($expressBaseCents + ($chargeableWeightKg * 750)) * $distanceMultiplier);
        $expressDays = max(1, (int) round(1.5 * $distanceMultiplier));

        return [
            'chargeable_weight_kg' => round($chargeableWeightKg, 2),
            'destination_zip'      => $cleanZip,
            'options' => [
                [
                    'service_code' => 'STANDARD',
                    'name'         => 'Nexus Entrega Padrão',
                    'cost_cents'   => $standardCost,
                    'days'         => $standardDays,
                ],
                [
                    'service_code' => 'EXPRESS',
                    'name'         => 'Nexus Full Express',
                    'cost_cents'   => $expressCost,
                    'days'         => $expressDays,
                ],
            ]
        ];
    }
}
