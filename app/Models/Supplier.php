<?php

namespace App\Models;

use App\Enums\SupplierPriceFormat;
use App\Services\Supplier\SupplierStockSync;
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
     * Условия поставщика влияют на цену и наличие «под заказ», поэтому после их
     * изменения предложения пересчитываются сразу, а не при следующей загрузке прайса.
     */
    protected static function booted(): void
    {
        static::saved(function (Supplier $supplier): void {
            if ($supplier->wasChanged(['markup_percent', 'lead_working_days', 'min_quantity', 'ignore_quantity', 'is_active'])) {
                app(SupplierStockSync::class)->sync();
            }
        });
    }

    /**
     * @return HasMany<SupplierOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
