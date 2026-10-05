<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Блоки страницы поиска вокруг списка товаров (product/search темы UniShop2).
 */
class SearchSuggestions
{
    /**
     * Плитки «Категории»: разделы, чьё название совпало с запросом, и бренды.
     * На старом сайте бренды АКБ были подкатегориями («Аккумуляторы → TITAN»),
     * поэтому запрос «TITAN» находил их; у нас это раздел с фильтром
     * по производителю — одна плитка на каждый раздел, где бренд продаётся.
     *
     * @return list<array{title: string, url: string}>
     */
    public function categoryLinks(string $search): array
    {
        if ($search === '') {
            return [];
        }

        $categories = Category::query()
            ->where('status', true)
            ->whereLike('name', '%'.$search.'%')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'title' => $category->name,
                'url' => route('category.show', $category),
            ]);

        $brands = Manufacturer::query()
            ->whereLike('manufacturers.name', '%'.$search.'%')
            ->join('products', fn ($join) => $join->on('products.manufacturer_id', '=', 'manufacturers.id')->where('products.status', true))
            ->join('category_product', 'category_product.product_id', '=', 'products.id')
            ->join('categories', fn ($join) => $join->on('categories.id', '=', 'category_product.category_id')
                ->whereNull('categories.parent_id')
                ->where('categories.status', true))
            ->select('manufacturers.id', 'manufacturers.name', 'categories.slug as category_slug', 'categories.name as category_name')
            ->distinct()
            ->orderBy('manufacturers.name')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (Manufacturer $brand): array => [
                'title' => $brand->name,
                'hint' => $brand->category_name,
                'url' => route('category.show', ['category' => $brand->category_slug, 'manufacturer' => [$brand->id]]),
            ]);

        return $categories->concat($brands)->values()->all();
    }

    /**
     * «Найдено в категориях»: разделы, к которым привязаны найденные товары.
     *
     * @return Collection<int, Category>
     */
    public function foundInCategories(string $search): Collection
    {
        if ($search === '') {
            return collect();
        }

        return Category::query()
            ->where('status', true)
            ->whereHas('products', fn (Builder $products) => $products->where('status', true)->matchingSearch($search))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Выбор раздела в форме поиска: разделы и их подразделы.
     *
     * @return Collection<int, Category>
     */
    public function categoryOptions(): Collection
    {
        $active = fn ($query) => $query->where('status', true)->orderBy('sort_order')->orderBy('name');

        return Category::query()
            ->whereNull('parent_id')
            ->where('status', true)
            ->with(['children' => $active, 'children.children' => $active])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
