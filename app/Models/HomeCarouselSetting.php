<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeCarouselSetting extends Model
{
    protected $fillable = ['banners'];

    protected $casts = [
        'banners' => 'array',
    ];
}
