<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\TradeInType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Данные для блока фильтра категории: какие производители и значения
 * характеристик показывать и сколько товаров будет при выборе каждого
 * (с учётом остальных выбранных условий).
 */
class CatalogFacets
{
    /**
     * @return Collection<int, Manufacturer> с атрибутом count
     */
    public function manufacturers(Category $category, CatalogFilter $filter): Collection
    {
        $inCategory = $this->activeProductIds($category);
        $matching = $this->activeProductIds($category)->catalogFilter($filter->withoutManufacturers());

        return Manufacturer::query()
            ->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $inCategory))
            ->withCount(['products as count' => fn (Builder $products) => $products->whereIn('products.id', $matching)])
            ->orderBy('name')
            ->get();
    }

    /**
     * Типы корпуса, которые есть у товаров категории, и сколько товаров будет при выборе
     * каждого (с учётом остальных условий). Типы без товаров в категории не показываются.
     *
     * @return Collection<int, TradeInType> с атрибутом count
     */
    public function caseTypes(Category $category, CatalogFilter $filter): Collection
    {
        $inCategory = $this->activeProductIds($category);
        $matching = $this->activeProductIds($category)->catalogFilter($filter->withoutCaseTypes());

        return TradeInType::query()
            ->active()
            ->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $inCategory))
            ->withCount(['products as count' => fn (Builder $products) => $products->whereIn('products.id', $matching)])
            ->ordered()
            ->get();
    }

    /**
     * Характеристики, привязанные к категории и отмеченные «Показывать в
     * фильтре», только со значениями, которые есть у товаров категории.
     *
     * @return Collection<int, Attribute> со значениями (values) с атрибутом count
     */
    public function attributes(Category $category, CatalogFilter $filter): Collection
    {
        $inCategory = $this->activeProductIds($category);

        return $category->productAttributes()
            ->where('is_filterable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (Attribute $attribute) use ($category, $filter, $inCategory): void {
                $matching = $this->activeProductIds($category)->catalogFilter($filter->withoutAttribute($attribute->id));

                $values = $attribute->values()
                    ->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $inCategory))
                    ->withCount(['products as count' => fn (Builder $products) => $products->whereIn('products.id', $matching)])
                    ->get();

                // Габариты — по размеру (длина, ширина, высота), а не в порядке старого справочника;
                // то, что не разобралось как размеры, в конце.
                if ($attribute->id === (int) config('shop.battery_fitment.attributes.dimensions')) {
                    $values = $values->sortBy(fn (AttributeValue $value): string => ($size = TradeInTypeMatcher::parseDimensions($value->value)) === null
                        ? '~'.$value->value
                        : sprintf('%04d%04d%04d', ...$size))->values();
                }

                $attribute->setRelation('values', $values);
            })
            ->filter(fn (Attribute $attribute): bool => $attribute->values->isNotEmpty())
            ->values();
    }

    /**
     * Минимальная и максимальная цена активных товаров категории — подсказки в полях цены.
     *
     * @return array{min: float, max: float}
     */
    public function priceBounds(Category $category): array
    {
        $bounds = Product::query()
            ->whereIn('id', $this->activeProductIds($category))
            ->selectRaw('MIN('.Product::displayPriceSql().') as min_price, MAX('.Product::displayPriceSql().') as max_price', [
                config('shop.supplier_order_min_quantity'),
                config('shop.supplier_order_min_quantity'),
            ])
            ->toBase()
            ->first();

        return ['min' => (float) ($bounds->min_price ?? 0), 'max' => (float) ($bounds->max_price ?? 0)];
    }

    /**
     * Подзапрос id активных товаров категории вместе с подкатегориями.
     *
     * @return Builder<Product>
     */
    private function activeProductIds(Category $category): Builder
    {
        return Product::query()
            ->select('products.id')
            ->where('status', true)
            ->whereIn('products.id', DB::table('category_product')
                ->select('product_id')
                ->whereIn('category_id', $category->treeIds()));
    }
}
