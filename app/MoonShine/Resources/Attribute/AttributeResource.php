<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Attribute;

use App\Models\Attribute;
use App\MoonShine\Resources\Attribute\Pages\AttributeDetailPage;
use App\MoonShine\Resources\Attribute\Pages\AttributeFormPage;
use App\MoonShine\Resources\Attribute\Pages\AttributeIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Attribute, AttributeIndexPage, AttributeFormPage, AttributeDetailPage>
 */
class AttributeResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Attribute::class;

    protected string $title = 'Характеристики';

    protected string $column = 'name';

    protected array $with = ['categories'];

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            AttributeIndexPage::class,
            AttributeFormPage::class,
            AttributeDetailPage::class,
        ];
    }
}
