<?php

namespace App\Models;

use Database\Factories\SupplierOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOffer extends Model
{
    /** @use HasFactory<SupplierOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'supplier_code',
        'name',
        'cost',
        'quantity',
        'in_price',
        'product_id',
        'seen_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'quantity' => 'integer',
        'in_price' => 'boolean',
        'seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
