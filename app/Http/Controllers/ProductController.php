<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CarLanding\CarLandingCatalog;
use App\Services\Catalog\BrandPages;
use App\Services\Catalog\GuideLocator;
use App\Services\Catalog\SimilarProducts;
use App\Services\Seo\ProductSeo;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product, SimilarProducts $similarProducts, ProductSeo $seo, CarLandingCatalog $carLandings, GuideLocator $guides, BrandPages $brandPages): View
    {
        abort_unless($product->status, 404);

        $product->load(['manufacturer', 'categories', 'images', 'attributeValues.attribute', 'tradeInType']);
        $seo->apply($product);

        return view('product', [
            'product' => $product,
            'manufacturerUrl' => $product->manufacturer && ($category = $product->categories->first()) ? $brandPages->url($category, $product->manufacturer) : null,
            'specifications' => $product->specifications(visibleOnly: true),
            'specificationGroups' => $product->specificationGroups(visibleOnly: true),
            'similarProducts' => $similarProducts->for($product),
            'carLinks' => $carLandings->linksForProduct($product->id),
            'guide' => $guides->forProduct($product),
            'caseType' => $product->tradeInType?->code,
            // После какой характеристики показывать «Тип корпуса»: после «Габаритов»; нет их — в конце.
            'caseTypeAfter' => $product->specificationGroups(visibleOnly: true)
                ->search(fn ($values): bool => $values->first()->attribute_id === (int) config('shop.battery_fitment.attributes.dimensions')),
        ]);
    }
}
