<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        $product->load(['manufacturer', 'categories', 'images', 'attributeValues.attribute']);

        // Группируем значения по характеристике (у одного товара может быть
        // несколько значений одной характеристики — например, технология).
        $specifications = $product->attributeValues
            ->sortBy([['attribute.sort_order', 'asc'], ['attribute.name', 'asc'], ['sort_order', 'asc']])
            ->groupBy('attribute.name')
            ->map(fn ($values) => $values->pluck('value')->implode(', '));

        return view('product', [
            'product' => $product,
            'specifications' => $specifications,
        ]);
    }
}
