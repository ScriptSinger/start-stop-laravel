<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CustomerRequest\Pages;

use App\Enums\CustomerRequestType;
use App\MoonShine\Resources\Concerns\HasTextFilters;
use App\MoonShine\Resources\CustomerRequest\CustomerRequestResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
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
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<CustomerRequestResource>
 */
class CustomerRequestIndexPage extends IndexPage
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
            Enum::make('Тип', 'type')->attach(CustomerRequestType::class)->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Phone::make('Телефон', 'phone')->sortable(),
            BelongsTo::make('Товар', 'product', resource: ProductResource::class),
            Text::make('Комментарий', 'comment'),
            Switcher::make('Обработана', 'is_processed')->updateOnPreview()->sortable(),
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
            Select::make('Статус', 'is_processed')
                ->options(['0' => 'Новые', '1' => 'Обработанные'])
                ->nullable(),
            Enum::make('Тип', 'type')->attach(CustomerRequestType::class)->nullable(),
            DateRange::make('Дата', 'created_at'),
            $this->containsFilter('Имя', 'name'),
            $this->phoneFilter('Телефон', 'phone'),
            BelongsTo::make('Товар', 'product', resource: ProductResource::class)->nullable()->searchable(),
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
