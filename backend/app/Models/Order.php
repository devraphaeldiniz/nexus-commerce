<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'customer_email',
        'total_cents',
        'idempotency_key',
        'status',
        'delivered_at',
        'shipping_zip_code',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_service',
        'shipping_cost_cents',
        'estimated_delivery_days',
        'tracking_code',
        'shipped_at',
        'shipping_address',
        'gateway_payment_id',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'shipped_at' => 'datetime',
        'total_cents' => 'integer',
        'shipping_cost_cents' => 'integer',
        'status' => OrderStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function paymentWebhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }
}
