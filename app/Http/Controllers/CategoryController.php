<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(Category $category): View
    {
        $subcategories = Category::where('parent_id', $category->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();

        $products = $category->products()
            ->where('status', true)
            ->paginate(24);

        return view('category', [
            'category' => $category,
            'subcategories' => $subcategories,
            'products' => $products,
        ]);
    }
}
