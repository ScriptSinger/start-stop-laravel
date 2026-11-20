<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogFacets;
use App\Services\Catalog\CatalogFilter;
use App\Services\Catalog\CategoryMenu;
use App\Services\Catalog\GuideLocator;
use App\Services\Seo\BreadcrumbsSeo;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $request, Category $category, CatalogFacets $facets, CategoryMenu $categoryMenu, BreadcrumbsSeo $breadcrumbs, GuideLocator $guides): View
    {
        $filter = $request->toFilter();
        $priceBounds = $facets->priceBounds($category);

        $products = $this->filteredProducts($category, $filter)
            ->withCardData()
            ->sortedBy($request->sort())
            ->paginate($request->perPage())
            ->withQueryString();

        $this->describe($category, $products, $breadcrumbs);

        return view('category', [
            'category' => $category,
            'categoryLinks' => $categoryMenu->subcategoryLinks($category),
            'guide' => $guides->forCategory($category),
            'products' => $products,
            'filter' => $filter,
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
            'manufacturerFacet' => $facets->manufacturers($category, $filter),
            'attributeFacets' => $facets->attributes($category, $filter),
            // Границы шкалы цены — целыми рублями, наружу.
            'priceMin' => (int) floor($priceBounds['min']),
            'priceMax' => (int) ceil($priceBounds['max']),
        ]);
    }

    /**
     * Число товаров раздела с выбранным фильтром: на телефоне фильтр
     * применяется кнопкой «Показать N товаров», а не каждой галочкой.
     */
    public function count(CatalogFilterRequest $request, Category $category): JsonResponse
    {
        return response()->json([
            'count' => $this->filteredProducts($category, $request->toFilter())->count(),
        ]);
    }

    /**
     * Фильтры и сортировка — варианты страницы категории: canonical ведёт на
     * неё саму (как на старом сайте). Страницы пагинации — самостоятельные,
     * со ссылками prev/next без фильтров.
     */
    private function describe(Category $category, LengthAwarePaginator $products, BreadcrumbsSeo $breadcrumbs): void
    {
        $page = $products->currentPage();
        $title = $category->meta_title ?: $category->name;

        SEOTools::setTitle($page > 1 ? "{$title} — страница {$page}" : $title, appendDefault: ! $category->meta_title);
        SEOTools::setDescription($category->meta_description ?: "{$category->name} — купить в Уфе в магазине ".config('shop.name').'. Цены, характеристики, наличие. Доставка по городу, гарантия.');

        $breadcrumbs->apply(array_values(array_filter([
            ['name' => config('shop.name'), 'url' => route('home')],
            $category->parent ? ['name' => $category->parent->name, 'url' => route('category.show', $category->parent)] : null,
            ['name' => $category->name, 'url' => route('category.show', $category)],
        ])));

        $pageUrl = fn (int $page): string => route('category.show', $page > 1 ? [$category, 'page' => $page] : $category);

        SEOMeta::setCanonical($pageUrl($page));

        if ($page > 1) {
            SEOMeta::setPrev($pageUrl($page - 1));
        }

        if ($products->hasMorePages()) {
            SEOMeta::setNext($pageUrl($page + 1));
        }
    }

    /**
     * Товары раздела вместе с подразделами.
     *
     * @return Builder<Product>
     */
    private function filteredProducts(Category $category, CatalogFilter $filter): Builder
    {
        return Product::query()
            ->where('status', true)
            ->inCategoryTree($category)
            ->catalogFilter($filter);
    }
}
