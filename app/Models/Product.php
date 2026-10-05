<?php

namespace App\Models;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Concerns\HasHtmlDescription;
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
     * Фильтр каталога. $except — условие, которое не применять: так считаются
     * счётчики в блоке фильтра («сколько будет, если отметить ещё и это»).
     * Значения: 'manufacturer' или 'attr:<id характеристики>'.
     */
    #[Scope]
    protected function catalogFilter(Builder $query, CatalogFilterRequest $filter, ?string $except = null): void
    {
        $manufacturerIds = $filter->manufacturerIds();

        if ($manufacturerIds !== [] && $except !== 'manufacturer') {
            $query->whereIn('manufacturer_id', $manufacturerIds);
        }

        $query->withAttributeValues(array_filter(
            $filter->attributeValueIds(),
            fn (int $attributeId): bool => $except !== "attr:{$attributeId}",
            ARRAY_FILTER_USE_KEY,
        ));

        if (($priceFrom = $filter->priceFrom()) !== null) {
            $query->where('price', '>=', $priceFrom);
        }

        if (($priceTo = $filter->priceTo()) !== null) {
            $query->where('price', '<=', $priceTo);
        }

        if ($filter->onlyAvailable()) {
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
