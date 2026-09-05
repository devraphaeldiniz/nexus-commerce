<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'email_verification_code',
        'email_verification_expires_at',
        'role',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'api_token_expires_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'api_token_expires_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'password' => 'hashed',
        ];
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class, 'user_id');
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'user_id');
    }

    public function savedCards(): HasMany
    {
        return $this->hasMany(UserSavedCard::class, 'user_id')->latest();
    }

    public function isEmailVerified(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isSeller(): bool
    {
        return $this->role === 'SELLER';
    }

    public function isLogistics(): bool
    {
        return $this->role === 'LOGISTICS';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'CUSTOMER';
    }
}
