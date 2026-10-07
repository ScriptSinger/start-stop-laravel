<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BatteryFitment\Pages;

use App\Models\BatteryFitment;
use App\MoonShine\Resources\BatteryFitment\BatteryFitmentResource;
use App\MoonShine\Resources\Concerns\HasTextFilters;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<BatteryFitmentResource>
 */
class BatteryFitmentIndexPage extends IndexPage
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
            Text::make('Марка', 'brand')->sortable(),
            Text::make('Модель', 'model')->sortable(),
            Text::make('Поколение', 'generation')->sortable(),
            Text::make('Двигатель', 'engine')->sortable(),
            Text::make('Ёмкость', 'capacity'),
            Text::make('Полярность', 'polarity')->sortable(),
            Text::make('Клеммы', 'terminals', fn (BatteryFitment $fitment): string => (string) $fitment->terminalsLabel())->sortable(),
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
            Select::make('Марка', 'brand')
                ->options(BatteryFitment::query()->distinct()->orderBy('brand')->pluck('brand', 'brand')->all())
                ->searchable()
                ->nullable(),
            $this->containsFilter('Модель', 'model'),
            $this->containsFilter('Поколение', 'generation'),
            $this->containsFilter('Двигатель', 'engine'),
            $this->containsFilter('Ёмкость', 'capacity')->hint('Например: 60'),
            Select::make('Полярность', 'polarity')
                ->options(['Обратная' => 'Обратная', 'Прямая' => 'Прямая', 'Универсальная' => 'Универсальная'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => $query->where('polarity', 'like', '%'.$value.'%')),
            Select::make('Клеммы', 'terminals')
                ->options(collect(config('shop.battery_fitment.terminal_values'))
                    ->keys()
                    ->mapWithKeys(fn (string $key): array => [$key => (string) (new BatteryFitment(['terminals' => $key]))->terminalsLabel()])
                    ->all())
                ->nullable(),
            Checkbox::make('Без данных для подбора', 'without_data')
                ->onApply(fn (Builder $query, mixed $value): Builder => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->where(fn (Builder $query) => $query->whereNull('capacity')->whereNull('dims')->where(fn (Builder $query) => $query->whereNull('polarity')->orWhere('polarity', 'Универсальная')))
                    : $query),
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
