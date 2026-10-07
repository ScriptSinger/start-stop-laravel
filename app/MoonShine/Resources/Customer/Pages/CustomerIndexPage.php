<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Customer\Pages;

use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Concerns\HasTextFilters;
use App\MoonShine\Resources\Customer\CustomerResource;
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
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<CustomerResource>
 */
class CustomerIndexPage extends IndexPage
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
            Text::make('Имя', 'name')->sortable(),
            Email::make('Email', 'email')->sortable(),
            Phone::make('Телефон', 'phone')->sortable(),
            Number::make('Заказов', 'orders_count')->sortable(),
            Money::make('Купил на', 'orders_sum_total')->sortable(),
            Date::make('Последний заказ', 'orders_max_created_at')->format('d.m.Y')->sortable(),
            Date::make('Регистрация', 'created_at')->format('d.m.Y')->sortable(),
        ];
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
            $this->containsFilter('Имя', 'name'),
            $this->phoneFilter('Телефон', 'phone'),
            $this->containsFilter('Email', 'email'),
            Select::make('Заказы', 'has_orders')
                ->options(['1' => 'Есть заказы', '0' => 'Без заказов'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => $value === '1' ? $query->has('orders') : $query->doesntHave('orders')),
            DateRange::make('Регистрация', 'created_at'),
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
