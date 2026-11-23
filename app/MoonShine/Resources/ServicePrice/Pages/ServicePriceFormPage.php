<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServicePrice\Pages;

use App\MoonShine\Resources\ServicePrice\ServicePriceResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;

/**
 * @extends FormPage<ServicePriceResource>
 */
class ServicePriceFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Number::make('Ёмкость от, Ah', 'capacity_from')->min(1)->required(),
                Number::make('Ёмкость до, Ah', 'capacity_to')->min(1)->required()
                    ->hint('Строка в таблице на странице «Обслуживание»: «от-до Ah»'),
                Number::make('Цена, ₽', 'price')->min(0)->required(),
                Number::make('Порядок', 'sort_order')->default(0),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'capacity_from' => ['required', 'integer', 'min:1', 'max:2000'],
            'capacity_to' => ['required', 'integer', 'min:1', 'max:2000', 'gte:capacity_from'],
            'price' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
