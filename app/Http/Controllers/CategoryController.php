<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use App\Services\Catalog\CatalogFacets;
use App\Services\Catalog\CatalogFilter;
use App\Services\Catalog\CategoryMenu;
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

    private function filteredProducts(Category $category, CatalogFilter $filter): BelongsToMany
    {
        return $category->products()
            ->where('status', true)
            ->catalogFilter($filter);
    }
}
