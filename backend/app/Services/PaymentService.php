<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function createPix(Order $order, array $data): array
    {
        return $this->create($order, 'pix', $data);
    }

    public function createCard(Order $order, array $data): array
    {
        return $this->create($order, 'card', $data);
    }

    private function create(Order $order, string $method, array $data): array
    {
        return DB::transaction(function () use ($order, $method, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->gateway_payment_id) {
                return ['payment_id' => $locked->gateway_payment_id, 'status' => 'PENDING', 'order_id' => $locked->id];
            }
            $result = $method === 'pix'
                ? AsaasService::createPixPayment($locked->total_cents / 100, $data['name'], $data['email'], $data['cpf'], 'Compra Nexus Marketplace')
                : AsaasService::createCreditCardPayment($locked->total_cents / 100, $data['name'], $data['email'], $data['cpf'], ['token' => $data['card_token'], 'holder_name' => $data['name'], 'postal_code' => $data['postal_code'] ?? null]);
            $locked->update(['gateway_payment_id' => $result['payment_id']]);
            return array_merge($result, ['order_id' => $locked->id]);
        });
    }
}
