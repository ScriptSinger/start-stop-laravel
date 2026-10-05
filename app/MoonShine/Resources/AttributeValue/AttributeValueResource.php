<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AttributeValue;

use App\Models\AttributeValue;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueDetailPage;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueFormPage;
use App\MoonShine\Resources\AttributeValue\Pages\AttributeValueIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<AttributeValue, AttributeValueIndexPage, AttributeValueFormPage, AttributeValueDetailPage>
 */
class AttributeValueResource extends ModelResource
{
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
