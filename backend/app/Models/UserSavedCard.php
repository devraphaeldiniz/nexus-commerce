<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSavedCard extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'card_holder_name',
        'card_brand',
        'last_four',
        'exp_month',
        'exp_year',
        'gateway_card_token',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
