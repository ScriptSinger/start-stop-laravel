<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryZone\Pages;

use App\MoonShine\Resources\DeliveryZone\DeliveryZoneResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Ability;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<DeliveryZoneResource>
 */
class DeliveryZoneIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        $canUpdate = fn (): bool => $this->getResource()->can(Ability::UPDATE);

        return [
            Text::make('Район', 'name')->sortable(),
            // Цена и «Показывать на сайте» меняются прямо в списке; 0 — бесплатно.
            Number::make('Цена доставки, ₽', 'price')
                ->min(0)
                ->updateOnPreview(condition: $canUpdate)
                ->sortable(),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Показывать на сайте', 'is_active')->updateOnPreview(condition: $canUpdate)->sortable(),
        ];
    }
}
