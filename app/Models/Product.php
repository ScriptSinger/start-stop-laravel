<?php

namespace App\Models;

use App\Enums\CatalogSort;
use App\Enums\ProductSelection;
use App\Jobs\GenerateProductThumbnail;
use App\Models\Concerns\HasHtmlDescription;
use App\Services\Catalog\CatalogFilter;
use App\Services\Supplier\SupplierDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasHtmlDescription;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if ($product->isDirty('image')) {
                $product->thumbnail_path = null;
            }
        });

        static::created(function (self $product): void {
            if (filled($product->image) && $product->image !== 'no_image.png') {
                GenerateProductThumbnail::dispatch($product->getKey(), $product->image)->afterCommit();
            }
        });

        static::updated(function (self $product): void {
            if ($product->wasChanged('image') && filled($product->image) && $product->image !== 'no_image.png') {
                GenerateProductThumbnail::dispatch($product->getKey(), $product->image)->afterCommit();
            }
        });
    }

    protected $fillable = [
        'manufacturer_id',
        'trade_in_type_id',
        'name',
        'heading',
        'meta_title',
        'meta_description',
        'slug',
        'sku',
        'external_id',
        'code',
        'description',
        'price',
        'special_price',
        'quantity',
        'supplier_quantity',
        'supplier_price',
        'supplier_id',
        'is_pickup_only',
        'image',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'special_price' => 'decimal:4',
        'supplier_price' => 'decimal:4',
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

    /** SQL-выражение с тем же приоритетом цен, что и displayPrice(). */
    public static function displayPriceSql(): string
    {
        return 'CASE WHEN products.special_price IS NOT NULL AND products.special_price < products.price THEN products.special_price '
            .'WHEN products.quantity <= 0 AND products.supplier_quantity >= ? AND products.supplier_price IS NOT NULL THEN products.supplier_price '
            .'ELSE products.price END';
    }

    /**
     * Характеристики в порядке карточки товара: «Напряжение» => «12V».
     * Несколько значений одной характеристики — через запятую. С $visibleOnly
     * служебные характеристики (show_on_card = 0) пропускаются: так их видит
     * покупатель, в админке — все. Нужна
     * загруженная связь attributeValues.attribute (см. withCardData()).
     *
     * @return Collection<string, string>
     */
    public function specifications(?int $limit = null, bool $visibleOnly = false): Collection
    {
        return $this->specificationGroups($limit, $visibleOnly)
            ->map(fn (Collection $values): string => $values->pluck('value')->implode(', '));
    }

    /**
     * Характеристики в том виде и порядке, как в блоке «Характеристики» карточки
     * (ProductController::show): видимые покупателю, а «Тип корпуса» (код из
     * поля товара) — после «Габаритов», нет их — в конце. Без типа корпуса
     * строки нет. Нужны связи attributeValues.attribute и tradeInType.
     *
     * @return Collection<string, string>
     */
    public function cardSpecifications(): Collection
    {
        $specifications = $this->specifications(visibleOnly: true);
        $caseType = $this->tradeInType?->code;

        if (blank($caseType)) {
            return $specifications;
        }

        $after = $this->specificationGroups(visibleOnly: true)
            ->search(fn (Collection $values): bool => $values->first()->attribute_id === (int) config('shop.battery_fitment.attributes.dimensions'));

        if ($after === false) {
            return $specifications->put('Тип корпуса', $caseType);
        }

        return $specifications->flatMap(fn (string $value, string $name): array => $name === $after
            ? [$name => $value, 'Тип корпуса' => $caseType]
            : [$name => $value]);
    }

    /**
     * Те же характеристики, но со значениями-моделями: страница товара по ним
     * строит значок «i» с подсказкой у названия и у отдельных значений.
     *
     * @return Collection<string, Collection<int, AttributeValue>>
     */
    public function specificationGroups(?int $limit = null, bool $visibleOnly = false): Collection
    {
        return $this->attributeValues
            ->when($visibleOnly, fn (Collection $values) => $values->filter(fn (AttributeValue $value): bool => $value->attribute->showsOnCard()))
            ->sortBy([['attribute.display_sort_order', 'asc'], ['attribute.name', 'asc'], ['sort_order', 'asc']])
            ->groupBy('attribute.name')
            ->when($limit !== null, fn (Collection $groups) => $groups->take($limit));
    }

    /**
     * Связи, нужные карточке товара в списках, — без запросов на каждую карточку.
     */
    #[Scope]
    protected function withCardData(Builder $query): void
    {
        $query->with(['attributeValues.attribute', 'tradeInType']);
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
        $criteria = $fitment->matchingAttributeValueIds();
        $dimensionIds = $criteria['dimensions'] ?? null;
        unset($criteria['dimensions']);

        $query
            ->whereHas('categories', fn (Builder $categories) => $categories
                ->whereIn('categories.id', config('shop.battery_fitment.category_ids')))
            ->withAttributeValues($criteria)
            // Габариты — размеры корпуса. Типы без размеров (грузовые Груз A/B/C) ими не
            // ограничиваются: такой АКБ подбирается по ёмкости и полярности. Тип берётся
            // из поля «Тип корпуса», а не из названия значения «Габаритов».
            ->when($dimensionIds !== null, fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $dimensionIds))
                    ->orWhereHas('tradeInType', fn (Builder $type) => $type->whereNull('length_min'))))
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

        if ($filter->caseTypeIds !== []) {
            $query->whereIn('trade_in_type_id', $filter->caseTypeIds);
        }

        $query->withAttributeValues($filter->attributeValueIds);

        if ($filter->priceFrom !== null) {
            $query->whereRaw('('.self::displayPriceSql().') >= CAST(? AS DECIMAL(20, 4))', [config('shop.supplier_order_min_quantity'), $filter->priceFrom]);
        }

        if ($filter->priceTo !== null) {
            $query->whereRaw('('.self::displayPriceSql().') <= CAST(? AS DECIMAL(20, 4))', [config('shop.supplier_order_min_quantity'), $filter->priceTo]);
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
     * Порядок товаров в каталоге. Если покупатель сортировку не выбирал,
     * товары в наличии идут первыми, как на старом сайте. Выбранная им
     * сортировка ($chosen) по цене или названию — чистая, по всему списку:
     * иначе на границе «в наличии» / «нет в наличии» цена или буква прыгает
     * назад и сортировка выглядит сломанной. Цена — с учётом акции; последним
     * ключом id, чтобы товары не «прыгали» между страницами.
     */
    #[Scope]
    protected function sortedBy(Builder $query, CatalogSort $sort, bool $chosen = false): void
    {
        $direction = $sort->isDescending() ? 'desc' : 'asc';

        if (! $chosen) {
            $query->orderByRaw('products.quantity > 0 desc');
        }

        match (true) {
            $sort->isByPrice() => $query->orderByRaw('('.self::displayPriceSql().") {$direction}", [config('shop.supplier_order_min_quantity')]),
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
     * цена для покупателя (под заказ — цена поставщика), фото, производитель
     * и категория — без них площадки товар не примут.
     */
    #[Scope]
    protected function readyForExport(Builder $query): void
    {
        $query->where('status', true)
            ->available()
            ->whereRaw('('.self::displayPriceSql().') > 0', [config('shop.supplier_order_min_quantity')])
            ->whereNotNull('image')
            ->whereNotNull('manufacturer_id')
            ->has('categories');
    }

    /**
     * Ошибка в цене: нулевая цена для покупателя (у товара под заказ это цена
     * поставщика, а не обычная) или «цена по акции» не ниже обычной (акция
     * тогда не показывается, а в выгрузке будет странная скидка).
     */
    #[Scope]
    protected function withPriceProblem(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereRaw('('.self::displayPriceSql().') <= 0', [config('shop.supplier_order_min_quantity')])
            ->orWhere(fn (Builder $query) => $query->whereNotNull('special_price')->whereColumn('special_price', '>=', 'price')));
    }

    /**
     * Скидка за сдачу старого АКБ — из типа корпуса товара (поле «Тип корпуса»).
     * Единственный источник суммы — таблица типов (админка → Трейд-ин); у товара
     * своей скидки нет. Нужна связь tradeInType (см. withCardData()).
     */
    public function tradeInDiscount(): float
    {
        return $this->tradeInType?->is_active ? max((float) $this->tradeInType->amount, 0.0) : 0.0;
    }

    public function hasTradeIn(): bool
    {
        return $this->tradeInDiscount() > 0;
    }

    /**
     * Цена за единицу для покупателя с учётом трейд-ина (сдаёт старый АКБ).
     */
    public function priceFor(bool $withTradeIn): float
    {
        $price = $this->displayPrice();

        if ($withTradeIn && $this->hasTradeIn()) {
            $price -= $this->tradeInDiscount();
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

    public function tradeInType(): BelongsTo
    {
        return $this->belongsTo(TradeInType::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Поставщик, у которого товар берём под заказ (см. SupplierStockSync).
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Когда ждать товар под заказ: рабочие дни поставщика от сегодняшнего
     * дня, без выходных. null — не под заказ или поставщик не указан.
     */
    public function onOrderDate(): ?CarbonImmutable
    {
        if (! $this->isAvailableOnOrder() || $this->supplier === null) {
            return null;
        }

        return SupplierDelivery::expectedDate(CarbonImmutable::now(), $this->supplier->lead_working_days);
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
