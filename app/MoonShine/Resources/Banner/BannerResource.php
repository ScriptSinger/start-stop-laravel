<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Banner;

use App\Models\Banner;
use App\MoonShine\Resources\Banner\Pages\BannerDetailPage;
use App\MoonShine\Resources\Banner\Pages\BannerFormPage;
use App\MoonShine\Resources\Banner\Pages\BannerIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Banner, BannerIndexPage, BannerFormPage, BannerDetailPage>
 */
class BannerResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Banner::class;

    protected string $title = 'Баннеры';

    protected string $column = 'title';

    // В порядке показа на витрине.
    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            BannerIndexPage::class,
            BannerFormPage::class,
            BannerDetailPage::class,
        ];
    }
}
