<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Catalog\BulkAttributeAssigner;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
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
            ID::make()->sortable(),
            Image::make('Фото', 'image'),
            Text::make('Название', 'name')->sortable(),
            Text::make('Код товара', 'code')->sortable(),
            Text::make('Артикул', 'sku')->sortable(),
            // По названию производителя, а не по его id.
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class)
                ->sortable(fn (Builder $query, string $column, string $direction): Builder => $query->orderBy(
                    Manufacturer::query()->select('name')->whereColumn('manufacturers.id', 'products.manufacturer_id'),
                    $direction,
                )),
            // Сортировка — по названию категории (у товара в нескольких — по первой
            // по алфавиту), внутри категории — по названию товара.
            BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)
                ->sortable(fn (Builder $query, string $column, string $direction): Builder => $query
                    ->orderBy(
                        DB::table('category_product')
                            ->join('categories', 'categories.id', '=', 'category_product.category_id')
                            ->whereColumn('category_product.product_id', 'products.id')
                            ->selectRaw('min(categories.name)'),
                        $direction,
                    )
                    ->orderBy('products.name'))
                ->inLine(
                    separator: ' ',
                    badge: true,
                    link: fn (Category $category, mixed $value, BelongsToMany $field): string => $field->getResource()->getFormPageUrl($category->getKey()),
                ),
            Money::make('Цена', 'price')->sortable(),
            Number::make('Остаток', 'quantity')->sortable(),
            Number::make('У поставщика', 'supplier_quantity')->sortable(),
            Switcher::make('Активен', 'status')->sortable(),
        ];
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->add(
            $this->bulkButton('Присвоить характеристику', 'tag', 'assignAttribute', [
                Select::make('Значение (поиск: «емкость 60», «полярность»)', 'attribute_value_id')
                    ->options($this->attributeValueOptions())
                    ->searchable()
                    ->required(),
                Select::make('Если у товара уже есть значение этой характеристики', 'mode')
                    ->options(['replace' => 'Заменить', 'add' => 'Добавить к имеющимся'])
                    ->default('replace'),
            ], 'Точная ёмкость («60 Ah») сразу добавляет и диапазон («55 - 65 Ah») — по нему работает подбор по машине.'),
            $this->bulkButton('Убрать характеристику', 'tag', 'detachAttribute', [
                Select::make('Характеристика', 'attribute_id')
                    ->options(Attribute::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
            ], 'У отмеченных товаров будут удалены все значения этой характеристики.'),
        );
    }

    /**
     * Присвоить отмеченным товарам значение характеристики (массовое действие).
     */
    #[AsyncMethod]
    public function assignAttribute(Request $request, BulkAttributeAssigner $assigner): MoonShineJsonResponse
    {
        $productIds = $this->selectedIds($request);
        $value = AttributeValue::query()->with('attribute')->find($request->integer('attribute_value_id'));

        if ($productIds === [] || $value === null) {
            return MoonShineJsonResponse::make()->toast('Отметьте товары и выберите значение', ToastType::ERROR);
        }

        $assigned = $assigner->assign($productIds, $value, $request->input('mode', 'replace') === 'replace');

        return $this->bulkDone('Товаров: '.count($productIds).'. Присвоено: '.$value->attribute->name.' — '.implode(', ', $assigned));
    }

    /**
     * Убрать у отмеченных товаров все значения характеристики.
     */
    #[AsyncMethod]
    public function detachAttribute(Request $request, BulkAttributeAssigner $assigner): MoonShineJsonResponse
    {
        $productIds = $this->selectedIds($request);
        $attribute = Attribute::query()->find($request->integer('attribute_id'));

        if ($productIds === [] || $attribute === null) {
            return MoonShineJsonResponse::make()->toast('Отметьте товары и выберите характеристику', ToastType::ERROR);
        }

        $assigner->detach($productIds, $attribute->id);

        return $this->bulkDone('Товаров: '.count($productIds).'. Убрано: '.$attribute->name);
    }

    /**
     * Массовая кнопка над таблицей: окно с полями, отмеченные строки — в ids.
     *
     * @param  list<FieldContract>  $fields
     */
    private function bulkButton(string $label, string $icon, string $method, array $fields, string $hint): ActionButton
    {
        $resource = $this->getResource();

        return ActionButton::make($label)
            ->bulk($resource->getListComponentName())
            ->method($method)
            ->withConfirm(
                title: $label,
                content: $hint,
                button: 'Применить',
                fields: $fields,
                formBuilder: fn (FormBuilderContract $form): FormBuilderContract => $form->async(events: [$resource->getListEventName()]),
            )
            ->icon($icon)
            ->showInLine();
    }

    /**
     * @return list<int>
     */
    private function selectedIds(Request $request): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $request->input('ids', [])))));
    }

    private function bulkDone(string $message): MoonShineJsonResponse
    {
        return MoonShineJsonResponse::make()
            ->toast($message, ToastType::SUCCESS)
            ->events([$this->getResource()->getListEventName()]);
    }

    /**
     * Значения, сгруппированные по характеристикам; в подписи — и
     * характеристика: «Емкость · 60 Ah».
     *
     * @return array<string, array<int, string>>
     */
    private function attributeValueOptions(): array
    {
        return Attribute::query()
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('value')])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Attribute $attribute): array => [
                // «Емкость · 60 Ah»: поиск находит и по характеристике, и по значению.
                $attribute->name => $attribute->values
                    ->mapWithKeys(fn (AttributeValue $value): array => [$value->id => $attribute->name.' · '.$value->value])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            // Несколько вариантов через запятую: «60, 65» — название содержит любой из них.
            Text::make('Название', 'name')
                ->hint('Несколько вариантов через запятую: 60, 65')
                ->onApply(fn (Builder $query, mixed $value): Builder => $query->where(function (Builder $query) use ($value): void {
                    foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $term) {
                        $query->orWhere('products.name', 'like', '%'.addcslashes($term, '%_\\').'%');
                    }
                })),
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class)
                ->nullable()
                ->searchable(),
            // Поиск по названию, подписи с разделом; раздел находит и товары подразделов.
            BelongsToMany::make('Категории', 'categories', fn (Category $category): string => $category->pathName(), CategoryResource::class)
                ->selectMode()
                ->searchable()
                ->onApply(fn (Builder $query, mixed $value): Builder => $query->whereHas('categories', fn (Builder $categories) => $categories->whereIn(
                    'categories.id',
                    Category::query()->whereKey(array_filter((array) $value))->get()->flatMap(fn (Category $category): array => $category->treeIds())->unique()->all(),
                ))),
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
            $this->missingFilter('АКБ без данных для подбора', 'without_fitment_data', fn (Builder $query) => $query->missingFitmentData()),
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
