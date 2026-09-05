<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_profile_id',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'is_default',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}
