<?php

namespace App\Models;

use App\Enums\CatalogSort;
use App\Enums\ProductSelection;
use App\Models\Concerns\HasHtmlDescription;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasHtmlDescription;

    protected $fillable = [
        'manufacturer_id',
        'name',
        'heading',
        'meta_title',
        'meta_description',
        'slug',
        'sku',
        'code',
        'description',
        'price',
        'special_price',
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
        'special_price' => 'decimal:4',
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
     * Есть цена по акции ниже обычной (на витрине — старая цена зачёркнута).
     */
    public function hasSpecial(): bool
    {
        return $this->special_price !== null && (float) $this->special_price < (float) $this->price;
    }

    /**
     * Цена для покупателя, как в теме OpenCart: цена по акции, иначе под
     * заказ — цена поставщика (если задана), иначе обычная.
     */
    public function displayPrice(): float
    {
        return (float) match (true) {
            $this->hasSpecial() => $this->special_price,
            $this->isAvailableOnOrder() && $this->supplier_price !== null => $this->supplier_price,
            default => $this->price,
        };
    }

    /**
     * Характеристики в порядке карточки товара: «Напряжение» => «12V».
     * Несколько значений одной характеристики — через запятую. Нужна
     * загруженная связь attributeValues.attribute (см. withCardData()).
     *
     * @return Collection<string, string>
     */
    public function specifications(?int $limit = null): Collection
    {
        return $this->attributeValues
            ->sortBy([['attribute.display_sort_order', 'asc'], ['attribute.name', 'asc'], ['sort_order', 'asc']])
            ->groupBy('attribute.name')
            ->map(fn (Collection $values): string => $values->pluck('value')->implode(', '))
            ->when($limit !== null, fn (Collection $specifications) => $specifications->take($limit));
    }

    /**
     * Связи, нужные карточке товара в списках, — без запросов на каждую карточку.
     */
    #[Scope]
    protected function withCardData(Builder $query): void
    {
        $query->with('attributeValues.attribute');
    }

    /**
     * Скидка по акции в рублях — для наклейки «Ваша скидка: …».
     */
    public function specialDiscount(): float
    {
        return $this->hasSpecial() ? (float) $this->price - (float) $this->special_price : 0.0;
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
            ->withAttributeValues($fitment->matchingAttributeValueIds())
            // Клеммы проверяем, только если у АКБ указаны «Токовыводы». Пустой
            // список — подходящих клемм нет ни у одного товара: только АКБ без них.
            ->when(($valueIds = $fitment->terminalValueIds()) !== null, fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $valueIds))
                    ->orWhereDoesntHave('attributeValues', fn (Builder $values) => $values->where('attribute_values.attribute_id', config('shop.battery_fitment.attributes.terminals')))));
    }

    /**
     * Товары категории вместе с её подкатегориями.
     */
    #[Scope]
    protected function inCategoryTree(Builder $query, Category $category): void
    {
        $query->whereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $category->treeIds()));
    }

    /**
     * Аккумуляторы, которых подбор по машине не покажет никогда: не
     * заполнена полярность, ёмкость или габариты.
     */
    #[Scope]
    protected function missingFitmentData(Builder $query): void
    {
        $attributes = config('shop.battery_fitment.attributes');

        $query
            ->whereHas('categories', fn (Builder $categories) => $categories
                ->whereIn('categories.id', config('shop.battery_fitment.category_ids')))
            ->where(function (Builder $query) use ($attributes): void {
                foreach (['polarity', 'capacity_range', 'dimensions'] as $criterion) {
                    $query->orWhereDoesntHave('attributeValues', fn (Builder $values) => $values
                        ->where('attribute_values.attribute_id', $attributes[$criterion]));
                }
            });
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
     * Поиск как в OpenCart: каждое слово запроса есть в названии,
     * либо запрос — часть кода товара.
     */
    #[Scope]
    protected function matchingSearch(Builder $query, string $search): void
    {
        $words = preg_split('/\s+/u', trim($search), flags: PREG_SPLIT_NO_EMPTY);

        if ($words === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->whereLike('products.name', '%'.self::escapeLike($word).'%');
                }
            })
            ->orWhereLike('products.code', '%'.self::escapeLike(trim($search)).'%'));
    }

    /**
     * «%» и «_» из запроса — обычные символы, а не шаблон LIKE.
     */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Порядок товаров в каталоге. Как на старом сайте (настройка темы
     * sort_qty = 2), товары в наличии всегда идут первыми. Цена — с учётом
     * акции; последним ключом id, чтобы товары не «прыгали» между страницами.
     */
    #[Scope]
    protected function sortedBy(Builder $query, CatalogSort $sort): void
    {
        $direction = $sort->isDescending() ? 'desc' : 'asc';

        $query->orderByRaw('products.quantity > 0 desc');

        match (true) {
            $sort->isByPrice() => $query->orderByRaw("COALESCE(products.special_price, products.price) {$direction}"),
            $sort->isByName() => $query->orderBy('products.name', $direction),
            default => $query->orderBy('products.name'),
        };

        $query->orderBy('products.id');
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

    /**
     * Можно отдавать в выгрузку (прайс, Яндекс Маркет): продаётся, есть
     * цена, фото, производитель и категория — без них площадки товар не примут.
     */
    #[Scope]
    protected function readyForExport(Builder $query): void
    {
        $query->where('status', true)
            ->available()
            ->where('price', '>', 0)
            ->whereNotNull('image')
            ->whereNotNull('manufacturer_id')
            ->has('categories');
    }

    /**
     * Ошибка в цене: нулевая цена или «цена по акции» не ниже обычной
     * (акция тогда не показывается, а в выгрузке будет странная скидка).
     */
    #[Scope]
    protected function withPriceProblem(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('price', '<=', 0)
            ->orWhere(fn (Builder $query) => $query->whereNotNull('special_price')->whereColumn('special_price', '>=', 'price')));
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

    /**
     * Товары подборки главной в заданном порядке.
     */
    #[Scope]
    protected function inSelection(Builder $query, ProductSelection $selection): void
    {
        $query->join('product_selection', 'product_selection.product_id', '=', 'products.id')
            ->where('product_selection.selection', $selection)
            ->orderBy('product_selection.sort_order')
            ->select('products.*');
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
