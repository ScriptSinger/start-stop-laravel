<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category\Pages;

use App\MoonShine\Resources\Category\CategoryResource;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<CategoryResource>
 */
class CategoryIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Slug', 'slug'),
            BelongsTo::make('Родитель', 'parent', resource: CategoryResource::class),
            Switcher::make('Активна', 'status'),
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
            Text::make('Название', 'name'),
            BelongsTo::make('Родитель', 'parent', resource: CategoryResource::class)
                ->nullable()
                ->searchable(),
            Select::make('Статус', 'status')
                ->options(['1' => 'Активные', '0' => 'Неактивные'])
                ->nullable(),
            Select::make('Уровень', 'level')
                ->options(['root' => 'Разделы', 'child' => 'Подкатегории'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => match ($value) {
                    'root' => $query->whereNull('parent_id'),
                    'child' => $query->whereNotNull('parent_id'),
                    default => $query,
                }),
            Select::make('Товары', 'has_products')
                ->options(['with' => 'С товарами', 'without' => 'Без товаров'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => match ($value) {
                    'with' => $query->has('products'),
                    'without' => $query->doesntHave('products'),
                    default => $query,
                }),
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
