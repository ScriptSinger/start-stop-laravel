<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * MoonShine при применении фильтра сохраняет номер страницы: листали
 * список до 112-й страницы, выбрали категорию с пятью товарами — и видите
 * «Записи не найдены». Если запрошенная страница за пределами результата,
 * показываем первую.
 */
trait ResetsPageOutOfRange
{
    protected function paginate(): Paginator|CursorPaginator
    {
        $paginator = parent::paginate();

        if ($paginator instanceof LengthAwarePaginator
            && $paginator->isEmpty()
            && $paginator->currentPage() > 1
            && $paginator->total() > 0) {
            return $this->setPaginatorPage(1)->paginate();
        }

        return $paginator;
    }
}
