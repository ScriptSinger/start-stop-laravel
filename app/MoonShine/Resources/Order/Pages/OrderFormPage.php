<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Customer\CustomerResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

/**
 * @extends FormPage<OrderResource>
 */
class OrderFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Enum::make('Статус', 'status')->attach(OrderStatus::class),
                BelongsTo::make('Клиент (аккаунт)', 'customer', resource: CustomerResource::class)
                    ->nullable()
                    ->searchable(),
                Text::make('Имя', 'customer_name')->required(),
                Phone::make('Телефон', 'customer_phone'),
                Email::make('Email', 'customer_email'),
                $this->labelSelect('Получение', 'delivery_method', array_map(fn (DeliveryMethod $method): string => $method->label(), DeliveryMethod::cases())),
                $this->labelSelect('Оплата', 'payment_method', array_map(fn (PaymentMethod $method): string => $method->label(), PaymentMethod::cases())),
                Textarea::make('Адрес доставки', 'shipping_address'),
                Textarea::make('Комментарий покупателя', 'comment'),
                Money::make('Сумма', 'total')->readonly(),
            ]),
            HasMany::make('Позиции', 'items', resource: OrderItemResource::class)
                ->fields([
                    BelongsTo::make('Товар', 'product', resource: ProductResource::class)->nullable(),
                    Text::make('Название', 'name'),
                    Money::make('Цена', 'price'),
                    Money::make('Скидка за обмен АКБ', 'trade_in_discount'),
                    Number::make('Кол-во', 'quantity'),
                    Money::make('Сумма', 'total'),
                ])
                ->disableOutside(),
        ];
    }

    /**
     * Выбор из подписей способов получения или оплаты. В заказ пишется сама
     * подпись (снимок на момент заказа), поэтому у старого заказа его текст
     * тоже остаётся среди вариантов.
     *
     * @param  list<string>  $labels
     */
    private function labelSelect(string $label, string $column, array $labels): Select
    {
        return Select::make($label, $column)
            ->options(fn (Select $field): array => collect([...$labels, $field->getValue()])
                ->filter()
                ->unique()
                ->mapWithKeys(fn (string $text): array => [$text => $text])
                ->all())
            ->nullable();
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [];
    }

    /**
     * @param  FormBuilder  $component
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
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
