<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TradeIn\Pages;

use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\TradeIn\TradeInResource;
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
 * @extends FormPage<TradeInResource>
 */
class TradeInFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Код типа', 'code')->required()
                    ->hint('Как тип записан в «Габаритах»: L3, D31, Груз B. Если код есть в названии значения, товар относится к типу сразу'),
                Text::make('Название для сайта', 'name')->required()
                    ->hint('Строка в таблице на странице «Трейд-ин», например «L3 - Евро до 77 Ah»'),
                Money::make('Скидка за старый АКБ, ₽', 'amount')->nullable()
                    ->hint('Покупатель сдаёт старый аккумулятор: цена меньше на эту сумму. Пусто — обмена нет'),
                Number::make('Порядок', 'sort_order')->default(0),
                Switcher::make('Активен', 'is_active')->default(true)
                    ->hint('Выключенный тип не применяется и не показывается на сайте'),
            ]),
            Box::make('Диапазон размеров корпуса, мм', [
                Number::make('Длина от', 'length_min')->nullable(),
                Number::make('Длина до', 'length_max')->nullable(),
                Number::make('Ширина от', 'width_min')->nullable(),
                Number::make('Ширина до', 'width_max')->nullable(),
                Number::make('Высота от', 'height_min')->nullable(),
                Number::make('Высота до', 'height_max')->nullable(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        $size = ['nullable', 'integer', 'min:1', 'max:2000'];

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('trade_in_types', 'code')->ignore($item->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'length_min' => $size,
            'length_max' => [...$size, 'required_with:length_min', 'gte:length_min'],
            'width_min' => $size,
            'width_max' => [...$size, 'required_with:width_min', 'gte:width_min'],
            'height_min' => $size,
            'height_max' => [...$size, 'required_with:height_min', 'gte:height_min'],
        ];
    }
}
