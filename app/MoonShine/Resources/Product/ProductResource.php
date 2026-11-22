<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\TradeInType;
use App\MoonShine\Fields\Money;
use App\MoonShine\Handlers\AvitoExportHandler;
use App\MoonShine\Handlers\SafeImportHandler;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Product\Pages\ProductDetailPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * @extends ModelResource<Product, ProductIndexPage, ProductFormPage, ProductDetailPage>
 */
class ProductResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;
    use ResetsPageOutOfRange;

    protected string $model = Product::class;

    protected string $title = 'Товары';

    protected string $column = 'name';

    protected array $with = ['manufacturer', 'categories', 'tradeInType', 'attributeValues'];

    /**
     * Ошибка загрузки галереи не должна оставлять частично сохранённую карточку.
     */
    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        return DB::transaction(fn (): DataWrapperContract => parent::save($item, $fields));
    }

    /**
     * Удалённые товары открываются по ссылке из списка (вкладка «Удалённые»).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $builder->withTrashed();
    }

    /**
     * Число характеристик в колонке списка («Хар-ки») — одним запросом.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('attributeValues');
    }

    /**
     * У удалённого товара можно только смотреть и восстанавливать: правка и
     * повторное удаление скрыты.
     */
    protected function isCan(Ability $ability): bool
    {
        $item = $this->getItem();

        if ($item instanceof Product && $item->trashed() && in_array($ability, [Ability::UPDATE, Ability::DELETE], true)) {
            return false;
        }

        return parent::isCan($ability);
    }

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name', 'code', 'sku', 'external_id'];
    }

    /**
     * Выгрузка — с текущими фильтрами и сортировкой списка.
     */
    protected function export(): ?Handler
    {
        return ExportHandler::make('Экспорт в Excel');
    }

    /**
     * @return ListOf<Handler>
     */
    protected function handlers(): ListOf
    {
        return new ListOf(Handler::class, [
            $this->export(),
            AvitoExportHandler::make('Экспорт в Авито'),
            $this->import(),
        ]);
    }

    protected function import(): ?Handler
    {
        return SafeImportHandler::make('Импорт из Excel')
            ->hint('Файл — выгрузка «Экспорт в Excel» с вашими правками. Товары находятся по колонке ID; новые импортом не создаются.');
    }

    /**
     * @return list<FieldContract>
     */
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            // Только для сверки: при загрузке обратно колонка игнорируется,
            // код 1С правится в карточке товара.
            Text::make('Код 1С', 'external_id'),
            // Название по id из справочника, загруженного один раз: выгрузка идёт
            // курсором, без загрузки связей.
            Text::make('Производитель', 'manufacturer_id')
                ->modifyRawValue(fn (mixed $id): string => (string) once(fn () => Manufacturer::query()->pluck('name', 'id'))->get($id)),
            // Тип корпуса (код, например L3) задаёт скидку за сдачу старого АКБ: его можно
            // править в файле и загрузить обратно. Сама скидка (колонка ниже) только для сверки:
            // сумму меняют в разделе «Трейд-ин».
            // Габариты (размеры) — только для сверки: у типа и у габаритов разные данные.
            Text::make('Габариты (мм)', 'id')
                ->modifyRawValue(fn (mixed $id): string => (string) (once(fn () => DB::table('attribute_value_product')
                    ->join('attribute_values', 'attribute_values.id', '=', 'attribute_value_product.attribute_value_id')
                    ->where('attribute_values.attribute_id', config('shop.battery_fitment.attributes.dimensions'))
                    ->pluck('attribute_values.value', 'attribute_value_product.product_id'))->get($id) ?? '')),
            Text::make('Тип корпуса', 'trade_in_type_id')
                ->modifyRawValue(fn (mixed $typeId): string => (string) (once(fn () => TradeInType::query()->pluck('code', 'id'))->get($typeId) ?? '')),
            Text::make('Скидка за трейд-ин', 'trade_in_type_id')
                ->modifyRawValue(fn (mixed $typeId): string => (string) (once(fn () => TradeInType::query()->active()->where('amount', '>', 0)->pluck('amount', 'id'))->get($typeId) ?? '')),
            ...$this->editableFields(),
        ];
    }

    /**
     * Что можно править в Excel и загрузить обратно. Производитель,
     * категории и характеристики — связи, их правят массовыми кнопками.
     *
     * @return list<FieldContract>
     */
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Код товара', 'code')->nullable(),
            Text::make('Артикул', 'sku')->nullable(),
            // Код типа корпуса (L3, Груз A…): преобразуется в тип в beforeImportFilling().
            Text::make('Тип корпуса', 'trade_in_type_id')->nullable(),
            ...$this->editableFields(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    private function editableFields(): array
    {
        return [
            // Пакет считает 0 пустым значением и подставляет default —
            // без него в базу ушёл бы NULL.
            Money::make('Цена', 'price')->default(0),
            Money::make('Цена по акции', 'special_price')->nullable(),
            Number::make('Остаток', 'quantity')->default(0),
            Number::make('Остаток у поставщика', 'supplier_quantity')->default(0),
            Money::make('Цена под заказ', 'supplier_price')->nullable(),
            Switcher::make('Только самовывоз', 'is_pickup_only')->default(false),
            Switcher::make('Активен', 'status')->default(false),
        ];
    }

    /**
     * Импорт только правит существующие товары: строка без ID или с чужим
     * ID останавливает загрузку целиком (она идёт одной транзакцией).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeImportFilling(array $data): array
    {
        if (blank($data['id'] ?? null)) {
            throw new RuntimeException('в файле есть строка без ID — новые товары импортом не создаются');
        }

        if (! Product::query()->whereKey($data['id'])->exists()) {
            throw new RuntimeException("товара с ID {$data['id']} нет в базе");
        }

        if (array_key_exists('trade_in_type_id', $data)) {
            $data['trade_in_type_id'] = $this->tradeInTypeIdByCode($data['id'], $data['trade_in_type_id']);
        }

        return $data;
    }

    /**
     * Код типа корпуса из файла → id типа; пусто — тип не задан.
     */
    private function tradeInTypeIdByCode(mixed $productId, mixed $code): ?int
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $type = TradeInType::query()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

        return $type?->id ?? throw new RuntimeException("у товара {$productId} указан неизвестный тип корпуса «{$code}»");
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ProductIndexPage::class,
            ProductFormPage::class,
            ProductDetailPage::class,
        ];
    }
}
