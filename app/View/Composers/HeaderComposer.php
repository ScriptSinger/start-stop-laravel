<?php

namespace App\View\Composers;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Services\Catalog\CategoryMenu;
use Illuminate\View\View;

/**
 * Шапка витрины: ссылки над шапкой, меню категорий и главное меню.
 */
class HeaderComposer
{
    public function __construct(private readonly CategoryMenu $categoryMenu) {}

    public function compose(View $view): void
    {
        $view->with([
            'topLinks' => MenuItem::query()->forLocation(MenuLocation::TopLinks)->get(),
            'mainMenu' => MenuItem::query()->forLocation(MenuLocation::Main)->get(),
            'categoryMenu' => $this->categoryMenu->items(),
        ]);
    }
}
