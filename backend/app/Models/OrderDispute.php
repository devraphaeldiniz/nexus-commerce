<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDispute extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'seller_id',
        'customer_email',
        'reason',
        'description',
        'status',
        'disputed_amount_cents',
        'resolution_notes',
        'resolved_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class, 'dispute_id')->oldest();
    }
}
