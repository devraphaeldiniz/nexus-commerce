<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerWallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_profile_id',
        'balance_available_cents',
        'balance_escrow_cents',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }
}
