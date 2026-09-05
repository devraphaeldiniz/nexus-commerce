<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_id',
        'wallet_id',
        'pix_key_type',
        'pix_key',
        'amount_cents',
        'fee_cents',
        'status',
        'idempotency_key',
        'bank_end_to_end_id',
        'failure_reason',
        'processed_at',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SellerWallet::class, 'wallet_id');
    }
}
