<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $filter, Category $category): View
    {
        $subcategories = Category::where('parent_id', $category->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();

        $products = $category->products()
            ->where('status', true)
            ->catalogFilter($filter)
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('category', [
            'category' => $category,
            'subcategories' => $subcategories,
            'products' => $products,
            'filter' => $filter,
            'manufacturerFacet' => $this->manufacturerFacet($category, $filter),
            'attributeFacets' => $this->attributeFacets($category, $filter),
            'priceBounds' => $category->products()->where('status', true)
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->toBase()
                ->first(),
        ]);
    }

    /**
     * Производители товаров категории; count — сколько товаров будет с учётом
     * остальных выбранных условий фильтра.
     *
     * @return Collection<int, Manufacturer>
     */
    private function manufacturerFacet(Category $category, CatalogFilterRequest $filter): Collection
    {
        $inCategory = $this->activeProductIds($category);
        $matching = $this->activeProductIds($category)->catalogFilter($filter, except: 'manufacturer');

        return Manufacturer::query()
            ->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $inCategory))
            ->withCount(['products as count' => fn (Builder $products) => $products->whereIn('products.id', $matching)])
            ->orderBy('name')
            ->get();
    }

    /**
     * Характеристики, привязанные к категории и отмеченные «Показывать в
     * фильтре», только со значениями, которые есть у товаров категории.
     *
     * @return Collection<int, Attribute>
     */
    private function attributeFacets(Category $category, CatalogFilterRequest $filter): Collection
    {
        $inCategory = $this->activeProductIds($category);

        return $category->productAttributes()
            ->where('is_filterable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (Attribute $attribute) use ($category, $filter, $inCategory): void {
                $matching = $this->activeProductIds($category)->catalogFilter($filter, except: "attr:{$attribute->id}");

                $attribute->setRelation('values', $attribute->values()
                    ->whereHas('products', fn (Builder $products) => $products->whereIn('products.id', $inCategory))
                    ->withCount(['products as count' => fn (Builder $products) => $products->whereIn('products.id', $matching)])
                    ->get());
            })
            ->filter(fn (Attribute $attribute): bool => $attribute->values->isNotEmpty())
            ->values();
    }

    /**
     * Подзапрос id активных товаров категории.
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
                ->where('category_id', $category->id));
    }
}
