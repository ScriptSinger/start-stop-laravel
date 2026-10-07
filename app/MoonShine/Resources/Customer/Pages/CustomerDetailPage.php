<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Customer\Pages;

use App\Enums\OrderStatus;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Customer\CustomerResource;
use App\MoonShine\Resources\Order\OrderResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<CustomerResource>
 */
class CustomerDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Имя', 'name'),
            Email::make('Email', 'email'),
            Phone::make('Телефон', 'phone'),
            Date::make('Регистрация', 'created_at')->format('d.m.Y'),
            HasMany::make('Заказы', 'orders', resource: OrderResource::class)
                ->fields([
                    ID::make(),
                    Date::make('Дата', 'created_at')->format('d.m.Y H:i'),
                    Enum::make('Статус', 'status')->attach(OrderStatus::class),
                    Money::make('Сумма', 'total'),
                ])
                ->disableOutside(),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyDetailComponent(ComponentContract $component): ComponentContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
