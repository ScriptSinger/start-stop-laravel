<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Supplier;

use App\Models\Supplier;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Supplier\Pages\SupplierFormPage;
use App\MoonShine\Resources\Supplier\Pages\SupplierIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<Supplier, SupplierIndexPage, SupplierFormPage>
 */
class SupplierResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Supplier::class;

    protected string $title = 'Поставщики';

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
        return $builder->withCount('offers');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            SupplierIndexPage::class,
            SupplierFormPage::class,
        ];
    }
}
