<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'dispute_id',
        'sender_type',
        'sender_name',
        'message',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(OrderDispute::class, 'dispute_id');
    }
}
