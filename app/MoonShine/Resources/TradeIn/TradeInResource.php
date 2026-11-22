<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TradeIn;

use App\Models\TradeInType;
use App\MoonShine\Resources\TradeIn\Pages\TradeInFormPage;
use App\MoonShine\Resources\TradeIn\Pages\TradeInIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Типы корпусов и скидка за сдачу старого АКБ. Тип — диапазон размеров в мм
 * (у грузовых ещё и ёмкости): товар с подходящими «Габаритами» получает скидку
 * типа. Единственное место, где задаётся сумма; на ней же строится таблица на
 * странице «Трейд-ин» ([[block:trade-in]] в тексте страницы).
 *
 * @extends ModelResource<TradeInType, TradeInIndexPage, TradeInFormPage, null>
 */
class TradeInResource extends ModelResource
{
    protected string $model = TradeInType::class;

    protected string $title = 'Трейд-ин';

    protected string $column = 'name';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'code', 'name'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            TradeInIndexPage::class,
            TradeInFormPage::class,
        ];
    }
}
