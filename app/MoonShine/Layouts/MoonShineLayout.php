<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\Attribute\AttributeResource;
use App\MoonShine\Resources\BatteryFitment\BatteryFitmentResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Customer\CustomerResource;
use App\MoonShine\Resources\CustomerRequest\CustomerRequestResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\MenuItem\MenuItemResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\Page\PageResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuItem::make(OrderResource::class, 'Заказы'),
            MenuItem::make(CustomerRequestResource::class, 'Заявки'),
            MenuItem::make(CustomerResource::class, 'Клиенты'),
            MenuGroup::make('Каталог', [
                MenuItem::make(ProductResource::class, 'Товары'),
                MenuItem::make(CategoryResource::class, 'Категории'),
                MenuItem::make(ManufacturerResource::class, 'Производители'),
                MenuItem::make(AttributeResource::class, 'Характеристики'),
                MenuItem::make(BatteryFitmentResource::class, 'Подбор АКБ'),
            ]),
            MenuItem::make(PageResource::class, 'Страницы'),
            MenuItem::make(MenuItemResource::class, 'Меню сайта'),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
