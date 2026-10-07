<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MenuItem;

use App\Models\MenuItem;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\MenuItem\Pages\MenuItemDetailPage;
use App\MoonShine\Resources\MenuItem\Pages\MenuItemFormPage;
use App\MoonShine\Resources\MenuItem\Pages\MenuItemIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<MenuItem, MenuItemIndexPage, MenuItemFormPage, MenuItemDetailPage>
 */
class MenuItemResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = MenuItem::class;

    protected string $title = 'Меню сайта';

    protected string $column = 'title';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            MenuItemIndexPage::class,
            MenuItemFormPage::class,
            MenuItemDetailPage::class,
        ];
    }
}
