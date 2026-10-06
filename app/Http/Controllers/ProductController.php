<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Catalog\SimilarProducts;
use App\Services\Seo\ProductSeo;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product, SimilarProducts $similarProducts, ProductSeo $seo): View
    {
        abort_unless($product->status, 404);

        $product->load(['manufacturer', 'categories', 'images', 'attributeValues.attribute']);
        $seo->apply($product);

        return view('product', [
            'product' => $product,
            'specifications' => $product->specifications(),
            'similarProducts' => $similarProducts->for($product),
        ]);
    }
}
