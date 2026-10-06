<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use App\Services\Catalog\CatalogFacets;
use App\Services\Catalog\CategoryMenu;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $request, Category $category, CatalogFacets $facets, CategoryMenu $categoryMenu): View
    {
        $filter = $request->toFilter();

        $products = $category->products()
            ->where('status', true)
            ->catalogFilter($filter)
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
            'priceBounds' => $facets->priceBounds($category),
        ]);
    }
}
