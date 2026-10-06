<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title',
        'heading',
        'meta_title',
        'meta_description',
        'slug',
        'description',
        'show_in_top',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'show_in_top' => 'boolean',
        'status' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
