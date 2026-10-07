<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AttributeValue;

use App\Models\AttributeValue;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueDetailPage;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueFormPage;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<AttributeValue, AttributeValueIndexPage, AttributeValueFormPage, AttributeValueDetailPage>
 */
class AttributeValueResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = AttributeValue::class;

    protected string $title = 'Значения характеристик';

    protected string $column = 'value';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            AttributeValueIndexPage::class,
            AttributeValueFormPage::class,
            AttributeValueDetailPage::class,
        ];
    }
}
