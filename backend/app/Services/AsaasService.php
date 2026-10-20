<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    protected static function client()
    {
        $apiKey = env('ASAAS_API_KEY');
        $baseUrl = env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3');

        return Http::withHeaders([
            'access_token' => $apiKey,
            'Content-Type' => 'application/json',
        ])->baseUrl($baseUrl);
    }

    /**
     * Localiza ou cadastra um cliente no Asaas
     */
    public static function findOrCreateCustomer(string $name, string $email, string $cpf)
    {
        $cleanCpf = preg_replace('/\D/', '', $cpf);
        
        // Busca cliente existente por email ou cpf
        $res = self::client()->get('/customers', [
            'email' => $email,
        ]);

        if ($res->successful() && !empty($res->json('data'))) {
            return $res->json('data')[0]['id'];
        }

        // Se não existir, cria novo
        $createRes = self::client()->post('/customers', [
            'name'                => $name,
            'email'               => $email,
            'cpfCnpj'             => $cleanCpf ?: '00000000000',
            'notificationDisabled'=> true,
        ]);

        if (!$createRes->successful()) {
            Log::error('Falha ao criar cliente Asaas: ' . $createRes->body());
            throw new \Exception('Erro ao registrar cliente no gateway Asaas: ' . ($createRes->json('errors')[0]['description'] ?? 'Erro desconhecido'));
        }

        return $createRes->json('id');
    }

    /**
     * Gera Cobrança via PIX e obtém o QR Code
     */
    public static function createPixPayment(float $amount, string $name, string $email, string $cpf, string $description = 'Compra Nexus Commerce')
    {
        $customerId = self::findOrCreateCustomer($name, $email, $cpf);

        $payload = [
            'customer'          => $customerId,
            'billingType'       => 'PIX',
            'value'             => (float)$amount,
            'dueDate'           => now()->addDays(2)->format('Y-m-d'),
            'description'       => $description,
            'postalService'     => false,
        ];

        $paymentRes = self::client()->post('/payments', $payload);

        if (!$paymentRes->successful()) {
            Log::error('Erro ao gerar cobranca PIX Asaas: ' . $paymentRes->body());
            throw new \Exception($paymentRes->json('errors')[0]['description'] ?? 'Erro ao criar cobranca PIX.');
        }

        $payment = $paymentRes->json();
        $paymentId = $payment['id'];

        // Consulta QR Code e chave copia e cola do PIX
        $qrRes = self::client()->get("/payments/{$paymentId}/pixQrCode");

        if (!$qrRes->successful()) {
            Log::error('Erro ao consultar Pix QR Code Asaas: ' . $qrRes->body());
            throw new \Exception('Erro ao gerar QR Code do PIX.');
        }

        $qrData = $qrRes->json();

        return [
            'payment_id'      => $paymentId,
            'status'          => $payment['status'],
            'qr_code_image'   => 'data:image/png;base64,' . $qrData['encodedImage'],
            'qr_code_payload' => $qrData['payload'],
            'expiration_date' => $qrData['expirationDate'],
        ];
    }

    /**
     * Processa Cobrança via Cartão de Crédito
     */
    public static function createCreditCardPayment(float $amount, string $name, string $email, string $cpf, array $cardData)
    {
        $customerId = self::findOrCreateCustomer($name, $email, $cpf);
        $cleanCpf = preg_replace('/\D/', '', $cpf);

        $payload = [
            'customer'          => $customerId,
            'billingType'       => 'CREDIT_CARD',
            'value'             => (float)$amount,
            'dueDate'           => now()->format('Y-m-d'),
            'description'       => 'Compra Nexus Commerce - Cartao',
            'creditCardToken' => $cardData['token'],
            'creditCardHolderInfo' => [
                'name'              => $cardData['holder_name'],
                'email'             => $email,
                'cpfCnpj'           => $cleanCpf ?: '00000000000',
                'postalCode'        => $cardData['postal_code'] ?? '30130000',
                'addressNumber'     => '100',
                'phone'             => $cardData['phone'] ?? '31999999999',
            ],
        ];

        $res = self::client()->post('/payments', $payload);

        if (!$res->successful()) {
            Log::error('Erro ao processar Cartao Asaas: ' . $res->body());
            throw new \Exception($res->json('errors')[0]['description'] ?? 'Transacao com cartao recusada.');
        }

        $data = $res->json();

        return [
            'payment_id' => $data['id'],
            'status'     => $data['status'], // 'CONFIRMED', 'RECEIVED', 'PENDING'
            'net_value'  => $data['netValue'] ?? $amount,
        ];
    }
}
