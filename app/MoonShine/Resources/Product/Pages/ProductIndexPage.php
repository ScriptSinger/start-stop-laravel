<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\MoonShine\Fields\Money;
use App\MoonShine\Fields\ProductPhoto;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Catalog\BulkAttributeAssigner;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\TextWrap;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
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
            ID::make()->sortable()->columnSelection(hideOnInit: true),
            ProductPhoto::make('Фото', 'image'),
            Text::make('Название', 'name')->sortable()->textWrap(TextWrap::CLAMP),
            Text::make('Код товара', 'code')->sortable()->columnSelection(hideOnInit: true),
            Text::make('Артикул', 'sku')->sortable(),
            Text::make('Код 1С', 'external_id')->sortable()->columnSelection(hideOnInit: true),
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
            // Сколько значений характеристик у товара; сами они — в просмотре товара.
            Number::make('Хар-ки', 'attribute_values_count')->sortable()->columnSelection(hideOnInit: true),
            Money::make('Цена', 'price')->sortable(),
            Number::make('Остаток', 'quantity')->sortable(),
            Number::make('У поставщика', 'supplier_quantity')->sortable(),
            Switcher::make('Активен', 'status')
                ->updateOnPreview(condition: fn (): bool => ! $this->isTrashedView() && $this->getResource()->can(Ability::UPDATE))
                ->sortable(),
            Date::make('Удалён', 'deleted_at')->format('d.m.Y H:i')->sortable()->canSee(fn (): bool => $this->isTrashedView()),
        ];
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        $resource = $this->getResource();

        return parent::buttons()->add(
            ActionButton::make('Восстановить')
                ->method('restore', events: [$resource->getListEventName()])
                ->icon('arrow-uturn-left')
                ->canSee(fn (mixed $item): bool => $item instanceof Product && $item->trashed())
                ->showInLine(),
            ActionButton::make('Восстановить')
                ->bulk($resource->getListComponentName())
                ->method('restore', events: [$resource->getListEventName()])
                ->icon('arrow-uturn-left')
                ->canSee(fn (): bool => $this->isTrashedView())
                ->showInLine(),
            $this->bulkButton('Активировать', 'check-circle', 'activateProducts', [], 'Отмеченные товары на этой странице станут активными и смогут отображаться на сайте.')
                ->canSee(fn (): bool => ! $this->isTrashedView() && $resource->can(Ability::UPDATE)),
            $this->bulkButton('Деактивировать', 'x-circle', 'deactivateProducts', [], 'Отмеченные товары на этой странице станут неактивными и перестанут отображаться на сайте.')
                ->canSee(fn (): bool => ! $this->isTrashedView() && $resource->can(Ability::UPDATE)),
            $this->bulkButton('Задать значение характеристики', 'tag', 'assignAttribute', [
                Select::make('Характеристика и её значение', 'attribute_value_id')
                    ->options($this->attributeValueOptions())
                    ->hint('Например: «Полярность · Обратная». Поиск по названию характеристики или значению.')
                    ->searchable()
                    ->required(),
                Select::make('Если у товара уже есть значение этой характеристики', 'mode')
                    ->options(['replace' => 'Заменить прежние значения этой характеристики', 'add' => 'Добавить значение, сохранив прежние'])
                    ->default('replace'),
            ], 'Выбранное значение будет задано каждому отмеченному товару на этой странице. Например: «Полярность · Обратная». Другие характеристики сохранятся. Для точной ёмкости автоматически добавятся подходящие диапазоны ёмкости для подбора по машине.'),
            $this->bulkButton('Удалить значения характеристики', 'tag', 'detachAttribute', [
                Select::make('Характеристика', 'attribute_id')
                    ->options(Attribute::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
            ], 'У каждого отмеченного товара на этой странице будут удалены все значения выбранной характеристики. Сама характеристика и её значения останутся в справочнике; другие товары не изменятся.'),
        );
    }

    #[AsyncMethod]
    public function activateProducts(Request $request): MoonShineJsonResponse
    {
        return $this->setSelectedProductsStatus($request, true);
    }

    #[AsyncMethod]
    public function deactivateProducts(Request $request): MoonShineJsonResponse
    {
        return $this->setSelectedProductsStatus($request, false);
    }

    private function setSelectedProductsStatus(Request $request, bool $status): MoonShineJsonResponse
    {
        abort_unless(auth(config('moonshine.auth.guard', 'moonshine'))->check(), 403);
        abort_unless($this->getResource()->can(Ability::UPDATE), 403);

        if ($request->input('ids') === null || $request->input('ids') === []) {
            return MoonShineJsonResponse::make()->toast('Отметьте товары в списке.', ToastType::ERROR);
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $updated = Product::query()->whereKey($ids)->where('status', '!=', $status)->update(['status' => $status]);

        return $this->bulkDone(($status ? 'Включено товаров: ' : 'Отключено товаров: ').$updated);
    }

    /**
     * Восстановить удалённые товары: отмеченные в списке или один (кнопка в строке).
     */
    #[AsyncMethod]
    public function restore(Request $request): MoonShineJsonResponse
    {
        $productIds = $this->selectedIds($request) ?: [$request->integer('resourceItem')];

        $restored = Product::onlyTrashed()->whereKey($productIds)->get()->each->restore()->count();

        return MoonShineJsonResponse::make()
            ->toast($restored > 0 ? "Восстановлено товаров: {$restored}" : 'Нечего восстанавливать', $restored > 0 ? ToastType::SUCCESS : ToastType::ERROR)
            ->events([$this->getResource()->getListEventName()]);
    }

    /**
     * Открыта вкладка «Удалённые».
     */
    private function isTrashedView(): bool
    {
        return request()->input('filter.deleted') === 'trashed';
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
            ->showInLine()
            ->when(
                in_array($method, ['assignAttribute', 'detachAttribute'], true),
                fn (ActionButton $button): ActionButton => $button->showInDropdown(),
            );
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
            Text::make('Код 1С', 'external_id')
                ->hint('Несколько кодов через запятую: РТ-00000805, 1252')
                ->onApply(fn (Builder $query, mixed $value): Builder => $query->whereIn('products.external_id', array_filter(array_map('trim', explode(',', (string) $value))))),
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
            Select::make('Удалённые товары', 'deleted')
                ->options(['trashed' => 'Только удалённые'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => $value === 'trashed' ? $query->onlyTrashed() : $query),
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
            $this->missingFilter('Без кода 1С', 'without_external_id', fn (Builder $query) => $query->whereNull('external_id')),
            $this->missingFilter('Без производителя', 'without_manufacturer', fn (Builder $query) => $query->whereNull('manufacturer_id')),
            $this->missingFilter('Без категории', 'without_categories', fn (Builder $query) => $query->doesntHave('categories')),
            $this->missingFilter('Без фото', 'without_image', fn (Builder $query) => $query->whereNull('image')),
            $this->missingFilter('АКБ без данных для подбора', 'without_fitment_data', fn (Builder $query) => $query->missingFitmentData()),
            $this->missingFilter('Без описания', 'without_description', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('description')->orWhere('description', ''))),
            $this->missingFilter('Ошибка в цене', 'price_problem', fn (Builder $query) => $query->withPriceProblem()),
            $this->missingFilter('Готовы к выгрузке', 'ready_for_export', fn (Builder $query) => $query->readyForExport()),
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
        return $component->columnSelection();
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
