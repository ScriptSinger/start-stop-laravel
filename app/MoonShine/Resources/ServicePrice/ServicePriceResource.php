<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServicePrice;

use App\Models\ServicePrice;
use App\MoonShine\Resources\ServicePrice\Pages\ServicePriceFormPage;
use App\MoonShine\Resources\ServicePrice\Pages\ServicePriceIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Цены на обслуживание аккумулятора по ёмкости: таблица на странице «Обслуживание»
 * ([[block:service-prices]] в тексте страницы). Цену можно поменять прямо в списке.
 *
 * @extends ModelResource<ServicePrice, ServicePriceIndexPage, ServicePriceFormPage, null>
 */
class ServicePriceResource extends ModelResource
{
    protected string $model = ServicePrice::class;

    protected string $title = 'Цены на обслуживание';

    protected string $column = 'capacity_from';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ServicePriceIndexPage::class,
            ServicePriceFormPage::class,
        ];
    }
}
