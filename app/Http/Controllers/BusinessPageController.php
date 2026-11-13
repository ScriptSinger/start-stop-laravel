<?php

namespace App\Http\Controllers;

use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\View\View;

class BusinessPageController extends Controller
{
    public function __invoke(): View
    {
        SEOTools::setTitle('Для организаций');
        SEOTools::setDescription('Аккумуляторы для организаций в Уфе. Подбор для служебного транспорта и автопарков, доставка по городу и самовывоз. Свяжитесь с магазином Старт-Стоп.');

        return view('business');
    }
}
