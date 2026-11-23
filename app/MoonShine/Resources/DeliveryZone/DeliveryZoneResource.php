<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryZone;

use App\Models\DeliveryZone;
use App\MoonShine\Resources\DeliveryZone\Pages\DeliveryZoneFormPage;
use App\MoonShine\Resources\DeliveryZone\Pages\DeliveryZoneIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Районы и цены курьерской доставки: таблица «Стоимость по районам» на
 * странице «Доставка и оплата». Цену можно поменять прямо в списке.
 *
 * @extends ModelResource<DeliveryZone, DeliveryZoneIndexPage, DeliveryZoneFormPage, null>
 */
class DeliveryZoneResource extends ModelResource
{
    protected string $model = DeliveryZone::class;

    protected string $title = 'Зоны доставки';

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
        return ['id', 'name'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            DeliveryZoneIndexPage::class,
            DeliveryZoneFormPage::class,
        ];
    }
}
