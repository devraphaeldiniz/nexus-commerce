<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;

class SellerDashboardController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $seller = SellerProfile::with([
            'wallet.seller',
            'user:id,name,email',
        ])->find($id);

        if (! $seller) {
            return response()->json(['message' => 'Seller não encontrado.'], 404);
        }

        // Busca transações recentes da carteira
        $transactions = [];
        if ($seller->wallet) {
            $transactions = $seller->wallet
                ->hasMany(\App\Models\WalletTransaction::class, 'wallet_id')
                ->latest()
                ->take(15)
                ->get();
        }

        return response()->json([
            'seller'       => $seller,
            'wallet'       => $seller->wallet,
            'transactions' => $transactions,
        ]);
    }

    public function listSellers(): JsonResponse
    {
        $sellers = SellerProfile::select('id', 'store_name', 'legal_name', 'kyc_status', 'reputation_score')
            ->orderBy('store_name')
            ->get();

        return response()->json($sellers);
    }
}
