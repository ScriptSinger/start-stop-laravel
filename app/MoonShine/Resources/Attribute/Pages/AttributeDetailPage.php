<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Attribute\Pages;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\MoonShine\Resources\Attribute\AttributeResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<AttributeResource>
 */
class AttributeDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Number::make('Порядок сортировки', 'sort_order'),
            Switcher::make('В фильтре', 'is_filterable'),
            BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)
                ->inLine(separator: ' ', badge: true),
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
            $this->valuesBox(),
        ];
    }

    /**
     * Значения характеристики и сколько товаров их используют; число — ссылка на
     * список товаров с этим значением в фильтре.
     */
    private function valuesBox(): Box
    {
        $attribute = $this->getResource()->getItem();

        $values = $attribute instanceof Attribute
            ? $attribute->values()->withCount('products')->orderBy('sort_order')->orderBy('value')->get()
            : collect();

        return Box::make('Значения и товары', [
            TableBuilder::make(items: $values->map(fn (AttributeValue $value): array => [
                'value' => $value->value,
                'products' => $value->products_count,
                'id' => $value->getKey(),
            ])->all())
                ->fields([
                    Text::make('Значение', 'value'),
                    Text::make('Товаров', 'products', fn (array $row): string => $row['products'] === 0 ? '0' : (string) Link::make($this->productsUrl($row['id']), (string) $row['products'])->render())->unescape(),
                ])
                ->withNotFound(),
        ]);
    }

    private function productsUrl(int $valueId): string
    {
        return (string) $this->getCore()->getRouter()->getEndpoints()->toPage(
            ProductIndexPage::class,
            ProductResource::class,
            params: ['filter' => ['attribute_values' => [$valueId]]],
        );
    }
}
