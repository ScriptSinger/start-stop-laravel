<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryZone\Pages;

use App\MoonShine\Resources\DeliveryZone\DeliveryZoneResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<DeliveryZoneResource>
 */
class DeliveryZoneFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Район', 'name')->required()
                    ->hint('Как район называется на странице «Доставка и оплата»'),
                Number::make('Цена доставки, ₽', 'price')->min(0)->default(0)->required()
                    ->hint('0 — бесплатно. Районы с одинаковой ценой на сайте собираются в одну группу'),
                Number::make('Порядок', 'sort_order')->default(0),
                Switcher::make('Показывать на сайте', 'is_active')->default(true)
                    ->hint('Выключенный район не показывается на странице «Доставка и оплата»'),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('delivery_zones', 'name')->ignore($item->getKey())],
            'price' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
