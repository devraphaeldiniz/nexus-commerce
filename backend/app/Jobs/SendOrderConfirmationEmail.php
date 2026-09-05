<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        Log::info("Processando recibo assincrono para o pedido: {$this->order->id}");
        Log::info("Destinatario: {$this->order->customer_email} | Total: R$ " . number_format($this->order->total_cents / 100, 2, ',', '.'));
    }
}
