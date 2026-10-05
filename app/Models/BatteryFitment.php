<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatteryFitment extends Model
{
    protected $fillable = [
        'brand',
        'model',
        'generation',
        'capacity',
        'polarity',
        'dims',
        'image',
    ];
}
