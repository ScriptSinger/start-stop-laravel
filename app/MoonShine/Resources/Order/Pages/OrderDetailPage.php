<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\Models\Order;
use App\MoonShine\Resources\Customer\CustomerResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

/**
 * @extends DetailPage<OrderResource>
 */
class OrderDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Date::make('Дата', 'created_at')->format('d.m.Y H:i'),
            Select::make('Статус', 'status')->options(Order::STATUSES),
            BelongsTo::make('Клиент (аккаунт)', 'customer', resource: CustomerResource::class),
            Text::make('Имя', 'customer_name'),
            Phone::make('Телефон', 'customer_phone'),
            Email::make('Email', 'customer_email'),
            Text::make('Оплата', 'payment_method'),
            Textarea::make('Адрес доставки', 'shipping_address'),
            Number::make('Сумма', 'total'),
            HasMany::make('Позиции', 'items', resource: OrderItemResource::class)
                ->fields([
                    BelongsTo::make('Товар', 'product', resource: ProductResource::class)->nullable(),
                    Text::make('Название', 'name'),
                    Number::make('Цена', 'price'),
                    Number::make('Кол-во', 'quantity'),
                    Number::make('Сумма', 'total'),
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
