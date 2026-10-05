<?php

namespace App\View\Composers;

use App\Services\BatterySelection;
use Illuminate\View\View;

/**
 * Марки автомобилей для блока подбора АКБ.
 */
class BatteryFilterComposer
{
    public function __construct(private readonly BatterySelection $selection) {}

    public function compose(View $view): void
    {
        $view->with($this->selection->brands());
    }
}
