<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Product;
use App\Services\Catalog\SearchSuggestions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Поиск по каталогу (на старом сайте — product/search).
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchSuggestions $suggestions): View
    {
        $search = $request->search();
        $category = $request->category();

        $products = Product::query()
            ->where('status', true)
            ->matchingSearch($search)
            ->when($category, fn (Builder $query) => $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($category->id)))
            ->withCardData()
            ->sortedBy($request->sort())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('search', [
            'search' => $search,
            'category' => $category,
            'products' => $products,
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
            'categoryOptions' => $suggestions->categoryOptions(),
            'categoryLinks' => $suggestions->categoryLinks($search),
            'foundInCategories' => $suggestions->foundInCategories($search),
        ]);
    }
}
