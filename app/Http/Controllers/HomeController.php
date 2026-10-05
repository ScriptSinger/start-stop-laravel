<?php

namespace App\Http\Controllers;

use App\Enums\BannerPosition;
use App\Enums\ProductSelection;
use App\Models\Banner;
use App\Models\Product;
use App\Services\Catalog\CategoryWall;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(CategoryWall $categoryWall): View
    {
        return view('home', [
            'sliderBanners' => Banner::query()->shownAt(BannerPosition::HomeSlider)->get(),
            'stripBanners' => Banner::query()->shownAt(BannerPosition::HomeStrip)->get(),
            'categoryWall' => $categoryWall->items(),
            'selections' => collect(ProductSelection::cases())->mapWithKeys(fn (ProductSelection $selection): array => [
                $selection->value => Product::query()->inSelection($selection)->where('products.status', true)->withCardData()->get(),
            ]),
        ]);
    }
}
