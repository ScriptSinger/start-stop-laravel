<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Manufacturer;

use App\Models\Manufacturer;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerDetailPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerFormPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Manufacturer, ManufacturerIndexPage, ManufacturerFormPage, ManufacturerDetailPage>
 */
class ManufacturerResource extends ModelResource
{
    protected string $model = Manufacturer::class;

    protected string $title = 'Производители';

    protected string $column = 'name';

    protected string $sortColumn = 'name';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('products');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ManufacturerIndexPage::class,
            ManufacturerFormPage::class,
            ManufacturerDetailPage::class,
        ];
    }
}
