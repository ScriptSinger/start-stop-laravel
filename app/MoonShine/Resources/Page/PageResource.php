<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Page;

use App\Models\Page;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Page\Pages\PageDetailPage;
use App\MoonShine\Resources\Page\Pages\PageFormPage;
use App\MoonShine\Resources\Page\Pages\PageIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Page, PageIndexPage, PageFormPage, PageDetailPage>
 */
class PageResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Page::class;

    protected string $title = 'Страницы';

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
            PageIndexPage::class,
            PageFormPage::class,
            PageDetailPage::class,
        ];
    }
}
