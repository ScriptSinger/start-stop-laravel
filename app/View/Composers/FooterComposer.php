<?php

namespace App\View\Composers;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\View\View;

/**
 * Колонки ссылок в подвале витрины.
 */
class FooterComposer
{
    public function compose(View $view): void
    {
        $view->with('footerColumns', MenuItem::query()->forLocation(MenuLocation::Footer)->get());
    }
}
