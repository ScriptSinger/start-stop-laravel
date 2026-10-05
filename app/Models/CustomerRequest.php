<?php

namespace App\Models;

use App\Enums\CustomerRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequest extends Model
{
    protected $fillable = [
        'type',
        'name',
        'phone',
        'email',
        'product_id',
        'comment',
        'admin_comment',
        'is_processed',
    ];

    protected $casts = [
        'type' => CustomerRequestType::class,
        'is_processed' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
