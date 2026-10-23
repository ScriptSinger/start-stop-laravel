<?php

namespace App\Models;

use App\Enums\SupplierPriceFormat;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'format',
        'markup_percent',
        'lead_working_days',
        'min_quantity',
        'ignore_quantity',
        'is_active',
    ];

    protected $casts = [
        'format' => SupplierPriceFormat::class,
        'markup_percent' => 'decimal:2',
        'lead_working_days' => 'integer',
        'min_quantity' => 'integer',
        'ignore_quantity' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<SupplierOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
