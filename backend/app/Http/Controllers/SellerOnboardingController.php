<?php

namespace App\Http\Controllers;

use App\Models\KycAuditLog;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\User;
use App\Rules\ValidTaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SellerOnboardingController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $docType = $request->input('document_type', 'CNPJ');

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8',
            'store_name'            => 'required|string|max:100|unique:seller_profiles,store_name',
            'legal_name'            => 'required|string|max:255',
            'document_type'         => ['required', Rule::in(['CPF', 'CNPJ'])],
            'document_number'       => ['required', 'string', new ValidTaxDocument($docType), 'unique:seller_profiles,document_number'],
            'state_registration'    => 'nullable|string|max:30',
        ]);

        $cleanDoc = preg_replace('/\D/', '', $validated['document_number']);

        $seller = DB::transaction(function () use ($validated, $cleanDoc) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $profile = SellerProfile::create([
                'user_id'            => $user->id,
                'store_name'         => $validated['store_name'],
                'legal_name'         => $validated['legal_name'],
                'document_type'      => $validated['document_type'],
                'document_number'    => $cleanDoc,
                'state_registration' => $validated['state_registration'] ?? null,
                'kyc_status'         => 'PENDING',
                'reputation_score'   => 5.00,
                'total_sales_count'  => 0,
                'cancellation_rate'  => 0.00,
            ]);

            KycAuditLog::create([
                'seller_profile_id' => $profile->id,
                'previous_status'   => 'NONE',
                'new_status'        => 'PENDING',
                'reason'            => 'Submissão inicial de cadastro do lojista.',
                'evaluated_by'      => 'ONBOARDING_API',
            ]);

            return $profile->load('user');
        });

        return response()->json([
            'message' => 'Cadastro de vendedor recebido com sucesso. Em análise KYC.',
            'seller'  => $seller,
        ], 201);
    }

    public function transitionKyc(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['APPROVE', 'REJECT', 'SUSPEND'])],
            'reason' => 'required|string|min:5|max:255',
        ]);

        $seller = SellerProfile::with('wallet')->find($id);

        if (! $seller) {
            return response()->json(['message' => 'Seller não encontrado.'], 404);
        }

        $prevStatus = $seller->kyc_status;
        $newStatus = match ($validated['action']) {
            'APPROVE' => 'APPROVED',
            'REJECT'  => 'REJECTED',
            'SUSPEND' => 'SUSPENDED',
        };

        if ($prevStatus === $newStatus) {
            return response()->json(['message' => "Seller já se encontra com status {$newStatus}."], 400);
        }

        DB::transaction(function () use ($seller, $prevStatus, $newStatus, $validated) {
            $seller->update(['kyc_status' => $newStatus]);

            // Se for aprovado pela primeira vez, inicializa a carteira contábil
            if ($newStatus === 'APPROVED' && ! $seller->wallet) {
                SellerWallet::create([
                    'seller_profile_id'       => $seller->id,
                    'balance_available_cents' => 0,
                    'balance_escrow_cents'    => 0,
                ]);
            }

            KycAuditLog::create([
                'seller_profile_id' => $seller->id,
                'previous_status'   => $prevStatus,
                'new_status'        => $newStatus,
                'reason'            => $validated['reason'],
                'evaluated_by'      => 'COMPLIANCE_OFFICER',
            ]);
        });

        return response()->json([
            'message' => "Status KYC transicionado de {$prevStatus} para {$newStatus} com sucesso.",
            'seller'  => $seller->fresh(['wallet']),
        ]);
    }
}
