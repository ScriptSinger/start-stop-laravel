<?php

namespace App\Models;

use App\Models\Concerns\SluggedByName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use SluggedByName;

    protected $fillable = [
        'name',
        'slug',
        'image',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
