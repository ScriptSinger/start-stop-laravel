<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\Models\Product;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

/**
 * @extends DetailPage<ProductResource>
 */
class ProductDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Image::make('Фото', 'image'),
            Text::make('Название', 'name'),
            Text::make('Slug', 'slug'),
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            Text::make('Код 1С', 'external_id'),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class),
            BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class),
            // Значения из справочников «Характеристики» (pivot attribute_value_product).
            Text::make('Характеристики', 'id', fn (Product $product): string => $this->specifications($product))->unescape(),
            Money::make('Цена', 'price'),
            Money::make('Цена по акции', 'special_price'),
            Number::make('Остаток', 'quantity'),
            Number::make('Остаток у поставщика', 'supplier_quantity'),
            Money::make('Цена под заказ', 'supplier_price'),
            Money::make('Скидка при обмене (трейд-ин)', 'trade_in_discount'),
            Switcher::make('Только самовывоз', 'is_pickup_only'),
            Textarea::make('Описание', 'description'),
            Switcher::make('Активен', 'status'),
            Date::make('Удалён', 'deleted_at')->format('d.m.Y H:i')->canSee(fn (Date $field): bool => $field->toValue() !== null),
        ];
    }

    /**
     * «Полярность: Обратная», по строке на характеристику, в порядке карточки товара.
     */
    private function specifications(Product $product): string
    {
        $product->loadMissing('attributeValues.attribute');

        $lines = $product->specifications()->map(fn (string $value, string $name): string => '<div><strong>'.e($name).':</strong> '.e($value).'</div>');

        return $lines->isEmpty() ? '—' : $lines->implode('');
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
