<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id',
        'order_id',
        'type',
        'amount_cents',
        'description',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SellerWallet::class, 'wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
