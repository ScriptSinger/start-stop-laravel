<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServicePrice\Pages;

use App\Models\ServicePrice;
use App\MoonShine\Resources\ServicePrice\ServicePriceResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Ability;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;

/**
 * @extends IndexPage<ServicePriceResource>
 */
class ServicePriceIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        $canUpdate = fn (): bool => $this->getResource()->can(Ability::UPDATE);

        return [
            Preview::make('Ёмкость', 'capacity_from', fn (ServicePrice $price): string => $price->capacityLabel())->sortable(),
            // Цена меняется прямо в списке и сразу попадает на страницу «Обслуживание».
            Number::make('Цена, ₽', 'price')
                ->min(0)
                ->updateOnPreview(condition: $canUpdate)
                ->sortable(),
            Number::make('Порядок', 'sort_order')->sortable(),
        ];
    }
}
