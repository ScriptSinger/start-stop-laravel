<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use App\Support\JsonLogReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Read-only admin resource over a daily JSON log (see JsonLogReader):
 * filters, status tabs, sorting and paging come from MoonShine, the rows from
 * the log files. Subclasses define the reader, pages and fields.
 *
 * @extends CrudResource<CoreContract, array<string, mixed>>
 */
abstract class LogResource extends CrudResource
{
    protected string $sortColumn = 'time_sort';

    protected SortDirection $sortDirection = SortDirection::DESC;

    protected int $itemsPerPage = 50;

    abstract protected function reader(): JsonLogReader;

    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW]);
    }

    public function getCaster(): DataCasterContract
    {
        return new LogEntryCaster;
    }

    public function getItems(): LengthAwarePaginator
    {
        $filters = $this->getFilterParams();

        // "Hide my IP" excludes whichever address the admin is browsing from.
        if (filter_var($filters['hide_my_ip'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $filters['exclude_ip'] = trim(($filters['exclude_ip'] ?? '').' '.request()->ip());
        }

        $rows = $this->reader()->entries($filters);

        /** @var QueryTag $tag */
        foreach ($this->getQueryTags() as $tag) {
            if ($tag->isActive()) {
                $rows = $tag->apply($rows);
                break;
            }
        }

        [$column, $direction] = $this->prepareOrder();

        $rows = $rows
            ->sortBy($column, SORT_REGULAR, $direction === 'desc')
            ->values();

        $pageName = $this->getQueryParamName('page');
        $page = max($this->getPaginatorPage(), 1);

        return (new LengthAwarePaginator(
            $rows->forPage($page, $this->getItemsPerPage())->values(),
            $rows->count(),
            $this->getItemsPerPage(),
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        ))->appends($this->getQueryParams()->except($pageName)->toArray());
    }

    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        $row = $this->reader()->find((string) $this->getItemID());

        if ($row === null) {
            $orFail && abort(404);

            return null;
        }

        return $this->getCaster()->cast($row);
    }

    public function massDelete(array $ids): void
    {
        //
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        return false;
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        return $item;
    }
}
