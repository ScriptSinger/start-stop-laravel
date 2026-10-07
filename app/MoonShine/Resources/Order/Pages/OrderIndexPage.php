<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Concerns\HasTextFilters;
use App\MoonShine\Resources\Order\OrderResource;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Range;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<OrderResource>
 */
class OrderIndexPage extends IndexPage
{
    use HasTextFilters;

    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make('Дата', 'created_at')->format('d.m.Y H:i')->sortable(),
            Text::make('Клиент', 'customer_name')->sortable(),
            Phone::make('Телефон', 'customer_phone')->sortable(),
            Enum::make('Статус', 'status')
                ->attach(OrderStatus::class)
                ->sortable(fn (Builder $query, string $column, string $direction): Builder => $this->orderByStatusFlow($query, $direction)),
            Text::make('Получение', 'delivery_method')->sortable(),
            Text::make('Оплата', 'payment_method')->sortable(),
            Money::make('Сумма', 'total')->sortable(),
        ];
    }

    /**
     * Статусы по ходу работы с заказом (порядок случаев в OrderStatus:
     * новый → ожидание → … → брошен), а не по алфавиту.
     */
    private function orderByStatusFlow(Builder $query, string $direction): Builder
    {
        $cases = OrderStatus::cases();
        $when = implode(' ', array_fill(0, count($cases), 'WHEN ? THEN ?'));
        $bindings = collect($cases)->flatMap(fn (OrderStatus $status, int $position): array => [$status->value, $position])->all();

        return $query->orderByRaw("CASE status {$when} END ".($direction === 'desc' ? 'desc' : 'asc'), $bindings);
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Enum::make('Статус', 'status')
                ->attach(OrderStatus::class)
                ->multiple()
                ->nullable(),
            $this->containsFilter('Клиент', 'customer_name'),
            $this->phoneFilter('Телефон', 'customer_phone'),
            Select::make('Получение', 'delivery_method')
                ->options([DeliveryMethod::City->value => 'Доставка по городу', DeliveryMethod::Pickup->value => 'Самовывоз'])
                ->nullable()
                ->onApply(fn (Builder $query, string $value): Builder => $query->withDelivery(DeliveryMethod::from($value))),
            Select::make('Оплата', 'payment_method')
                ->options(collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])->all())
                ->nullable()
                ->onApply(fn (Builder $query, string $value): Builder => $query->withPayment(PaymentMethod::from($value))),
            DateRange::make('Дата', 'created_at'),
            Range::make('Сумма', 'total')->nullable(),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @return list<Metric>
     */
    protected function metrics(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
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
