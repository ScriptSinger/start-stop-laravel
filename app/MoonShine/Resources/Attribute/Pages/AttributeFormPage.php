<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Attribute\Pages;

use App\MoonShine\Resources\Attribute\AttributeResource;
use App\MoonShine\Resources\AttributeValue\AttributeValueResource;
use App\MoonShine\Resources\Category\CategoryResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<AttributeResource>
 */
class AttributeFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Название', 'name')->required(),
                Number::make('Порядок сортировки', 'sort_order')->default(0),
                Switcher::make('Показывать в фильтре', 'is_filterable')->default(true),
                BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)
                    ->selectMode()
                    ->hint('Характеристика показывается в форме товаров этих категорий'),
            ]),
            // Удаление значения отвязывает его от всех товаров (cascade) —
            // это осознанно: справочник правят здесь, у товара только выбирают.
            RelationRepeater::make('Значения', 'values', resource: AttributeValueResource::class)
                ->fields([
                    ID::make(),
                    Text::make('Значение', 'value')->required(),
                    Number::make('Порядок', 'sort_order')->default(0),
                ])
                ->creatable()
                ->removable(),
        ];
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
