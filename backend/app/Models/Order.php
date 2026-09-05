<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_email',
        'shipping_zip_code',
        'shipping_service',
        'shipping_cost_cents',
        'estimated_delivery_days',
        'total_cents',
        'status',
        'idempotency_key',
        'tracking_code',
        'shipped_at',
        'delivered_at',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
