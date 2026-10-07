<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use MoonShine\UI\Fields\Text;

/**
 * Текстовые фильтры списков админки: по части значения, а не точному совпадению.
 */
trait HasTextFilters
{
    /**
     * «Содержит»: «vesta» найдёт «Lada Vesta».
     */
    protected function containsFilter(string $label, string $column): Text
    {
        return Text::make($label, $column)
            ->onApply(fn (Builder $query, mixed $value): Builder => $query->where($column, 'like', '%'.addcslashes((string) $value, '%_\\').'%'));
    }

    /**
     * Телефон по цифрам: «89191525000», «9191525000» и «152-50» найдут
     * «+7 (919) 152-50-00». Ведущая 8 или 7 у полного номера отбрасывается.
     */
    protected function phoneFilter(string $label, string $column): Text
    {
        return Text::make($label, $column)
            ->hint('Можно часть номера, в любом формате')
            ->onApply(function (Builder $query, mixed $value) use ($column): Builder {
                $digits = (string) preg_replace('/\D/', '', (string) $value);

                if ($digits === '') {
                    return $query;
                }

                if (strlen($digits) === 11) {
                    $digits = substr($digits, 1);
                }

                $column = $query->getQuery()->getGrammar()->wrap($column);

                return $query->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') like ?",
                    ['%'.$digits.'%'],
                );
            });
    }
}
