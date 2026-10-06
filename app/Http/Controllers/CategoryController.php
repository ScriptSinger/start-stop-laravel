<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use App\Services\Catalog\CatalogFacets;
use App\Services\Catalog\CatalogFilter;
use App\Services\Catalog\CategoryMenu;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $request, Category $category, CatalogFacets $facets, CategoryMenu $categoryMenu): View
    {
        $filter = $request->toFilter();
        $priceBounds = $facets->priceBounds($category);

        $products = $this->filteredProducts($category, $filter)
            ->withCardData()
            ->sortedBy($request->sort())
            ->paginate($request->perPage())
            ->withQueryString();

        $this->describe($category, $products);

        return view('category', [
            'category' => $category,
            'categoryLinks' => $categoryMenu->subcategoryLinks($category),
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
    private function describe(Category $category, LengthAwarePaginator $products): void
    {
        SEOTools::setTitle($category->meta_title ?: $category->name, appendDefault: ! $category->meta_title);

        if (filled($category->meta_description)) {
            SEOTools::setDescription($category->meta_description);
        }

        $pageUrl = fn (int $page): string => route('category.show', $page > 1 ? [$category, 'page' => $page] : $category);
        $page = $products->currentPage();

        SEOMeta::setCanonical($pageUrl($page));

        if ($page > 1) {
            SEOMeta::setPrev($pageUrl($page - 1));
        }

        if ($products->hasMorePages()) {
            SEOMeta::setNext($pageUrl($page + 1));
        }
    }

    private function filteredProducts(Category $category, CatalogFilter $filter): BelongsToMany
    {
        return $category->products()
            ->where('status', true)
            ->catalogFilter($filter);
    }
}
