<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BrandPage\Pages;

use App\MoonShine\Resources\BrandPage\BrandPageResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<BrandPageResource>
 */
class BrandPageDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Раздел', 'category', resource: CategoryResource::class),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class),
            Text::make('Заголовок H1', 'heading'),
            Text::make('Meta title', 'meta_title'),
            Textarea::make('Meta description', 'meta_description'),
        ];
    }
}
