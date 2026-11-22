<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BrandPage;

use App\Models\BrandPage;
use App\MoonShine\Resources\BrandPage\Pages\BrandPageDetailPage;
use App\MoonShine\Resources\BrandPage\Pages\BrandPageFormPage;
use App\MoonShine\Resources\BrandPage\Pages\BrandPageIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * Тексты страниц «раздел + производитель».
 *
 * @extends ModelResource<BrandPage, BrandPageIndexPage, BrandPageFormPage, BrandPageDetailPage>
 */
class BrandPageResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = BrandPage::class;

    protected string $title = 'Страницы производителей';

    protected string $column = 'id';

    protected string $sortColumn = 'id';

    protected SortDirection $sortDirection = SortDirection::DESC;

    /**
     * @var list<string>
     */
    protected array $with = ['category', 'manufacturer'];

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            BrandPageIndexPage::class,
            BrandPageFormPage::class,
            BrandPageDetailPage::class,
        ];
    }
}
