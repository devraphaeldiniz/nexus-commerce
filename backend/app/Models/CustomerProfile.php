<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'full_name',
        'document_number',
        'cpf',
        'phone',
        'birth_date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
