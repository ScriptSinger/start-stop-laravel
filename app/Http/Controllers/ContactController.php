<?php

namespace App\Http\Controllers;

use App\Services\Seo\ShopSeo;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(ShopSeo $shopSeo): View
    {
        SEOTools::setTitle('Связаться с нами');
        SEOTools::setDescription('Адрес, телефон и схема проезда магазина автомобильных аккумуляторов «'.config('shop.name').'» в Уфе.');
        $shopSeo->apply('Магазин автомобильных аккумуляторов в Уфе: '.config('shop.address').'.');

        return view('contact');
    }
}
