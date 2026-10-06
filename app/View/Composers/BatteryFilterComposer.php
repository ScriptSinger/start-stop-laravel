<?php

namespace App\View\Composers;

use App\Services\BatterySelection;
use App\Services\CarLanding\CarLandingCatalog;
use Illuminate\View\View;

/**
 * Марки автомобилей для блока подбора АКБ.
 */
class BatteryFilterComposer
{
    public function __construct(
        private readonly BatterySelection $selection,
        private readonly CarLandingCatalog $landings,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            ...$this->selection->brands(),
            // Обычные ссылки на страницы марок — по ним страницы находит и поисковик.
            'landingBrands' => $this->landings->brands(),
        ]);
    }
}
