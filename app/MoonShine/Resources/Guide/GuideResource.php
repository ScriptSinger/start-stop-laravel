<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Guide;

use App\Models\Guide;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Guide\Pages\GuideDetailPage;
use App\MoonShine\Resources\Guide\Pages\GuideFormPage;
use App\MoonShine\Resources\Guide\Pages\GuideIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Guide, GuideIndexPage, GuideFormPage, GuideDetailPage>
 */
class GuideResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Guide::class;

    protected string $title = 'Гайды';

    protected string $column = 'title';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'title', 'slug'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            GuideIndexPage::class,
            GuideFormPage::class,
            GuideDetailPage::class,
        ];
    }
}
