<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Supplier\Pages;

use App\Enums\SupplierPriceFormat;
use App\MoonShine\Resources\Supplier\SupplierResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<SupplierResource>
 */
class SupplierFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Название', 'name')->required(),
                Select::make('Формат прайса', 'format')
                    ->options(SupplierPriceFormat::options())
                    ->required()
                    ->hint('От формата зависит, из каких колонок берутся название, закупочная цена и остаток'),
                Number::make('Наценка, %', 'markup_percent')
                    ->min(0)
                    ->step(0.5)
                    ->default(20)
                    ->hint('Цена на сайте = закупка + наценка, округление вверх до 50 ₽'),
                Number::make('Срок поставки, рабочих дней', 'lead_working_days')
                    ->min(0)
                    ->default(2)
                    ->hint('На витрине: «Под заказ, ожидаем <дата>». Субботу и воскресенье не считаем'),
                Switcher::make('Не учитывать количество', 'ignore_quantity')
                    ->hint('Для прайсов без остатков: позиция из последнего прайса считается доступной'),
                Number::make('Порог остатка, шт.', 'min_quantity')
                    ->min(0)
                    ->default(5)
                    ->hint('Под заказ показываем, если у поставщика не меньше этого количества'),
                Switcher::make('Активен', 'is_active')->default(true)
                    ->hint('Выключенный поставщик не даёт «под заказ»'),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($item->getKey())],
            'format' => ['required', Rule::enum(SupplierPriceFormat::class)],
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'lead_working_days' => ['required', 'integer', 'min:0', 'max:60'],
            'min_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
