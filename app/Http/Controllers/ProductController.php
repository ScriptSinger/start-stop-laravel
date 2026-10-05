<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        $product->load(['manufacturer', 'categories', 'images']);

        return view('product', [
            'product' => $product,
        ]);
    }
}
