<?php

namespace App\Models;

use App\Models\Concerns\HasHtmlDescription;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasHtmlDescription;

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
        'trade_in_discount',
        'is_pickup_only',
        'image',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'supplier_price' => 'decimal:4',
        'trade_in_discount' => 'decimal:4',
        'is_pickup_only' => 'boolean',
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
     * Аккумуляторы, подходящие автомобилю.
     */
    #[Scope]
    protected function fitsBattery(Builder $query, BatteryFitment $fitment): void
    {
        $query
            ->whereHas('categories', fn (Builder $categories) => $categories
                ->whereIn('categories.id', config('shop.battery_fitment.category_ids')))
            ->withAttributeValues($fitment->matchingAttributeValueIds());
    }

    /**
     * Условия по характеристикам: внутри одного условия значения через «ИЛИ»
     * (60 или 62 Ah), между условиями — «И» (и ёмкость, и полярность).
     * null вместо списка — условия нет; пустой список — ничего не подходит.
     *
     * @param  array<array-key, list<int>|null>  $valueIdsByCondition
     */
    #[Scope]
    protected function withAttributeValues(Builder $query, array $valueIdsByCondition): void
    {
        foreach ($valueIdsByCondition as $valueIds) {
            if ($valueIds === null) {
                continue;
            }

            $query->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $valueIds));
        }
    }

    /**
     * Фильтр каталога: производитель, характеристики, цена, наличие.
     */
    #[Scope]
    protected function catalogFilter(Builder $query, CatalogFilter $filter): void
    {
        if ($filter->manufacturerIds !== []) {
            $query->whereIn('manufacturer_id', $filter->manufacturerIds);
        }

        $query->withAttributeValues($filter->attributeValueIds);

        if ($filter->priceFrom !== null) {
            $query->where('price', '>=', $filter->priceFrom);
        }

        if ($filter->priceTo !== null) {
            $query->where('price', '<=', $filter->priceTo);
        }

        if ($filter->onlyAvailable) {
            $query->available();
        }
    }

    /**
     * Можно купить: есть на складе или продаётся под заказ (см. isAvailableOnOrder()).
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('quantity', '>', 0)
            ->orWhere('supplier_quantity', '>=', config('shop.supplier_order_min_quantity')));
    }

    public function hasTradeIn(): bool
    {
        return $this->trade_in_discount !== null && (float) $this->trade_in_discount > 0;
    }

    /**
     * Цена за единицу для покупателя с учётом трейд-ина (сдаёт старый АКБ).
     */
    public function priceFor(bool $withTradeIn): float
    {
        $price = $this->displayPrice();

        if ($withTradeIn && $this->hasTradeIn()) {
            $price -= (float) $this->trade_in_discount;
        }

        return max($price, 0.0);
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
