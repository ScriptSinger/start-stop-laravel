<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\Models\Attribute;
use App\Models\Category;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Range;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<ProductResource>
 */
class ProductIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Image::make('Фото', 'image'),
            Text::make('Название', 'name'),
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class),
            BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)
                ->inLine(
                    separator: ' ',
                    badge: true,
                    link: fn (Category $category, mixed $value, BelongsToMany $field): string => $field->getResource()->getFormPageUrl($category->getKey()),
                ),
            Number::make('Цена', 'price'),
            Number::make('Остаток', 'quantity'),
            Number::make('У поставщика', 'supplier_quantity'),
            Switcher::make('Активен', 'status'),
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
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class)
                ->nullable()
                ->searchable(),
            BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)
                ->selectMode(),
            $this->attributeValuesFilter(),
            Range::make('Цена', 'price')->nullable(),
            Range::make('Остаток у поставщика', 'supplier_quantity')->nullable(),
            Range::make('Цена под заказ', 'supplier_price')->nullable(),
            Select::make('Статус', 'status')
                ->options(['1' => 'Активные', '0' => 'Неактивные'])
                ->nullable(),
            Select::make('Наличие', 'stock')
                ->options(['in' => 'В наличии', 'order' => 'Под заказ', 'out' => 'Нет в наличии'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => match ($value) {
                    'in' => $query->where('quantity', '>', 0),
                    'order' => $query->where('quantity', '<=', 0)
                        ->where('supplier_quantity', '>=', config('shop.supplier_order_min_quantity')),
                    'out' => $query->where('quantity', '<=', 0)
                        ->where('supplier_quantity', '<', config('shop.supplier_order_min_quantity')),
                    default => $query,
                }),
            $this->missingFilter('Без производителя', 'without_manufacturer', fn (Builder $query) => $query->whereNull('manufacturer_id')),
            $this->missingFilter('Без категории', 'without_categories', fn (Builder $query) => $query->doesntHave('categories')),
            $this->missingFilter('Без фото', 'without_image', fn (Builder $query) => $query->whereNull('image')),
        ];
    }

    /**
     * Чекбокс-фильтр «не заполнено»: срабатывает только когда отмечен
     * (неотмеченный чекбокс тоже отправляет значение — пустое/0).
     *
     * @param  Closure(Builder): mixed  $condition
     */
    private function missingFilter(string $label, string $column, Closure $condition): Checkbox
    {
        return Checkbox::make($label, $column)
            ->onApply(function (Builder $query, mixed $value) use ($condition): Builder {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $condition($query);
                }

                return $query;
            });
    }

    /**
     * Один список со значениями, сгруппированными по характеристикам.
     * Внутри одной характеристики значения работают как «ИЛИ» (60 Ah или
     * 62 Ah), между характеристиками — как «И» (и Обратная полярность).
     */
    private function attributeValuesFilter(): Select
    {
        $attributes = Attribute::query()->with('values')->orderBy('sort_order')->orderBy('name')->get();

        return Select::make('Характеристики', 'attribute_values')
            ->options($attributes->mapWithKeys(fn (Attribute $attribute): array => [
                $attribute->name => $attribute->values->pluck('value', 'id')->all(),
            ])->all())
            ->multiple()
            ->searchable()
            ->onApply(function (Builder $query, mixed $selected) use ($attributes): Builder {
                $selectedIds = array_map('intval', array_filter((array) $selected));

                if ($selectedIds === []) {
                    return $query;
                }

                foreach ($attributes as $attribute) {
                    $ids = $attribute->values->pluck('id')->intersect($selectedIds)->values()->all();

                    if ($ids !== []) {
                        $query->whereHas('attributeValues', fn (Builder $values) => $values->whereIn('attribute_values.id', $ids));
                    }
                }

                return $query;
            });
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
