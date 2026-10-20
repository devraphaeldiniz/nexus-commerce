<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class OrderTransitionService
{
    public function transition(Order $order, OrderStatus $next): Order
    {
        $current = OrderStatus::tryFrom((string) $order->status);
        if (! $current || ! $current->canTransitionTo($next)) {
            throw new DomainException("Transição inválida de {$order->status} para {$next->value}.");
        }

        $order->update(['status' => $next->value]);
        return $order->refresh();
    }
}
