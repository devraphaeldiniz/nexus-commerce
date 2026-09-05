<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price_cents',
        'stock_quantity',
        'image_url',
        'weight_grams',
        'height_cm',
        'width_cm',
        'length_cm',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Calcula o peso cúbico individual (Fator de cubagem padrão 6000)
    public function getCubedWeightGramsAttribute(): int
    {
        $cubedKg = ($this->height_cm * $this->width_cm * $this->length_cm) / 6000;
        return (int) round($cubedKg * 1000);
    }
}
