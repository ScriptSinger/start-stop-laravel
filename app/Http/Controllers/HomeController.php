<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $products = Product::query()
            ->where('status', true)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('home', [
            'products' => $products,
            'search' => $search,
            // Нужны тут же явно (не только в партиалах через composer) —
            // плитки "Популярные категории" рендерятся прямо в теле home.blade.php.
            'menuCategories' => Category::whereNull('parent_id')->where('status', true)->orderBy('sort_order')->get(),
        ]);
    }
}
