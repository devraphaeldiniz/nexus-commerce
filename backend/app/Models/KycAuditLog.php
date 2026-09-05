<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_profile_id',
        'previous_status',
        'new_status',
        'reason',
        'evaluated_by',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }
}
