<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Manufacturer;

use Illuminate\Database\Eloquent\Model;
use App\Models\Manufacturer;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerIndexPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerFormPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<Manufacturer, ManufacturerIndexPage, ManufacturerFormPage, ManufacturerDetailPage>
 */
class ManufacturerResource extends ModelResource
{
    protected string $model = Manufacturer::class;

    protected string $title = 'Производители';

    protected string $column = 'name';
    
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
