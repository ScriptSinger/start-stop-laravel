<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use Illuminate\Contracts\Pagination\Paginator;
use MoonShine\Contracts\Core\Paginator\PaginatorContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\TypeCasts\MixedDataWrapper;
use MoonShine\Crud\TypeCasts\PaginatorCaster;

/**
 * The stock MixedDataCaster returns no paginator, so a resource over plain
 * arrays would render the table without paging links.
 */
final readonly class LogEntryCaster implements DataCasterContract
{
    public function cast(mixed $data): DataWrapperContract
    {
        return new MixedDataWrapper($data, is_array($data) ? ($data['id'] ?? null) : null);
    }

    public function paginatorCast(mixed $data): ?PaginatorContract
    {
        if (! $data instanceof Paginator) {
            return null;
        }

        $pageName = method_exists($data, 'getPageName') ? $data->getPageName() : 'page';

        return (new PaginatorCaster(
            $data->appends(moonshine()->getRequest()->getExcept($pageName))->toArray(),
            $data->items(),
            pageName: $pageName,
        ))->cast();
    }
}
