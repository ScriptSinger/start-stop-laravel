<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\MenuManager\MenuItem;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\BatteryFitment\BatteryFitmentResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;

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
            MenuItem::make(ProductResource::class, 'Products'),
            MenuItem::make(CategoryResource::class, 'Categories'),
            MenuItem::make(BatteryFitmentResource::class, 'BatteryFitments'),
            MenuItem::make(ManufacturerResource::class, 'Manufacturers'),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
