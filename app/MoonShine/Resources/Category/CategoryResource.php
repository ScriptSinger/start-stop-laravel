<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category;

use App\Models\Category;
use App\MoonShine\Resources\Category\Pages\CategoryDetailPage;
use App\MoonShine\Resources\Category\Pages\CategoryFormPage;
use App\MoonShine\Resources\Category\Pages\CategoryIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<Category, CategoryIndexPage, CategoryFormPage, CategoryDetailPage>
 */
class CategoryResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Category::class;

    protected string $title = 'Категории';

    protected string $column = 'name';

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name', 'slug'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            CategoryIndexPage::class,
            CategoryFormPage::class,
            CategoryDetailPage::class,
        ];
    }
}
