<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'store_name',
        'legal_name',
        'document_type',
        'document_number',
        'state_registration',
        'kyc_status',
        'reputation_score',
        'total_sales_count',
        'cancellation_rate',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(SellerWallet::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function isOperational(): bool
    {
        return $this->kyc_status === 'APPROVED';
    }
}
