<?php

namespace Tests\Feature;

use App\Services\AsaasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_requires_configured_token(): void
    {
        config(['services.asaas.webhook_token' => 'secret']);
        $response = $this->postJson('/api/webhooks/asaas', ['event' => 'PAYMENT_CONFIRMED']);
        $response->assertStatus(401);
    }

    public function test_webhook_is_idempotent(): void
    {
        config(['services.asaas.webhook_token' => 'secret']);
        Http::fake(['sandbox.asaas.com/*' => Http::response(['id' => 'pay_test', 'status' => 'CONFIRMED'])]);
        $payload = ['event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_test']];
        $headers = ['asaas-access-token' => 'secret', 'asaas-event-id' => 'evt_test'];

        $this->withHeaders($headers)->postJson('/api/webhooks/asaas', $payload)->assertOk();
        $this->withHeaders($headers)->postJson('/api/webhooks/asaas', $payload)->assertOk()->assertJson(['status' => 'ALREADY_PROCESSED']);
    }
}
