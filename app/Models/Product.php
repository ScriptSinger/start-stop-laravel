<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'name',
        'slug',
        'sku',
        'code',
        'description',
        'price',
        'quantity',
        'supplier_quantity',
        'supplier_price',
        'image',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'supplier_price' => 'decimal:4',
        'status' => 'boolean',
    ];

    /**
     * Своего остатка нет, но у поставщика достаточно — продаём под заказ.
     */
    public function isAvailableOnOrder(): bool
    {
        return $this->quantity <= 0
            && $this->supplier_quantity >= config('shop.supplier_order_min_quantity');
    }

    /**
     * Цена для покупателя: под заказ — цена поставщика, если она задана.
     */
    public function displayPrice(): float
    {
        return (float) ($this->isAvailableOnOrder() && $this->supplier_price !== null
            ? $this->supplier_price
            : $this->price);
    }

    /**
     * Аккумуляторы, подходящие автомобилю: внутри условия значения через
     * «ИЛИ» (60 или 62 Ah), между условиями — «И» (и полярность, и габариты).
     */
    #[Scope]
    protected function fitsBattery(Builder $query, BatteryFitment $fitment): void
    {
        $query->whereHas('categories', fn (Builder $categories) => $categories
            ->whereIn('categories.id', config('shop.battery_fitment.category_ids')));

        foreach ($fitment->matchingAttributeValueIds() as $valueIds) {
            if ($valueIds === null) {
                continue;
            }

            $query->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $valueIds));
        }
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }
}
