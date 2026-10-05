<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Category;
use App\Services\Catalog\CatalogFacets;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $request, Category $category, CatalogFacets $facets): View
    {
        $filter = $request->toFilter();

        $subcategories = Category::where('parent_id', $category->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();

        $products = $category->products()
            ->where('status', true)
            ->catalogFilter($filter)
            ->orderBy('name')
            ->orderBy('products.id')
            ->paginate(24)
            ->withQueryString();

        return view('category', [
            'category' => $category,
            'subcategories' => $subcategories,
            'products' => $products,
            'filter' => $filter,
            'manufacturerFacet' => $facets->manufacturers($category, $filter),
            'attributeFacets' => $facets->attributes($category, $filter),
            'priceBounds' => $facets->priceBounds($category),
        ]);
    }
}
