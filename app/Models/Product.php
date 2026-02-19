<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'barcode',
        'name',
        'category',
        'brand',
        'subcategory',
        'is_promo',
        'is_combo',
        'description',
        'base_price',
        'markup',
        'price',
        'stock',
        'image',
    ];
}
