<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

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

    // Cria os atributos virtuais 'price' e 'title' para compatibilidade total com o Vue
    protected $appends = ['price', 'title'];

    public function getPriceAttribute()
    {
        return $this->price_cents ? round($this->price_cents / 100, 2) : 0.00;
    }

    public function getTitleAttribute()
    {
        return $this->name;
    }
}
