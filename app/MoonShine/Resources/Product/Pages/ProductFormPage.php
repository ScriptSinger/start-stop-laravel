<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product\Pages;

use App\Enums\ProductSelection;
use App\Models\Attribute;
use App\Models\Product;
use App\MoonShine\Fields\Money;
use App\MoonShine\Fields\SeoFields;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\ProductImage\ProductImageResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Fields\Relationships\RelationRepeater;
use MoonShine\Laravel\Fields\Slug;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\TinyMce\Fields\TinyMce;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<ProductResource>
 */
class ProductFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Tabs::make([
                Tab::make('Основное', [
                    Box::make([
                        ID::make(),
                        Text::make('Название', 'name'),
                        Slug::make('Slug', 'slug')->from('name')->unique(),
                        Text::make('Код товара', 'code')->nullable()->hint('Типоразмер, например 115D31L'),
                        Text::make('Артикул', 'sku')->nullable(),
                        BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class)->nullable(),
                        BelongsToMany::make('Категории', 'categories', resource: CategoryResource::class)->selectMode(),
                        Money::make('Цена', 'price'),
                        Money::make('Цена по акции', 'special_price')
                            ->nullable()
                            ->hint('Ниже обычной цены — на сайте старая цена зачёркнута, наклейка «Ваша скидка»'),
                        $this->selectionsField(),
                        Number::make('Остаток', 'quantity')->default(0),
                        Number::make('Остаток у поставщика', 'supplier_quantity')
                            ->default(0)
                            ->hint('Если своего остатка нет, а у поставщика не меньше '.config('shop.supplier_order_min_quantity').' шт. — товар продаётся под заказ'),
                        Money::make('Цена под заказ', 'supplier_price')
                            ->nullable()
                            ->hint('Пусто — под заказ действует обычная цена'),
                        TinyMce::make('Описание', 'description')->locale('ru')->nullable(),
                        Money::make('Скидка при обмене (трейд-ин)', 'trade_in_discount')
                            ->nullable()
                            ->hint('Покупатель сдаёт старый АКБ — цена меньше на эту сумму. Пусто — обмена нет'),
                        Switcher::make('Только самовывоз', 'is_pickup_only')->default(false),
                        Switcher::make('Активен', 'status')->default(true),
                    ]),
                ]),
                Tab::make('Фото', [
                    Box::make([
                        $this->photoField('Главное фото', 'image')
                            ->removable()
                            ->nullable(),
                    ]),
                    RelationRepeater::make('Дополнительные фото', 'images', resource: ProductImageResource::class)
                        ->fields([
                            ID::make(),
                            $this->photoField('Фото', 'path'),
                            Number::make('Порядок', 'sort_order')->default(0),
                        ])
                        ->creatable()
                        ->removable(),
                ]),
                Tab::make('Характеристики', [
                    Box::make($this->attributeFields()),
                ]),
                Tab::make('SEO', [
                    Box::make(SeoFields::make('название товара')),
                ]),
            ]),
        ];
    }

    /**
     * Подборки на главной («Рекомендуем», «Акции») — связь product_selection;
     * новый товар встаёт в конец подборки.
     */
    private function selectionsField(): Select
    {
        return Select::make('На главной', 'selections')
            ->options(collect(ProductSelection::cases())->mapWithKeys(fn (ProductSelection $selection): array => [$selection->value => $selection->toString()])->all())
            ->multiple()
            ->nullable()
            ->changeFill(fn (mixed $product): array => $product instanceof Product
                ? DB::table('product_selection')->where('product_id', $product->getKey())->pluck('selection')->all()
                : [])
            ->onApply(fn (Product $product): Product => $product)
            ->onAfterApply(function (Product $product, mixed $selected): Product {
                $selected = array_values(array_intersect((array) ($selected ?: []), array_column(ProductSelection::cases(), 'value')));

                DB::table('product_selection')->where('product_id', $product->getKey())->whereNotIn('selection', $selected)->delete();

                foreach ($selected as $selection) {
                    DB::table('product_selection')->insertOrIgnore([
                        'selection' => $selection,
                        'product_id' => $product->getKey(),
                        'sort_order' => (int) DB::table('product_selection')->where('selection', $selection)->max('sort_order') + 1,
                    ]);
                }

                return $product;
            });
    }

    /**
     * Фото товара. Папку для новых загрузок задаём в имени файла, а не через
     * dir(): MoonShine приписывает dir() к любому пути без него, а
     * импортированные фото лежат в разных папках (catalog/productimg,
     * catalog/i, catalog/akb...) — превью у всех старых фото ломалось.
     *
     * Файлы с диска не удаляем: после миграции 261 файл фото общий у
     * нескольких товаров (одна картинка на серию).
     */
    private function photoField(string $label, string $column): Image
    {
        return Image::make($label, $column)
            ->customName(fn (UploadedFile $file): string => 'catalog/products/'.$file->hashName())
            ->disableDeleteFiles();
    }

    /**
     * По выпадающему списку на каждую характеристику — как вкладка OCFilter
     * в старой админке. Значения хранятся в pivot attribute_value_product,
     * колонок в products у этих полей нет, поэтому onApply ничего не пишет
     * в модель, а синхронизация идёт после сохранения товара.
     *
     * @return list<FieldContract>
     */
    private function attributeFields(): array
    {
        $product = $this->getResource()->getItem();

        return $this->attributesFor($product instanceof Product ? $product : null)
            ->map(fn (Attribute $attribute): FieldContract => Select::make($attribute->name, "attribute_{$attribute->id}")
                ->options($attribute->values->pluck('value', 'id')->all())
                ->multiple()
                ->searchable()
                ->nullable()
                ->changeFill(fn (mixed $product): array => $product instanceof Product
                    ? $product->attributeValues->where('attribute_id', $attribute->id)->pluck('id')->all()
                    : [])
                ->onApply(fn (Product $product): Product => $product)
                ->onAfterApply(function (Product $product, mixed $selected) use ($attribute): Product {
                    $attributeValueIds = $attribute->values->pluck('id');

                    $product->attributeValues()->detach($attributeValueIds);
                    $product->attributeValues()->attach(
                        $attributeValueIds->intersect(array_map('intval', (array) ($selected ?: [])))->all(),
                    );

                    return $product;
                }))
            ->all();
    }

    /**
     * Только характеристики категорий товара: у аккумулятора нет вязкости,
     * у масла — полярности. Характеристику, значение которой у товара уже
     * заполнено, показываем всегда — иначе данные спрятались бы и их нельзя
     * было бы исправить. Новому товару без категорий — все характеристики.
     *
     * Набор полей одинаков при показе формы и при сохранении (считается от
     * сохранённого товара), поэтому скрытые характеристики не затираются.
     *
     * @return Collection<int, Attribute>
     */
    private function attributesFor(?Product $product): Collection
    {
        return Attribute::query()
            ->with('values')
            ->when(
                $product?->categories()->exists(),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $product->categories()->select('categories.id')))
                    ->orWhereHas('values.products', fn (Builder $products) => $products->where('products.id', $product->getKey()))),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
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
        return SeoFields::rules();
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
