<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BrandPage\Pages;

use App\MoonShine\Resources\BrandPage\BrandPageResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<BrandPageResource>
 */
class BrandPageIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Раздел', 'category', resource: CategoryResource::class),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class),
            Text::make('Meta title', 'meta_title'),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Раздел', 'category', resource: CategoryResource::class)->nullable(),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class)->nullable(),
        ];
    }
}
